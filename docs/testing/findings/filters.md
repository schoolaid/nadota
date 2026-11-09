# Findings — filters (2026-11-02)

Area: `src/Http/Filters/*`, `src/Http/Criteria/FilterCriteria.php`,
`src/Http/Services/Pipes/ApplyFiltersPipe.php`, `src/Http/Resources/Filters/FilterResource.php`,
field-generated filters (`FilterableTrait::filters()`, `Exists::filters()`), the filter part of
`RelationIndexService`, `GET /{resource}/resource/filters` and the index with `filters[...]`.
IDs use the `FLT-` prefix. Nothing under `src/` was changed. Every **bug** below has a test that
asserts the correct behaviour and is skipped with `ISSUE FLT-0NN`. Each skipped test was checked by
running it with the skip removed, and it fails. Everything ran on SQLite; MySQL/PostgreSQL behaviour
is inferred where stated.

Severity scale: **high** = wrong data or a 500 in normal use · **medium** = wrong data or a 500 with
plausible input · **low** = edge case, cosmetic, or a documentation problem.

---

### FLT-001 — A field filter and a resource filter with the same key: duplicate keys, an unreachable range filter, a hijacked exact filter
- **Location**: `src/Http/Criteria/FilterCriteria.php:20` (`collect($filters)->first(...)`, so the first match wins);
  `src/Http/Services/Pipes/ApplyFiltersPipe.php::normalizeRangeFilters()` (`$normalized[$key] = ['start'…]`
  overwrites the plain value stored under the same key); `tests/Fixtures/Resources/StudentResource.php`
  (`Number age ->filterable()` + `NumberFilter 'Age Range' age`; `Select status ->filterable()` + `SelectFilter 'Status Filter' status`).
- **Observed**: `GET /student/resource/filters` returns two descriptors keyed `age` and two keyed `status`.
  `filters[age_from]=10&filters[age_to]=12` is normalised to `age => {start:10,end:12}` and handed to the
  *first* `age` filter, which is the exact NumberFilter. It runs `where('age', [10, 12])`, and the query builder binds only the
  first element (FLT-020), so the result is `age = 10`. `filters[age]=15&filters[age_from]=10` loses the 15: the range
  overwrites it, and the result is again `age = 10`. The resource "Status Filter" never runs.
- **Expected**: every listed filter can be reached, through unique keys or a namespacing rule. At minimum, a duplicate key
  should be rejected or reported. `docs/filters/README.md` says "Both approaches can be combined on the same Resource" and gives no warning.
- **Verified by**: `tests/Unit/Services/Pipes/ApplyFiltersPipeTest.php` (2 skipped),
  `tests/Feature/Http/Filters/IndexFiltersTest.php` ("applies the Age Range resource filter"),
  `tests/Feature/Http/Filters/FiltersEndpointTest.php` ("gives every listed filter a unique key").
- **Severity**: high. The results are silently wrong on the kitchen-sink fixture, which mirrors real usage. **Kind**: bug.

### FLT-002 — The documented nested range format `filters[key][from|to]` is silently ignored
- **Location**: `docs/api/filtering.md` (§ Range filter: `"filterKeys": {"from": "created_at[from]"}`; the requests use
  `filters[created_at][from]=…`; the mapping summary table) against `DateFilter/NumberFilter/RangeFilter::getFilterKeys()`
  (`{field}_from` / `{field}_to`) and `ApplyFiltersPipe::normalizeRangeFilters()`. For a range filter, the normaliser reads only
  the flat keys and never reaches the `elseif` that would pass `filters[key]` through.
- **Observed**: `filters[enrolled_at][from]=2024-01-15&filters[enrolled_at][to]=2024-02-02` comes back unfiltered and
  raises no error. `docs/internals/filters.md` already names the flat keys as "the current convention", so the two documents disagree.
- **Expected**: the API documentation and the code agree. Either accept both forms or fix `docs/api/filtering.md`.
- **Verified by**: `ApplyFiltersPipeTest` ("accepts the nested … format"), `IndexFiltersTest` ("accepts the nested …").
- **Severity**: medium. A frontend built from the API documentation gets unfiltered lists and no error. **Kind**: contradiction.

### FLT-003 — A date range "to" bound excludes the end day on datetime columns
- **Location**: `src/Http/Filters/DateFilter.php::apply()` (`whereBetween` / `where('<=', $end)` with the raw `Y-m-d`
  bound); `RangeFilter::apply()` does the same.
- **Observed**: a row with `enrolled_at = '2024-01-31 10:00:00'` is left out by `enrolled_at_to=2024-01-31`, because the bound means
  midnight. On SQLite a DATE column written through Eloquent's `date` cast holds `Y-m-d 00:00:00`, so even
  `birth_date` between `2016-03-03` and `2016-03-03` returns nothing.
- **Expected**: the end day is included (`whereDate(<=)` or an end-of-day bound). The UI component is a date picker (`FilterDateRange`).
- **Verified by**: `tests/Unit/Filters/DateFilterTest.php` (2 skipped), `IndexFiltersTest` ("includes the whole "to" day").
- **Severity**: medium. **Kind**: bug.

### FLT-004 — Filters of fields hidden from the index are listed but never applied, and that covers every to-many and has-one relation filter by default
- **Location**: `ResourceIndexController::filters()` / `config()` collect field filters from `flattenFields()`.
  `ApplyFiltersPipe` collects them from `IndexRequestDTO::getFields()`, which is `fieldsForIndex()`. `HasMany`, `HasOne`, `BelongsToMany`,
  `MorphMany`, `MorphOne`, `MorphToMany`, `MorphedByMany`, `HasManyThrough` and `HasOneThrough` all set `showOnIndex = false`
  in their constructors.
- **Observed**: `Input::make('Website')->hideFromIndex()->filterable()`, `BelongsToMany::make('Courses')->filterable()`
  and `MorphMany::make('Notes')->filterable()` all appear in `/filters`, but `filters[website]=ana` and `filters[courses][]=…` return
  every row. `RelationIndexService` is consistent, because it uses `fieldsForIndex()` both to list and to apply.
- **Expected**: one source both for listing and for applying, so that every listed filter works.
- **Verified by**: `ApplyFiltersPipeTest` ("applies the filter of a filterable field hidden from the index"),
  `tests/Feature/Http/Filters/FilterLabIndexTest.php` (3 skipped). `FieldGeneratedFiltersTest` records the default visibility.
- **Severity**: high. On the main index, a `RelationFilter` produced by `->filterable()` never runs. **Kind**: bug.

### FLT-005 — DynamicSelectFilter static options in `{value, label}` form lose their label
- **Location**: `src/Http/Filters/DynamicSelectFilter.php::toArray()`. It re-maps `$this->options` and, for array items, does
  `collect($value)->put('label', $label)`, where `$label` is the list index.
- **Observed**: `options([['value' => 1, 'label' => 'G1']])` is serialised as `[{value: 1, label: 0}]`. `Filter::toArray()` had already
  normalised the options correctly before they were overwritten.
- **Expected**: the options pass through unchanged, as they do in `Filter::toArray()`.
- **Verified by**: `tests/Unit/Filters/DynamicSelectFilterTest.php` ("keeps the label of static options …").
- **Severity**: medium. **Kind**: bug.

### FLT-006 — Zero and empty-string values are handled differently by each filter
- **Location**: `RangeFilter::apply()` and `DateFilter::apply()` test the bounds for truthiness (`if ($start && $end)`).
  `NumberFilter::apply()` uses `!== null`. `DynamicSelectFilter::apply()` and `RelationFilter::apply()` use `empty($value)`.
- **Observed**: the RangeFilter request `balance_from=0&balance_to=0` applies no constraint and returns every row. `filters[grade_id]=0` is ignored.
  A NumberFilter range called with `start = ''` produces `age >= ''`, which returns nothing on SQLite. Over HTTP this cannot happen,
  because `''` arrives as null, but a direct caller can hit it.
- **Expected**: `0` and `'0'` are values; `''` and `null` mean "no bound" in every filter.
- **Verified by**: `RangeFilterTest`, `NumberFilterTest`, `DynamicSelectFilterTest`, `FilterLabIndexTest` ("treats 0 as a real RangeFilter bound").
- **Severity**: medium for RangeFilter, which is the fallback for `filterableRange()` on Currency and similar fields; low for the rest. **Kind**: bug.

### FLT-007 — ExistsFilter treats every unrecognised value as "false"
- **Location**: `src/Http/Filters/ExistsFilter.php::apply()` calls `filter_var($value, FILTER_VALIDATE_BOOLEAN)` without
  `FILTER_NULL_ON_FAILURE`. The deprecated `Exists::applyFilter()` has the same logic.
- **Observed**: `'banana'`, `''` and `['1']` all turn into `whereDoesntHave()`. BooleanFilter leaves the query untouched for unknown values.
- **Expected**: unknown values are ignored, as BooleanFilter does.
- **Verified by**: `ExistsFilterTest` (3 dataset rows skipped), `FilterLabIndexTest`.
- **Severity**: low. **Kind**: bug.

### FLT-008 — A `filters` parameter that is not an array answers 500
- **Location**: `ApplyFiltersPipe::handle()`. `$request->get('filters', [])` returns whatever is present, and
  `normalizeRangeFilters(array $requestFilters, …)` throws a `TypeError`. `RelationIndexService::applyFilters()` builds
  `new FilterCriteria($requestFilters)`, whose constructor is also typed `array`, so a string fails the same way (`null` is stopped by `empty()`).
- **Observed**: `?filters=abc` gives 500. `?filters=` gives 500 too, because `ConvertEmptyStringsToNull` turns it into null.
- **Expected**: the parameter is ignored, or the request is answered with 422.
- **Verified by**: `ApplyFiltersPipeTest` (2 skipped), `IndexFiltersTest` ("answers 200 and ignores a malformed filters parameter").
- **Severity**: medium, since anyone can trigger a 500 from the query string. **Kind**: bug.

### FLT-009 — DefaultFilter answers 500 when given an array
- **Location**: `src/Http/Filters/DefaultFilter.php:11`, `'%' . $value . '%'`.
- **Observed**: `filters[email][]=x` raises an `ErrorException` ("Array to string conversion"), and the request answers 500.
- **Expected**: the value is ignored, or the request is answered with 422.
- **Verified by**: `DefaultFilterTest`, `FilterLabIndexTest` ("answers 200 for an array sent to a text filter").
- **Severity**: low–medium. **Kind**: bug.

### FLT-010 — Paginated relation listings do not normalise range filters
- **Location**: `src/Http/Services/RelationIndexService.php:237`. `applyFilters()` passes `filters[]` straight to
  `FilterCriteria` and never calls `normalizeRangeFilters()`.
- **Observed**: `GET /grade/resource/1/relation/students?filters[enrolled_at_from]=…&filters[enrolled_at_to]=…` comes back unfiltered,
  even though `meta.filters` advertises `filterKeys: {from, to}`. The reverse also holds: `filters[age][start]=…` works here but not on the main index.
- **Expected**: the same normalisation as the main index. Sharing it, for example by moving it into `FilterCriteria`, would also fix the reverse case.
- **Verified by**: `tests/Unit/Services/RelationIndexServiceFiltersTest.php`, `tests/Feature/Http/Filters/RelationListingFiltersTest.php`.
- **Severity**: medium. **Kind**: bug.

### FLT-011 — `Count->filterable()` filters on the `withCount` alias
- **Location**: `FilterableTrait::createFilterForFieldType()`. Count reports type `number`, so it gets
  `NumberFilter('courses_count')`. The alias exists only in the SELECT list that `BuildQueryPipe::applyWithCount()` adds.
- **Observed**: on SQLite, `"courses_count"` in WHERE is read as a string literal, so `courses_count=0` silently returns nothing.
  MySQL and PostgreSQL fail with "unknown column", and the pagination count query drops the alias anyway.
- **Expected**: Count overrides `filters()`, as Exists already does, with `has($relation, '=', n)` or a subquery.
- **Verified by**: `FilterLabIndexTest` ("filters a Count field …"). `FieldGeneratedFiltersTest` records the mapping.
- **Severity**: medium: silently wrong on SQLite, a 500 elsewhere. **Kind**: bug.

### FLT-012 — `Time->filterable()` can never match
- **Location**: `FilterableTrait` maps `TIME` to `DateFilter`, which runs `whereDate('class_time', '10:00')`.
- **Expected**: compare as time (`whereTime`) or as a string.
- **Verified by**: `FilterLabIndexTest` ("filters a Time field by its time").
- **Severity**: low. **Kind**: bug.

### FLT-013 — `CheckboxList->filterable()` can never match
- **Location**: `FilterableTrait` maps `CHECKBOX_LIST` to `SelectFilter`, which compares the JSON array column with `=` or `whereIn`.
- **Observed**: `filters[interests]=art` returns nothing for a row whose value is `["art"]`.
- **Expected**: `whereJsonContains`.
- **Verified by**: `FilterLabIndexTest` ("filters a CheckboxList field …").
- **Severity**: low–medium. **Kind**: bug.

### FLT-014 — MorphTypeFilter ignores the morph map
- **Location**: `src/Http/Filters/MorphTypeFilter.php::resolveModelClass()` returns the FQCN. `MorphToFilter::apply()` does the same.
- **Observed**: with `Relation::morphMap(['student' => Student::class])`, Eloquent stores `notable_type = 'student'`, but
  `filters[notable_type]=student` compares against `SchoolAid\…\Student`, so nothing matches.
- **Expected**: compare against `(new $model)->getMorphClass()`.
- **Verified by**: `tests/Unit/Filters/MorphTypeFilterTest.php` ("matches the stored morph alias …").
- **Severity**: medium for applications that use `enforceMorphMap()`. **Kind**: bug.

### FLT-015 — `SelectFilter::options()` takes `[label => value]`, while `Select::options()` takes `[value => label]`
- **Location**: `SelectFilter::options()` with `Filter::toArray()`, against `Select::formatOptions()`. Fixture:
  `StudentResource::filters()` passes `self::STATUSES` (`['draft' => 'Draft', …]`), the same map the Select field uses.
- **Observed**: the field filter serialises `{value:'draft', label:'Draft'}`, but the resource "Status Filter" serialises
  `{label:'draft', value:'Draft'}`, and filtering with that value returns nothing. The documentation
  (`built-in-filters.md`, `filters/README.md`) does say `[label => value]`, so the fixture follows the *field* convention and gets swapped output.
- **Expected**: a decision. Options: one convention everywhere; recommending the `{value, label}` list form, which SelectFilter passes through; or fixing the fixture.
- **Verified by**: `SelectFilterTest` ("inverts the options when given the [value => label] map …"), `FiltersEndpointTest`.
- **Severity**: low; it is about API ergonomics, but even the fixture gets it wrong. **Kind**: doubt.

### FLT-016 — SelectFilter with an empty array hides every row
- **Location**: `SelectFilter::apply()`. `whereIn($field, [])` compiles to `0 = 1`.
- **Observed**: an empty multi-select produces an empty list. DynamicSelectFilter and the range filters ignore empty arrays.
- **Verified by**: `SelectFilterTest` ("ignores an empty array …").
- **Severity**: low. **Kind**: bug.

### FLT-017 — A `"0"` default is dropped from the serialised filter
- **Location**: `src/Http/Filters/Filter.php:126`, `'value' => $this->default() ?: ''`.
- **Observed**: a filter whose `default()` is `"0"`, for example `DynamicSelectFilter::withDefault(0)`, serialises `value: ''`.
- **Verified by**: `FilterTest` ("keeps a "0" default …").
- **Severity**: low. **Kind**: bug.

### FLT-018 — DynamicSelectFilter: dead foreign-key resolution, and an `applyToQuery` prop that can be wrong
- **Location**: `DynamicSelectFilter::resolveFilterColumn()` and `props()`.
- **Observed**:
  - `resolveFilterColumn()` acts only when `$this->relation` is set, but in that case `apply()` has already returned through
    `whereHas()`, so the method always returns `$this->field`. `docs/filters/built-in-filters.md` ("For a `belongsTo`
    relation it resolves the actual foreign key name from Eloquent") and `docs/filters/README.md` ("`belongsTo`
    filters resolve the real foreign key") describe behaviour that never happens: BelongsTo filters always use `whereHas()`.
  - `props()['applyToQuery'] = applyToQuery || relation !== null`. As a result, `->relation('x')->applyToQuery(false)` advertises `true`
    while `apply()` ignores the filter. `RelationFilter`, for its part, ignores `applyToQuery(false)` completely.
- **Verified by**: `DynamicSelectFilterTest` ("reports applyToQuery=false …", skipped), `RelationFilterTest`.
- **Severity**: low. **Kind**: contradiction (documentation) and bug (props).

### FLT-019 — DefaultFilter does not escape LIKE wildcards
- **Location**: `DefaultFilter::apply()`.
- **Observed**: `_` and `%` in the value act as wildcards. The search pipe has the same behaviour (HTTP-004).
- **Verified by**: `DefaultFilterTest` ("matches LIKE wildcards in the value literally").
- **Severity**: low. **Kind**: doubt; it may be intended, and should be decided together with HTTP-004.

### FLT-020 — An array sent to a single-value filter is silently reduced to its first element
- **Location**: `NumberFilter::apply()` (exact mode), `DynamicSelectFilter::apply()` and `RelationFilter::apply()` when
  `multiple` is off, and therefore the BelongsTo filter too. `where($col, [a, b])` makes Laravel bind `head(Arr::flatten())`.
- **Observed**: `filters[age][]=8&filters[age][]=12` is applied as `age = 8`. This is also the mechanism behind FLT-001.
- **Expected**: `whereIn`, or the value is ignored or rejected with 422.
- **Verified by**: `NumberFilterTest`, `DynamicSelectFilterTest`, `RelationFilterTest`, `IndexFiltersTest`.
- **Severity**: low–medium. **Kind**: bug.

### FLT-021 — The descriptor examples in the documentation do not match the real payload
- **Location**: `docs/api/filtering.md`, `docs/filters/README.md`.
- **Observed**:
  - Boolean options are documented as `{"label": "Yes", "value": "true"}`. The real payload is `{"label": "Sí", "value": true}`: real
    booleans, with labels configurable through `nadota.filters.boolean.*`.
  - The `type` list and the range example use `dateRange`. No filter has that type: a range DateFilter keeps `date`
    (or the field type, `datetime`/`time`), and only the component changes (`FilterDateRange`).
  - The quick start in `docs/filters/README.md` uses `Text::make` and `Boolean::make`. Neither class exists (the real ones are `Input`, `Toggle` and `Checkbox`).
- **Verified by**: `FiltersEndpointTest` (real payloads), and by reading the documents and `src/Http/Fields/`.
- **Severity**: low. **Kind**: contradiction.

### FLT-022 — ExistsFilter renders as FilterBoolean but ships no options
- **Location**: `ExistsFilter` does not override `resources()`, while BooleanFilter, on the same component, ships Sí/No.
- **Observed**: the `notes_exists` descriptor has `options: []`.
- **Severity**: low. **Kind**: doubt; it depends on whether the frontend builds its own options for `FilterBoolean`.

### FLT-023 — `MorphToFilter::$resourceKey` is stored but never used
- **Location**: `MorphToFilter` (constructor argument and `resourceKey()`). `generateFilters()` never reads it, because the endpoint
  comes from the request's resource. `FilterableTrait::createMorphToFilters()` computes it anyway. `$lastPart` in
  `generateFilters()` is also unused.
- **Severity**: low. **Kind**: doubt (dead API, and `docs/filters/morph-filters.md` documents it).

### FLT-024 — Currency, Status and Color fall back to a LIKE DefaultFilter
- **Location**: the `default` arm of `FilterableTrait::createFilterForFieldType()`. MultiImage, Signature, Lookup and Dynamic also
  fall into it.
- **Observed**: `Currency->filterable()` runs a LIKE on a decimal column, so `10` matches 110.00 and 10.50. `Status->filterable()` gets a
  text LIKE filter instead of a SelectFilter with its statuses.
- **Verified by**: `FieldGeneratedFiltersTest` ("falls back to a LIKE DefaultFilter …").
- **Severity**: low. **Kind**: doubt; this is a mapping decision.

### FLT-025 — RelationFilter and DynamicSelectFilter compare an unqualified primary key inside `whereHas()`
- **Location**: `RelationFilter::apply()` and `DynamicSelectFilter::apply()`, `$q->getModel()->getKeyName()`.
- **Observed**: a `BelongsToMany` whose pivot table has its own `id` (`fx_course_student`) fails with
  `SQLSTATE[HY000]: ambiguous column name: id`, a QueryException. This hits relation listings and every BelongsToMany/MorphToMany filter
  once FLT-004 no longer hides it.
- **Expected**: `getQualifiedKeyName()`.
- **Verified by**: `RelationFilterTest` (2 skipped).
- **Severity**: high. Pivot tables with an `id` column are the Laravel default. **Kind**: bug.

### FLT-026 — The MorphOne filter is keyed by its label
- **Location**: `MorphOne::__construct()` leaves `fieldData->key = ''`, while `HasMany` and `HasOne` set it to the relation name.
  `RelationFilter($label, '')` then falls back to `Filter::key()`, which is the label lower-cased without spaces.
- **Observed**: `MorphOne::make('Latest Note', 'latestNote')->filterable()` is keyed `latestnote`, so translating the label changes the key.
- **Verified by**: `FieldGeneratedFiltersTest` ("keys a MorphOne filter by its relation …").
- **Severity**: low. **Kind**: bug.

### FLT-027 — `Radio` and `CheckboxList` produce filters with swapped options
- **Location**: `FilterableTrait::createSelectFilter()` calls `getOptions()`. `Radio` and `CheckboxList` do not override it, so it returns
  `[]` (only `Select` overrides it), and the trait then reflects the raw `$options` (`[value => label]`), which `SelectFilter` reads as `[label => value]`.
- **Observed**: `/filters` for gender returns `[{label: 'f', value: 'Female'}]`. Picking "Female" sends `Female`, and no row matches.
  `docs/filters/README.md` says the options are "copied from field".
- **Verified by**: `FieldGeneratedFiltersTest`, `FiltersEndpointTest`, `FilterLabIndexTest`.
- **Severity**: medium. **Kind**: bug.

### FLT-028 — A field's `filterKeys` disagree with the keys of the filters it generates
- **Location**: `FilterableTrait::getFilterKeys()` uses the **attribute**, while the generated filters use `key()`.
- **Observed**: `HasMany` and `BelongsToMany` advertise `{value: ''}`, while the filter listens on `students` / `courses`.
  `BelongsTo->foreignKey('current_grade_id')` advertises `current_grade_id`, while the filter listens on `grade_id`. MorphTo advertises
  `notable_id` but generates two filters. The `filterKeys` in field payloads therefore cannot be trusted.
  `tests/Unit/Fields/Traits/FilterableTraitTest.php`, owned by the traits agent, records the current
  "attribute, not the key" behaviour.
- **Verified by**: `FieldGeneratedFiltersTest` ("advertises on the field the same request key …", 3 dataset rows skipped).
- **Severity**: low. **Kind**: contradiction.

### FLT-029 — A resource-level DynamicSelectFilter advertises an options endpoint for a field that does not exist
- **Location**: `DynamicSelectFilter::getEndpointUrl()` builds `/{resource}/resource/field/{filter field}/options`, or
  `/{resourceKey}/…`, from the filter's own field.
- **Observed**: `DynamicSelectFilter::make('School', 'school_id')->relation('grade.school')` advertises
  `/filter-lab-student/resource/field/school_id/options`, which answers `{"success": false, "message": "Field not found"}`. The
  example in `src/Http/Filters/README.md` (`->resourceKey('users')->relation('user')` on `user_id`) produces the same kind of
  dead endpoint. `RelationFilter` and BelongsTo-generated filters work, because a field with that key exists.
- **Verified by**: `FiltersEndpointTest` ("advertises a working options endpoint for a resource-level DynamicSelectFilter").
- **Severity**: low–medium. **Kind**: doubt (the documentation should say an explicit `endpoint()` is required).

---

## Observations from other areas (no FLT id)

- **HTTP-001**: index `meta` values are duplicated into two-element arrays. The filter tests read them through `Arr::first(Arr::wrap(...))`.
- `docs/api/filtering.md` says the search parameter defaults to `search`. The main index reads `globalSearch`
  (`Resource::getSearchKey()`), while relation listings read `search`. This is for the search/HTTP owner.
- When the MorphTo name has no dot, its type filter and entity filter share the same label (both "Notable"). `docs/filters/morph-filters.md`
  documents this for backward compatibility.
- CORE-010 (PHP 8.4 implicit-nullable parameters in the Filter constructors) is not logged again.
