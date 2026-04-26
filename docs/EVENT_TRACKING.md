# Event Tracking (ActionEvent)

Este documento describe el sistema de seguimiento de acciones de usuario sobre los modelos en Nadota: cómo se registran los eventos, qué piezas intervienen, cómo está cableado y cómo se configura.

> Nota: Nadota **no usa Eloquent Observers** para registrar eventos. En su lugar, los servicios HTTP de CRUD invocan explícitamente al `ActionEventService` mediante el trait `TracksActionEvents`. Esto da control fino sobre qué se registra, cuándo y con qué metadatos.

---

## 1. Visión general

```
HTTP Request (Store / Update / Destroy / Restore / ForceDelete)
    │
    ▼
Resource*Service (extiende AbstractResourcePersistService o usa TracksActionEvents)
    │
    ▼
TracksActionEvents trait  ──► ActionEventService::log*()
                                 │
                                 ├── SYNC  → ActionEvent::create() → event(ActionLogged)
                                 │
                                 └── ASYNC → LogActionEvent::dispatch()
                                                 │
                                                 ▼
                                             handle() en queue
                                                 │
                                                 ├── ActionEvent::create()
                                                 └── event(ActionLogged)
```

Acciones rastreadas por defecto: `create`, `update`, `delete`, `restore`, `forceDelete`. También se pueden registrar acciones personalizadas vía `ActionEventService::logAction()` / `trackCustomAction()`.

Las Actions de Resources (ver [`ACTIONS.md`](ACTIONS.md)) generan eventos con prefijo `action:<key>` y se registran tanto en éxito (`status=finished`) como en fallo (`status=failed` con `exception` poblado). Las Actions `standalone` producen un único `ActionEvent` sin `model_id`. Para detalles del lifecycle ver [`ACTIONS_EVENT_LOGGING_IMPLEMENTATION.md`](ACTIONS_EVENT_LOGGING_IMPLEMENTATION.md).

---

## 2. Modelo: `ActionEvent`

**Archivo:** `src/Models/ActionEvent.php`
**Tabla:** `action_events`

### Columnas / fillable

| Campo             | Tipo    | Descripción                                                           |
|-------------------|---------|-----------------------------------------------------------------------|
| `batch_id`        | UUID    | Agrupa acciones relacionadas en un mismo flujo / request              |
| `user_id`         | int?    | Usuario autenticado que realizó la acción (o `system_user_id`)        |
| `name`            | string  | Tipo de acción: `create`, `update`, `delete`, `restore`, `forceDelete`|
| `actionable_type` | morph   | Clase del Resource sobre el que se actúa                              |
| `actionable_id`   | int?    | ID del recurso                                                        |
| `target_type`     | morph   | Clase del modelo objetivo                                             |
| `target_id`       | int?    | ID del objetivo                                                       |
| `model_type`      | morph   | Clase del modelo afectado                                             |
| `model_id`        | int?    | ID del modelo afectado                                                |
| `fields`          | json    | Campos enviados en el request                                         |
| `status`          | string  | `running`, `finished`, `failed`                                       |
| `exception`       | text?   | Mensaje de error si la acción falla                                   |
| `original`        | json    | Estado del modelo antes del cambio                                    |
| `changes`         | json    | Cambios aplicados (formato `{campo: {old, new}}` al usar helper)      |
| `created_at`      | ts      |                                                                       |
| `updated_at`      | ts      |                                                                       |

### Boot hook

`ActionEvent::boot()` (líneas 59-68) genera automáticamente un `batch_id` (UUIDv4) en `creating` si no se proporcionó uno.

### Relaciones

- `user()` → `BelongsTo` al modelo de auth (`config('auth.providers.users.model')`).
- `actionable()` → `MorphTo` al Resource.
- `target()` → `MorphTo` al modelo objetivo.
- `model()` → `MorphTo` al modelo afectado.

### Scopes de consulta (líneas 107-150)

- `byUser($userId)`
- `byStatus($status)`
- `byBatch($batchId)`
- `byActionableType($type)`
- `byActionName($name)`
- `recent()` — orden por `created_at` desc

### Helpers de estado

- `markAsFinished()`
- `markAsFailed(string $exception)`
- `isRunning()`, `isFinished()`, `isFailed()`

### Utilidades

- `getActionDisplayName()` — etiqueta legible (`Created`, `Updated`, …).
- `getChangedFields()` — devuelve únicamente los campos modificados con `{old, new}` comparando `original` vs `changes`.

---

## 3. Servicio principal: `ActionEventService`

**Archivo:** `src/Http/Services/ActionEventService.php`
**Registrado como singleton** en `src/Providers/ServiceBindingServiceProvider.php:51` para mantener un mismo `batch_id` durante el ciclo del request.

### API pública relevante

| Método                                                                                           | Propósito                                                  |
|--------------------------------------------------------------------------------------------------|------------------------------------------------------------|
| `getBatchId(): string`                                                                           | Devuelve / genera el UUID de batch del request actual      |
| `resetBatchId(): void`                                                                           | Limpia el batch (útil entre operaciones independientes)    |
| `resolveUserId(): ?int`                                                                          | Toma el user autenticado o `nadota.action_events.system_user_id` |
| `logCreate($model, $resource, $request, $fields)`                                                | Registra una creación                                      |
| `logUpdate($model, $resource, $request, $fields, ?array $originalData)`                          | Registra una edición y el `original` previo                |
| `logDelete($model, $resource, $request, $fields = [])`                                           | Registra un soft delete                                    |
| `logRestore($model, $resource, $request, $fields = [])`                                          | Registra un restore                                        |
| `logAction($action, $model, $resource, $request, $fields, $metadata)`                            | Registra una acción arbitraria con metadata adicional      |
| `getModelHistory($modelClass, $modelId)`                                                         | Historial de un modelo concreto                            |
| `getUserActivity($userId)`                                                                       | Actividad de un usuario                                    |
| `getResourceActivity($resourceClass)`                                                            | Actividad sobre un Resource                                |

### Núcleo: `log()` (líneas 160-192)

Construye el array de datos (batch, user, name, polimórficos, `fields`, `original`, `changes`) y, según `nadota.action_events.queue`:

- **Sync** (`logSync`, líneas 197-230): crea el `ActionEvent` directamente, dispara `ActionLogged` y, ante excepción, registra un `ActionEvent` con `status=failed` para no romper la operación principal.
- **Async** (`logAsync`, líneas 235-246): despacha el job `LogActionEvent` en la cola configurada (`nadota.action_events.queue_name`). Devuelve un `ActionEvent` temporal con `status=running` para feedback inmediato.

### Saneamiento de datos sensibles

`sanitizeFields()` / `sanitizeData()` (líneas 287-320) reemplazan por `***REDACTED***` cualquier clave cuyo nombre contenga (case-insensitive) los términos definidos en `nadota.action_events.exclude_fields`:

```
password, remember_token, api_token, token, secret, api_key, private_key
```

---

## 4. Trait: `TracksActionEvents`

**Archivo:** `src/Http/Traits/TracksActionEvents.php`

Es la fachada que usan los services HTTP para no acoplarse directamente a `ActionEventService`.

| Método                                                              | Acción registrada     |
|---------------------------------------------------------------------|-----------------------|
| `trackCreate($model, $request, $fields)`                            | `create`              |
| `trackUpdate($model, $request, $fields, ?array $originalData)`      | `update`              |
| `trackDelete($model, $request, $fields = [])`                       | `delete`              |
| `trackRestore($model, $request, $fields = [])`                      | `restore`             |
| `trackCustomAction($action, $model, $request, $fields, $metadata)`  | acción arbitraria     |
| `shouldTrackActions(): bool`                                        | Lee `nadota.track_actions` |

Cada método consulta `shouldTrackActions()` antes de delegar al servicio, por lo que se puede desactivar globalmente.

`getActionEventService()` resuelve perezosamente el singleton del contenedor, garantizando que el `batch_id` se comparta dentro del request.

---

## 5. Integración en los servicios CRUD

Servicios que actualmente registran eventos:

| Servicio                                                            | Trait | Llamada                                           |
|---------------------------------------------------------------------|-------|---------------------------------------------------|
| `src/Http/Services/ResourceStoreService.php` (línea 70)             | vía `AbstractResourcePersistService` | `trackCreate(...)` |
| `src/Http/Services/ResourceUpdateService.php` (línea 86)            | vía `AbstractResourcePersistService` | `trackUpdate(...)` con `$originalData` |
| `src/Http/Services/ResourceDestroyService.php` (línea 30)           | `TracksActionEvents` | `trackDelete(...)` antes del delete |
| `src/Http/Services/ResourceRestoreService.php` (línea 48)           | `TracksActionEvents` | `trackRestore(...)` |
| `src/Http/Services/ResourceForceDeleteService.php` (líneas 41-44)   | `TracksActionEvents` | `trackCustomAction('forceDelete', ..., ['original' => $model->getAttributes(), 'changes' => ['permanently_deleted' => true]])` |

`AbstractResourcePersistService` (`src/Http/Services/AbstractResourcePersistService.php`) usa el trait y centraliza la llamada `trackAction(...)` tras `model->save()` y `callAfterHook()` (línea 86), por lo que store y update heredan el comportamiento.

---

## 6. Evento Laravel: `ActionLogged`

**Archivo:** `src/Events/ActionLogged.php`

```php
namespace SchoolAid\Nadota\Events;

class ActionLogged
{
    public function __construct(
        public ActionEvent $actionEvent,
        public string $action,
    ) {}

    public function isCreate(): bool;
    public function isUpdate(): bool;
    public function isDelete(): bool;
    public function isRestore(): bool;
    public function isForceDelete(): bool;
}
```

Se dispara desde:

1. `ActionEventService::logSync()` (`src/Http/Services/ActionEventService.php:254`).
2. `LogActionEvent::handle()` (`src/Jobs/LogActionEvent.php:46`).

Está condicionado por la config `nadota.action_events.dispatch_events` (default `true`). Cualquier app consumidora puede registrar listeners propios para reaccionar (notificar, auditar, sincronizar a un servicio externo, etc.).

---

## 7. Job de cola: `LogActionEvent`

**Archivo:** `src/Jobs/LogActionEvent.php`

- `implements ShouldQueue`
- Constructor: `(array $data, string $action)`
- `handle()` crea el `ActionEvent` y dispara `ActionLogged`. Si falla, escribe en log y persiste un `ActionEvent` con `status=failed` para que se pueda diagnosticar después.
- `tags()` devuelve `['nadota', 'action-event', "action:{$action}"]` para Horizon u otras herramientas de cola.

---

## 8. API HTTP para consultar el historial

**Controlador:** `src/Http/Controllers/ActionEventController.php`

**Endpoint:** `GET /nadota-api/{resourceKey}/{id}/action-events`

Filtros disponibles vía query string:

- `name` — tipo de acción
- `status` — `running` | `finished` | `failed`
- `user_id`
- `page`, `per_page` (máximo 100)

Respuesta:

```json
{
  "data": [
    {
      "id": 1,
      "batchId": "uuid",
      "name": "update",
      "nameLabel": "Updated",
      "status": "finished",
      "user": { "id": 1, "name": "...", "email": "..." },
      "modelType": "App\\Models\\Post",
      "modelId": 42,
      "fields": { "...": "..." },
      "original": { "...": "..." },
      "changes": { "...": "..." },
      "exception": null,
      "createdAt": "...",
      "updatedAt": "..."
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 25, "total": 1 }
}
```

Etiquetas legibles vía `actionLabel()` (líneas 86-95): `Created`, `Updated`, `Deleted`, `Restored`, `Permanently Deleted`.

---

## 9. Recurso de admin: `ActionEventResource`

**Archivo:** `src/Resources/ActionEventResource.php`

- Modelo: `ActionEvent::class`, título `Action Events`.
- `softDelete = false` — los eventos son permanentes.
- `with = ['user']` — eager load del usuario.
- `displayInMenu() = false` — no aparece en el menú principal por defecto.
- Búsqueda por `batch_id`, `name`, `actionable_type`, `model_type`, `status`.
- Todos los campos se exponen en modo readonly (Batch ID, User, Action, Status, Actionable/Target/Model type & id, Fields, Original, Changes, Exception, timestamps).
- Filtros: acción, estado, model type, model id, user id y rango de fechas.

Se registra automáticamente en `src/Providers/ResourceServiceProvider.php:72-75` cuando `nadota.track_actions` es `true`.

---

## 10. Migraciones

Ubicación: `database/migrations/`

| Migración                                                       | Cambio                                                       |
|-----------------------------------------------------------------|--------------------------------------------------------------|
| `2025_01_15_000000_create_action_events_table.php`              | Crea la tabla `action_events` con índices en `(actionable_type, actionable_id)`, `(batch_id, model_type, model_id)`, `user_id`, `name`. |
| `2025_01_15_000001_*_exception_field.php`                       | Hace nullable la columna `exception`.                        |
| `2025_12_15_000001_*_alter_actionable_nullable.php`             | Hace nullable `user_id`, `actionable_id`, `target_id` para soportar acciones de sistema. |

---

## 11. Configuración

**Archivo:** `config/nadota.php` (líneas 142-174)

```php
'track_actions' => env('NADOTA_TRACK_ACTIONS', true),

'action_events' => [
    'enabled'         => env('NADOTA_TRACK_ACTIONS', true),
    'table'           => 'action_events',
    'system_user_id'  => env('NADOTA_SYSTEM_USER_ID', null),

    'exclude_fields' => [
        'password', 'remember_token', 'api_token',
        'token', 'secret', 'api_key', 'private_key',
    ],

    'track_fields'    => true,
    'track_original'  => true,
    'track_changes'   => true,

    'dispatch_events' => env('NADOTA_DISPATCH_ACTION_EVENTS', true),

    'queue'           => env('NADOTA_ACTION_EVENTS_QUEUE', false),
    'queue_name'      => env('NADOTA_ACTION_EVENTS_QUEUE_NAME', 'default'),
],
```

Variables de entorno:

| ENV                                  | Efecto                                                         |
|--------------------------------------|----------------------------------------------------------------|
| `NADOTA_TRACK_ACTIONS`               | Activa/desactiva todo el tracking y el registro del Resource   |
| `NADOTA_SYSTEM_USER_ID`              | User id a usar cuando no hay usuario autenticado (jobs, CLI)   |
| `NADOTA_DISPATCH_ACTION_EVENTS`      | Si se debe disparar el evento `ActionLogged`                   |
| `NADOTA_ACTION_EVENTS_QUEUE`         | Activa el logging asíncrono via job                            |
| `NADOTA_ACTION_EVENTS_QUEUE_NAME`    | Nombre de la cola para el job `LogActionEvent`                 |

---

## 12. Observers / Hooks de modelo

- **No existen clases `Observer`** dedicadas en el código (`src/`). El registro de actividad se hace en los servicios HTTP de forma explícita.
- El único *hook* sobre eventos Eloquent es `ActionEvent::boot()` que asigna el `batch_id` automáticamente al crear el registro.
- Los servicios CRUD invocan `track*` en el momento adecuado del flujo (`store`/`update` lo hacen tras `save()` + `afterHook`; `destroy`/`forceDelete` antes de borrar; `restore` después de restaurar).

Si una aplicación necesita reaccionar a un evento registrado (notificaciones, auditorías externas, etc.), la vía recomendada es **escuchar el evento `ActionLogged`** en lugar de añadir Observers paralelos.

---

## 13. Resumen de archivos clave

| Componente                    | Ruta                                                                       |
|-------------------------------|----------------------------------------------------------------------------|
| Modelo                        | `src/Models/ActionEvent.php`                                               |
| Servicio                      | `src/Http/Services/ActionEventService.php`                                 |
| Trait                         | `src/Http/Traits/TracksActionEvents.php`                                   |
| Evento                        | `src/Events/ActionLogged.php`                                              |
| Job                           | `src/Jobs/LogActionEvent.php`                                              |
| Controlador HTTP              | `src/Http/Controllers/ActionEventController.php`                           |
| Resource admin                | `src/Resources/ActionEventResource.php`                                    |
| Registro singleton            | `src/Providers/ServiceBindingServiceProvider.php:51`                       |
| Auto-registro del Resource    | `src/Providers/ResourceServiceProvider.php:72-75`                          |
| Migraciones                   | `database/migrations/2025_01_15_000000_create_action_events_table.php` y siblings |
| Configuración                 | `config/nadota.php` (`track_actions` y `action_events`)                    |
| Servicios que disparan logs   | `ResourceStoreService`, `ResourceUpdateService`, `ResourceDestroyService`, `ResourceRestoreService`, `ResourceForceDeleteService` |
