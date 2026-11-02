# Attachment Action Events — Implementation and Pending Work

> **Status (v1.2.6+): Implemented.** Verified against
> [`src/Http/Services/Attachments/AbstractAttachmentService.php`](../../src/Http/Services/Attachments/AbstractAttachmentService.php),
> `BelongsToManyAttachmentService`, `HasManyAttachmentService` and
> `MorphManyAttachmentService`. The base tracking infrastructure (P4 — `try/catch` +
> `$original` parameter), accurate detach IDs (P2), the `original` capture on sync (P3)
> and the integration test suite (P1,
> [`tests/ServiceIntegration/AttachmentEventIntegrationTest.php`](../../tests/ServiceIntegration/AttachmentEventIntegrationTest.php))
> are all in place. Writing P1 surfaced three bugs, fixed alongside it: the P4 null guard
> never fired, `BelongsToMany` detach recorded the requested IDs, and `sync` stored
> `detached` as a JSON object.

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
| `detach()` | `detach` | `['detached' => [id, ...]]` — only the IDs that were attached |
| `sync()` | `sync` | `['attached' => [...], 'detached' => [...], 'updated' => [...]]` — Laravel's return, each re-indexed as a list |

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

- ✅ **P4** implemented — `try/catch` (now also around `getResource()`) + `$original` parameter
- ✅ **P3** implemented — `sync()` captures `attached_before` in `original`
- ✅ **P2** implemented — `HasMany` / `MorphMany` / `BelongsToMany` detach store the real IDs
- ✅ **P1** implemented — integration tests (covers P2/P3/P4 in a single pass)

---

### P4 — Protect `trackAttachmentAction` (done)

**Rationale:** The audit trail must never take down the main operation. The original
change guarded against `$request->getResource()` returning `null` (programmatic use that
bypasses `AttachmentController::prepareResource()`), but `getResource()` never returns
`null`: with no resource set it resolves one from the route and aborts with a 404. The
guard never fired and the 404 escaped after the attach had been written. The call now
runs inside the `try`.

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

    try {
        // getResource() aborts with a 404 when no resource is set
        $resource = $request->getResource();
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
belonged to the relation (inaccurate audit trail). `BelongsToManyAttachmentService` had
the same problem: `->detach()` only deletes pivot rows of the relation, but the event
stored the request IDs. It now plucks the attached IDs before detaching.

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

### P1 — Integration Tests (done)

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
> Decision: option (b), to avoid inflating the test setup. The test file says so.

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
- [x] BelongsToMany `attach` → record `name=attach`, `changes.attached=[tagId]`
- [x] BelongsToMany `detach` → record `name=detach`, `changes.detached` contains only the attached IDs
- [x] BelongsToMany `sync` → record `name=sync`, `changes` with attached/detached/updated, `original.attached_before` (covers P3)
- [x] HasMany `attach` → record `name=attach`
- [x] HasMany `detach` with a non-existent ID → `changes.detached` contains only the real IDs (covers P2)
- [x] MorphToMany `attach` → record `name=attach` (via inheritance from BelongsToMany)
- [x] `nadota.action_events.enabled = false` → `assertDatabaseCount('action_events', 0)` after attach (covers P4 happy path)
- [x] attach with a `$request` without a resource (`setResource` not called) → no exception, no record (covers P4)

---

## Modified Files (delivered)

| File | Change |
|------|--------|
| `src/Http/Services/Attachments/AbstractAttachmentService.php` | `+use TracksActionEvents`, `+trackAttachmentAction()`, `try/catch` around `getResource()` and the log call, `$original` |
| `src/Http/Services/Attachments/BelongsToManyAttachmentService.php` | tracking on `attach`, `detach` (real detached IDs), `sync` (+ `attached_before` original, list-indexed changes) |
| `src/Http/Services/Attachments/HasManyAttachmentService.php` | tracking on `attach`, `detach` (real detached IDs) |
| `src/Http/Services/Attachments/MorphManyAttachmentService.php` | tracking on `attach`, `detach` (real detached IDs) |
| `src/Http/Services/Attachments/MorphToManyAttachmentService.php` | no change (inherits from BelongsToMany) |
| `tests/ServiceIntegration/AttachmentEventIntegrationTest.php` | P1 integration suite (9 tests) |
