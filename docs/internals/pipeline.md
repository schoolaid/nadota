# Index Query Pipeline (Internals)

How `ResourceIndexService` turns an index request into a paginated, transformed collection by passing a single DTO through an ordered set of pipes. Version 1.1.4, namespace `SchoolAid\Nadota`.

## Overview

The index endpoint (`GET /{resourceKey}/resource`) is handled by `src/Http/Services/ResourceIndexService.php`. It authorizes the request, builds an `IndexRequestDTO`, and runs it through Laravel's `Illuminate\Pipeline\Pipeline`. Each pipe receives the same mutable DTO, mutates its `query` (or, in the final pipe, replaces the payload with a paginator), and calls `$next($data)`.

```
NadotaRequest
   └─ ResourceIndexService::handle()
        authorized('viewAny')
        new IndexRequestDTO(request, resource)
        Pipeline->send(dto)->through([pipes])->then(fn → new IndexResource)
```

## Pipeline order

`ResourceIndexService::handle()`:

```php
$pipes = [
    Pipes\BuildQueryPipe::class,
    Pipes\ApplySearchPipe::class,
    Pipes\ApplyFiltersPipe::class,
    Pipes\ApplySortingPipe::class,
    Pipes\PaginateAndTransformPipe::class,
];

return app(Pipeline::class)
    ->send(new IndexRequestDTO($request, $resource))
    ->through($pipes)
    ->then(fn ($data) => new IndexResource($data));
```

The order is significant: the query must exist before search/filter/sort can apply `where`/`orderBy` clauses, and pagination must run last because it executes the query and replaces the DTO with a paginator.

> `ApplyFieldsPipe` exists in `src/Http/Services/Pipes/` but is **not** part of this pipeline — `BuildQueryPipe` already performs optimized column selection. Treat `ApplyFieldsPipe` as a standalone/legacy pipe (it simply does `select(explode(',', $request->input('fields')))`).

> `src/Http/Services/Pipes/BuildQueryPipe 2.php` is an accidental, byte-identical duplicate of `BuildQueryPipe.php`. Ignore it; the real pipe is `BuildQueryPipe.php`.

## IndexRequestDTO

`src/Http/DataTransferObjects/IndexRequestDTO.php` is the shared mutable context that travels through the pipeline:

```php
class IndexRequestDTO
{
    public Builder $query;
    public Model $modelInstance;

    public function __construct(
        public NadotaRequest $request,
        public ?Resource $resource
    ) {}

    public function prepareQuery(): void
    {
        $this->prepareModel();                                       // $this->modelInstance = new $resource->model
        $this->query = $this->resource->getQuery($this->request, $this->modelInstance);
        $this->query = $this->resource->queryIndex($this->request, $this->query);
    }

    public function getFields(): Collection { return $this->resource->fieldsForIndex($this->request); }
    public function getFilters(): array     { return $this->resource->filters($this->request); }
}
```

- `query` and `modelInstance` are set by `prepareQuery()` (called inside `BuildQueryPipe`).
- `getFields()` returns the resource's index fields; `getFilters()` returns resource-level filters.
- `prepareQuery()` runs the resource's base `getQuery()` then the index-specific `queryIndex()` hook so a resource can scope its index listing.

## The pipes

### 1. BuildQueryPipe

`src/Http/Services/Pipes/BuildQueryPipe.php` — builds and optimizes the base query.

Input: DTO with no query yet. Output: DTO with `query` selected, eager-loaded, and soft-delete-scoped.

Steps:

1. `$data->prepareQuery()` — populates `modelInstance` and `query`.
2. `addTrashedCondition()` — applies soft-delete scope from the `withTrashed` request param **only if** `resource->getUseSoftDeletes()`:
   - `with` / `all` / `2` / `true` → `withTrashed()`
   - `only` / `deleted` / `1` → `onlyTrashed()`
   - `without` / `active` / `0` / null / `''` / default → no change (active only)
3. Filters index fields to those where `isAppliedInIndexQuery()` is true.
4. `applyColumnSelection()` — `resource->getSelectColumns($request, $fields)`; ensures the soft-delete column is always selected (for the `deletedAt` payload and restore permission) when not selecting `*`.
5. `applyEagerLoading()` — merges resource-configured relations (`getWithOnIndex()`, no constraints) with field-derived relations (`getEagerLoadRelations()`, with column constraints) into one `with()` call.
6. `applyWithCount()` — `withCount()` for `Count` fields (guarded by `method_exists(..., 'getWithCountRelations')`).
7. `applyWithExists()` — `withExists()` for `Exists` fields (guarded by `method_exists(..., 'getWithExistsRelations')`).

### 2. ApplySearchPipe

`src/Http/Services/Pipes/ApplySearchPipe.php` — applies global search.

Input/Output: DTO query, possibly wrapped with an additional `where(...)` group.

- Reads the search term from `request->get($resource->getSearchKey())`. Returns early if empty.
- Collects `getSearchableAttributes()`, `getSearchableRelations()`, and whether the resource defines `applySearch()`. Returns early if all three are empty/absent.
- Wraps everything in a single `where(function ($query) { ... })` so OR conditions don't leak past the search group:
  - direct attributes → `orWhere($attr, 'LIKE', "%term%")`
  - relations → `applyRelationSearch()`, which splits a dotted path (`user.name`, `category.parent.title`) into the relation and trailing attribute and runs `orWhereHas($relation, fn ($q) => $q->where($attr, 'LIKE', "%term%"))`
  - custom `applySearch($query, $search)` if the resource defines it.

### 3. ApplyFiltersPipe

`src/Http/Services/Pipes/ApplyFiltersPipe.php` — applies field- and resource-level filters.

```php
$filters = array_merge(
    $data->getFields()
        ->filter(fn ($field) => $field->isFilterable())
        ->flatMap(fn ($field) => $field->filters())
        ->all(),
    $data->getFilters()
);

$requestFilters = $data->request->get('filters', []);
$normalizedFilters = $this->normalizeRangeFilters($requestFilters, $filters);

(new FilterCriteria($normalizedFilters))->apply($data->request, $data->query, $filters);
```

- Auto-generated field filters (from `->filterable()`) are merged with explicit resource filters.
- `normalizeRangeFilters()` converts split range keys (`{attr}_from` / `{attr}_to`) from the request into the `['start' => ..., 'end' => ...]` shape range filters expect, keyed by the filter `key()`. Non-range filters pass through using their own key.
- `FilterCriteria` matches each request value to a filter by `key()` and calls `apply()`. See [filter internals](./filters.md).

### 4. ApplySortingPipe

`src/Http/Services/Pipes/ApplySortingPipe.php` — applies ordering.

- Reads `sortField` and `sortDirection` (default `desc`) from the request.
- If no `sortField`, or the field is not found / not `isSortable()`, falls back to `applyDefaultSort()`.
- For a valid field: relationship fields delegate to `$field->applySorting($query, $direction, $modelInstance)`; plain fields call `orderBy($field->getAttribute(), $direction)`.
- `applyDefaultSort()` uses `resource->getDefaultSort()` (`['field' => ..., 'direction' => ...]`) if set and the field exists; otherwise, if the model uses timestamps, orders by the created-at column descending.

### 5. PaginateAndTransformPipe

`src/Http/Services/Pipes/PaginateAndTransformPipe.php` — executes the query and transforms rows. This is the terminal pipe: it replaces the DTO with the paginator.

```php
$perPage = $data->request->input('perPage', $data->resource->getPerPage());
$collection = $data->query->paginate($perPage);
$fields = $data->getFields();

$collection->transform(fn ($item) =>
    $data->resource->transformForIndex($item, $data->request, $fields));

return $next($collection);
```

After this pipe, `$next` is the pipeline's final closure, which wraps the paginator in `IndexResource`. Each row is transformed by the resource's `transformForIndex()` using the index fields.

## Handlers

The persist handlers in `src/Http/Services/Handlers/` are **not** part of the index pipeline — they are used by the store/update services (`AbstractResourcePersistService` and subclasses). They are documented here because they are the equivalent "step" objects for writes.

### DefaultValueHandler

`src/Http/Services/Handlers/DefaultValueHandler.php` — fills in field defaults before persistence.

```php
public function applyDefaults(Collection $fields, array $validatedData, $request, Model $model, Resource $resource): array
{
    foreach ($fields as $field) {
        $attribute = $field->getAttribute();
        if (!array_key_exists($attribute, $validatedData) || is_null($validatedData[$attribute])) {
            $validatedData[$attribute] = $field->hasDefault()
                ? $field->resolveDefault($request, $model, $resource)
                : $validatedData[$attribute];
        }
    }
    return $validatedData;
}
```

For any field whose attribute is missing or null in the validated data, and that declares a default (`hasDefault()`), it resolves the default (`resolveDefault()`).

### RelationHandler

`src/Http/Services/Handlers/RelationHandler.php` — persists relation values when saving a model.

```php
public function handleRelations(Model $model, array &$validatedData): void
{
    foreach ($validatedData as $attribute => $value) {
        $field = $this->getField($model, $attribute);          // calls $model->{$attribute}() if the method exists
        if ($field instanceof Field) {
            match ($field->getRelationType()) {
                'hasOne'    => $this->handleHasOne($model, $field, $value),
                'hasMany'   => $this->handleHasMany($model, $field, $value),
                'belongsTo' => $this->handleBelongsTo($model, $field, $value),
                default     => $model->{$attribute} = $value,
            };
        } else {
            $model->{$attribute} = $value;
        }
    }
}
```

- `hasOne` — `firstOrNew([])`, fill, save, and set the relation back on the parent.
- `hasMany` — deletes existing related rows then `create()`s each value.
- `belongsTo` — resolves the foreign key name from the relation and assigns it on the parent.
- Anything else (or non-relation attributes) is assigned directly to the model.

## Output: IndexResource

The pipeline result is wrapped in `SchoolAid\Nadota\Http\Resources\Index\IndexResource`, which formats the paginator (data + pagination meta) for the API response.

## Related documents

- [Architecture](./architecture.md)
- [Filter internals](./filters.md)
- [Filters guide](../filters/README.md)
- [Fields guide](../fields/README.md)
- [API routes](../api/routes.md)
