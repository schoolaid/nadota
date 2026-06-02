# Filter System (Internals)

The mechanics of how filters are declared, auto-generated from fields, serialized to the frontend, and applied to the Eloquent query. Version 1.1.4, namespace `SchoolAid\Nadota`. For usage and the filter catalog, see [filters guide](../filters/README.md).

## Overview

A filter is an object that knows how to (a) serialize itself into a descriptor the frontend renders, and (b) mutate an Eloquent query given a value. Filters reach the query from two sources: explicit resource filters (`Resource::filters()`) and filters auto-generated from `->filterable()` fields. Both streams are merged, matched to request values by key, and applied.

```
Definition
   ├─ Resource::filters()                explicit Filter instances
   └─ Field->filterable()                FilterableTrait::filters() → auto Filter(s)

Serialization (metadata endpoint)
   └─ Filter::toArray($request)          → { key, label, component, type, options, value, props, isRange, filterKeys }

Application (index pipeline)
   └─ ApplyFiltersPipe
        normalizeRangeFilters()
        FilterCriteria(values)->apply(request, query, filters)
             └─ Filter::apply(request, query, value)  → mutated query
```

## Base class: Filter

`src/Http/Filters/Filter.php` is the abstract base implementing `FilterInterface` and using `Makeable`. Core properties: `name`, `type` (`'text'` default), `component` (`'select-filter'` default), `field`, `id`, `key`.

Constructor signature: `__construct(?string $name, ?string $field, ?string $type, ?string $component, $id)`. When no component is passed it derives `'Filter' . ucfirst($type)`; subclasses override this in their own constructors (e.g. `FilterSelect`, `FilterBoolean`). The `id` defaults to `Helpers::slug($name)`. `$this->key` is cached from `key()`.

Key methods:

- `abstract apply(NadotaRequest $request, $query, $value)` — every concrete filter implements this.
- `key()` — returns `$this->field` (the attribute) when set, else a slugified name. **The attribute is the identity** used to match request values.
- `resources(NadotaRequest $request): array` — option source (empty by default).
- `default()`, `props()`, `isRange()` (false), `getFilterKeys()` (`['value' => field]`).
- `toArray($request)` — the serialized descriptor:

```php
return [
    'key'        => $this->key(),
    'label'      => $this->name(),
    'component'  => $this->component(),
    'type'       => $this->type,
    'options'    => /* normalized from resources($request) */,
    'value'      => $this->default() ?: '',
    'props'      => $this->props(),
    'isRange'    => $this->isRange(),
    'filterKeys' => $this->getFilterKeys(),
];
```

`toArray()` normalizes options into `{ value, label }`: already-shaped entries pass through; arrays gain a `label` from the collection key; scalar `[label => value]` entries become `{ label, value }`.

## FilterCriteria

`src/Http/Criteria/FilterCriteria.php` is the applier. Constructed with the array of submitted filter values, its `apply()` matches each value to a filter by `key()` and calls `apply()`:

```php
public function apply(NadotaRequest $request, $query, $filters)
{
    foreach ($this->filterValues as $filterName => $value) {
        $filter = collect($filters)->first(fn ($filter) => $filter->key() === $filterName);
        if ($filter && $value !== null) {
            $query = $filter->apply($request, $query, $value);
        }
    }
    return $query;
}
```

A submitted value with no matching filter is silently ignored; a matching filter with a `null` value is skipped. Filters are AND-combined in iteration order (each `apply()` adds `where`/`whereHas` clauses to the same builder).

## How fields wire filters: FilterableTrait

`src/Http/Fields/Traits/FilterableTrait.php` is mixed into the field base. It turns `->filterable()` into one or more `Filter` instances.

State:

- `filterable` (bool), `filterableType` (defaults to the field's type), `filterAsRange` (bool).
- `filterable()` enables filtering and copies `fieldData->type` as the filter type.
- `filterableRange()` enables filtering and marks the field as a range.
- `getFilterKeys()` returns `['value' => attribute]` normally, or `['from' => "{attr}_from", 'to' => "{attr}_to"]` for range fields — this is what `ApplyFiltersPipe::normalizeRangeFilters()` keys on.

`filters(): array` is the factory the pipeline calls. Dispatch order:

1. Not filterable → `[]`.
2. `whereHas` relation types (`hasMany`, `hasOne`, `belongsToMany`, `morphMany`, `morphOne`) → `createWhereHasFilter()` → one `RelationFilter`.
3. `morphTo` → `createMorphToFilters()` → `MorphToFilter::generateFilters()` (two filters).
4. Otherwise → `createFilterForFieldType()` (single filter, possibly null).

`createFilterForFieldType()` maps `FieldType` to a concrete filter:

| Field type(s) | Filter produced |
|---|---|
| `belongsTo` | `DynamicSelectFilter` (via `createDynamicSelectFilter`) |
| `text`, `textarea`, `email`, `url`, `password` | `DefaultFilter` (LIKE) |
| `number` | `NumberFilter` (exact unless range) |
| `date`, `datetime`, `time` | `DateFilter` (single unless range) |
| `boolean`, `checkbox` | `BooleanFilter` (via `createBooleanFilter`) |
| `select`, `radio`, `checkboxList` | `SelectFilter` (via `createSelectFilter`) |
| `hidden`, `file`, `image`, `json`, `code`, `customComponent`, `keyValue`, `array`, `html` | `null` (not filterable) |
| default | `DefaultFilter` |

When `filterAsRange` is set, `createRangeFilter()` returns a `NumberFilter`/`DateFilter` constructed with `isRange = true`, falling back to `RangeFilter` for other types.

The trait uses **reflection** to copy field configuration onto the generated filter:

- `createBooleanFilter()` reads `trueValue`/`falseValue` properties off the field if present.
- `createSelectFilter()` calls the field's `getOptions()`, falling back to reflecting an `options` property.
- `createDynamicSelectFilter()` reflects the `relation` property and reads `getAttributeForDisplay()` to set `labelField`; enables `searchable`. It intentionally does **not** set `resourceKey` — the options endpoint is resolved at serialization time from the current request's resource.
- `createWhereHasFilter()` builds a `RelationFilter`, mapping the field type to a relation type, marking multiplicity (`hasMany`/`belongsToMany`/`morphMany` are multiple), and wiring `relation`, `labelField`, and `relatedResource`.
- `createMorphToFilters()` reflects `morphTypeAttribute`, `morphModels`, and `morphResources`, builds a `MorphToFilter`, and returns its `generateFilters()` output.

## Filter application by type

Each concrete filter's `apply()` defines its query semantics:

- **DefaultFilter** (`src/Http/Filters/DefaultFilter.php`) — `where(field, 'like', "%value%")`.
- **SelectFilter** (`src/Http/Filters/SelectFilter.php`) — `whereIn` for array values, else `where(field, value)`. `props()` adds `translateLabels`.
- **BooleanFilter** (`src/Http/Filters/BooleanFilter.php`) — maps truthy strings/ints (`'true'`,`true`,`'1'`,`1`) to `where(field, trueValue)` and falsy ones to `where(field, falseValue)` (`trueValue`/`falseValue` default to `1`/`0`); unrecognized values leave the query untouched. Its option source is `['Sí' => true, 'No' => false]`.
- **NumberFilter** / **DateFilter** — when range and given an array, apply `whereBetween` / `>=` / `<=` from `start`/`end`; otherwise exact match (`DateFilter` uses `whereDate` for the single case). Component switches between `FilterNumber`/`FilterNumberRange` and `FilterDate`/`FilterDateRange`.
- **RangeFilter** (`src/Http/Filters/RangeFilter.php`) — generic range; `apply()` reads `value['start']`/`value['end']` and applies `whereBetween`/`>=`/`<=`. `isRange()` is true; `getFilterKeys()` returns `{from: "{field}_from", to: "{field}_to"}`.
- **DynamicSelectFilter** (`src/Http/Filters/DynamicSelectFilter.php`) — options are loaded from an HTTP endpoint rather than embedded. `apply()` short-circuits when `applyToQuery` is false or value is empty; if a `relation` is set it uses `whereHas($relation, fn ($q) => $q->where/whereIn(relatedKey, value))`, else it filters the resolved FK column directly. `resolveFilterColumn()` inspects the model's `BelongsTo` relation to find the true foreign key. `getEndpointUrl()` builds the options URL from the current resource (`/{prefix}/{resourceKey}/resource/field/{field}/options`) or a morph variant. Carries the dependency/UX props: `valueField`, `labelField`, `multiple`, `searchable`, `applyToQuery`, `dependsOn` (hard — resets dependents), `softDependsOn` (soft — re-filters, keeps valid value), `filtersToSend`, `relation`.
- **RelationFilter** (`src/Http/Filters/RelationFilter.php`) — extends `DynamicSelectFilter` for `hasMany`/`hasOne`/`belongsToMany`/`morphMany`/`morphOne`. `apply()` uses `whereHas($relation, ...)` against the related model's primary key (`whereIn` when multiple). Adds `relationType` to props.
- **MorphToFilter** (`src/Http/Filters/MorphToFilter.php`) — not applied directly; `generateFilters()` produces a pair (`MorphTypeFilter` selecting the morph type, plus a dynamic-select entity filter). Application sets `where(morphTypeField, modelClass)` and `where`/`whereIn(morphIdField, idValue)`. Alias-to-class resolution happens here, so the frontend sends the alias (e.g. `post`) and the query filters on the full class (`App\Models\Post`).

## End-to-end flow in the index pipeline

`ApplyFiltersPipe` (`src/Http/Services/Pipes/ApplyFiltersPipe.php`) is where field and resource filters meet:

```php
$filters = array_merge(
    $data->getFields()->filter(fn ($f) => $f->isFilterable())->flatMap(fn ($f) => $f->filters())->all(),
    $data->getFilters()                                      // Resource::filters()
);

$requestFilters    = $data->request->get('filters', []);
$normalizedFilters = $this->normalizeRangeFilters($requestFilters, $filters);

(new FilterCriteria($normalizedFilters))->apply($data->request, $data->query, $filters);
```

`normalizeRangeFilters()` walks the merged filter list; for range filters with `from`/`to` keys it collapses the two request params (`{attr}_from`, `{attr}_to`) into `['start' => ..., 'end' => ...]` keyed by the filter `key()`; non-range filters pass through using their own key. See [pipeline internals](./pipeline.md) for the full pipe ordering.

The same `FilterCriteria` mechanism is reused for paginated relations: `RelationIndexService` collects the **related** resource's filterable fields and `filters()`, then applies `FilterCriteria` against the relation query (the filters come from the related resource, not the parent).

## Request / response shapes

**Request** (query string): filters arrive under `filters[...]`:

```
?filters[title]=Laravel
&filters[category_id]=1
&filters[is_published]=true
&filters[created_at_from]=2025-01-01
&filters[created_at_to]=2025-12-31
```

Range fields use the `{attr}_from` / `{attr}_to` keys defined by `getFilterKeys()`; `normalizeRangeFilters()` reassembles them.

**Response** (filter descriptors, from `GET /{resource}/resource/filters` or the `config` endpoint) follows the `toArray()` shape: `key`, `label`, `component`, `type`, `options`, `value`, `props`, `isRange`, `filterKeys`. Dynamic-select descriptors additionally carry `endpoint` and morph descriptors carry `props.isMorphEndpoint` / `props.endpointTemplate` (placeholder `{morphType}` is substituted client-side).

## Discrepancies with old docs

The legacy `docs/FILTERS_TECHNICAL.md` (Spanish) is largely accurate in flow but contains stale details corrected here:

- It shows `ApplyFiltersPipe` calling `$resource->getFilters($request)` and `(new FilterCriteria($requestFilters))->apply(...)`. The real pipe merges **field-generated** filters with `Resource::filters()` and applies `normalizeRangeFilters()` before constructing `FilterCriteria` — range params are not passed raw.
- It states text fields generate a "TextFilter". There is no `TextFilter` class; text-like fields generate a **`DefaultFilter`** (a LIKE filter).
- Range request examples use nested `filters[created_at][from]`. The current convention is flat sibling keys `filters[created_at_from]` / `filters[created_at_to]` (from `getFilterKeys()`), which `normalizeRangeFilters()` expects.
- Boolean option labels in the source are `['Sí' => true, 'No' => false]` (the doc shows `Yes`/`No`).

## Related documents

- [Filters guide](../filters/README.md)
- [Index query pipeline](./pipeline.md)
- [Fields guide](../fields/README.md)
- [Architecture](./architecture.md)
