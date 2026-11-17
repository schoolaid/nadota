# Attachments

Manage the records on a "many" relationship field — list attachable items, attach, detach, and (where supported) sync — through dedicated resource endpoints.

Attachments power the UI for relationship fields that hold multiple related records. The `AttachmentController` resolves the right service for the field's relation type and delegates the operation. See [relation fields](../fields/relation-fields.md) for how to define and configure these fields.

## Supported relation types

| Relation | attachable | attach | detach | sync | Pivot data |
|----------|:---------:|:------:|:------:|:----:|:----------:|
| HasMany ¹ | yes | yes | yes | no | no |
| MorphMany ¹ | yes | yes | yes | no | no |
| BelongsToMany | yes | yes | yes | yes | yes |
| MorphToMany | yes | yes | yes | yes | yes |
| MorphedByMany | yes | yes | yes | yes | yes |

Each type maps to a service (`AttachmentController::getServiceForField()`):

- `HasMany` → `HasManyAttachmentService`
- `BelongsToMany` → `BelongsToManyAttachmentService`
- `MorphMany` → `MorphManyAttachmentService`
- `MorphToMany` and `MorphedByMany` → `MorphToManyAttachmentService` (extends the BelongsToMany service)

Only `BelongsToMany`, `MorphToMany`, and `MorphedByMany` override `supportsSync()` to return `true`. `HasMany` and `MorphMany` do not support sync.

> `MorphMany` is supported for attach/detach by setting the foreign key and morph-type columns; sync is not available for it.

### One-to-many fields (HasMany, MorphMany)

¹ On these fields the child row holds its owner, so attaching takes the child for this parent and detaching leaves it without one. The rules are stricter than for a pivot:

- **Off by default.** Turn it on per field with `->attachable()`. Without it the field sends no `attachable`/`attach`/`detach` URLs (the frontend hides the buttons) and the endpoints answer `422 Field is not attachable`.
- **Attach only adopts orphans** (children whose foreign key is `null`). Taking a child away from another record needs `->reassignable()`, which implies `attachable()`. The attachable list and attach use the same candidates (orphans or, if reassignable, any child not already here), scoped by the related resource's `optionsQuery()` and the field's `attachableQuery()`: if any requested id is outside them the request is rejected as a whole — `422` with `rejected: [ids]` — and nothing changes. Ids already attached are skipped.
- **Detach needs nullable owner columns.** When the foreign key (or the morph type) is `NOT NULL`, `canDetach` is `false`, no `detach` URL is sent, and the endpoint answers `422`.
- Each child is saved through its model (events and observers run), all in one transaction.
- `HasMany` only writes an Eloquent `hasMany` relation: a `morphMany` declared as `HasMany` has neither attach nor detach (it would write the id without the type). Declare it as `MorphMany`.

```php
// Orphan rows can be adopted; rows of other records are not offered
HasMany::make('Paragraphs', 'paragraphs', ParagraphResource::class)->attachable();

// Moving a child between parents is the point of the field
HasMany::make('Students', 'students', StudentResource::class)->reassignable();
```

**Behavior change (Unreleased):** HasMany and MorphMany fields used to be attachable by default and attach took children from any other record. Fields that really need attach/detach must now call `->attachable()` (or `->reassignable()` to keep moving children between parents).

## Endpoints

All routes are under the API prefix (`nadota-api` by default) within the `{resourceKey}/resource` group. The `{id}` is the parent record; `{field}` is the field key or relation name.

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/{resourceKey}/resource/{id}/attachable/{field}` | List items available to attach |
| POST | `/{resourceKey}/resource/{id}/attach/{field}` | Attach items |
| POST | `/{resourceKey}/resource/{id}/detach/{field}` | Detach items |
| POST | `/{resourceKey}/resource/{id}/sync/{field}` | Replace all items (sync) |

The controller resolves the field by key first, then by relation name. If the field cannot be found it returns `404 { "success": false, "message": "Field not found" }`. If the field is not attachable it returns `422 { "success": false, "message": "Field is not attachable" }`.

See [API routes](../api/routes.md) for the full route table.

## List attachable items

```http
GET /nadota-api/posts/resource/1/attachable/tags?search=laravel&per_page=25&page=1
```

| Parameter | Type | Default | Notes |
|-----------|------|---------|-------|
| `search` | string | `''` | Matches the field's searchable attributes |
| `per_page` | int | 25 | Capped at 100 |
| `page` | int | 1 | |

The query excludes already-attached items, applies the related resource's `optionsQuery()` if defined, applies search, the field's `attachableQuery()` callback (HasMany/MorphMany), and ordering.

```json
{
  "success": true,
  "data": [
    { "id": 15, "label": "Laravel" },
    { "id": 23, "label": "Laravel Nova" }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 25,
    "total": 2,
    "attached_count": 5
  }
}
```

Meta differs slightly by service: `BelongsToMany`/`MorphToMany` include `attached_count`; `HasMany`/`MorphMany` include `attachable_limit` and each item carries a `meta` object built from the field's select fields.

The `attachable` endpoint checks the `attach` ability with field context, like `attach` itself, and answers `403 { "success": false, "message": "Unauthorized" }` when it is denied (since 1.3.0). (Contrast with [field options](../fields/relation-fields.md), used for simple selects.)

## Attach

```http
POST /nadota-api/posts/resource/1/attach/tags
Content-Type: application/json

{
  "items": [15, 23],
  "pivot": { "order": 1 }
}
```

- `items` — array of related IDs. Empty/invalid yields `422 { "success": false, "message": "No items to attach" }`.
- `pivot` — optional pivot data (BelongsToMany / MorphToMany only). Same data for all items, or keyed by item ID:

```json
{
  "items": [3, 7],
  "pivot": {
    "3": { "expires_at": "2026-12-31", "is_admin": true },
    "7": { "expires_at": "2027-06-30", "is_admin": false }
  }
}
```

Pivot data is filtered to the field's declared pivot columns (`getPivotColumns()`) when defined. Whatever is declared, the payload can never set the columns that identify the row — the parent and related pivot keys and the morph type, which come from the URL and `items` — nor the timestamps, which Eloquent manages.

The records offered by `attachable` and accepted by `attach`/`sync` start from the related resource's `getQuery()`, so rows it hides (tenant, ownership) can be neither listed nor attached.

Behavior by type:

- **HasMany** — sets each child's foreign key to the parent ID (only orphans unless `reassignable()`; see above).
- **MorphMany** — sets the foreign key and morph-type columns (same rule).
- **BelongsToMany / MorphToMany / MorphedByMany** — inserts pivot rows (with pivot data).

Already-attached items are skipped. An id that is not a candidate rejects the request (`422`, `rejected`) and nothing is written: on HasMany/MorphMany the candidates are described above; on BelongsToMany they are the related resource's `optionsQuery()` plus the field's `attachableQuery()` (sync uses the same check, and keeps ids that are already attached). Response:

```json
{
  "success": true,
  "message": "Items attached successfully",
  "attached": [15, 23],
  "count": 2
}
```

If everything was already attached, the message reflects that and `count`/`attached` are empty.

## Detach

```http
POST /nadota-api/posts/resource/1/detach/tags
Content-Type: application/json

{ "items": [15] }
```

- **HasMany** — sets the children's foreign key to `null` (`422` when the column is `NOT NULL`).
- **MorphMany** — nulls the foreign key and morph-type columns (same rule).
- **BelongsToMany / MorphToMany** — deletes the pivot rows.

```json
{
  "success": true,
  "message": "Items detached successfully",
  "detached": 1
}
```

`detached` is the number of affected rows. Empty `items` returns `422 { "success": false, "message": "No items to detach" }`.

## Sync (BelongsToMany / MorphToMany / MorphedByMany)

Replaces the full set of attached items in one call.

```http
POST /nadota-api/users/resource/1/sync/roles
Content-Type: application/json

{
  "items": [3, 7, 9],
  "pivot": {
    "3": { "is_admin": true },
    "7": { "is_admin": false },
    "9": { "is_admin": false }
  },
  "detaching": true
}
```

| Parameter | Type | Default | Notes |
|-----------|------|---------|-------|
| `items` | array | required | IDs to sync to |
| `pivot` | object | `{}` | Same for all, or keyed by ID |
| `detaching` | bool | `true` | Remove items not in `items` |

```json
{
  "success": true,
  "message": "Items synced successfully",
  "attached": [9],
  "detached": [5],
  "updated": [3, 7]
}
```

Calling sync on a relation type that does not support it returns `422 { "success": false, "message": "Sync operation not supported for this relation type" }`.

## Attachment limits

If a field defines `getAttachableLimit()`, attach and sync enforce it. Exceeding the limit returns:

```json
{
  "success": false,
  "message": "Attachment limit exceeded. Maximum allowed: 10",
  "current": 8,
  "limit": 10,
  "attempting": 5
}
```

(`current`/`attempting` are included on attach; sync returns `limit` and `attempting`.) HTTP status `422`.

## Permissions

Every attachment operation checks a resource ability with field context (see [Authorization](authorization.md)). When the policy defines neither the field-specific nor the generic method, the parent's `update` ability decides:

| Operation | Ability checked | Fallback when the policy lacks it |
|-----------|-----------------|-----------------------------------|
| attachable | `attach` | `update` |
| attach | `attach` | `update` |
| detach | `detach` | `update` |
| sync | `attach` | `update` |

The authorization context passed to the policy includes `field`, the requested `items`, and (for attach/sync) `pivot`; sync also passes `detaching`. A failed check returns `403 { "success": false, "message": "Unauthorized" }`.

```php
class PostPolicy
{
    public function attach(User $user, Post $post, array $context = []): bool
    {
        return $user->can('update', $post);
    }

    public function detach(User $user, Post $post, array $context = []): bool
    {
        return $user->can('update', $post);
    }
}
```

## Query customization

Attachable queries respect the related resource's `optionsQuery()`, so you can scope which records may be attached:

```php
class RoleResource extends Resource
{
    public function optionsQuery(Builder $query, NadotaRequest $request, array $params = []): Builder
    {
        return $query->where('is_active', true);
    }
}
```

For `HasMany` and `MorphMany`, the field may also provide an `attachableQuery()` callback that runs against the attachable query.

## Error responses summary

| Status | Body | Cause |
|--------|------|-------|
| 404 | `{ "success": false, "message": "Field not found" }` | Unknown field |
| 422 | `{ "success": false, "message": "Field is not attachable" }` | Field not attachable |
| 422 | `{ "success": false, "message": "Attachment not supported for this field type: <type>" }` | Unsupported relation |
| 422 | `{ "success": false, "message": "No items to attach" }` / `"No items to detach"` | Empty `items` |
| 422 | `{ "success": false, "message": "Sync operation not supported for this relation type" }` | Sync on HasMany/MorphMany |
| 422 | Attachment limit body (above) | Limit exceeded |
| 403 | `{ "success": false, "message": "Unauthorized" }` | Ability denied |

## Hooks

Resources can run code around every attach, detach and sync with `beforeAttach` / `afterAttach`, `beforeDetach` / `afterDetach` and `beforeSync` / `afterSync`. They share a transaction with the write, so a `before*` hook can veto the operation and an `after*` hook that throws rolls it back. See [Lifecycle hooks](lifecycle-hooks.md#attachment-hooks).

## Action events

Successful attach/detach/sync operations are logged as [action events](action-events.md) (`attach`, `detach`, `sync`) against the parent model, recording the relation name and the changed IDs.

## Related

- [Relation fields](../fields/relation-fields.md) — defining attachable fields, pivot columns, limits
- [Authorization](authorization.md)
- [Action events](action-events.md)
- [API routes](../api/routes.md), [API responses](../api/responses.md)
