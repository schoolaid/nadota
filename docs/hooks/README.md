# Resource Hooks

Nadota provides lifecycle hooks that allow you to execute custom logic at specific points during CRUD operations.

## Available Hooks

| Operation | Before | After | On Failure |
|-----------|--------|-------|------------|
| **Store (Create)** | `beforeStore` | `afterStore` | - |
| **Update** | `beforeUpdate` | `afterUpdate` | - |
| **Delete** | `beforeDelete` | `afterDelete` | `onDeleteFailed` |
| **Restore** | `beforeRestore` | `afterRestore` | - |

## Store Hooks

### beforeStore

Called before saving a new model. The model does not have an ID yet.

```php
public function beforeStore(Model $model, NadotaRequest $request): void
{
    $model->created_by = auth()->id();
    $model->school_id = $request->user()->school_id;
}
```

### afterStore

Called after the model is saved successfully. The model now has an ID.

```php
public function afterStore(Model $model, NadotaRequest $request): void
{
    // Send welcome notification
    $model->notify(new WelcomeNotification());

    // Create related records
    $model->settings()->create(['theme' => 'default']);

    // Dispatch job
    ProcessNewStudent::dispatch($model);
}
```

## Update Hooks

### beforeUpdate

Called before updating an existing model.

```php
public function beforeUpdate(Model $model, NadotaRequest $request): void
{
    $model->updated_by = auth()->id();
}
```

### afterUpdate

Called after the model is updated. Receives original data for comparison.

```php
public function afterUpdate(Model $model, NadotaRequest $request, array $originalData): void
{
    // Check if status changed
    if ($originalData['status'] !== $model->status) {
        event(new StatusChanged($model, $originalData['status'], $model->status));
    }

    // Check if email changed
    if ($originalData['email'] !== $model->email) {
        $model->notify(new EmailChangedNotification());
    }
}
```

## Delete Hooks

All delete hooks run inside a database transaction. If any hook throws an exception, the entire operation is rolled back.

### beforeDelete

Called before deleting. Use this to:
- Validate if deletion is allowed
- Delete related records without cascade
- Prepare external cleanup

```php
public function beforeDelete(Model $model, NadotaRequest $request): void
{
    // Validate - throw exception to prevent deletion
    if ($model->invoices()->unpaid()->exists()) {
        throw new \Exception('Cannot delete: has unpaid invoices');
    }

    // Delete relations without DB cascade (WILL BE ROLLED BACK if delete fails)
    $model->enrollments()->delete();
    $model->grades()->delete();
    $model->attendances()->delete();

    // Detach many-to-many relations
    $model->courses()->detach();

    // Store file path for later deletion (don't delete yet!)
    $this->pendingFileDelete = $model->photo_path;
}
```

### afterDelete

Called after successful deletion, before transaction commit.

```php
public function afterDelete(Model $model, NadotaRequest $request): void
{
    // Safe to delete files now - transaction will commit
    if ($this->pendingFileDelete) {
        Storage::delete($this->pendingFileDelete);
    }

    // Log the deletion
    activity()->log("Deleted {$model->name}");

    // Notify admins
    Notification::send($admins, new RecordDeletedNotification($model));
}
```

### onDeleteFailed

Called after rollback when deletion fails. Use this for:
- Error logging
- Cleanup of external resources
- Admin notifications

```php
public function onDeleteFailed(Model $model, NadotaRequest $request, \Exception $exception): void
{
    Log::error('Failed to delete record', [
        'model' => get_class($model),
        'id' => $model->id,
        'error' => $exception->getMessage(),
        'user' => auth()->id(),
    ]);

    // Notify admins of failure
    Notification::send($admins, new DeleteFailedNotification($model, $exception));
}
```

## Restore Hooks (Soft Deletes)

### beforeRestore

Called before restoring a soft-deleted model.

```php
public function beforeRestore(Model $model, NadotaRequest $request): void
{
    // Validate restoration
    if ($model->deleted_at->diffInDays(now()) > 30) {
        throw new \Exception('Cannot restore: record older than 30 days');
    }
}
```

### afterRestore

Called after successful restoration.

```php
public function afterRestore(Model $model, NadotaRequest $request): void
{
    // Reactivate related records
    $model->enrollments()->withTrashed()->restore();

    // Notify user
    $model->notify(new AccountRestoredNotification());
}
```

## Transaction Behavior

### What Gets Rolled Back

All database operations inside hooks are part of the transaction:

```php
public function beforeDelete(Model $model, NadotaRequest $request): void
{
    $model->enrollments()->delete();  // ← Rolled back if delete fails
    $model->grades()->delete();       // ← Rolled back if delete fails
}
```

### What Does NOT Get Rolled Back

External operations are NOT part of the transaction:

- File storage operations (`Storage::delete()`)
- API calls to external services
- Sent emails/notifications
- Cache operations
- Queue jobs (already dispatched)

**Best Practice:** Delay external operations until `afterDelete`:

```php
// BAD - file deleted even if transaction fails
public function beforeDelete(Model $model, NadotaRequest $request): void
{
    Storage::delete($model->photo);  // ← NOT rolled back!
    $model->children()->delete();
}

// GOOD - file deleted only after successful commit
public function beforeDelete(Model $model, NadotaRequest $request): void
{
    $this->photoToDelete = $model->photo;  // Store reference
    $model->children()->delete();
}

public function afterDelete(Model $model, NadotaRequest $request): void
{
    Storage::delete($this->photoToDelete);  // Safe - transaction committed
}
```

## Execution Order

### Store
1. Validation
2. Field `fill()`
3. `beforeStore($model, $request)`
4. `$model->save()`
5. Field `afterSave()` (for relations like BelongsToMany)
6. `afterStore($model, $request)`

### Update
1. Capture `$originalData`
2. Validation
3. Field `fill()`
4. `beforeUpdate($model, $request)`
5. `$model->save()`
6. Field `afterSave()` (for relations)
7. `afterUpdate($model, $request, $originalData)`

### Delete
1. `beforeDelete($model, $request)`
2. Track action event
3. `performDelete($model, $request)`
4. `afterDelete($model, $request)`
5. Commit transaction
6. *(On failure)* Rollback → `onDeleteFailed($model, $request, $exception)`

## Custom Delete Logic

Override `performDelete` to customize deletion behavior:

```php
public function performDelete(Model $model, NadotaRequest $request): bool
{
    // Archive instead of delete
    $model->archived_at = now();
    $model->archived_by = auth()->id();
    return $model->save();
}
```

## Search Hooks

### applySearch

Called during index and options search to add custom search logic.

```php
public function applySearch($query, string $search): void
{
    // Search by ID if numeric
    if (is_numeric($search)) {
        $query->orWhere('id', $search);
    }

    // Search in JSON field
    $query->orWhereJsonContains('tags', $search);

    // Search concatenated fields
    $query->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
}
```

**Note:** This hook is called inside a `WHERE (...)` clause with `orWhere` conditions from `$searchable` fields. Use `orWhere` to add conditions.

## Options Query Hook

### optionsQuery

Called for every options request (with or without search). Use for filtering.

```php
public function optionsQuery($query, NadotaRequest $request, array $params = [])
{
    // Filter by route parameter
    if ($routeId = $request->get('routeId')) {
        $query->where('route_id', $routeId);
    }

    // Filter by user's school
    $query->where('school_id', $request->user()->school_id);

    // Only active records
    $query->where('active', true);

    return $query;
}
```
