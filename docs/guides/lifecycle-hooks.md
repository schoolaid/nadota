# Lifecycle Hooks

Nadota runs your custom logic at well-defined points during every write operation — store, update, delete, restore, and force delete — all inside a database transaction. This guide covers each hook, the execution order, transaction behavior, custom delete logic, and the search/options hooks.

## Overview

Every hook is a method on your Resource class. The base `Resource` ships empty (no-op) implementations, so you only override the ones you need.

| Operation | Before | Custom action | After | On failure |
|---|---|---|---|---|
| **Store** | `beforeStore` | — | `afterStore` | — |
| **Update** | `beforeUpdate` | — | `afterUpdate` | — |
| **Delete** | `beforeDelete` | `performDelete` | `afterDelete` | `onDeleteFailed` |
| **Restore** | `beforeRestore` | `performRestore` | `afterRestore` | — |
| **Force delete** | `beforeForceDelete` | `performForceDelete` | `afterForceDelete` | — |

All five operations wrap their hooks in `DB::beginTransaction()` / `DB::commit()`, rolling back on any exception.

## Store hooks

Handled by `ResourceStoreService` (extends `AbstractResourcePersistService`).

### `beforeStore`

Called after validation and field filling, **before** `$model->save()`. The model has no ID yet.

```php
public function beforeStore(Model $model, NadotaRequest $request): void
{
    $model->created_by = $request->user()->id;
    $model->tenant_id  = $request->user()->tenant_id;
}
```

### `afterStore`

Called after `$model->save()` and after relationship fields run their `afterSave()`. The model now has an ID.

```php
public function afterStore(Model $model, NadotaRequest $request): void
{
    $model->settings()->create(['theme' => 'default']);
    ProcessNewRecord::dispatch($model);
}
```

## Update hooks

Handled by `ResourceUpdateService`.

### `beforeUpdate`

Called after validation/fill, before `$model->save()`:

```php
public function beforeUpdate(Model $model, NadotaRequest $request): void
{
    $model->updated_by = $request->user()->id;
}
```

### `afterUpdate`

Called after save. Receives `$originalData` — the model's attributes captured **before** filling — so you can diff:

```php
public function afterUpdate(Model $model, NadotaRequest $request, array $originalData): void
{
    if (($originalData['status'] ?? null) !== $model->status) {
        event(new StatusChanged($model, $originalData['status'] ?? null, $model->status));
    }
}
```

## Delete hooks

Handled by `ResourceDestroyService`. The whole sequence runs in a transaction.

### `beforeDelete`

Validate, cascade, or stage cleanup. Throwing here aborts and rolls back:

```php
public function beforeDelete(Model $model, NadotaRequest $request): void
{
    if ($model->invoices()->unpaid()->exists()) {
        throw new \Exception('Cannot delete: has unpaid invoices');
    }

    $model->enrollments()->delete();   // rolled back if the delete fails
    $model->courses()->detach();

    $this->pendingFileDelete = $model->photo_path; // stage, don't delete yet
}
```

### `performDelete`

The actual delete. Default implementation calls `$model->delete()` (which soft-deletes when the model uses `SoftDeletes`). Override to change the behavior — see [Custom delete logic](#custom-delete-logic). Must return `true` on success; returning `false` triggers a rollback with "Delete operation failed".

### `afterDelete`

Runs after a successful `performDelete`, still inside the transaction (before commit). Safe place to finalize staged external work:

```php
public function afterDelete(Model $model, NadotaRequest $request): void
{
    if (isset($this->pendingFileDelete)) {
        Storage::delete($this->pendingFileDelete);
    }
}
```

### `onDeleteFailed`

Called **after rollback** when any step throws. Use it for logging and external cleanup:

```php
public function onDeleteFailed(Model $model, NadotaRequest $request, \Exception $exception): void
{
    Log::error('Delete failed', ['id' => $model->id, 'error' => $exception->getMessage()]);
}
```

## Restore hooks

Handled by `ResourceRestoreService`. Only available when the resource uses soft deletes; otherwise the endpoint returns `400`. The record is loaded with `onlyTrashed()`.

```php
public function beforeRestore(Model $model, NadotaRequest $request): void
{
    if ($model->deleted_at->diffInDays(now()) > 30) {
        throw new \Exception('Cannot restore: older than 30 days');
    }
}

public function performRestore(Model $model, NadotaRequest $request): bool
{
    return $model->restore(); // default
}

public function afterRestore(Model $model, NadotaRequest $request): void
{
    $model->enrollments()->withTrashed()->restore();
}
```

The service refreshes the model (`$model->refresh()`) after `performRestore` and before `afterRestore`.

## Force delete hooks

Handled by `ResourceForceDeleteService`. Only available when the resource uses soft deletes (else `400`). The record is loaded with `withTrashed()`, so it works whether or not it was already trashed.

```php
public function beforeForceDelete(Model $model, NadotaRequest $request): void
{
    if (!$request->user()->isSuperAdmin()) {
        abort(403, 'Only super admins may permanently delete');
    }
}

public function performForceDelete(Model $model, NadotaRequest $request): bool
{
    if ($model->file_path) {
        Storage::delete($model->file_path);
    }
    $model->versions()->forceDelete();
    return $model->forceDelete(); // default just calls forceDelete()
}

public function afterForceDelete(Model $model, NadotaRequest $request): void
{
    // post-cleanup
}
```

See [the soft deletes guide](soft-deletes.md) for the restore/force-delete requirements and endpoints.

## Transaction behavior

Every operation wraps its hooks in a single transaction:

```php
DB::beginTransaction();
//   beforeHook → process fields / performX → afterHook → track action event
DB::commit();   // or DB::rollBack() on any exception
```

### Rolled back on failure

- All Eloquent/DB writes inside any hook (`$model->relation()->delete()`, updates, etc.).
- The main save / delete / restore itself.
- The recorded action event.

### NOT rolled back

External side effects are outside the database transaction and **persist even if the transaction fails**:

- File storage operations (`Storage::delete()`, uploads)
- HTTP calls to external services
- Sent emails / notifications
- Cache writes
- Already-dispatched queue jobs

**Best practice:** stage external work in the `before*` hook and execute it in the `after*` hook, which only runs on the success path:

```php
public function beforeDelete(Model $model, NadotaRequest $request): void
{
    $this->photoToDelete = $model->photo; // stage
    $model->children()->delete();         // transactional
}

public function afterDelete(Model $model, NadotaRequest $request): void
{
    Storage::delete($this->photoToDelete); // safe — about to commit
}
```

## Execution order

### Store
1. Validate request against field rules.
2. `beforeStore($model, $request)`.
3. Fields run `beforeSave()` (if supported) and `fill()`.
4. `$model->save()`.
5. Relationship fields run `afterSave()` (e.g. `BelongsToMany`).
6. `afterStore($model, $request)`.
7. Track the create action event.
8. Commit.

### Update
1. Capture `$originalData` (`$model->getAttributes()`).
2. Validate.
3. `beforeUpdate($model, $request)`.
4. Fields `beforeSave()` + `fill()`.
5. `$model->save()`.
6. Fields `afterSave()`.
7. `afterUpdate($model, $request, $originalData)`.
8. Track the update action event.
9. Commit.

> In store/update the resource `before*` hook fires **before** field processing; the action event is tracked after the `after*` hook.

### Delete
1. `beforeDelete($model, $request)`.
2. Track the delete action event.
3. `performDelete($model, $request)`.
4. `afterDelete($model, $request)`.
5. Commit. *(On exception → rollback → `onDeleteFailed`.)*

### Restore
1. Load with `onlyTrashed()`, authorize `restore`.
2. `beforeRestore` → `performRestore` → track restore event → `refresh()` → `afterRestore` → commit.

### Force delete
1. Load with `withTrashed()`, authorize `forceDelete`.
2. `beforeForceDelete` → track forceDelete event → `performForceDelete` → `afterForceDelete` → commit.

## Custom delete logic

Override `performDelete` (or `performRestore` / `performForceDelete`) to replace the default behavior. It must return a boolean.

**Archive instead of delete:**

```php
public function performDelete(Model $model, NadotaRequest $request): bool
{
    $model->status = 'archived';
    $model->archived_at = now();
    return $model->save();
}

public function performRestore(Model $model, NadotaRequest $request): bool
{
    $model->status = 'active';
    $model->archived_at = null;
    return $model->save();
}
```

**Cascade + extend default:**

```php
public function performDelete(Model $model, NadotaRequest $request): bool
{
    $model->comments()->delete();
    return parent::performDelete($model, $request);
}
```

**Conditional force delete with cleanup:**

```php
public function performForceDelete(Model $model, NadotaRequest $request): bool
{
    if ($model->file_path) {
        Storage::delete($model->file_path);
    }
    $model->versions()->forceDelete();
    return $model->forceDelete();
}
```

Because everything already runs inside a Nadota-managed transaction, you do not need to wrap these in `DB::transaction()` yourself — though you may for clarity. Throwing an exception (e.g. `abort(403, ...)`) inside any hook rolls the whole operation back.

## Search hooks

### `applySearch`

Optional. If your resource defines `applySearch($query, string $search)`, it is detected automatically and called during index search, **inside** the `where(function () { ... })` group that also contains the `$searchableAttributes` / `$searchableRelations` conditions. Use `orWhere` so your conditions join the OR group:

```php
public function applySearch($query, string $search): void
{
    if (is_numeric($search)) {
        $query->orWhere('id', $search);
    }

    $query->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
}
```

This only runs when the request includes a non-empty search term under the resource's search key (default `globalSearch`). See [search configuration](resources.md#search-configuration).

## Options hooks

These customize the data returned by the options endpoints used by relation pickers (`/{resource}/resource/options` and `/{resource}/resource/field/{field}/options`).

### `optionsQuery`

Called for every options request. Override to scope or filter the base query:

```php
public function optionsQuery(Builder $query, NadotaRequest $request, array $params = []): Builder
{
    return $query
        ->where('tenant_id', $request->user()->tenant_id)
        ->where('is_active', true);
}
```

`$params` may include `search`, `limit` (capped at 100), `exclude` (IDs), `orderBy`, and `orderDirection`.

### `optionsSearch`

Return a custom collection to bypass the default DB `LIKE` search (e.g. Scout/Meilisearch). Return `null` to fall back to the default:

```php
public function optionsSearch(NadotaRequest $request, array $params = []): Collection|array|null
{
    $search = $params['search'] ?? '';
    if ($search === '') {
        return null; // use default DB search
    }

    return Student::search($search)->take($params['limit'] ?? 15)->get();
}
```

Returned models are formatted with `displayLabel()` / `optionsFormat()`.

## Related guides

- [Resources](resources.md)
- [Soft deletes](soft-deletes.md)
- [Authorization](authorization.md)
- [Fields](../fields/README.md)
- [API routes](../api/routes.md)
