# Morph Filters

Filtering a polymorphic `MorphTo` relation requires **two** coordinated filters: one to pick the type (`commentable_type`), and one to pick the entity of that type (`commentable_id`). The entity filter depends on the type filter and loads its options dynamically once a type is chosen.

This is handled by `MorphToFilter`, which generates a `MorphTypeFilter` plus a `DynamicSelectFilter` (see [Built-in filters](built-in-filters.md)). The entity options endpoint is also exposed by the corresponding [relation field](../fields/relation-fields.md).

## Adding morph filters

### Automatically from a MorphTo field

Call `filterable()` on a `MorphTo` field. Nadota reads the field's morph models/resources and generates both filters for you.

```php
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

public function fields(NadotaRequest $request): array
{
    return [
        MorphTo::make('Commentable', 'commentable')
            ->resources([
                'post'  => PostResource::class,
                'video' => VideoResource::class,
            ])
            ->filterable(),
    ];
}
```

`FilterableTrait::createMorphToFilters()` reads the field's `morphTypeAttribute`, `morphModels`, and `morphResources` (via reflection), formats them into a `morphTypes` map, and calls `MorphToFilter::generateFilters()`.

### Manually in `filters()`

When you want explicit control, construct the `MorphToFilter` yourself:

```php
use SchoolAid\Nadota\Http\Filters\MorphToFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

public function filters(NadotaRequest $request): array
{
    $morph = new MorphToFilter(
        name:           'Commentable',
        morphTypeField: 'commentable_type',
        morphIdField:   'commentable_id',
        morphTypes: [
            'post'  => ['model' => \App\Models\Post::class,  'label' => 'Post',  'resource' => PostResource::class],
            'video' => ['model' => \App\Models\Video::class, 'label' => 'Video', 'resource' => VideoResource::class],
        ],
        resourceKey: 'comments',
    );

    return $morph->generateFilters();
}
```

`generateFilters()` returns a two-element array `[MorphTypeFilter, DynamicSelectFilter]`. Both are merged into the resource's filter list by the index pipeline.

## The two generated filters

### 1. Type filter (`MorphTypeFilter`)

A `SelectFilter` whose options are the morph type aliases. When applied, it converts the chosen alias into the fully-qualified model class before filtering the morph type column.

```json
{
  "key": "commentable_type",
  "label": "fields.commentable_type",
  "component": "FilterSelect",
  "type": "select",
  "options": [
    { "label": "Post",  "value": "post" },
    { "label": "Video", "value": "video" }
  ],
  "value": "",
  "props": {}
}
```

Option format: `formatMorphTypes()` emits `[label => alias]`, so after serialization the frontend receives `{ "label": "Post", "value": "post" }` — the human-readable label is the label, the alias is the value.

When applied, `MorphTypeFilter::apply()` resolves the alias through the `morphTypes` map and runs:

```sql
WHERE commentable_type = 'App\Models\Post'
```

If the alias can't be resolved, the query is left unchanged.

### 2. Entity filter (`DynamicSelectFilter`)

A dynamic select that depends on the type filter and loads its options from a morph-specific endpoint. It is created via `asMorphFilter($fieldName)`, which makes `getEndpointUrl()` build a `/morph-options/{morphType}` endpoint from the **current** resource.

```json
{
  "key": "commentable_id",
  "label": "Commentable",
  "component": "FilterDynamicSelect",
  "type": "dynamicSelect",
  "endpoint": "/nadota-api/comments/resource/field/commentable/morph-options/{morphType}",
  "options": [],
  "value": "",
  "props": {
    "endpoint": "/nadota-api/comments/resource/field/commentable/morph-options/{morphType}",
    "endpointTemplate": "/nadota-api/comments/resource/field/commentable/morph-options/{morphType}",
    "isMorphEndpoint": true,
    "valueField": "id",
    "labelField": "name",
    "multiple": false,
    "searchable": true,
    "dependsOn": ["commentable_type"],
    "filtersToSend": ["commentable_type"],
    "applyToQuery": true
  }
}
```

The entity filter is configured with a **hard dependency** on the type field (`dependsOn`) so it resets when the type changes, and `filtersToSend` so the chosen type is forwarded when loading options.

## Current behavior and configuration

The following describe how morph filters behave today. (These were the result of fixing earlier inversion/endpoint/label issues; they are now the standard behavior.)

### Option label/value orientation

Type filter options are emitted as `[label => alias]` by `formatMorphTypes()`. After `Filter::toArray()` processing, the frontend receives `{ label: "Post", value: "post" }` — label is the capitalized/human name, value is the lowercase alias. Do not invert these.

A morph type entry may be either a plain model class string or a config array:

```php
'post' => \App\Models\Post::class,                        // label derived from alias → "Post"
'post' => ['model' => \App\Models\Post::class, 'label' => 'Blog Post'], // explicit label
```

### Morph endpoint pattern

The entity filter endpoint always uses the `/morph-options/{morphType}` pattern, built from the **current** resource (the one being listed), not the related resource:

```
/nadota-api/{currentResource}/resource/field/{fieldName}/morph-options/{morphType}
```

This is produced by `asMorphFilter($fieldName)` on the `DynamicSelectFilter`; `getEndpointUrl()` resolves the current resource key via `$request->getResource()` and the `nadota.api.prefix` config. The frontend replaces `{morphType}` with the selected alias before fetching. Because the endpoint contains `{morphType}` / `/morph-options/`, `props.isMorphEndpoint` is set to `true` and `props.endpointTemplate` carries the placeholder template.

### Type filter label (translation-friendly)

The type filter label is derived from the field name with no hardcoded suffix:

| Field name (label)            | Type filter label                |
| ----------------------------- | -------------------------------- |
| `fields.commentable`          | `fields.commentable_type`        |
| `resources.forms.targetable`  | `resources.forms.targetable_type`|
| `Commentable` (no dots)       | `Commentable` (unchanged)        |

When the name looks like a translation key (contains a `.`), `generateFilters()` replaces the last segment with the morph **type** field name so the frontend can translate it. Names without dots are kept as-is for backward compatibility.

## Request / response flow

1. The index `/config` (or `/filters`) response includes the two filters above.
2. The user selects a type — e.g. `commentable_type = "post"`.
3. The frontend replaces `{morphType}` and fetches entity options:
   ```
   GET /nadota-api/comments/resource/field/commentable/morph-options/post
   ```
4. The user selects an entity — e.g. `commentable_id = 1`.
5. Both values are sent on the index request:
   ```
   GET /nadota-api/comments/resource?filters[commentable_type]=post&filters[commentable_id]=1
   ```
6. Generated query:
   ```sql
   WHERE commentable_type = 'App\Models\Post'
     AND commentable_id = 1
   ```

The type alias is resolved to the model class by `MorphTypeFilter`; the entity id is matched directly by the `DynamicSelectFilter`.

## Applying both at once (helper)

`MorphToFilter::apply()` can apply both halves together if you receive type and id as a single array. It resolves the alias to the model class, then applies `where(morphTypeField, modelClass)` plus `where`/`whereIn` on the id field:

```php
$morph->apply($request, $query, [
    'commentable_type' => 'post',
    'commentable_id'   => 1,
]);
```

It no-ops unless both the type and id values are present and the alias resolves to a model class.

## See also

- [Built-in filters](built-in-filters.md) — `MorphToFilter`, `MorphTypeFilter`, `DynamicSelectFilter` reference.
- [Relation fields](../fields/relation-fields.md) — the `MorphTo` field and its morph-options endpoint.
- [Filtering (API)](../api/filtering.md) — wire format for the filter request/response.
- [Filters overview](README.md) — how filters attach to a resource.
