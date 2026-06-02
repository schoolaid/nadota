# Roadmap: ResourceEditService

> **Status (v1.1.4): Fully implemented.** Verified against
> [`src/Http/Services/ResourceEditService.php`](../../src/Http/Services/ResourceEditService.php).
> All four phases are delivered: fields are transformed via `toArray()` under the
> `attributes` key, the query uses column selection and eager loading, soft-delete
> columns are included, and the response carries `id`, `key`, `title`, `permissions`
> and `deletedAt`. A custom edit response resource is also supported through
> `getEditResponseResource()`. The shipped implementation additionally improves on the
> original plan with a two-pass field strategy: a first pass over `flattenFields()`
> (no visibility filter) builds the query so `showWhen` / `hideWhen` callbacks are not
> evaluated without a model, then a second pass filters by visibility once the model is
> loaded.

## Original State

The `ResourceEditService` had a basic but incomplete implementation:

```php
// ORIGINAL — with problems
class ResourceEditService implements ResourceEditInterface
{
    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();
        $model = $resource->getQuery($request)->findOrFail($id);
        $request->authorized('update', $model);

        $fields = $resource->fieldsForForm($request, $model);

        return response()->json([
            'data' => [
                'id' => $model->getKey(),
                'fields' => $fields,  // ❌ Raw Collection
            ],
        ], 200);
    }
}
```

## Identified Problems

1. **Untransformed fields**: `$fields` was a Collection not mapped to `toArray()`
2. **Inconsistent key**: used `'fields'` instead of `'attributes'`
3. **No eager loading**: relations were not loaded efficiently (N+1)
4. **No column selection**: loaded all columns unnecessarily
5. **Missing metadata**: did not include `key`, `title`, `permissions`, `deletedAt`
6. **No resolved values**: fields did not carry their current values

---

## Objective

Align `ResourceEditService` with `ResourceShowService` and `ResourceCreateService` for:
- Consistent response structure
- Fields with resolved values
- Efficiently loaded relations
- Complete metadata for the frontend

---

## Tasks

### Phase 1: Response Structure — Done

- [x] **1.1** Transform fields with `toArray()` including model and resource
- [x] **1.2** Change key from `'fields'` to `'attributes'`
- [x] **1.3** Add `key` (resource key)
- [x] **1.4** Add `title` (resource title)

### Phase 2: Model Data — Done

- [x] **2.1** Implement column selection for edit fields
- [x] **2.2** Implement eager loading of relations for edit
- [x] **2.3** Add `deletedAt` for soft deletes

### Phase 3: Metadata and Permissions — Done

- [x] **3.1** Add `permissions` for the model
- [x] **3.2** Add relevant URLs (update, show, delete) — exposed through field `getProps()`

### Phase 4: Optimization — Done

- [x] **4.1** Build the query from flattened fields, then filter by visibility once loaded
- [x] **4.2** Add support for a custom response resource (`getEditResponseResource()`)

---

## Final Response Structure

```json
{
  "data": {
    "id": 1,
    "key": "users",
    "title": "Users",
    "deletedAt": null,
    "permissions": {
      "view": true,
      "update": true,
      "delete": true,
      "forceDelete": false,
      "restore": false
    },
    "attributes": [
      {
        "key": "name",
        "label": "Name",
        "type": "text",
        "value": "John Doe",
        "props": { },
        "rules": ["required", "string", "max:255"],
        "readonly": false,
        "disabled": false,
        "required": true
      },
      {
        "key": "category_id",
        "label": "Category",
        "type": "belongsTo",
        "value": {
          "id": 5,
          "label": "Technology",
          "resource": "categories"
        },
        "props": {
          "urls": {
            "options": "/nadota-api/users/resource/field/category_id/options?resourceId=1"
          }
        }
      },
      {
        "key": "tags",
        "label": "Tags",
        "type": "belongsToMany",
        "value": {
          "data": [
            { "id": 1, "label": "Laravel" },
            { "id": 2, "label": "PHP" }
          ],
          "meta": { }
        },
        "props": {
          "urls": {
            "options": "/nadota-api/users/resource/field/tags/options?resourceId=1",
            "attach": "/nadota-api/users/resource/1/attach/tags",
            "detach": "/nadota-api/users/resource/1/detach/tags",
            "sync": "/nadota-api/users/resource/1/sync/tags"
          }
        },
        "pivotFields": [ ]
      }
    ]
  }
}
```

---

## Implementation Notes

### `Field.toArray()` With Model

The Field `toArray()` method already handles the model correctly:

```php
public function toArray(NadotaRequest $request, ?Model $model = null, ?ResourceInterface $resource = null): array
{
    $data = array_merge($this->fieldData->toArray(), [
        'key' => $this->key(),
        // ... other fields
        'props' => $this->getProps($request, $model, $resource),
    ]);

    if ($model) {
        $data['value'] = $this->resolve($request, $model, $resource);  // ✅ Resolves value
    }

    return $data;
}
```

### Relations in `getProps()`

Relation fields (`BelongsTo`, `BelongsToMany`, etc.) generate URLs using `$model` in
`getProps()`:

```php
// BelongsToMany::getProps()
if ($model) {
    $props['urls']['options'] = ".../options?resourceId={$modelId}";
    $props['urls']['attach'] = ".../attach/{$fieldKey}";
    // etc.
}
```

---

## Progress

| Phase | Task | Status |
|-------|------|--------|
| 1 | Response structure | Done |
| 2 | Model data | Done |
| 3 | Metadata and permissions | Done |
| 4 | Optimization | Done |

---

## Dependencies (all satisfied)

- `Field::toArray()` already supports model and resource
- `Field::resolve()` already retrieves values from the model
- Relation fields already generate URLs in `getProps()`
- `getEagerLoadRelations()` accepts `$fields` as an optional parameter
- `getSelectColumns()` accepts `$fields` as an optional parameter
- `getEditResponseResource()` added to Resource
- `$editResponseResource` property added to Resource
