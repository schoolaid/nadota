# Attachment Action Events — Implementación y Pendientes

## Qué hace

Registra cada operación `attach`, `detach` y `sync` sobre relaciones en la tabla `action_events`, usando el mismo sistema de `TracksActionEvents` que usan los servicios CRUD. El nombre de la acción registrada es `attach`, `detach` o `sync`.

---

## Cambios realizados

### `AbstractAttachmentService` — infraestructura base

Se agregó el trait `TracksActionEvents` y el método helper `trackAttachmentAction()`.

```php
use SchoolAid\Nadota\Http\Traits\TracksActionEvents;

abstract class AbstractAttachmentService implements AttachmentServiceInterface
{
    use TracksActionEvents;

    protected function trackAttachmentAction(
        string $action,
        Model $parentModel,
        NadotaRequest $request,
        Field $field,
        array $changes
    ): void {
        // ...
        $this->getActionEventService()->logAction(
            action: $action,
            model: $parentModel,
            resource: $request->getResource(),
            request: $request,
            fields: ['relation' => $relation],
            metadata: ['changes' => $changes]
        );
    }
}
```

El campo `fields` guarda el nombre de la relación. La columna `changes` guarda el payload específico de cada operación.

---

### `BelongsToManyAttachmentService`

| Método | Acción | `changes` guardado |
|---|---|---|
| `attach()` | `attach` | `['attached' => [id, ...]]` |
| `detach()` | `detach` | `['detached' => [id, ...]]` |
| `sync()` | `sync` | `['attached' => [...], 'detached' => [...], 'updated' => [...]]` (directo del return de Laravel) |

---

### `HasManyAttachmentService`

| Método | Acción | `changes` guardado |
|---|---|---|
| `attach()` | `attach` | `['attached' => [id, ...]]` — IDs de los modelos efectivamente actualizados |
| `detach()` | `detach` | `['detached' => [id, ...]]` — IDs recibidos en el request |

> **Nota:** El detach en HasMany usa un `->update()` masivo que retorna el conteo de filas afectadas, no los IDs. Se guardan los IDs del request como referencia (los que se intentó desasociar).

---

### `MorphManyAttachmentService`

| Método | Acción | `changes` guardado |
|---|---|---|
| `attach()` | `attach` | `['attached' => [id, ...]]` — IDs de los modelos efectivamente actualizados |
| `detach()` | `detach` | `['detached' => [id, ...]]` — IDs recibidos en el request |

Mismo comportamiento que `HasManyAttachmentService` respecto al detach masivo.

---

### `MorphToManyAttachmentService`

Sin cambios. Hereda de `BelongsToManyAttachmentService` y obtiene tracking automáticamente.

---

## Qué guarda cada evento en `action_events`

| Columna | Valor |
|---|---|
| `name` | `attach`, `detach` o `sync` |
| `actionable_type` | Clase del Resource de Nadota |
| `actionable_id` | `0` (mismo comportamiento que CRUD) |
| `target_type` | Clase del modelo padre |
| `target_id` | ID del modelo padre |
| `model_type` | Clase del modelo padre |
| `model_id` | ID del modelo padre |
| `fields` | `{ "relation": "nombreDeLaRelacion" }` |
| `original` | `null` |
| `changes` | Payload de IDs adjuntados/desasociados |
| `status` | `finished` |

---

## Plan de implementación validado

> Validado contra el código real: `ActionEventService`, `AttachmentController`, modelos y tablas de test.

### Estado

- ✅ **P4** implementado — guard de resource null + try/catch + param `$original`
- ✅ **P3** implementado — `sync()` captura `attached_before` en `original`
- ✅ **P2** implementado — `HasMany`/`MorphMany` detach guardan IDs reales
- ⬜ **P1** pendiente — tests de integración (cubre P2/P3/P4 en una sola pasada)

---

### P4 — Proteger `trackAttachmentAction` (hacer primero)

**Validación:** `ActionEventService::log()` (línea 173) llama `get_class($resource)`. Si `$request->getResource()` retorna `null` (uso programático sin pasar por `AttachmentController::prepareResource()`), esto es un **fatal error** que rompe la operación de attach completa. Los servicios CRUD no tienen este problema porque siempre reciben el resource. El audit trail nunca debe tumbar la operación principal.

**Cambio — `AbstractAttachmentService::trackAttachmentAction()`:**

```php
protected function trackAttachmentAction(
    string $action,
    Model $parentModel,
    NadotaRequest $request,
    Field $field,
    array $changes,
    ?array $original = null   // ← agregado para P3
): void {
    if (!$this->shouldTrackActions()) {
        return;
    }

    $resource = $request->getResource();
    if ($resource === null) {
        return; // sin resource no hay contexto que registrar; no romper el attach
    }

    try {
        $relation = method_exists($field, 'getRelation') ? $field->getRelation() : $field->getAttribute();

        $this->getActionEventService()->logAction(
            action: $action,
            model: $parentModel,
            resource: $resource,
            request: $request,
            fields: ['relation' => $relation],
            metadata: ['changes' => $changes, 'original' => $original]
        );
    } catch (\Throwable $e) {
        \Log::error('Failed to track attachment action', [
            'action' => $action,
            'error'  => $e->getMessage(),
        ]);
    }
}
```

> `logSync()` ya tiene su propio try/catch anidado, pero ese catch solo cubre fallos de DB **dentro** de `log()`. El `get_class(null)` y un `getResource()` ausente ocurren **antes** de entrar a `logSync`, por eso el guard + try/catch va aquí.

---

### P3 — Capturar `original` en `sync` (y opcionalmente en detach)

**Validación:** `ActionEventService::logAction()` (línea 152) **ya** acepta `$metadata['original']` y lo persiste en la columna `original`. No requiere cambios en el servicio core — solo pasar el dato. El parámetro `?array $original = null` ya quedó agregado en el cambio de P4.

**Cambio — `BelongsToManyAttachmentService::sync()`**, capturar IDs antes del sync:

```php
$relatedKeyName = $relation->getRelated()->getKeyName();
$originalIds = $relation->pluck(
    $relation->getRelated()->getTable() . '.' . $relatedKeyName
)->toArray();

// ... lógica de sync existente ...

$this->trackAttachmentAction(
    'sync', $parentModel, $request, $field,
    $changes,
    ['attached_before' => $originalIds]
);
```

Resultado: la fila `action_events` queda con `original = {attached_before: [...]}` y `changes = {attached, detached, updated}` → auditoría completa del antes/después.

---

### P2 — IDs precisos en detach de HasMany / MorphMany

**Validación:** `HasManyAttachmentService::detach()` y `MorphManyAttachmentService::detach()` usan `->update([$fk => null])` masivo, cuyo retorno es un **conteo de filas**, no IDs. Hoy se guardan los IDs del request — si un ID no pertenecía a la relación se registra igual (audit trail inexacto). `BelongsToManyAttachmentService` no tiene este problema (`->detach()` ya filtra por la relación).

**Cambio — `HasManyAttachmentService::detach()`**, consultar IDs reales antes del update:

```php
$relatedKeyName = $parentModel->{$relationName}()->getRelated()->getKeyName();

$actualIds = $parentModel->{$relationName}()
    ->whereIn($relatedKeyName, $items)
    ->pluck($relatedKeyName)
    ->toArray();

$detached = $parentModel->{$relationName}()
    ->whereIn($relatedKeyName, $items)
    ->update([$foreignKey => null]);

$this->trackAttachmentAction('detach', $parentModel, $request, $field, ['detached' => $actualIds]);
```

Mismo patrón en `MorphManyAttachmentService::detach()` (el query de la relación ya incluye el scope morph, así que `pluck` devuelve solo los del padre correcto).

---

### P1 — Tests de integración

**Validación de infraestructura existente (confirmado):**

| Tipo | Relación de test disponible | Tabla |
|---|---|---|
| BelongsToMany | `TestModel::simpleTags()` → `Tag` | `test_model_tag` ✅ |
| HasMany | `TestModel::relatedModels()` → `RelatedModel` | `related_models` ✅ |
| MorphToMany | `TestModel::tags()` → `Tag` | `taggables` ✅ |
| MorphMany | **no existe** relación de test | — ❌ |

> **Gap:** No hay relación `morphMany` en los modelos de test. Opciones: (a) agregar `RelatedModel` polimórfico + relación `morphMany` en `TestModel` + columnas morph en `related_models` vía `TestCase`, o (b) documentar `MorphManyAttachmentService` como cubierto-por-paridad-de-código con `HasMany` (misma estructura de tracking) y testear solo HasMany. Recomendado: opción (b) para no inflar el setup de test; anotar la decisión.

**Patrón de invocación (igual que `ActionEventIntegrationTest`, llamada directa al servicio):**

```php
$model = TestModel::create(['name' => 'Parent']);
$tag   = Tag::create(['name' => 'T1']);

$field = BelongsToMany::make('Tags', 'simpleTags');

$request = new NadotaRequest();
$request->merge(['items' => [$tag->id]]);
$request->setResource(new TestResource());

(new BelongsToManyAttachmentService())->attach($request, $model, $field);

$this->assertDatabaseHas('action_events', [
    'name'       => 'attach',
    'status'     => 'finished',
    'model_type' => TestModel::class,
    'model_id'   => $model->id,
]);
```

Archivo: `tests/ServiceIntegration/AttachmentEventIntegrationTest.php`

Casos mínimos:
- [ ] BelongsToMany `attach` → registro `name=attach`, `changes.attached=[tagId]`
- [ ] BelongsToMany `detach` → registro `name=detach`
- [ ] BelongsToMany `sync` → registro `name=sync`, `changes` con attached/detached/updated, `original.attached_before` (cubre P3)
- [ ] HasMany `attach` → registro `name=attach`
- [ ] HasMany `detach` con un ID inexistente → `changes.detached` solo contiene los IDs reales (cubre P2)
- [ ] MorphToMany `attach` → registro `name=attach` (vía herencia de BelongsToMany)
- [ ] `nadota.action_events.enabled = false` → `assertDatabaseCount('action_events', 0)` tras attach (cubre P4 path feliz)
- [ ] attach con `$request` sin resource (`setResource` no llamado) → no lanza excepción, no crea registro (cubre P4)

---

### Resumen de archivos a tocar por pendiente

| Pendiente | Archivos |
|---|---|
| P4 | `AbstractAttachmentService.php` |
| P3 | `AbstractAttachmentService.php` (firma, hecho en P4) + `BelongsToManyAttachmentService::sync()` |
| P2 | `HasManyAttachmentService::detach()`, `MorphManyAttachmentService::detach()` |
| P1 | `tests/ServiceIntegration/AttachmentEventIntegrationTest.php` (nuevo) |

---

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `src/Http/Services/Attachments/AbstractAttachmentService.php` | +`use TracksActionEvents`, +`trackAttachmentAction()` |
| `src/Http/Services/Attachments/BelongsToManyAttachmentService.php` | +tracking en `attach`, `detach`, `sync` |
| `src/Http/Services/Attachments/HasManyAttachmentService.php` | +tracking en `attach`, `detach` |
| `src/Http/Services/Attachments/MorphManyAttachmentService.php` | +tracking en `attach`, `detach` |
| `src/Http/Services/Attachments/MorphToManyAttachmentService.php` | Sin cambios (hereda de BelongsToMany) |
