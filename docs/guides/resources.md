# Defining a Resource

A Resource maps an Eloquent model to Nadota's CRUD API: it declares the fields, filters, actions, search, pagination, and labels for one model. This guide shows how to build one from scratch and the reference for every option.

## Create your first resource

A resource is a class that extends `SchoolAid\Nadota\Resource` and lives in `app/Nadota`. The only hard requirements are the `$model` property and a `fields()` method.

```php
<?php

namespace App\Nadota;

use App\Models\Post;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class PostResource extends Resource
{
    public string $model = Post::class;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Title', 'title')->rules('required', 'max:255'),
            Textarea::make('Body', 'body'),
        ];
    }
}
```

That is enough to expose the full CRUD API for `Post`. See [the routes reference](../api/routes.md) for every endpoint this enables.

## Auto-discovery and registration

You do not register resources manually. On boot, Nadota scans the directory configured in `config/nadota.php` under `path_resources` (default `app/Nadota`) for any file named `*Resource.php`:

```php
// config/nadota.php
'path_resources' => 'app/Nadota',
```

`ResourceManager::registerResource()` walks that directory with Symfony Finder, loads every class whose name ends in `Resource` and that is a subclass of `Resource`, and indexes it by its URI key. Each resource **must** declare a `$model` property — registration throws an exception otherwise.

The URI key is derived from the class name (`Helpers::toUri()`), and it is what appears in the route as `{resourceKey}`. You can read it from `PostResource::getKey()`.

> In production, discovery results can be cached. The cache key is `nadota.key_resources_cache`.

## The `$model` property

```php
public string $model = \App\Models\Post::class;
```

This is the only required property. Everything else (queries, validation context, soft-delete detection) is derived from it. The base query is built by `getQuery()`:

```php
public function getQuery(NadotaRequest $request, Model $modelInstance = null): Builder
{
    return (new $this->model)->newQuery();
}
```

Override `getQuery()` to change the base model instance, or use `queryIndex()` (below) to scope index/update queries without touching the model factory.

## `fields()`

`fields()` is abstract — every resource must implement it. It returns an array of field instances and runs on every request, so you can vary it by user or context:

```php
public function fields(NadotaRequest $request): array
{
    $fields = [
        Input::make('Name', 'name')->rules('required'),
        Email::make('Email', 'email')->rules('required', 'email'),
    ];

    if ($request->user()->isAdmin()) {
        $fields[] = Boolean::make('Active', 'is_active');
    }

    return $fields;
}
```

Fields control validation, visibility per view (index/detail/create/update), sorting, filtering, and relationships. See [the fields guide](../fields/README.md) for the full catalog and per-field options.

## `filters()`

Return an array of filter instances. The base implementation returns `[]`.

```php
public function filters(NadotaRequest $request): array
{
    return [
        new StatusFilter(),
        new CreatedBetweenFilter(),
    ];
}
```

See [the filters guide](../filters/README.md).

## `actions()`

Return an array of action instances that operate on one or many selected records. The base implementation returns `[]`.

```php
public function actions(NadotaRequest $request): array
{
    return [
        new PublishPosts(),
        new ExportSelected(),
    ];
}
```

See [the actions guide](actions.md).

## Search configuration

Global search is configured with two properties from the `ResourceSearchable` trait. A resource is considered searchable (`isSearchable()`) only when at least one of them is non-empty.

```php
class StudentResource extends Resource
{
    public string $model = Student::class;

    // Direct columns searched with LIKE
    protected array $searchableAttributes = ['name', 'email', 'student_id'];

    // Related attributes searched with whereHas() (dot notation, supports nesting)
    protected array $searchableRelations = [
        'family.name',
        'grade.title',
        'author.profile.bio',
    ];
}
```

The search term arrives under the query key `globalSearch` (configurable via `$searchKey`). At runtime the search pipe wraps all conditions in a single `where(function () { ... })` group using `orWhere` / `orWhereHas`.

You can mutate the configuration fluently: `setSearchableAttributes()`, `addSearchableAttribute()`, `removeSearchableAttribute()`, and the matching `*Relation` methods, plus `clearSearchable()`.

For advanced search (e.g. searching by ID, JSON columns, or concatenated names) add an `applySearch()` method to the resource — it is detected automatically and called inside the same `where()` group, so use `orWhere`:

```php
public function applySearch($query, string $search): void
{
    if (is_numeric($search)) {
        $query->orWhere('id', $search);
    }

    $query->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
}
```

To customize search for the **options** endpoints (relation pickers) instead, override `optionsSearch()` — see the [lifecycle hooks guide](lifecycle-hooks.md#options-hooks).

## Pagination

Pagination comes from the `ResourcePagination` trait:

```php
class PostResource extends Resource
{
    public string $model = Post::class;

    protected int $perPage = 20;                       // default page size
    protected array $allowedPerPage = [10, 20, 50, 100]; // sizes the client may request
}
```

Both values are exposed to the frontend via `/info` and `/config` (`perPage`, `allowedPerPage`). They can also be set at runtime with `setPerPage()` / `setAllowedPerPage()`.

## Ordering

Set a default sort with `$defaultSort`:

```php
protected array $defaultSort = ['field' => 'created_at', 'direction' => 'desc'];
```

Per-request sorting is driven by sortable fields (see [fields](../fields/README.md)); `$defaultSort` applies when the request does not specify one. To scope or join the index query before sorting and filtering run, override `queryIndex()`:

```php
public function queryIndex(NadotaRequest $request, $query)
{
    return $query->where('tenant_id', $request->user()->tenant_id);
}
```

`queryIndex()` is applied to the index query **and** the update lookup, so records outside the scope are also hidden from updates.

## Labels, title, and presentation

| Method / property | Purpose |
|---|---|
| `title()` | Plural human title. Defaults to the pluralized, title-cased class basename. Override the `$title` property or the method. |
| `description()` | Optional subtitle shown in the UI (defaults to `null`). |
| `$displayIcon` / `displayIcon()` | Icon name shown in menus/headers. |
| `displayLabel(Model $model)` | How a single record is rendered in relationships/options. Defaults to the first present attribute among `name`, `title`, `label`, `display_name`, `full_name`, `description`, falling back to the primary key. |
| `optionsFormat($item)` | Shape of each entry returned by options endpoints (`['value' => ..., 'label' => ...]`). Override to add extra keys. |

```php
class PostResource extends Resource
{
    public string $model = Post::class;
    protected ?string $title = 'Blog Posts';
    protected ?string $displayIcon = 'document-text';

    public function displayLabel(Model $model): string
    {
        return "{$model->title} (#{$model->id})";
    }
}
```

## Menu options

The `ResourceMenuOptions` trait controls how the resource appears in the admin menu:

```php
public function displayInMenu(NadotaRequest $request): bool { return true; }  // show/hide
public function displayInSubMenu(): ?string { return 'Content'; }             // group label
public function orderInMenu(): int { return 10; }                            // sort order
```

See [the menu guide](menu.md).

## Visibility of the whole resource

The `VisibleWhen` trait lets you gate a resource behind a callback with `canSee()`:

```php
$resource->canSee(fn (NadotaRequest $request) => $request->user()->isAdmin());
```

`isVisible($request)` returns `true` when no callback is set.

## Reference

### Required

| Member | Type | Notes |
|---|---|---|
| `$model` | `string` | Fully-qualified Eloquent model class. |
| `fields(NadotaRequest $request)` | `array` | Abstract; the field definitions. |

### Common overridable methods

| Method | Default | Purpose |
|---|---|---|
| `filters($request)` | `[]` | Filter instances. |
| `actions($request)` | `[]` | Action instances. |
| `tools($request)` | `[]` | Resource tools. |
| `queryIndex($request, $query)` | passthrough | Scope index/update queries. |
| `getQuery($request, $model = null)` | new query | Base query builder. |
| `title()` | pluralized class name | Resource title. |
| `description()` | `null` | Subtitle. |
| `displayLabel($model)` | best-guess attribute | Single-record label. |
| `optionsFormat($item)` | `value`/`label` | Options payload shape. |

### Key properties

| Property | Default | Purpose |
|---|---|---|
| `$perPage` | `20` | Default page size. |
| `$allowedPerPage` | `[10,20,50,100]` | Selectable page sizes. |
| `$defaultSort` | `[]` | Default ordering. |
| `$searchableAttributes` | `[]` | Columns for global search. |
| `$searchableRelations` | `[]` | Relations for global search. |
| `$searchKey` | `globalSearch` | Search query-param key. |
| `$title` | `null` | Overrides `title()`. |
| `$displayIcon` | `null` | Menu/header icon. |
| `$pollingInterval` | `null` | Seconds between auto-refresh (null disables). |
| `$includeIdInResponse` | `true` | Include `id` in index/show payloads (`withoutId()` to disable). |
| `$withOnIndex` / `$withOnShow` | `[]` | Relations eager-loaded on index / detail. |
| `$usesSoftDeletes` | `null` | Force soft-delete support on/off; `null` autodetects from the model. See [soft deletes](soft-deletes.md). |
| `$canDelete` / `$canForceDelete` / `$canRestore` | `true` | Globally enable each destructive action. |
| `$availableInGlobalOptions` | `false` | Expose the resource in the global `/options` endpoint. |
| `$showRowCheckbox` / `$showSelectAll` | `false` | Bulk-selection UI. |
| `$detailCardWidth` | `null` | Detail card width (`sm`…`full` or CSS value). |
| `$mainCardCollapsible` / `$mainCardDefaultCollapsed` | `true` | Index main-card behavior. |
| `$mainCardTitle` | `null` | Index main-card title. |
| `$showResponseResource` / `$editResponseResource` | `null` | Custom Laravel API Resource classes for show/edit responses. |
| `$indexComponent` … `$deleteComponent` | `ResourceIndex` … | Frontend component names (`setComponents()` to override). |

## Related guides

- [Fields](../fields/README.md)
- [Filters](../filters/README.md)
- [Actions](actions.md)
- [Authorization](authorization.md)
- [Lifecycle hooks](lifecycle-hooks.md)
- [Soft deletes](soft-deletes.md)
- [API routes](../api/routes.md)
