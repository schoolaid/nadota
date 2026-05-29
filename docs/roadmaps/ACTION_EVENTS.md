# Action Events — Estado del sistema y pendientes

## Qué hace el sistema

Registra cada operación CRUD y acción personalizada ejecutada sobre un resource en la tabla `action_events`. Cada registro guarda quién, qué acción, sobre qué modelo, con qué datos (antes/después).

---

## Arquitectura

```
Request → Controller → Service (usa TracksActionEvents) → ActionEventService → ActionEvent (DB)
                                                                              ↘ ActionLogged (Event)
                                                                                ↘ LogActionEvent (Job, si queue=true)
```

### Archivos del sistema

| Archivo | Rol |
|---|---|
| `src/Http/Traits/TracksActionEvents.php` | Trait que usan los services. Métodos: `trackCreate`, `trackUpdate`, `trackDelete`, `trackRestore`, `trackCustomAction` |
| `src/Http/Services/ActionEventService.php` | Core: construye el payload y persiste (sync o queue) |
| `src/Models/ActionEvent.php` | Eloquent model para `action_events` |
| `src/Events/ActionLogged.php` | Evento despachado al guardar un evento |
| `src/Jobs/LogActionEvent.php` | Job para logging asíncrono |
| `src/Http/Controllers/ActionEventController.php` | Endpoint `GET /{resourceKey}/resource/{id}/action-events` |
| `src/Resources/ActionEventResource.php` | Resource de Nadota para ver eventos desde el admin |
| `database/migrations/2025_01_15_000000_create_action_events_table.php` | Migración principal |

### Quién usa el trait

| Service | Acción registrada |
|---|---|
| `ResourceStoreService` (vía `AbstractResourcePersistService`) | `create` |
| `ResourceUpdateService` (vía `AbstractResourcePersistService`) | `update` |
| `ResourceDestroyService` | `delete` |
| `ResourceRestoreService` | `restore` |
| `ResourceForceDeleteService` | `forceDelete` (como custom action) |
| `ActionExecutionService` | `action:{key}` para acciones personalizadas |

---

## Estructura de la tabla `action_events`

| Columna | Tipo | Descripción |
|---|---|---|
| `batch_id` | `char(36)` | UUID que agrupa acciones relacionadas en un request |
| `user_id` | `unsignedBigInteger` nullable | Usuario autenticado (null = sistema) |
| `name` | `string` | Nombre de la acción: `create`, `update`, `delete`, `restore`, `forceDelete`, `action:{key}` |
| `actionable_type` | `string` | Clase del Resource de Nadota |
| `actionable_id` | `unsignedBigInteger` nullable | Siempre `0` (Resources no tienen ID) |
| `target_type` | `string` | Clase del Model afectado |
| `target_id` | `unsignedBigInteger` nullable | ID del model afectado |
| `model_type` | `string` | Mismo que `target_type` |
| `model_id` | `unsignedBigInteger` nullable | Mismo que `target_id` |
| `fields` | `text` (JSON) | Datos enviados en el request (sanitizados) |
| `status` | `string(25)` | `running` / `finished` / `failed` |
| `exception` | `text` nullable | Mensaje de error si falló |
| `original` | `text` (JSON) nullable | Estado del modelo antes de la acción |
| `changes` | `text` (JSON) nullable | Cambios aplicados |

### Qué guarda cada acción

| Acción | `fields` | `original` | `changes` |
|---|---|---|---|
| `create` | datos validados del request | — | todos los atributos del modelo recién creado |
| `update` | datos validados del request | atributos antes de guardar | sólo los campos que cambiaron (`getChanges()`) |
| `delete` | — | todos los atributos del modelo | — |
| `restore` | — | — | — |
| `forceDelete` | — | todos los atributos del modelo | `{permanently_deleted: true}` |
| `action:{key}` | campos del request | metadata['original'] | metadata['changes'] |

---

## Configuración relevante

```php
// config/nadota.php
'track_actions' => env('NADOTA_TRACK_ACTIONS', true),   // controla shouldTrackActions()

'action_events' => [
    'enabled'        => env('NADOTA_TRACK_ACTIONS', true),
    'system_user_id' => env('NADOTA_SYSTEM_USER_ID', null),
    'exclude_fields' => ['password', 'remember_token', 'api_token', 'token', 'secret', 'api_key', 'private_key'],
    'track_fields'   => true,
    'track_original' => true,
    'track_changes'  => true,
    'dispatch_events'=> env('NADOTA_DISPATCH_ACTION_EVENTS', true),
    'queue'          => env('NADOTA_ACTION_EVENTS_QUEUE', false),
    'queue_name'     => env('NADOTA_ACTION_EVENTS_QUEUE_NAME', 'default'),
],
```

---

## Bugs / inconsistencias identificadas

### 1. Duplicación de config flag — BAJA severidad
`shouldTrackActions()` lee `nadota.track_actions` pero el config también tiene `nadota.action_events.enabled` con el mismo env var. Son dos llaves que hacen lo mismo.

**Fix:** Unificar. `shouldTrackActions()` debería leer `nadota.action_events.enabled`.

```php
// TracksActionEvents.php
protected function shouldTrackActions(): bool
{
    return config('nadota.action_events.enabled', true);
}
```

Y eliminar la llave `track_actions` del config (o dejarla como alias apuntando al mismo lugar).

---

### 2. `logSync` error handler puede lanzar excepción sin capturar — MEDIA severidad
En `ActionEventService::logSync()`, el bloque `catch` intenta crear un registro de error. Si la DB está caída, ese segundo `create` también lanza una excepción que **no está capturada**, rompiendo la petición principal.

**Fix:** Envolver el create del catch en otro try/catch que solo loguee:

```php
catch (\Exception $e) {
    \Log::error('Failed to log action event', [...]);

    try {
        return ActionEvent::query()->create([...'status' => 'failed'...]);
    } catch (\Exception $inner) {
        \Log::error('Failed to create failed action event record', ['error' => $inner->getMessage()]);
        return new ActionEvent(['status' => 'failed', 'name' => $action]);
    }
}
```

---

### 3. `logAsync` devuelve instancia no persistida — BAJA severidad (diseño documentado)
Cuando `queue=true`, se despacha el job y se retorna `new ActionEvent($data)` sin ID ni `created_at`. El caller en el trait usa `void`, así que no es un problema práctico, pero si alguien usara el retorno de `logAction()` directamente se confundiría.

**Fix cosmético:** No urgente. El trait usa `void` así que el retorno se descarta.

---

### 4. `actionable_id` siempre es `0` — DISEÑO ACEPTADO
Los Resources de Nadota son clases, no registros de DB. Se guarda `0` como placeholder. El campo `actionable_type` (nombre de clase del resource) es suficiente para identificar el contexto.

**No requiere cambio.** Documentado aquí para evitar confusión.

---

### 5. Columnas `fields`, `original`, `changes` son `text` en lugar de `json` — BAJA
Funciona con el cast `array` de Laravel, pero usar tipo `json` en MySQL permitiría queries JSON nativas. No es un blocker.

**Fix opcional:** Crear una migración para cambiar el tipo de columna si se necesitan queries sobre el JSON en el futuro.

---

## Pendientes de implementación

### ~~P1 — Tests~~ ✅ COMPLETADO (57 tests: 19 model + 23 service + 15 integration)

Crear en `tests/Unit/Services/ActionEventServiceTest.php`:
- [ ] `logCreate` guarda registro con `status=finished`, `name=create`, `changes` con atributos del modelo
- [ ] `logUpdate` guarda `original` y `changes` correctamente
- [ ] `logDelete` guarda `original` con atributos del modelo eliminado
- [ ] `shouldTrackActions()` retorna false cuando config está desactivado → no guarda nada
- [ ] Datos sensibles son redactados (`password`, `token`, etc.)
- [ ] `getBatchId()` retorna el mismo UUID en llamadas sucesivas dentro del mismo request
- [ ] `logSync` en fallo de DB: no rompe la petición principal

Crear en `tests/Unit/Models/ActionEventTest.php`:
- [ ] `getChangedFields()` retorna solo campos que cambiaron con valores `old`/`new`
- [ ] `markAsFinished()` y `markAsFailed()` actualizan status correctamente
- [ ] Scopes: `byUser`, `byStatus`, `byBatch`, `recent`

Crear en `tests/ServiceIntegration/ActionEventIntegrationTest.php`:
- [ ] Store → verifica que se crea registro con `name=create`
- [ ] Update → verifica `original` vs `changes`
- [ ] Destroy → verifica `original` con datos del modelo
- [ ] `NADOTA_TRACK_ACTIONS=false` → no crea ningún registro

---

### P2 — Unificar config flag

Ver Bug #1 arriba. Un cambio de una línea en el trait + limpieza del config.

---

### P3 — Proteger `logSync` error handler

Ver Bug #2 arriba.

---

### ~~P4 — Endpoint `show` para ActionEvent individual~~ ✅ COMPLETADO

Actualmente el `ActionEventController` solo tiene `index` (lista paginada). Si el frontend necesita ver el detalle completo de un evento con `fields`/`original`/`changes` completos, falta un endpoint `show`.

**Ruta a agregar en `routes/api.php`:**
```php
Route::get('/{id}/action-events/{eventId}', [ActionEventController::class, 'show'])
    ->name('resource.action-events.show')
    ->where(['id' => '[0-9]+', 'eventId' => '[0-9]+']);
```

**Método a agregar en `ActionEventController`:**
```php
public function show(NadotaRequest $request, string $resourceKey, int $id, int $eventId): JsonResponse
{
    $event = ActionEvent::query()
        ->where('model_id', $id)
        ->with('user')
        ->findOrFail($eventId);

    return response()->json(['data' => $this->formatEvent($event)]);
}
```

---

### P5 — Registrar `ActionEventResource` en el ServiceProvider (VERIFICAR)

Confirmar que `ActionEventResource` está registrado en `ResourceServiceProvider` o similar para ser accesible desde el admin.

---

## Orden de trabajo sugerido

1. **Fix Bug #1** (unificar config) — 5 min
2. **Fix Bug #2** (proteger error handler) — 10 min  
3. **P3 Tests** — escribir suite completa de tests
4. **P4 Endpoint show** — si el frontend lo necesita
5. **P5 Verificar registro del Resource** — confirmar que aparece en el admin

---

## Endpoint disponible

```
GET /nadota-api/{resourceKey}/resource/{id}/action-events
    ?per_page=15
    &page=1
    &name=update        (filtro por acción)
    &status=finished    (filtro por status)
    &user_id=1          (filtro por usuario)
```

Respuesta:
```json
{
  "data": [{
    "id": 1,
    "batchId": "uuid",
    "name": "update",
    "nameLabel": "Updated",
    "status": "finished",
    "user": { "id": 1, "name": "...", "email": "..." },
    "modelType": "App\\Models\\Student",
    "modelId": 42,
    "fields": { "name": "nuevo valor" },
    "original": { "name": "valor anterior", ... },
    "changes": { "name": "nuevo valor" },
    "exception": null,
    "createdAt": "2026-05-17 10:00:00",
    "updatedAt": "2026-05-17 10:00:00"
  }],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 42
  }
}
```

---

## Cómo consumir Action Events desde la app externa

Nadota expone Action Events por **4 vías**. La app consumidora no necesita escribir eventos a mano — Nadota los registra solo. Lo que la app hace es **leerlos** o **reaccionar** a ellos.

### 1. Endpoints REST (lectura desde el frontend/admin)

Las rutas se registran automáticamente bajo el prefix y middleware configurados en `config/nadota.php` (`prefix` → `nadota-api`, `middlewares` → tu stack de auth). No hay que registrar nada en la app.

```
GET /nadota-api/{resourceKey}/resource/{id}/action-events           → listado paginado
GET /nadota-api/{resourceKey}/resource/{id}/action-events/{eventId} → detalle de un evento
```

El `show` valida que el evento pertenezca al modelo indicado (404 si no). Útil para una pestaña "Historial / Auditoría" en el detalle de cualquier recurso.

### 2. Listener del evento `ActionLogged` (reaccionar en tiempo real)

Cada vez que se registra un evento (si `nadota.action_events.dispatch_events = true`), Nadota dispara `SchoolAid\Nadota\Events\ActionLogged`. La app puede escucharlo para notificaciones, webhooks, sync a un datalake, etc.

```php
// app/Providers/EventServiceProvider.php
use SchoolAid\Nadota\Events\ActionLogged;

protected $listen = [
    ActionLogged::class => [
        \App\Listeners\NotifyOnCriticalChange::class,
    ],
];
```

```php
// app/Listeners/NotifyOnCriticalChange.php
public function handle(ActionLogged $event): void
{
    if ($event->isDelete() && $event->actionEvent->model_type === \App\Models\Student::class) {
        // $event->action  → 'delete'
        // $event->actionEvent → instancia ActionEvent persistida (con id, changes, user_id...)
        // helpers: $event->isCreate() / isUpdate() / isDelete() / isRestore() / isForceDelete()
    }
}
```

> Para attachments `$event->action` será `attach` / `detach` / `sync` (no hay helper `isAttach()`; comparar `$event->action` directo).

Si `nadota.action_events.queue = true`, el listener corre después de que el job persista el registro.

### 3. Modelo `ActionEvent` (consultas directas / reportes)

`SchoolAid\Nadota\Models\ActionEvent` es un modelo Eloquent normal. La app puede consultarlo con scopes y relaciones ya incluidos:

```php
use SchoolAid\Nadota\Models\ActionEvent;

// Historial de un modelo concreto
ActionEvent::where('model_type', Student::class)
    ->where('model_id', $student->id)
    ->recent()
    ->get();

// Scopes disponibles
ActionEvent::byUser($userId)->get();
ActionEvent::byStatus('failed')->get();
ActionEvent::byBatch($uuid)->get();          // todas las acciones de un mismo request
ActionEvent::byActionName('delete')->get();
ActionEvent::byActionableType(StudentResource::class)->get();

// Relaciones
$event->user;     // BelongsTo al User (configurable vía auth.providers.users.model)
$event->model;    // MorphTo al modelo afectado
$event->target;   // MorphTo al target

// Helpers de instancia
$event->getChangedFields();      // ['campo' => ['old' => ..., 'new' => ...]]
$event->getActionDisplayName();  // "Force Delete"
$event->isFinished() / isFailed() / isRunning();
```

> El campo `batch_id` agrupa todas las acciones de un mismo request HTTP — útil para reconstruir "qué pasó en esta operación" (ej: un sync que disparó varios attach/detach).

### 3.b Registrar eventos desde jobs / comandos / API / otros controllers

El panel Nadota audita su propia superficie automáticamente (CRUD + attach/detach/sync + resource actions). Para auditar cambios **fuera** del panel (jobs, comandos artisan, endpoints API propios, otros controllers), la app usa el método público context-free `ActionEventService::record()`. No requiere `NadotaRequest` ni un Resource de Nadota; el usuario se resuelve vía `Auth::id()` o `system_user_id`, así que funciona sin request HTTP.

```php
use SchoolAid\Nadota\Http\Services\ActionEventService;

// En un job, comando, listener, controller propio...
app(ActionEventService::class)->record(
    action: 'roster:import',
    model: $student,
    changes: $student->getChanges(),
    original: $student->getOriginal(),
    fields: ['source' => 'nightly-import'],
    actionableType: \App\Console\Commands\ImportRoster::class // opcional; default = clase del modelo
);
```

Firma:
```php
record(
    string $action,
    Model $model,
    ?array $changes = null,
    ?array $original = null,
    array $fields = [],
    ?string $actionableType = null
): ActionEvent
```

- Respeta `nadota.action_events.queue` (async si está activo) y la redacción de `exclude_fields`.
- Comparte `batch_id` con otras escrituras del mismo request/proceso.
- **Diseño deliberado:** Nadota provee la *capacidad* de escritura; los *disparadores* (qué modelos, qué jobs, dedupe vs. panel) son responsabilidad de la app de dominio, no del paquete.

### 4. Servicio `ActionEventService` (helpers de agregación)

Inyectable o vía `app(ActionEventService::class)`:

```php
use SchoolAid\Nadota\Http\Services\ActionEventService;

$svc = app(ActionEventService::class);
$svc->getModelHistory($student, 50);                  // historial de un modelo
$svc->getUserActivity($userId, 50);                   // actividad de un usuario
$svc->getResourceActivity(StudentResource::class);    // actividad sobre un resource
```

### Qué controla la app vía config / env

| Variable env | Efecto |
|---|---|
| `NADOTA_TRACK_ACTIONS` | Activa/desactiva todo el tracking (`action_events.enabled`) |
| `NADOTA_SYSTEM_USER_ID` | user_id a usar cuando no hay usuario autenticado (jobs, comandos) |
| `NADOTA_DISPATCH_ACTION_EVENTS` | Si se dispara o no el evento `ActionLogged` |
| `NADOTA_ACTION_EVENTS_QUEUE` | Persistir en cola (async) en vez de síncrono |
| `NADOTA_ACTION_EVENTS_QUEUE_NAME` | Nombre de la cola si async |
| `action_events.exclude_fields` (config) | Llaves que se redactan como `***REDACTED***` antes de guardar |

> La app **no** debe instanciar `ActionEvent` directamente ni reimplementar un modelo paralelo. Para CRUD/attachment dentro del panel lo hace Nadota solo; para todo lo demás (jobs/comandos/API) la app usa `ActionEventService::record()` (sección 3.b).
