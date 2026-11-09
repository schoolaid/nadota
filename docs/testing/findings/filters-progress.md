# Progress — filters (2026-11-02)

Issues are in [filters.md](./filters.md) (FLT-001 … FLT-029). Every skipped test is a regression test
for one of them, and each was checked to fail with its skip removed.

Result for these paths: **380 tests: 327 passed, 53 skipped (ISSUE FLT-…), 0 failed**. The run was
repeated with three random seeds and the result was stable.

```bash
./vendor/bin/pest tests/Unit/Filters tests/Unit/Criteria tests/Unit/Services/Pipes/ApplyFiltersPipeTest.php \
  tests/Unit/Services/RelationIndexServiceFiltersTest.php tests/Unit/Resources/Filters tests/Feature/Http/Filters
```

## Done

- **Audit of the existing tests**
  - `tests/Unit/Filters/BooleanFilterTest.php`, `ExistsFilterTest.php` and `MorphToFilterTest.php`: kept as they were.
    They only covered labels and constructors, so DB-backed `apply()` tests, defaults and serialisation were added to each.
    `ExistsFilterTest` imported the unused `TestModel`.
  - `tests/Unit/Services/RelationIndexServiceFiltersTest.php` was rewritten:
    - It declared a named global class `TestModelResource`, which goes against the conventions; it now uses the existing `Tests\Resources\TestResource`.
    - It had a no-op `use ReflectionClass;`.
    - The parent resource referenced `$GLOBALS['relatedResource']`, which is never set, and would hit `get_class(null)` if its fields were ever read.
    - Several `$query` variables and factory rows were unused.
    - Its assertions were loose (`toContain`, `not->toBeEmpty`).
    - It never tested `applyFilters()`, which actually applies the filters. Tests for that now exist, including FLT-010.
- **Unit tests for every filter class** (constructor defaults, component, type, id, key, `toArray`, props, options,
  `filterKeys`, `isRange`, and `apply()` against the real `fx_*` tables: single values, arrays, ranges with only a start or only an end,
  empty and null values, `'true'`/`'false'`, invalid input).
- `FilterCriteria`: key matching on `key()` rather than the label or id, unknown keys, null skipped while other "empty" values are
  forwarded, first-match on duplicate keys, AND combination, and the returned builder.
- `ApplyFiltersPipe`: field filters merged with resource filters, non-filterable fields ignored, unknown keys,
  `_from/_to` normalisation (both sides, one side, none), plain and nested values sent to range keys, key collisions
  (FLT-001), malformed parameters (FLT-008), fields hidden from the index (FLT-004).
- `FilterResource`: pass-through, plus the `data` collection envelope.
- Field-generated filters (`FilterableTrait::filters()` and `Exists::filters()`) are in
  `tests/Unit/Filters/FieldGeneratedFiltersTest.php`. This avoids clashing with the traits agent's `FilterableTraitTest.php`, which covers the flags only.
  The file covers the mapping of every field type, the copied true/false values and options, the BelongsTo/relation/MorphTo wiring, and default relation visibility.
- **HTTP** (`tests/Feature/Http/Filters/`):
  - the `/filters` payload for StudentResource, Grade, School, Course, Note and both lab resources;
  - the advertised option and morph-option endpoints, checked to answer;
  - every StudentResource filter on the index, plus combinations with search (`globalSearch`), sorting and pagination,
    with `meta.total` and `last_page` reflecting the filtered count;
  - ignored input and malformed input;
  - every remaining filter type, through the lab resources;
  - relation listings with filters, and `meta.filters`.
- **Documentation comparison**: `docs/filters/*.md`, `docs/api/filtering.md` and `src/Http/Filters/README.md` against the actual behaviour
  produced FLT-002, FLT-018, FLT-021, FLT-023, FLT-027 and FLT-029.
- **New fixtures** (additive; the shared fixtures were not touched):
  - `tests/Fixtures/Resources/FilterLabStudentResource.php` (route key `filter-lab-student`)
  - `tests/Fixtures/Resources/FilterLabNoteResource.php` (route key `filter-lab-note`)

  Both are registered per test with `bootNadotaApi([...$this->fixtureResources, FilterLab…::class])`.

## Pending (and why)

- **MySQL/PostgreSQL**: the suite runs only on SQLite. FLT-011 (500 instead of an empty result) and the date comparisons in FLT-003
  (DATE columns behave correctly on MySQL) are inferred for those engines, not executed.
- **Export with filters**: `ResourceExportService` reuses `ApplyFiltersPipe`, so FLT-001, 002, 004 and 008 apply to exports too. That area belongs to the export agent and was not tested here.
- **`GET /options` (GlobalOptionsController)** lists the key and label of each filter. It belongs to the options agent. It uses
  `flattenFields()` as `/filters` does, so it is affected by FLT-001 (duplicate keys) and FLT-004.
- **Authorization of `/filters` (`viewAny`)**: left to the HTTP CRUD and authorization tests.
- `Exists::applyFilter()` (deprecated) belongs to the fields agent (`tests/Unit/Fields/ExistsTest.php`). It has the same FLT-007 logic.

## Missing (not covered by any test yet)

- `MorphToMany`, `MorphedByMany`, `HasManyThrough` and `HasOneThrough` made `->filterable()`. They are covered only indirectly: they fall
  under the `whereHas` relation types or the `default` arm, but no test checks it.
- `DynamicSelectFilter::dependsOn` / `softDependsOn` / `filtersToSend` are only checked as props; their semantics live in the frontend.
- `BooleanFilter` with a translated label config is covered by unit tests only, not over HTTP.

## Coverage

| Class / surface | Test files | # tests (incl. skipped) | Coverage |
|---|---|---|---|
| `Filter` (abstract) | `Unit/Filters/FilterTest.php` | 16 | full |
| `BooleanFilter` | `Unit/Filters/BooleanFilterTest.php`; HTTP `IndexFiltersTest`, `FilterLabIndexTest` | 21 + 6 | full |
| `DateFilter` | `Unit/Filters/DateFilterTest.php`; HTTP `IndexFiltersTest`, `FilterLabIndexTest` | 22 + 5 | full |
| `DefaultFilter` | `Unit/Filters/DefaultFilterTest.php`; HTTP `FilterLabIndexTest` | 9 + 3 | full |
| `DynamicSelectFilter` | `Unit/Filters/DynamicSelectFilterTest.php`; HTTP `FiltersEndpointTest`, `FilterLabIndexTest` | 28 + 4 | full |
| `ExistsFilter` | `Unit/Filters/ExistsFilterTest.php`; HTTP `FilterLabIndexTest` | 21 + 3 | full |
| `MorphToFilter` | `Unit/Filters/MorphToFilterTest.php`; `RelationIndexServiceFiltersTest` | 19 + 1 | full |
| `MorphTypeFilter` | `Unit/Filters/MorphTypeFilterTest.php`; HTTP `FilterLabIndexTest` | 11 + 3 | full |
| `NumberFilter` | `Unit/Filters/NumberFilterTest.php`; HTTP `IndexFiltersTest`, `FilterLabIndexTest` | 21 + 4 | full |
| `RangeFilter` | `Unit/Filters/RangeFilterTest.php`; HTTP `FilterLabIndexTest` | 11 + 2 | full |
| `RelationFilter` | `Unit/Filters/RelationFilterTest.php`; HTTP `FilterLabIndexTest`, `FiltersEndpointTest` | 15 + 4 | full |
| `SelectFilter` | `Unit/Filters/SelectFilterTest.php`; HTTP `IndexFiltersTest`, `FilterLabIndexTest` | 11 + 3 | full |
| `FilterCriteria` | `Unit/Criteria/FilterCriteriaTest.php` | 9 | full |
| `ApplyFiltersPipe` (incl. `_from/_to`) | `Unit/Services/Pipes/ApplyFiltersPipeTest.php`; all HTTP index tests | 20 | full |
| `FilterResource` | `Unit/Resources/Filters/FilterResourceTest.php` | 3 | full |
| `FilterableTrait::filters()` + `Exists::filters()` | `Unit/Filters/FieldGeneratedFiltersTest.php` | 52 | partial (through-relations, see Missing) |
| `RelationIndexService` filters | `Unit/Services/RelationIndexServiceFiltersTest.php`; `Feature/Http/Filters/RelationListingFiltersTest.php` | 11 + 7 | full |
| `GET /{resource}/resource/filters` | `Feature/Http/Filters/FiltersEndpointTest.php` | 18 | full (authorization: see Pending) |
| Index with `filters[...]` | `Feature/Http/Filters/IndexFiltersTest.php`, `FilterLabIndexTest.php` | 25 + 30 | full |
