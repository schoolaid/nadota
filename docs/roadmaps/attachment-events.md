# Attachment Action Events — Implementation and Pending Work

> **Status (v1.1.4): Implemented, one item pending.** Verified against
> [`src/Http/Services/Attachments/AbstractAttachmentService.php`](../../src/Http/Services/Attachments/AbstractAttachmentService.php),
> `BelongsToManyAttachmentService`, `HasManyAttachmentService` and
> `MorphManyAttachmentService`. The base tracking infrastructure (P4 — null-resource
> guard + `try/catch` + `$original` parameter), accurate detach IDs for HasMany/MorphMany
> (P2), and the `original` capture on sync (P3) are all in place. The only outstanding
> item is **P1 — the integration test suite** (`tests/ServiceIntegration/AttachmentEventIntegrationTest.php`
> does not yet exist).

## What It Does

Records every `attach`, `detach` and `sync` operation on relations in the `action_events`
table, using the same `TracksActionEvents` system as the CRUD services. The recorded
action name is `attach`, `detach` or `sync`.

---

## Changes Made

### `AbstractAttachmentService` — Base Infrastructure

The `TracksActionEvents` trait and the `trackAttachmentAction()` helper were added.

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
        array $changes,
        ?array $original = null
    ): void {
        // ...
        $this->getActionEventService()->logAction(
            action: $action,
            model: $parentModel,
            resource: $request->getResource(),
            request: $request,
            fields: ['relation' => $relation],
            metadata: ['changes' => $changes, 'original' => $original]
        );
    }
}
```

The `fields` column stores the relation name. The `changes` column stores the
operation-specific payload.

---

### `BelongsToManyAttachmentService`

| Method | Action | Stored `changes` |
|--------|--------|------------------|
| `attach()` | `attach` | `['attached' => [id, ...]]` |
| `detach()` | `detach` | `['detached' => [id, ...]]` |
| `sync()` | `sync` | `['attached' => [...], 'detached' => [...], 'updated' => [...]]` (direct from Laravel's return) |

---

### `HasManyAttachmentService`

| Method | Action | Stored `changes` |
|--------|--------|------------------|
| `attach()` | `attach` | `['attached' => [id, ...]]` — IDs of the models actually updated |
| `detach()` | `detach` | `['detached' => [id, ...]]` — real IDs queried before the bulk update |

> **Note:** Detach on HasMany uses a bulk `->update()` that returns the affected row
> count, not IDs. The service queries the actual IDs (P2) before the update so the audit
> trail is accurate.

---

### `MorphManyAttachmentService`

| Method | Action | Stored `changes` |
|--------|--------|------------------|
| `attach()` | `attach` | `['attached' => [id, ...]]` — IDs of the models actually updated |
| `detach()` | `detach` | `['detached' => [id, ...]]` — real IDs queried before the bulk update |

Same behavior as `HasManyAttachmentService` regarding bulk detach.

---

### `MorphToManyAttachmentService`

No changes. Inherits from `BelongsToManyAttachmentService` and gets tracking automatically.

---

## What Each Event Stores in `action_events`

| Column | Value |
|--------|-------|
| `name` | `attach`, `detach` or `sync` |
| `actionable_type` | The Nadota Resource class |
| `actionable_id` | `0` (same behavior as CRUD) |
| `target_type` | The parent model class |
| `target_id` | The parent model ID |
| `model_type` | The parent model class |
| `model_id` | The parent model ID |
| `fields` | `{ "relation": "relationName" }` |
| `original` | `null` (or `{ attached_before: [...] }` for sync) |
| `changes` | Payload of attached/detached IDs |
| `status` | `finished` |

---

## Validated Implementation Plan

> Validated against real code: `ActionEventService`, `AttachmentController`, test models
> and tables.

### Status

- ✅ **P4** implemented — null-resource guard + `try/catch` + `$original` parameter
- ✅ **P3** implemented — `sync()` captures `attached_before` in `original`
- ✅ **P2** implemented — `HasMany` / `MorphMany` detach store the real IDs
- ⬜ **P1** pending — integration tests (covers P2/P3/P4 in a single pass)

---

### P4 — Protect `trackAttachmentAction` (done)

**Rationale:** `ActionEventService::log()` calls `get_class($resource)`. If
`$request->getResource()` returns `null` (programmatic use that bypasses
`AttachmentController::prepareResource()`), that would be a **fatal error** breaking the
whole attach operation. The audit trail must never take down the main operation.

**Change — `AbstractAttachmentService::trackAttachmentAction()`:**

```php
protected function trackAttachmentAction(
    string $action,
    Model $parentModel,
    NadotaRequest $request,
    Field $field,
    array $changes,
    ?array $original = null   // ← added for P3
): void {
    if (!$this->shouldTrackActions()) {
        return;
    }

    $resource = $request->getResource();
    if ($resource === null) {
        return; // no resource means no context to record; do not break the attach
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

> `logSync()` has its own nested `try/catch`, but it only covers DB failures *inside*
> `log()`. The `get_class(null)` / missing `getResource()` happen *before* entering
> `logSync`, which is why the guard + `try/catch` live here.

---

### P3 — Capture `original` in `sync` (done; optional in detach)

**Rationale:** `ActionEventService::logAction()` already accepts `$metadata['original']`
and persists it in the `original` column. No core change required — just pass the data.
The `?array $original = null` parameter was already added in P4.

**Change — `BelongsToManyAttachmentService::sync()`**, capture IDs before the sync:

```php
$relatedKeyName = $relation->getRelated()->getKeyName();
$originalIds = $relation->pluck(
    $relation->getRelated()->getTable() . '.' . $relatedKeyName
)->toArray();

// ... existing sync logic ...

$this->trackAttachmentAction(
    'sync', $parentModel, $request, $field,
    $changes,
    ['attached_before' => $originalIds]
);
```

Result: the `action_events` row ends up with `original = {attached_before: [...]}` and
`changes = {attached, detached, updated}` → full before/after audit.

---

### P2 — Accurate IDs in HasMany / MorphMany detach (done)

**Rationale:** `HasManyAttachmentService::detach()` and
`MorphManyAttachmentService::detach()` use a bulk `->update([$fk => null])`, whose return
is a **row count**, not IDs. Storing the request IDs would record IDs that may not have
belonged to the relation (inaccurate audit trail). `BelongsToManyAttachmentService` does
not have this problem (`->detach()` already filters by the relation).

**Change — `HasManyAttachmentService::detach()`**, query the real IDs before the update:

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

Same pattern in `MorphManyAttachmentService::detach()` (the relation query already
includes the morph scope, so `pluck` returns only the correct parent's records).

---

### P1 — Integration Tests (pending)

**Confirmed existing test infrastructure:**

| Type | Available test relation | Table |
|------|-------------------------|-------|
| BelongsToMany | `TestModel::simpleTags()` → `Tag` | `test_model_tag` ✅ |
| HasMany | `TestModel::relatedModels()` → `RelatedModel` | `related_models` ✅ |
| MorphToMany | `TestModel::tags()` → `Tag` | `taggables` ✅ |
| MorphMany | **does not exist** as a test relation | — ❌ |

> **Gap:** There is no `morphMany` relation in the test models. Options: (a) add a
> polymorphic `RelatedModel` + a `morphMany` relation on `TestModel` + morph columns on
> `related_models` via `TestCase`, or (b) document `MorphManyAttachmentService` as
> covered-by-code-parity with `HasMany` (same tracking structure) and test only HasMany.
> Recommended: option (b) to avoid inflating the test setup; note the decision.

**Invocation pattern (same as `ActionEventIntegrationTest`, direct service call):**

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

File: `tests/ServiceIntegration/AttachmentEventIntegrationTest.php`

Minimum cases:
- [ ] BelongsToMany `attach` → record `name=attach`, `changes.attached=[tagId]`
- [ ] BelongsToMany `detach` → record `name=detach`
- [ ] BelongsToMany `sync` → record `name=sync`, `changes` with attached/detached/updated, `original.attached_before` (covers P3)
- [ ] HasMany `attach` → record `name=attach`
- [ ] HasMany `detach` with a non-existent ID → `changes.detached` contains only the real IDs (covers P2)
- [ ] MorphToMany `attach` → record `name=attach` (via inheritance from BelongsToMany)
- [ ] `nadota.action_events.enabled = false` → `assertDatabaseCount('action_events', 0)` after attach (covers P4 happy path)
- [ ] attach with a `$request` without a resource (`setResource` not called) → no exception, no record (covers P4)

---

### Files to Touch per Pending Item

| Item | Files |
|------|-------|
| P1 | `tests/ServiceIntegration/AttachmentEventIntegrationTest.php` (new) |

---

## Modified Files (delivered)

| File | Change |
|------|--------|
| `src/Http/Services/Attachments/AbstractAttachmentService.php` | `+use TracksActionEvents`, `+trackAttachmentAction()`, null-resource guard, `try/catch`, `$original` |
| `src/Http/Services/Attachments/BelongsToManyAttachmentService.php` | tracking on `attach`, `detach`, `sync` (+ `attached_before` original) |
| `src/Http/Services/Attachments/HasManyAttachmentService.php` | tracking on `attach`, `detach` (real detached IDs) |
| `src/Http/Services/Attachments/MorphManyAttachmentService.php` | tracking on `attach`, `detach` (real detached IDs) |
| `src/Http/Services/Attachments/MorphToManyAttachmentService.php` | no change (inherits from BelongsToMany) |
