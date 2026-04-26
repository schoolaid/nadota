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
| 1 | `src/Http/Services/ActionEventService.php`        | `logAction()` acepta `?Model`; `log()` acepta `status`/`exception`; fallback de tipos | pendiente |
| 2 | `src/Http/Services/ActionExecutionService.php`    | `try/catch` alrededor de `handle()`; `logActionExecution()` con `status`/`exception`  | pendiente |
| 3 | `src/Http/Services/ActionExecutionService.php`    | Logueo de Actions `standalone` (un único registro sin model_id)                       | pendiente |
| 4 | `src/Http/Services/ActionExecutionService.php`    | Aplicar el mismo lifecycle a `executeBatched()`                                       | pendiente |
| 5 | `src/Http/Controllers/ActionController.php`       | Eliminar `try/catch` redundante alrededor del `execute()` del servicio                | pendiente |
| 6 | `tests/`                                          | Tests para: éxito, fallo (excepción) y standalone                                     | pendiente |
| 7 | Documentación: `EVENT_TRACKING.md` y `ACTIONS.md` | Actualizar la sección "Integración con ActionEvent" con los nuevos estados            | pendiente |

A continuación se documenta cada cambio en su sección.

---

## 5. Cambios realizados

> Cada cambio incluye: archivo, snippet relevante, justificación.

### 5.1 — pendiente

_(se completará al ejecutar el cambio 1)_

### 5.2 — pendiente

### 5.3 — pendiente

### 5.4 — pendiente

### 5.5 — pendiente

### 5.6 — pendiente

### 5.7 — pendiente

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
