# Built-in Filters

Reference for every filter class shipped in `SchoolAid\Nadota\Http\Filters`. All filters (except `MorphToFilter`) extend the abstract `Filter` base class and can be instantiated with `make()` or `new`.

## How filters apply to the query

Filters are applied during the index pipeline by `FilterCriteria` (`src/Http/Criteria/FilterCriteria.php`). It receives the request's filter values keyed by filter key, then for each value finds the matching filter (by `key()`) and calls its `apply()` method:

```php
foreach ($this->filterValues as $filterName => $value) {
    $filter = collect($filters)->first(fn($f) => $f->key() === $filterName);

    if ($filter && $value !== null) {
        $query = $filter->apply($request, $query, $value);
    }
}
```

So a filter is only applied when an incoming value matches its `key()` and that value is not `null`. The `key()` defaults to the filter's `field` (the database column / attribute).

## Filter (base class)

`abstract class Filter` — the foundation for all filters. You won't instantiate it directly, but every option below is inherited.

### Constructor

```php
new Filter(
    string $name = null,       // Display label
    string $field = null,      // DB column / attribute (also the default key)
    string $type = null,       // Filter type string (default 'text')
    string $component = null,  // Frontend component (default 'Filter' . ucfirst($type))
    $id = null,                // Slug id (default: slug of $name)
);
```

`make(...)` is available on every filter via the `Makeable` trait and forwards to the constructor.

### Methods

| Method                  | Purpose                                                                 |
| ----------------------- | ----------------------------------------------------------------------- |
| `apply($request, $query, $value)` | Abstract. Each subclass implements its own query logic.        |
| `key(): string`         | Unique key; returns `field` or a slug of the name.                      |
| `name(): string`        | Display label.                                                          |
| `id(): string`          | Filter id.                                                              |
| `component(): string`   | Frontend component name.                                                |
| `default(): string`     | Default value (empty string by default).                                |
| `resources($request): array` | Option source (empty by default; overridden by select-type filters). |
| `props(): array`        | Extra props for the frontend.                                           |
| `isRange(): bool`       | Whether this is a range filter (false by default).                      |
| `getFilterKeys(): array`| Maps logical keys to request keys (`['value' => $field]` by default).   |
| `toArray($request): array` | Full serialized representation (key, label, component, type, options, value, props, isRange, filterKeys). |

## DefaultFilter

Partial text match using `LIKE %value%`. This is the fallback for text-like field types.

```php
use SchoolAid\Nadota\Http\Filters\DefaultFilter;

DefaultFilter::make('Title', 'title');
// WHERE title LIKE '%value%'
```

No extra configuration methods.

## BooleanFilter

Matches a column against configurable true/false values. Accepts `true`/`false`, `'true'`/`'false'`, `1`/`0`, `'1'`/`'0'` as input.

```php
use SchoolAid\Nadota\Http\Filters\BooleanFilter;

BooleanFilter::make('Published', 'is_published')
    ->trueValue(1)    // value used when filter is "true" (default 1)
    ->falseValue(0);  // value used when filter is "false" (default 0)
```

| Method               | Purpose                            |
| -------------------- | ---------------------------------- |
| `trueValue($value)`  | Column value matched for true.     |
| `falseValue($value)` | Column value matched for false.    |

Component: `FilterBoolean`. Options resolve to `Sí => true`, `No => false`.

## NumberFilter

Exact numeric match, or a numeric range when range mode is enabled.

```php
use SchoolAid\Nadota\Http\Filters\NumberFilter;

// Exact match
NumberFilter::make('Quantity', 'quantity');
// WHERE quantity = value

// Range
NumberFilter::make('Price', 'price')->range();
// WHERE price BETWEEN start AND end (or >=/<= when only one bound given)
```

| Method                  | Purpose                                              |
| ----------------------- | ---------------------------------------------------- |
| `range(bool $isRange = true)` | Toggle range mode.                             |
| `isRange(): bool`       | Whether range mode is active.                        |
| `getFilterKeys(): array`| Range mode returns `['from' => '{field}_from', 'to' => '{field}_to']`. |

Constructor also accepts `$isRange` as the 6th argument: `new NumberFilter($name, $field, $type, $component, $id, true)`.

Component: `FilterNumber` (single) / `FilterNumberRange` (range). Range values accept either `['start' => ..., 'end' => ...]` or `[0 => ..., 1 => ...]`.

## DateFilter

Single-date match (`whereDate`) or a date range.

```php
use SchoolAid\Nadota\Http\Filters\DateFilter;

// Single date
DateFilter::make('Created At', 'created_at');
// WHERE DATE(created_at) = value

// Range
DateFilter::make('Created At', 'created_at')->range();
// WHERE created_at BETWEEN start AND end (or >=/<= for a single bound)
```

Same `range()` / `isRange()` / `getFilterKeys()` behavior and `$isRange` constructor argument as `NumberFilter`.

Component: `FilterDate` (single) / `FilterDateRange` (range). Range values accept `start`/`end` or positional `[0]`/`[1]`.

## RangeFilter

Generic range filter applying `whereBetween` / `>=` / `<=`. Used as the fallback when `filterableRange()` is called on a non-number, non-date field.

```php
use SchoolAid\Nadota\Http\Filters\RangeFilter;

RangeFilter::make('Score', 'score');
```

- `isRange()` always returns `true`.
- `getFilterKeys()` returns `['from' => '{field}_from', 'to' => '{field}_to']`.
- Expects values as `['start' => ..., 'end' => ...]`.

## SelectFilter

Exact match against a column; supports single value (`where`) or an array of values (`whereIn`).

```php
use SchoolAid\Nadota\Http\Filters\SelectFilter;

SelectFilter::make('Status', 'status')
    ->options([
        'Active'   => 'active',   // [label => value]
        'Inactive' => 'inactive',
    ])
    ->withTranslation(); // tell the frontend to translate option labels
```

| Method                          | Purpose                                                      |
| ------------------------------- | ------------------------------------------------------------ |
| `options(array $options)`       | Set the option list.                                         |
| `translateLabels(bool = true)`  | Toggle frontend label translation (default false).           |
| `withTranslation()`             | Shortcut for `translateLabels(true)`.                        |

Component: `FilterSelect`. When the filter receives an array value it applies `whereIn`.

## DynamicSelectFilter

A select whose options are fetched dynamically from an API endpoint (or supplied statically/by closure). This is the filter generated for `belongsTo` fields, and the base for `RelationFilter`.

```php
use SchoolAid\Nadota\Http\Filters\DynamicSelectFilter;

DynamicSelectFilter::make('Category', 'category_id')
    ->endpoint('/nadota-api/categories/resource/field/category/options')
    ->valueField('id')
    ->labelField('name')
    ->searchable()
    ->multiple()
    ->relation('category')        // use whereHas on this relation instead of a direct column
    ->dependsOn(['parent_id'])    // hard dependency: resets when parent changes
    ->softDependsOn(['region_id'])// soft dependency: re-filters, keeps value if still valid
    ->filtersToSend(['parent_id'])// filters forwarded to the options endpoint
    ->withDefault(5)              // default selected value
    ->applyToQuery(true)          // whether the filter mutates the final query
    ->options([...]);             // static options or a Closure(array $filters): array
```

### Query behavior

- With `relation()` set: applies `whereHas($relation, fn($q) => $q->where($key, $value))` (or `whereIn` when `multiple`).
- Without a relation: filters a direct column. For a `belongsTo` relation it resolves the actual foreign key name from Eloquent; otherwise it uses `field`.
- Empty values or `applyToQuery(false)` skip query mutation.

### Configuration methods

| Method                         | Purpose                                                        |
| ------------------------------ | -------------------------------------------------------------- |
| `endpoint(string)`             | Explicit options endpoint URL.                                 |
| `valueField(string)`           | Option value key (default `id`).                               |
| `labelField(string)`           | Option label key (default `name`).                             |
| `options(array\|Closure)`      | Static options array, or closure receiving current filters.    |
| `multiple(bool = true)`        | Allow multiple selections (uses `whereIn`).                    |
| `searchable(bool = true)`      | Enable searchable select (default true).                       |
| `withDefault(mixed)`           | Default selected value(s).                                     |
| `applyToQuery(bool = true)`    | Whether to apply this filter to the final query.               |
| `dependsOn(array)`             | Hard dependencies — child resets when a parent changes.        |
| `softDependsOn(array)`         | Soft dependencies — re-filters but keeps value if still valid. |
| `filtersToSend(array)`         | Filter keys forwarded when fetching options.                   |
| `relation(string)`             | Relation path for `whereHas`.                                  |
| `resourceKey(string)`          | Resource key used to build the options endpoint.               |
| `asMorphFilter(string $fieldName)` | Mark as a morph entity filter; builds a `/morph-options/{morphType}` endpoint. |

Component: `FilterDynamicSelect`. If no explicit `endpoint` is set, the URL is derived from the current resource and field, or from a morph pattern when `asMorphFilter()` is used. See [Morph filters](morph-filters.md).

## RelationFilter

Extends `DynamicSelectFilter` to filter to-many / to-one relations via `whereHas`. This is what `filterable()` generates for `hasMany`, `hasOne`, `belongsToMany`, `morphMany`, and `morphOne` fields.

```php
use SchoolAid\Nadota\Http\Filters\RelationFilter;

RelationFilter::make('Tags', 'tags')
    ->relation('tags')              // relation to query (falls back to field)
    ->relationType('belongsToMany') // hasMany | hasOne | belongsToMany | morphMany | morphOne
    ->multiple()
    ->searchable()
    ->relatedResource(TagResource::class);
```

### Query behavior

```php
$query->whereHas($relation, function ($q) use ($value) {
    $key = $q->getModel()->getKeyName();
    $this->multiple && is_array($value)
        ? $q->whereIn($key, $value)
        : $q->where($key, $value);
});
```

| Method                          | Purpose                                                    |
| ------------------------------- | ---------------------------------------------------------- |
| `relationType(string)`          | Eloquent relation type (informational; default `hasMany`). |
| `relatedResource(string)`       | Related resource class for building the options endpoint.  |

Inherits all `DynamicSelectFilter` methods. The options endpoint is built from the current resource and field.

## ExistsFilter

Filters by relation existence using `whereHas` / `whereDoesntHave`. Pairs with the Exists field. Unlike `BooleanFilter`, it does not test a column — it tests whether a relation exists.

```php
use SchoolAid\Nadota\Http\Filters\ExistsFilter;

new ExistsFilter(
    label:      'Has filled form',
    attribute:  'filled_form_exists',
    relation:   'filledForm',
    constraint: fn ($q) => $q->where('completed', true), // optional
);
```

### Constructor

```php
new ExistsFilter(
    string $label,
    string $attribute,
    string $relation,
    ?\Closure $constraint = null,
);
```

### Query behavior

- Truthy value: `whereHas($relation)` (with `$constraint` if provided).
- Falsy value: `whereDoesntHave($relation)` (with `$constraint` if provided).

The value is normalized with `filter_var(..., FILTER_VALIDATE_BOOLEAN)`.

| Method               | Purpose                       |
| -------------------- | ----------------------------- |
| `getRelation()`      | The relation name.            |
| `getConstraint()`    | The optional constraint closure. |

Component: `FilterBoolean`.

## MorphTypeFilter

Extends `SelectFilter`. Converts a morph type **alias** to a fully-qualified model class before filtering the morph type column. Used as the type half of a `MorphTo` filter pair.

```php
use SchoolAid\Nadota\Http\Filters\MorphTypeFilter;

MorphTypeFilter::make('Type', 'commentable_type')
    ->options(['Post' => 'post', 'Video' => 'video'])
    ->morphTypes([
        'post'  => \App\Models\Post::class,                       // string form
        'video' => ['model' => \App\Models\Video::class, 'label' => 'Video'], // config form
    ]);
```

### Query behavior

Resolves the alias to its model class via `morphTypes`, then applies `where($field, $modelClass)` (or `whereIn` for an array of aliases). If the alias can't be resolved, the query is left unchanged.

| Method                  | Purpose                                          |
| ----------------------- | ------------------------------------------------ |
| `morphTypes(array)`     | Map `alias => modelClass` or `alias => ['model' => ..., 'label' => ...]`. |
| `options(array)`        | (inherited) Select options shown to the user.    |

See [Morph filters](morph-filters.md) for the full MorphTo workflow.

## MorphToFilter

Not a `Filter` subclass — a **composite builder** that generates the two filters needed to filter a `MorphTo` relation: a `MorphTypeFilter` (type) plus a `DynamicSelectFilter` (entity). Documented in detail in [Morph filters](morph-filters.md).

```php
use SchoolAid\Nadota\Http\Filters\MorphToFilter;

public function filters(NadotaRequest $request): array
{
    $morph = new MorphToFilter(
        name:           'Commentable',
        morphTypeField: 'commentable_type',
        morphIdField:   'commentable_id',
        morphTypes: [
            'post'  => ['model' => \App\Models\Post::class,  'label' => 'Post'],
            'video' => ['model' => \App\Models\Video::class, 'label' => 'Video'],
        ],
        resourceKey: 'comments',
    );

    return $morph->generateFilters(); // [MorphTypeFilter, DynamicSelectFilter]
}
```

| Method                    | Purpose                                              |
| ------------------------- | ---------------------------------------------------- |
| `morphTypes(array)`       | Set the morph type map.                              |
| `resourceKey(string)`     | Resource key used to build endpoints.                |
| `searchable(bool = true)` | Make the entity filter searchable.                   |
| `multiple(bool = true)`   | Allow multiple entity selections.                    |
| `generateFilters(): array`| Returns the `[MorphTypeFilter, DynamicSelectFilter]` pair. |
| `apply($request, $query, array $values)` | Optional helper to apply both type + id together (resolves alias to model class). |
