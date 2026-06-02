# Filters

Filters let users narrow an index listing by constraining the underlying Eloquent query. Each filter declares how it renders on the frontend and how it applies to the query.

## Quick start

There are two ways to add filters to a Resource.

### 1. Mark a field as filterable

The simplest approach. Call `filterable()` (or `filterableRange()`) on a field. Nadota picks an appropriate built-in filter based on the field type.

```php
use SchoolAid\Nadota\Http\Fields\Text;
use SchoolAid\Nadota\Http\Fields\Boolean;
use SchoolAid\Nadota\Http\Fields\Date;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

public function fields(NadotaRequest $request): array
{
    return [
        Text::make('Title', 'title')->filterable(),
        Boolean::make('Published', 'is_published')->filterable(),
        Date::make('Created At', 'created_at')->filterableRange(),
    ];
}
```

### 2. Declare dedicated Filter classes

Return filter instances from the `filters()` method on the Resource. Use this when you need explicit control over the filter type, options, or query logic.

```php
use SchoolAid\Nadota\Http\Filters\SelectFilter;
use SchoolAid\Nadota\Http\Filters\NumberFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

public function filters(NadotaRequest $request): array
{
    return [
        SelectFilter::make('Status', 'status')->options([
            'Active'   => 'active',
            'Inactive' => 'inactive',
        ]),
        NumberFilter::make('Price', 'price')->range(),
    ];
}
```

Both approaches can be combined on the same Resource. At index time Nadota merges the field-derived filters with the ones returned by `filters()`.

## How field `filterable()` relates to Filter classes

`filterable()` does not invent new behavior — it instantiates the same built-in `Filter` classes you would create manually. The mapping lives in `FilterableTrait` (`src/Http/Fields/Traits/FilterableTrait.php`):

| Field type                                   | Filter produced                          |
| -------------------------------------------- | ---------------------------------------- |
| `text`, `textarea`, `email`, `url`, `password` | `DefaultFilter` (LIKE match)             |
| `number`                                     | `NumberFilter`                           |
| `date`, `datetime`, `time`                   | `DateFilter`                             |
| `boolean`, `checkbox`                        | `BooleanFilter`                          |
| `select`, `radio`, `checkboxList`            | `SelectFilter` (options copied from field) |
| `belongsTo`                                  | `DynamicSelectFilter`                    |
| `hasMany`, `hasOne`, `belongsToMany`, `morphMany`, `morphOne` | `RelationFilter` (`whereHas`) |
| `morphTo`                                    | A `MorphTypeFilter` + a `DynamicSelectFilter` (two filters) |
| `hidden`, `file`, `image`, `json`, `code`, `customComponent`, `keyValue`, `array`, `html` | none |
| anything else                                | `DefaultFilter`                          |

Notes:

- `filterableRange()` forces a range variant: `NumberFilter`/`DateFilter` in range mode, otherwise a generic `RangeFilter`.
- For `boolean`/`checkbox` and `select`-style fields, `filterable()` copies the field's `trueValue`/`falseValue` and `options` into the generated filter via reflection, so no extra config is needed.
- `belongsTo` filters resolve the real foreign key from the Eloquent relationship and build the options endpoint from the current resource.

When you need to override any of this — custom options, custom query logic, a specific component — declare the Filter class explicitly in `filters()` instead of (or in addition to) calling `filterable()`.

## Request / response contract (high level)

The backend exposes the available filters as serialized JSON. Each filter's `toArray()` produces a `key`, `label`, `component`, `type`, `options`, `value`, `props`, `isRange`, and `filterKeys`. The frontend renders the matching component and sends values back under a `filters[...]` query parameter.

```
GET /nadota-api/{resource}/resource?filters[title]=Laravel&filters[is_published]=true
```

The index pipeline (`ApplyFiltersPipe`) matches each incoming value to a filter by its `key()`, normalizes range keys, and calls the filter's `apply()` method to mutate the query. See [Filtering (API)](../api/filtering.md) for the exact wire format and [Filters internals](../internals/filters.md) for the matching and normalization mechanics.

## Reference

- [Built-in filters](built-in-filters.md) — every shipped filter class, its constructor, configuration methods, and examples.
- [Morph filters](morph-filters.md) — filtering polymorphic `MorphTo` relations.
- [Relation fields](../fields/relation-fields.md) — how relationship fields expose filters.
- [Resources guide](../guides/resources.md) — the `filters()` method in context.
