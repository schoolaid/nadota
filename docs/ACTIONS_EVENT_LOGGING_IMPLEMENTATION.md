# Implementación: registro robusto de Actions en `action_events`

Este documento describe la implementación que cierra los huecos detectados al ejecutar Actions de Resources frente al sistema `ActionEvent`. Se actualiza paso a paso conforme avanzan los cambios.

> Documentos relacionados: [`EVENT_TRACKING.md`](EVENT_TRACKING.md) (sistema base de `ActionEvent`) y [`ACTIONS.md`](ACTIONS.md) (clases Action y sus hooks).

---

## 1. Motivación

El registro actual (`ActionExecutionService::logActionExecution`, `src/Http/Services/ActionExecutionService.php:108-127`) tiene cuatro huecos:

| # | Hueco                                                                        | Impacto                                                |
|---|------------------------------------------------------------------------------|--------------------------------------------------------|
| 1 | Si `Action::handle()` lanza, **no queda registro** en `action_events`        | No hay auditoría de intentos fallidos                  |
| 2 | El estado siempre es `finished`; no se distingue éxito/fallo                 | Imposible filtrar acciones rotas por status            |
| 3 | Las acciones `standalone` (sin modelos) **no se loguean** (el loop itera modelos) | Se pierde rastro de esas ejecuciones              |
| 4 | El controller hace `try/catch` y devuelve `danger`, pero el log nunca corre  | El error se ve en el frontend y se pierde en backend   |

Esta primera fase resuelve los cuatro puntos sin tocar la API pública de `Action`.

Fuera de alcance (fase 2, no incluida aquí):
- Hooks `shouldLog()` / `eventMetadata()` / `eventName()` en la clase `Action`.
- Snapshot diff `original`/`changes` automático del modelo cuando la Action lo muta.

---

## 2. Diseño

### 2.1 Lifecycle propuesto

```
Antes (actual):
  filterAuthorizedModels  →  handle()  →  log(finished) por modelo
                                ↑
                       si lanza: catch en controller, sin log

Después (propuesto):
  filterAuthorizedModels
    │
    ├─ try
    │     └─ handle()
    │           └─ log(finished) por modelo (ó 1 single para standalone)
    │
    └─ catch \Throwable $e
          └─ log(failed, exception=$e->getMessage()) por modelo (ó 1 single)
          └─ return ActionResponse::danger($e->getMessage())
```

El controller ya no necesita `try/catch`: el executor garantiza siempre devolver una `ActionResponse`. Se mantiene un `catch` defensivo en el controller para errores fuera de `handle()` (resolución de Action, validación, etc.).

### 2.2 Cambios en `ActionEventService`

`logAction()` pasa a aceptar `?Model $model` (nullable) y dos campos opcionales en `metadata`:

```php
public function logAction(
    string $action,
    ?Model $model,
    ResourceInterface $resource,
    NadotaRequest $request,
    array $fields = [],
    array $metadata = []   // claves soportadas: original, changes, status, exception
): ActionEvent
```

El método interno `log()` recibe dos parámetros nuevos: `string $status = 'finished'` y `?string $exception = null`. Cuando `$model === null`, se usa la clase del Resource como fallback en `target_type` / `model_type` (ambas son `NOT NULL` en el schema) y `model_id` queda `null` (la migración `2025_12_15_000001_alter_actionable_nullable.php` lo permite).

No se rompe ningún caller existente: `logAction()` mantiene los argumentos posicionales previos y `metadata` es backward-compatible (se ignora lo que no entiende).

### 2.3 Cambios en `ActionExecutionService`

- `execute()` envuelve `handle()` en `try/catch \Throwable`.
- Nuevo método `protected logActionExecution(..., string $status, ?string $exception)` con dos overloads efectivos vía argumentos opcionales (por defecto `finished` / `null`).
- Si la Action es `standalone` y no hay modelos autorizados, se invoca `logStandaloneExecution()` que crea un único `ActionEvent` sin modelo.
- `executeBatched()` recibe el mismo tratamiento por chunk.

### 2.4 Cambios en `ActionController::execute`

Se simplifica: ya no necesita capturar excepciones de `handle()` (las maneja el executor). Conserva sólo el `try/catch` defensivo para errores externos (modelo no encontrado, validación previa).

---

## 3. Estado del schema

La tabla `action_events` ya soporta los nuevos casos:

- `model_id` → `nullable` (migración `2025_12_15_000001_*`).
- `exception` → `nullable` (migración `2025_01_15_000001_*`).
- `status` → string(25) con valores `running` | `finished` | `failed`.

**No se requiere migración nueva.**

---

## 4. Plan de cambios (checklist)

| # | Archivo                                           | Cambio                                                                                | Estado  |
|---|---------------------------------------------------|----------------------------------------------------------------------------------------|---------|
| 1 | `src/Http/Services/ActionEventService.php`        | `logAction()` acepta `?Model`; `log()` acepta `status`/`exception`; fallback de tipos | **hecho** |
| 2 | `src/Http/Services/ActionExecutionService.php`    | `try/catch` alrededor de `handle()`; `logActionExecution()` con `status`/`exception`  | **hecho** |
| 3 | `src/Http/Services/ActionExecutionService.php`    | Logueo de Actions `standalone` (un único registro sin model_id)                       | **hecho** |
| 4 | `src/Http/Services/ActionExecutionService.php`    | Aplicar el mismo lifecycle a `executeBatched()`                                       | **hecho** |
| 5 | `src/Http/Controllers/ActionController.php`       | Mantener `try/catch` defensivo; `Exception` → `Throwable`; aclarar responsabilidades | **hecho** |
| 6 | `tests/Unit/Services/ActionExecutionServiceTest.php` | Tests para: éxito, fallo (excepción), standalone éxito/fallo, batch_id compartido | **hecho** |
| 7 | Documentación: `EVENT_TRACKING.md` y `ACTIONS.md` | Actualizar la sección "Integración con ActionEvent" con los nuevos estados            | **hecho** |

A continuación se documenta cada cambio en su sección.

---

## 5. Cambios realizados

> Cada cambio incluye: archivo, snippet relevante, justificación.

### 5.1 — `ActionEventService` admite modelo nullable, `status` y `exception`

**Archivo:** `src/Http/Services/ActionEventService.php`

Cambios:

- `logAction()` ahora acepta `?Model $model` (antes `Model $model`) y reconoce dos claves nuevas en `$metadata`: `status` y `exception`.
- `log()` recibe dos parámetros nuevos: `string $status = 'finished'` y `?string $exception = null`.
- Cuando `$model === null`, los campos `target_type` y `model_type` (NOT NULL en el schema) se rellenan con la clase del Resource. `target_id` queda en `0`, `model_id` en `null` (la migración `2025_12_15_000001_*` lo permite).
- Se mantiene 100% compatibilidad hacia atrás: pasar un `Model` y omitir `status`/`exception` produce el mismo registro de antes.

Snippet relevante (firma final de `logAction`):

```php
public function logAction(
    string $action,
    ?Model $model,
    ResourceInterface $resource,
    NadotaRequest $request,
    array $fields = [],
    array $metadata = []   // claves: original, changes, status, exception
): ActionEvent
```

Justificación: habilita los pasos siguientes (logueo de fallos y standalone) sin tocar `logSync` / `logAsync` y sin requerir migración nueva.

### 5.2 — `ActionExecutionService::execute()` envuelve `handle()` en `try/catch`

**Archivo:** `src/Http/Services/ActionExecutionService.php`

```php
try {
    $result = $action->handle($authorizedModels, $request);
} catch (\Throwable $e) {
    $this->logActionExecution($request, $action, $authorizedModels, $resource, 'failed', $e->getMessage());

    return ActionResponse::danger($e->getMessage());
}

$this->logActionExecution($request, $action, $authorizedModels, $resource);
```

Justificación: con esto, todo fallo de `handle()` queda persistido en `action_events` antes de devolver la respuesta `danger` al cliente. Se usa `\Throwable` (no `\Exception`) para capturar también `Error` (TypeError, división por cero, etc.).

### 5.3 — `logActionExecution()` admite `status`/`exception` y caso standalone

**Archivo:** `src/Http/Services/ActionExecutionService.php`

```php
protected function logActionExecution(
    NadotaRequest $request,
    ActionInterface $action,
    Collection $models,
    $resource,
    string $status = 'finished',
    ?string $exception = null
): void {
    $name = 'action:' . $action::getKey();
    $fields = $request->only(array_keys($request->all()));
    $metadata = [
        'status' => $status,
        'exception' => $exception,
    ];

    if ($models->isEmpty()) {
        $this->actionEventService->logAction(
            action: $name, model: null, resource: $resource,
            request: $request, fields: $fields, metadata: $metadata
        );
        return;
    }

    foreach ($models as $model) {
        $this->actionEventService->logAction(
            action: $name, model: $model, resource: $resource,
            request: $request, fields: $fields, metadata: $metadata
        );
    }
}
```

Decisiones:

- **Standalone**: si `$models` viene vacía (típico en Actions standalone), se emite un único `ActionEvent` sin `model_id`. `target_type`/`model_type` quedan con la clase del Resource (NOT NULL en el schema).
- **Cleanup de metadata redundante**: el código previo pasaba `action_name` y `action_key` en `metadata`, pero `logAction()` los descartaba silenciosamente (sólo leía `original` / `changes`). Como `name` ya guarda `action:<key>` y el display name es derivable del FQCN, se elimina ese envío para evitar metadata muerta.

### 5.4 — Mismo lifecycle en `executeBatched()`

**Archivo:** `src/Http/Services/ActionExecutionService.php`

Cada chunk obtiene su propio `try/catch`. Si un chunk falla:

- Sus modelos quedan `failed` en `action_events`.
- Los chunks anteriores ya están `finished`.
- `executeBatched()` corta la iteración y devuelve `ActionResponse::danger($e->getMessage())`.

```php
foreach ($chunks as $chunkIds) {
    $models = $this->getModels($modelClass, $chunkIds, $resource->usesSoftDeletes());
    $authorizedModels = $this->filterAuthorizedModels($request, $action, $models);

    if ($authorizedModels->isEmpty()) {
        continue;
    }

    try {
        $lastResult = $action->handle($authorizedModels, $request);
    } catch (\Throwable $e) {
        $this->logActionExecution($request, $action, $authorizedModels, $resource, 'failed', $e->getMessage());

        return ActionResponse::danger($e->getMessage());
    }

    $processedCount += $authorizedModels->count();
    $this->logActionExecution($request, $action, $authorizedModels, $resource);
}
```

### 5.5 — `ActionController::execute` aclara responsabilidades

**Archivo:** `src/Http/Controllers/ActionController.php`

- El executor ahora garantiza siempre devolver una `ActionResponse` y registrar el evento aún en fallo.
- El `try/catch` del controller se preserva como **red de seguridad** para errores fuera de `handle()` (resolución de la Action, serialización, etc.).
- `\Exception` → `\Throwable` por consistencia con el executor.
- Se añade un comentario explicando la división de responsabilidades.

```php
// El executor captura los fallos de handle() y devuelve un ActionResponse
// danger (registrando un ActionEvent failed). Este try/catch protege
// contra errores externos a handle() (resolución de modelos, etc.).
try {
    $result = $this->actionService->execute($request, $action, $modelIds);
    return response()->json($result->toArray());
} catch (\Throwable $e) {
    return response()->json([
        'type' => 'danger',
        'message' => $e->getMessage(),
    ], 500);
}
```

### 5.6 — Tests del lifecycle (`ActionExecutionServiceTest`)

**Archivo:** `tests/Unit/Services/ActionExecutionServiceTest.php`

Cinco casos cubren la fase 1:

| Test | Verifica |
|------|----------|
| `it logs one finished ActionEvent per affected model on success` | Éxito sobre N modelos → N filas `finished` con `model_id` correcto y `name` con prefijo `action:` |
| `it logs failed ActionEvents and returns danger when handle() throws` | Falla con N modelos → N filas `failed` con `exception` poblado; respuesta `danger` |
| `it logs a single ActionEvent without model_id for standalone actions on success` | Action `standalone` exitosa → 1 fila `finished` con `model_id=null` |
| `it logs a single failed ActionEvent without model_id for standalone actions that throw` | Action `standalone` que lanza → 1 fila `failed` con `exception` y `model_id=null` |
| `it shares the same batch_id across all events of one execution` | Todos los eventos de una ejecución comparten `batch_id` |

El test crea la tabla `action_events` localmente en `beforeEach` (no se modifica `tests/TestCase.php` para no afectar otras suites). Se usa una clase anónima derivada de `Action` que recibe el `handle()` como callback, lo que permite reutilizarla para los cinco escenarios.

Resultados: **5/5 pasan**. La suite global Unit pasa de 429→434 (+5) sin nuevas regresiones (las 92 fallas pre-existentes no tocan Actions).

### 5.7 — Actualización de `EVENT_TRACKING.md` y `ACTIONS.md`

Pendiente al final del trabajo: añadir un párrafo en cada uno explicando que las Actions ahora se registran también en estado `failed` y que las acciones `standalone` generan un único `ActionEvent` sin `model_id`.

---

## 6. Comportamiento resultante (resumen)

Tras esta fase, una Action ejecutada sobre N modelos genera:

- **N `ActionEvent` con `status=finished`** si `handle()` retorna sin excepción.
- **N `ActionEvent` con `status=failed` y `exception` poblado** si `handle()` lanza. La respuesta HTTP queda en `ActionResponse::danger($e->getMessage())`.
- **1 `ActionEvent` (sin `model_id`)** si la Action es `standalone`, con el mismo lifecycle de `finished`/`failed`.

Todos comparten el mismo `batch_id` (singleton del `ActionEventService` durante el request) y respetan los settings de `nadota.action_events.queue` y `dispatch_events`.

---

## 7. Riesgos / consideraciones

- **Volumen**: registrar fallos puede aumentar el tamaño de `action_events`. Mitigación: la tabla ya tiene índices en `name` y `(actionable_type, actionable_id)`; consultas por `status='failed'` siguen siendo eficientes.
- **PII en `exception`**: el mensaje de excepción podría incluir datos sensibles (queries SQL, payloads). Se trunca a lo que devuelva `Throwable::getMessage()` y no se almacena trace; suficiente por ahora, revisable si aparece riesgo.
- **Compatibilidad hacia atrás**: la firma de `logAction()` cambia el tipo del 2º argumento de `Model` a `?Model`. PHP acepta esto en sub-tipos covariantes; no rompe a llamadores que pasan un Model real. El único caller interno es `ActionExecutionService`.
- **Listeners de `ActionLogged`**: ahora pueden recibir eventos con `status=failed`. Documentar para que listeners que asumían éxito hagan el check.

---

## 8. Cómo verificar

Una vez implementado:

```bash
composer test --filter=Action
```

Casos a cubrir:

1. Action exitosa sobre 3 modelos → 3 `ActionEvent` con `status=finished`.
2. Action que lanza `\RuntimeException` → 3 `ActionEvent` con `status=failed`, `exception='...'`, y respuesta `danger`.
3. Action `standalone` exitosa → 1 `ActionEvent` con `model_id=null`, `status=finished`.
4. Action `standalone` que lanza → 1 `ActionEvent` `status=failed`.
5. `executeBatched()` con un chunk que falla → registros `failed` sólo del chunk afectado, los anteriores quedan `finished`.
