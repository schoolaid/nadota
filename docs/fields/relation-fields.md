# Relation Fields

Eloquent relationship fields for Nadota resources — render related records, build select options, paginate collections, and attach/detach items. All live under the `SchoolAid\Nadota\Http\Fields\Relations` namespace.

## Usage

```php
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;

public function fields(NadotaRequest $request): array
{
    return [
        BelongsTo::make('Author', 'author', UserResource::class)
            ->relationAttribute('name')
            ->relatedModel(\App\Models\User::class),

        HasMany::make('Comments', 'comments', CommentResource::class)
            ->paginated()
            ->orderBy('created_at', 'desc'),

        BelongsToMany::make('Tags', 'tags', TagResource::class)
            ->withPivot(['order'])
            ->withTimestamps(),
    ];
}
```

## Relation Types

| Field | Cardinality | Pivot | `->paginated()` | Default visibility (index / detail / create / update) |
|-------|-------------|:-----:|:----:|-------------------------------------------------------|
| [`BelongsTo`](#belongsto) | inverse 1:1 | – | – | yes / yes / yes / yes |
| [`HasOne`](#hasone) | 1:1 | – | – | no / yes / no / no |
| [`HasMany`](#hasmany) | 1:N | – | yes | no / yes / no / no |
| [`BelongsToMany`](#belongstomany) | N:N | yes | yes | no / yes / yes / yes |
| [`MorphTo`](#morphto) | polymorphic inverse | – | – | yes / yes / yes / yes |
| [`MorphOne`](#morphone) | polymorphic 1:1 | – | – | no / yes / no / no |
| [`MorphMany`](#morphmany) | polymorphic 1:N | – | yes | no / yes / no / no |
| [`MorphToMany`](#morphtomany) | polymorphic N:N | yes | yes | no / yes / yes / yes |
| [`MorphedByMany`](#morphedbymany) | inverse of MorphToMany | yes | yes | no / yes / no / no |
| [`HasManyThrough`](#hasmanythrough) | 1:N through | – | yes | no / yes / no / no |
| [`HasOneThrough`](#hasonethrough) | 1:1 through | – | – | no / yes / no / no |

> Note: `BelongsTo` does not override the base visibility flags, so its on-create/on-update visibility follows the base `Field` defaults (shown). Use `->onlyOnDetail()`, `->hideFromIndex()`, etc. to adjust.

## Common Configuration

Most relation fields share these constructor and builder methods.

### Constructor

```php
BelongsTo::make(?string $name, string $relation, ?string $resource = null)
HasOne::make(string $name, string $relation, ?string $resource = null)
HasMany::make(string $name, string $relation, ?string $resource = null)
BelongsToMany::make(string $name, string $relation, ?string $resource = null)
MorphTo::make(string $name, string $relation, ?array $resources = null)
MorphOne::make(string $name, string $relation, ?string $resource = null)
MorphMany::make(string $name, string $relation, ?string $resource = null)
MorphToMany::make(string $name, string $relation, ?string $resource = null)
MorphedByMany::make(string $name, string $relation, ?string $resource = null)
HasManyThrough::make(string $name, string $relation, ?string $resource = null)
HasOneThrough::make(string $name, string $relation, ?string $resource = null)
```

- `$name` — display label.
- `$relation` — the Eloquent relation method name on the parent model (e.g. `'author'`, `'comments'`).
- `$resource` — the related Nadota resource class. Used to render fields, resolve labels via `displayLabel()`, and build option/attach URLs. For `MorphTo` this is instead an `['alias' => ResourceClass::class]` map.

### Shared methods

| Method | Available on | Description |
|--------|--------------|-------------|
| `relation(string $relation)` | all | Set/override the Eloquent relation method name. |
| `resource(string $class)` | all (single-target) | Set the related resource class. |
| `relatedModel(string $modelClass)` | `BelongsTo` | Set the related model and auto-add an `exists:{table},id` validation rule. |
| `model(string $modelClass)` | all (via `RelationshipTrait`) | Set the related model class used for options. |
| `displayAttribute(string $attr)` | all | Column on the related model to use as the label. |
| `relationAttribute(string $attr)` | `BelongsTo` | Alias of `displayAttribute()`. |
| `displayUsing(callable $cb)` | all | Custom label resolver: `fn($relatedModel) => string`. Takes priority over `displayAttribute`. |
| `fields(array $fields)` | all | Custom `Field` instances to render for related records (overrides the related resource's index fields). |
| `withFields(bool $value = true)` | all | Include rendered `fields` in the response. Default `false` for lighter payloads. |
| `optionsScope(callable $cb)` | all | Scope the options query: `fn(Builder $q) => Builder`. |
| `optionsLimit(?int $limit)` | all | Cap the number of options returned (`null` = no limit). Overrides the request `limit`. |

### Collection-only methods

Available on `HasMany`, `BelongsToMany`, `MorphMany`, `MorphToMany`, `MorphedByMany`, `HasManyThrough`:

| Method | Description |
|--------|-------------|
| `limit(?int $limit)` | Max items rendered inline (default `10`). Drives the `hasMore` meta flag. |
| `orderBy(string $field, string $direction = 'desc')` | Default ordering for inline rendering, options, and pagination. |
| `paginated(bool $paginated = true)` | Load the collection lazily via the pagination endpoint instead of inline. See [Pagination](#pagination). |
| `exceptFields(array $keys)` | Exclude related field keys from the rendered output. |

### Pivot methods

Available on `BelongsToMany`, `MorphToMany`, `MorphedByMany`:

| Method | Description |
|--------|-------------|
| `withPivot(array $columns = [])` | Pivot columns to include in the response. |
| `pivotFields(array $fields)` | `Field` instances rendered as form inputs for pivot data. Their attributes are auto-added to `withPivot`. Serialized under a top-level `pivotFields` key. |
| `withTimestamps(bool $value = true)` | Include pivot `created_at` / `updated_at`. |

### Create/attach methods

| Method | Available on | Description |
|--------|--------------|-------------|
| `canCreate(bool $value = true)` | `HasMany`, `BelongsToMany` | Allow inline creation of related records (gated by the related resource's `create` policy). Emits `createContext`. |
| `autoAttach(bool $value = true)` | `BelongsToMany` | After creating, attach the new record automatically (default `true`). |
| `pivotDefaults(array $defaults)` | `BelongsToMany` | Default pivot values per related ID, or a `'*' => fn($item) => [...]` callback for dynamic resolution. |

Attach/detach configuration (the `attachable()` builder, search fields, button label, etc.) comes from the `ManagesAttachments` trait — see [attachments guide](../guides/attachments.md). Fields using it: `HasMany`, `BelongsToMany`, `MorphMany`, `MorphToMany`, `MorphedByMany`.

## Response Shapes

### Single-record relations (`BelongsTo`, `HasOne`, `HasOneThrough`)

Without `withFields()` the `value` is a compact object:

```json
{
  "id": 5,
  "label": "ACME Corp",
  "resource": "users",
  "deletedAt": null
}
```

When no related resource is configured, `resource` is `null` and the label falls back to (in order) the `displayUsing` callback, `displayAttribute`, the resource `displayLabel()`, then the primary key. With `withFields()` enabled the object additionally carries the rendered `fields` array.

### Collection relations (`HasMany`, `BelongsToMany`, `MorphMany`, `MorphToMany`, `MorphedByMany`, `HasManyThrough`)

```json
{
  "data": [
    { "id": 1, "label": "Comment 1", "deletedAt": null }
  ],
  "meta": {
    "total": 1,
    "hasMore": false
  }
}
```

- `hasMore` is `true` when the rendered count reaches the configured `limit`.
- Pivot fields (`BelongsToMany`, `MorphToMany`, `MorphedByMany`) add a per-item `pivot` object and a `meta.pivotColumns` array.
- Polymorphic collections add `meta.isPolymorphic: true`; `MorphedByMany` items also carry `morphType` (the related model class).
- When `paginated()` is set, the inline `value` is an empty stub (`data: []`, `meta.paginated: true`) and the real data is loaded from `paginationUrl`.

## Options Endpoints

Single-select and multi-select relation components fetch their choices from the field options endpoints. Every relation field with a related resource exposes `props.urls.options`; the value is also surfaced at the field's root `optionsUrl` (from `RelationshipTrait::getOptionsUrl()`).

### Routes

```
GET /{api-prefix}/{resourceKey}/resource/field/{fieldName}/options
GET /{api-prefix}/{resourceKey}/resource/field/{fieldName}/options/paginated
GET /{api-prefix}/{resourceKey}/resource/field/{fieldName}/morph-options/{morphType}   # MorphTo only
```

Default `api-prefix` is `nadota-api`. See [API routes](../api/routes.md).

When a model context is present (detail/edit views) the field appends `?resourceId={id}` to the options URL. The service then auto-excludes records already attached to that parent (for collection relations), so the dropdown only offers attachable items. The same URL without `resourceId` (used in create forms) returns all candidates.

### Query parameters

| Param | Default | Description |
|-------|---------|-------------|
| `search` | `''` | Matches the related resource's searchable attributes/relations, or the fallback set (`name`, `title`, `label`, `display_name`, `full_name`, `description`). |
| `limit` | `15` | Max options. A field's `optionsLimit()` takes priority. |
| `exclude` | `[]` | IDs to exclude (CSV string or array). Merged with auto-excluded attached IDs. |
| `resourceId` | – | Parent model id; enables auto-exclusion of already-attached records. |
| `orderBy` / `orderDirection` | – / `asc` | Ordering; falls back to the field's `orderBy()` config. |
| `filters` | `[]` | Column filters (`whereIn` for arrays, `like` for scalars, `{value,operator}` objects, `null`). Restricted by the resource's `getAllowedOptionsFilters()` if defined. |

### `options` response

```json
{
  "success": true,
  "options": [
    { "value": 1, "label": "Admin" },
    { "value": 2, "label": "Editor" }
  ],
  "meta": { "total": 2, "search": "", "limit": 15, "fieldType": "belongsToMany" }
}
```

### `options/paginated` response

Used for large option sets. `per_page` defaults to `15` (max `100`), with `page` for navigation:

```json
{
  "success": true,
  "data": [ { "value": 1, "label": "Admin" } ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 120,
    "last_page": 8,
    "from": 1,
    "to": 15,
    "search": "",
    "fieldType": "belongsToMany"
  }
}
```

Both endpoints authorize via the resource policy: `viewAny` is checked first, falling back to `viewOptions` (which defaults to allowed if undefined).

Resources may customize the options query by implementing `optionsQuery($query, $request, $params)`.

## Pagination

Collection relations marked `->paginated()` are not eager-loaded in `show`; instead the field props expose a `paginationUrl`.

### Route

```
GET /{api-prefix}/{resourceKey}/resource/{id}/relation/{field}
```

### Query parameters

| Param | Default | Description |
|-------|---------|-------------|
| `page` | `1` | Page number. |
| `per_page` | `15` | Items per page. |
| `search` | – | Searches the related resource's searchable attributes. |
| `filters[{field}]` | – | Filters from the related resource's filterable fields and its `filters()` method. |
| `sort_field` | – | Falls back to the field's `orderBy()`. |
| `sort_direction` | `desc` | `asc` / `desc`. |

### Response

```json
{
  "data": [
    { "id": 1, "label": "Juan Perez", "attributes": { "name": "Juan Perez" } }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 25,
    "from": 1,
    "to": 10,
    "resource": "comments",
    "relation_type": "hasMany",
    "has_pivot": false,
    "filters": [ ... ],
    "actions": [ ... ]
  },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

`meta.filters` exposes the filters available on the related resource (so the frontend can render them on first load); `meta.actions` lists its authorized actions. `has_pivot` is `true` when the field has pivot columns configured.

## Field Reference

### BelongsTo

Inverse one-to-one. The parent owns the foreign key. Fillable.

```php
BelongsTo::make('Author', 'author', UserResource::class)
    ->relatedModel(\App\Models\User::class)   // adds exists validation, enables getOptions()
    ->relationAttribute('name')               // label column
    ->foreignKey('created_by_user_id')        // override the inferred {relation}_id column
    ->withFields()                            // include rendered fields (default false)
    ->fields([ Input::make('Email', 'email') ]);
```

- The attribute defaults to `Str::snake($relation) . '_id'`; the actual FK is resolved from the Eloquent relation (`getForeignKeyName()`) at fill time, or overridden by `foreignKey()`.
- `getStorageAttribute()` / `getColumnsForSelect()` return the resolved FK so create/update writes the right column.
- Supports sorting: joins the related table and orders by `displayAttribute` (or the owner key).
- `getOptions()` returns a simple `[{value,label}]` array from `relatedModel`, but the recommended choice source is the options endpoint above.

Response: [single-record shape](#single-record-relations-belongsto-hasone-hasonethrough).

### HasOne

One-to-one where the related model owns the FK. Read-only in forms (`fill()` is a no-op); managed through the related resource's own CRUD.

```php
HasOne::make('Profile', 'profile', ProfileResource::class)
    ->withFields()
    ->displayAttribute('full_name');
```

Props include `relationType: "hasOne"`, `resource`, a `urls` object (`create`, `show`) and a `createContext` (with `foreignKey`, `prefill`, `lock`) when rendered with a model — see [createContext](#createcontext).

### HasMany

One-to-many; the related model owns the FK. Read-only in the parent form; records are created/attached separately.

```php
HasMany::make('Comments', 'comments', CommentResource::class)
    ->limit(10)
    ->orderBy('created_at', 'desc')
    ->paginated()
    ->canCreate()           // inline-create button (gated by related 'create' policy)
    ->withFields()
    ->exceptFields(['post']);
```

Props: `limit`, `paginated`, `orderBy`, `orderDirection`, `relationType`, `resource`, `canCreate`, `canAttach`, `canDetach`, and a `urls` object. `urls.options` is always present; with a model it gains `?resourceId=` and (when authorized) `attachable`, `attach`, `detach`, and — if `paginated` — a top-level `paginationUrl`. When `canCreate` and the FK resolve, a `createContext` is emitted. An `export` URL is added when the field is exportable.

### BelongsToMany

Many-to-many through a pivot table. Synced on save via `afterSave()` (the field reads the request value under its key and calls `sync()`), so it is shown on create/update by default.

```php
BelongsToMany::make('Roles', 'roles', RoleResource::class)
    ->withPivot(['expires_at', 'is_admin'])
    ->pivotFields([
        Date::make('Expires', 'expires_at'),
        Boolean::make('Admin', 'is_admin'),
    ])
    ->withTimestamps()
    ->limit(20)
    ->orderBy('name', 'asc')
    ->canCreate()
    ->autoAttach()
    ->pivotDefaults([
        1 => ['is_admin' => true],
        '*' => fn ($role) => ['expires_at' => $role->default_expiry],
    ]);
```

Form submission accepts either a flat ID array (`[1, 2, 3]`) or pivot-bearing entries (`[{ "id": 1, "expires_at": "..." }]`); the latter is detected and synced with pivot attributes.

Props add `pivotColumns`, `withPivot`, `withTimestamps`, `pivotDefaults`, `autoAttach`, plus `canCreate`/`canAttach`/`canDetach`. The `urls` object always carries `options`; with a model it adds `attach`, `sync`, `detach`, optional `paginationUrl`, and a `createContext` when `canCreate`. `toArray()` adds a top-level `pivotFields` array.

Response items include a `pivot` object; see [collection shape](#collection-relations-hasmany-belongstomany-morphmany-morphtomany-morphedbymany-hasmanythrough). For attach/detach/sync semantics see the [attachments guide](../guides/attachments.md).

### MorphTo

Polymorphic inverse — the parent stores `{relation}_type` and `{relation}_id`. Fillable. Pass an alias→resource map instead of a single resource.

```php
MorphTo::make('Commentable', 'commentable', [
    'post'  => PostResource::class,
    'video' => VideoResource::class,
])
    ->withFields()
    ->displayAttribute('title');
```

- `models([...])`, `addMorphType($alias, $model, $resource)` register types; models are auto-detected from each resource's `model` property.
- `fill()` accepts the resource key (e.g. `"posts"`) or alias as the type value and resolves it to the model class (falling back to `ResourceManager`).
- Each morph type carries its own `optionsUrl` (`.../morph-options/{alias}`). Props expose `morphTypes` (`[{value,label,resource,optionsUrl}]`), `morphTypeAttribute`, `morphIdAttribute`, `isPolymorphic: true`, and `baseOptionsUrl`.

The resolved `value` adds `type`, `typeLabel`, and `optionsUrl` to the [single-record shape](#single-record-relations-belongsto-hasone-hasonethrough). See [morph filters](../filters/morph-filters.md) for filtering polymorphic relations.

### MorphOne

Polymorphic one-to-one (the related model stores morph type + id). Read-only in forms.

```php
MorphOne::make('Image', 'image', ImageResource::class)
    ->withFields()
    ->displayAttribute('filename');
```

Props: `relationType: "morphOne"`, `resource`, `isPolymorphic: true`, a `urls` object (`create`, `show`), and a polymorphic `createContext` (with `morphType`, `morphId`, `morphClass`, and a `prefill`/`lock` covering both morph columns). The resolved value carries the related model class under `morphType`.

### MorphMany

Polymorphic one-to-many. Read-only in forms.

```php
MorphMany::make('Comments', 'comments', CommentResource::class)
    ->limit(10)
    ->orderBy('created_at', 'desc')
    ->paginated();
```

Props mirror `HasMany` plus `isPolymorphic: true`. `urls.options` is always present (gaining `?resourceId=` with a model); with a model it adds `attachable`, `attach`, `detach`, optional `paginationUrl`, and a polymorphic `createContext`.

### MorphToMany

Polymorphic many-to-many with a pivot. Synced on save via `afterSave()`; shown on create/update by default. Same pivot API as `BelongsToMany` (`withPivot`, `pivotFields`, `withTimestamps`).

```php
MorphToMany::make('Tags', 'tags', TagResource::class)
    ->withPivot(['order'])
    ->pivotFields([ Number::make('Order', 'order') ])
    ->withTimestamps()
    ->limit(20);
```

Props add `pivotColumns`, `withPivot`, `withTimestamps`, `isPolymorphic: true`. The `urls` object always carries `options`; with a model it adds `attach`, `detach`, `sync`, and optional `paginationUrl`. `toArray()` adds a top-level `pivotFields` array. Note: `MorphToMany` does **not** emit a `createContext`.

### MorphedByMany

Inverse of `MorphToMany` — e.g. on a `Tag` resource, the posts/videos carrying that tag. Read-only in forms. Same pivot API as the others.

```php
MorphedByMany::make('Posts', 'posts', PostResource::class)
    ->withPivot(['order'])
    ->limit(10);
```

Props: `relationType: "morphedByMany"`, pivot props, `isPolymorphic: true`, and a `urls` object (`options`, plus `attach`/`detach`/`sync` and optional `paginationUrl` with a model). Response items include `morphType` (related model class).

### HasManyThrough

One-to-many through an intermediate model (e.g. `Country → Users → Posts`). Read-only — there is no `createContext`, attach, or detach (creation belongs to the intermediate resource).

```php
HasManyThrough::make('Country Posts', 'posts', PostResource::class)
    ->limit(10)
    ->orderBy('created_at', 'desc')
    ->paginated();
```

Props: `limit`, `paginated`, `orderBy`, `orderDirection`, `relationType: "hasManyThrough"`, `resource`, and a `paginationUrl` only when `paginated` and rendered with a model.

### HasOneThrough

One-to-one through an intermediate model (e.g. `Mechanic → Car → Owner`). Read-only.

```php
HasOneThrough::make('Owner', 'carOwner', OwnerResource::class)
    ->withFields();
```

Props: `relationType: "hasOneThrough"` and `resource`. Response uses the [single-record shape](#single-record-relations-belongsto-hasone-hasonethrough).

## createContext

For relations that support inline creation of related records, the field props include a `createContext` object the frontend uses to prefill/lock the linking field and redirect back to the parent after saving.

### HasMany / HasOne (foreign-key based)

```json
{
  "parentResource": "posts",
  "parentId": 1,
  "relatedResource": "comments",
  "foreignKey": "post_id",
  "prefill": { "post_id": 1 },
  "lock": ["post_id"],
  "returnUrl": "/resources/posts/1",
  "createUrl": "/nadota-api/comments/resource/create",
  "storeUrl": "/nadota-api/comments/resource"
}
```

`HasMany` only emits this when `canCreate()` is enabled and the FK resolves; `HasOne` emits it whenever a related resource and model are present.

### MorphOne / MorphMany (polymorphic)

Same as above but without `foreignKey`; instead `morphType`, `morphId`, `morphClass`, `isPolymorphic: true`, and a `prefill`/`lock` covering both morph columns:

```json
{
  "parentResource": "posts",
  "parentId": 5,
  "relatedResource": "comments",
  "morphType": "commentable_type",
  "morphId": "commentable_id",
  "morphClass": "App\\Models\\Post",
  "prefill": { "commentable_type": "App\\Models\\Post", "commentable_id": 5 },
  "lock": ["commentable_type", "commentable_id"],
  "returnUrl": "/resources/posts/5",
  "createUrl": "/nadota-api/comments/resource/create",
  "storeUrl": "/nadota-api/comments/resource",
  "isPolymorphic": true
}
```

### BelongsToMany (auto-attach)

`BelongsToMany` relates existing records, so its context drops `foreignKey`/`prefill`/`lock` and instead provides `relationType: "belongsToMany"`, `autoAttach`, and an `attachUrl`. Emitted only when `canCreate()` is enabled:

```json
{
  "parentResource": "posts",
  "parentId": 1,
  "relatedResource": "tags",
  "relationType": "belongsToMany",
  "autoAttach": true,
  "attachUrl": "/nadota-api/posts/resource/1/attach/tags",
  "returnUrl": "/resources/posts/1",
  "createUrl": "/nadota-api/tags/resource/create",
  "storeUrl": "/nadota-api/tags/resource"
}
```

`HasManyThrough`, `HasOneThrough`, and `MorphToMany`/`MorphedByMany` do not emit a `createContext`.

## See Also

- [Fields overview](./README.md)
- [Attachments guide](../guides/attachments.md) — attach / detach / sync behavior
- [API routes](../api/routes.md)
- [Morph filters](../filters/morph-filters.md)

## Narrowing options from another form field

A relation field's options can be constrained by the value of another field in the
same form, without that field being persisted. See
[Lookup Fields and Scoped Options](lookup.md).
