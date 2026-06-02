# Soft Deletes

Nadota supports Laravel soft deletes out of the box: listing trashed records, restoring them, and permanently removing them. This guide covers the requirement, how `withTrashed` flows through the index, the restore and force-delete endpoints, and how to customize the behavior.

## Requirement

Two things must line up.

1. **The Eloquent model uses the `SoftDeletes` trait** (a `deleted_at` column, or whatever `getDeletedAtColumn()` returns):

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;
}
```

2. **The resource reports that it uses soft deletes.** By default Nadota **autodetects** this from the model's trait, so usually you do nothing. The `$usesSoftDeletes` property lets you force it:

```php
class PostResource extends Resource
{
    public string $model = Post::class;

    // null (default) = autodetect from the model's SoftDeletes trait
    // true / false   = explicit override that wins over autodetection
    protected ?bool $usesSoftDeletes = null;
}
```

The effective value is resolved by `getUseSoftDeletes()`: an explicit `true`/`false` always wins; `null` checks whether the model uses the `SoftDeletes` trait. If the resource does not use soft deletes, the **restore** and **force-delete** endpoints return `400`.

> `usesSoftDeletes()` is a public alias of `getUseSoftDeletes()` and is what the services and pipes call.

## Listing with trashed records

The index endpoint controls which records it returns via the `withTrashed` query parameter. This only takes effect when the resource uses soft deletes; otherwise it is ignored and only active records are returned.

```
GET /nadota-api/{resourceKey}/resource?withTrashed=only
```

The value is normalized by `BuildQueryPipe::addTrashedCondition()`:

| Behavior | Accepted values | Scope applied |
|---|---|---|
| Active only (default) | `without`, `active`, `0`, `''`, `false`, absent | none (Eloquent default) |
| Only trashed | `only`, `deleted`, `1` | `onlyTrashed()` |
| All (active + trashed) | `with`, `all`, `2`, `true` | `withTrashed()` |

It combines with search, filters, sorting, and pagination. The pipe also guarantees the `deleted_at` column is selected even with optimized column selection, so each row can expose `deletedAt` and compute the `restore` permission.

### Response shape

Each record carries `deletedAt` and a `permissions` block:

```json
{
  "data": [
    {
      "id": 12,
      "attributes": [ /* index fields */ ],
      "deletedAt": "2026-05-28T14:03:00.000000Z",
      "permissions": {
        "view": true,
        "update": true,
        "delete": true,
        "forceDelete": true,
        "restore": true,
        "attach": true,
        "detach": true,
        "fields": {}
      }
    }
  ]
}
```

- `deletedAt` is `null` for active records and a timestamp for trashed ones.
- `permissions.restore` is `true` only when the resource uses soft deletes **and** the record is trashed (`deletedAt != null`) **and** the policy authorizes `restore`.
- `permissions.forceDelete` is `true` when the resource uses soft deletes **and** the policy authorizes `forceDelete`.

These flags let the frontend decide when to show the "Restore" and "Delete permanently" buttons. See [authorization](authorization.md#per-record-permission-matrix) for how the matrix is computed.

## Restore endpoint

```
POST /nadota-api/{resourceKey}/resource/{id}/restore
```

Handled by `ResourceRestoreService`:

- Looks up the record with `onlyTrashed()` — it must currently be trashed.
- Requires the policy ability `restore`.
- Runs, inside a transaction: `beforeRestore` → `performRestore` → track a `restore` action event → `refresh()` → `afterRestore`.

Responses:

| Status | Body |
|---|---|
| `200` | `{ "message": "Resource restored successfully", "data": { ... } }` |
| `400` | `{ "message": "This resource does not support restore" }` (resource not soft-deletable) |
| `404` | No trashed record with that `id`. |
| `403` | Policy denied. |
| `500` | `{ "message": "Failed to restore resource", "error": "..." }` (rolled back) |

```bash
curl -X POST https://your-app.test/nadota-api/posts/resource/12/restore \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json"
```

## Force-delete endpoint

```
DELETE /nadota-api/{resourceKey}/resource/{id}/force
```

Handled by `ResourceForceDeleteService`:

- Looks up the record with `withTrashed()` — works whether the record is active or already trashed.
- Requires the policy ability `forceDelete`.
- Runs, inside a transaction: `beforeForceDelete` → track a `forceDelete` action event (with `changes: { "permanently_deleted": true }`) → `performForceDelete` → `afterForceDelete`.

Responses:

| Status | Body |
|---|---|
| `200` | `{ "message": "Resource permanently deleted" }` |
| `400` | `{ "message": "This resource does not support force delete" }` |
| `404` | No record (active or trashed) with that `id`. |
| `403` | Policy denied. |
| `500` | `{ "message": "Failed to permanently delete resource", "error": "..." }` (rolled back) |

```bash
curl -X DELETE https://your-app.test/nadota-api/posts/resource/12/force \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json"
```

> The route IDs are restricted to integers (`[0-9]+`). See [the routes reference](../api/routes.md) for the full route list.

## Customizing soft-delete behavior

Override the lifecycle hooks and the per-record permission methods on the resource:

```php
class PostResource extends Resource
{
    public string $model = Post::class;
    protected ?bool $usesSoftDeletes = true;

    // Restore
    public function beforeRestore(Model $model, NadotaRequest $request): void { /* ... */ }
    public function performRestore(Model $model, NadotaRequest $request): bool { return $model->restore(); }
    public function afterRestore(Model $model, NadotaRequest $request): void { /* ... */ }

    // Force delete
    public function beforeForceDelete(Model $model, NadotaRequest $request): void { /* ... */ }
    public function performForceDelete(Model $model, NadotaRequest $request): bool { return $model->forceDelete(); }
    public function afterForceDelete(Model $model, NadotaRequest $request): void { /* ... */ }

    // Per-record gates (combined with the policy via AND)
    public function canRestore(Model $model, NadotaRequest $request): bool { return true; }
    public function canForceDelete(Model $model, NadotaRequest $request): bool { return true; }
}
```

You can also globally disable an action regardless of policy:

```php
protected bool $canForceDelete = false; // also $canRestore, $canDelete
```

The final permission is always `can*() AND authorizedTo()`. For the full hook sequence, transaction behavior, and custom delete recipes, see [lifecycle hooks](lifecycle-hooks.md).

## Related guides

- [Lifecycle hooks](lifecycle-hooks.md)
- [Authorization](authorization.md)
- [Resources](resources.md)
- [API routes](../api/routes.md)
