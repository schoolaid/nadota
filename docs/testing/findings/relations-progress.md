# Progress — relation fields (2026-11-02)

Issues: [relations.md](./relations.md) (REL-001 … REL-021).

Run only this area:

```bash
./vendor/bin/pest tests/Unit/Fields/Relations tests/ServiceIntegration/Relations \
  tests/ServiceIntegration/RelationInlineLimitTest.php tests/Unit/Services/RelationIndexServiceTest.php \
  tests/Unit/Services/Handlers/RelationHandlerTest.php tests/Feature/Http/Relations
```

Result: **405 tests — 357 passed, 48 skipped** (every skip is an `ISSUE REL-…` and was checked to fail when un-skipped).

## Done

### Audit of the existing tests
| File | Finding | Action |
|------|---------|--------|
| `tests/ServiceIntegration/Relations/BelongsToTest.php`, `HasOneTest.php` | Verbatim copies of the unit tests (`diff` empty); no service was exercised. | Rewritten as real service tests (Store/Update/Show/Edit/Index/Create services on the fixture domain). |
| `tests/Unit/Fields/Relations/BelongsToTest.php` | `applies sorting with join` only asserted `instanceof Builder`; `generates options` only checked keys; no fill/filter/export/eager-load tests. | Rewritten: join table, order column and select asserted, real ordering checked; fill (FK, custom FK, null, absent, readonly/disabled), options, filters, export, column selection. 14 → 40 tests. |
| `tests/Unit/Fields/Relations/HasOneTest.php` | Legacy-only, no FK/column-selection/createContext exact shape. | Fixture-domain tests added; exact props/createContext/eager columns. 13 → 22. |
| `tests/Unit/Fields/Relations/HasManyTest.php` | `limit` read through the magic `__get`; urls/attach gating/createContext/export untested. | Rewritten on the fixture domain; urls, gating by `attachable()` and policy, createContext, export url, paginated stub, eager columns. 17 → 33. |
| `tests/Unit/Fields/Relations/BelongsToManyTest.php` | No afterSave/sync, no fixture pivot data, no url gating. | Sync (ids, pivot entries, empty, absent key), pivot `score` via eager load, pivot defaults, url gating, eager SQL. Options-strategy tests kept. 20 → 35. |
| `tests/Unit/Fields/Relations/BelongsToManyCreateContextTest.php` | Unused `Request::create()`; `toBeString()` assertions. | Exact `createContext`, deny-create case, no-model case. |
| `tests/ServiceIntegration/RelationInlineLimitTest.php` | Global helpers named `labels()`, `seedRelated()`… (convention #4: collision risk). | Renamed with an `inlineLimit…` prefix; added per-parent eager limits for BelongsToMany, MorphMany, MorphToMany, MorphedByMany, HasManyThrough, plus paginated/qualified-column cases. 6 → 12. |
| `tests/Unit/Services/RelationIndexServiceTest.php` | A reflection-only "method exists" test; *handles empty filter values* asserted only the collection type; `use ReflectionClass;` in the global namespace (no-op). | Replaced by behaviour tests: field lookup by key/relation, multi-relation dataset (11 types), qualified select, pivot columns, search, sort, formatting, label priority. 10 → 31. |
| `tests/Unit/Services/RelationIndexServiceFiltersTest.php` | Dead `$GLOBALS['relatedResource']` lookup; a named global class `TestModelResource` declared in the file. | **Not touched:** the filters agent (FLT-) rewrote this file in parallel. Reported here so they can drop the leftovers if still present. |

### New tests
- Unit, one file per relation that had none: `HasManyThroughTest`, `HasOneThroughTest`, `MorphManyTest`, `MorphOneTest`,
  `MorphToTest`, `MorphToManyTest`, `MorphedByManyTest` (`tests/Unit/Fields/Relations/`).
  Each covers construction (type/component/key/attribute), default visibility and index/show query flags, props/urls,
  resolve (resource shape, basic shape, labels, `withFields`, limit/orderBy, paginated stub, null/default), eager-load
  columns, fill/afterSave, sorting, filters and export.
- `tests/Unit/Services/Handlers/RelationHandlerTest.php`.
- HTTP (`tests/Feature/Http/Relations/`):
  - `StudentRelationsTest` — show of every Student relation (grade, profile, courses + pivot score, notes, latestNote,
    labels), bounded query count, paginated fields not eager loaded, limit/orderBy, withFields/exceptFields, index
    (BelongsTo only, single grade query, filter by FK), create/edit forms, store/update sync of BelongsToMany/MorphToMany.
  - `ThroughRelationsTest` — Grade show (BelongsTo, HasMany, HasOneThrough, MorphMany), School show (Count, HasMany,
    HasManyThrough), query count independent of rows, custom through keys.
  - `NoteMorphToTest` — show to Student/Grade/none, index with one query per morph type, MorphTo filters, store with
    both aliases, required MorphTo, update moving the target, partial update, morph map.
  - `RelationPaginationEndpointTest` — `/relation/{field}` for the 7 to-many relations (dataset), pagination/links,
    sorting, search, filters + meta, pivot, custom fields, `withoutId`, 404/422 errors, authorization/scoping/visibility.

### Fixtures added (no shared file modified)
`tests/Fixtures/Relations/`:
- `ConfiguresFields` trait — resources whose fields (and query scope) are declared per test with `using()` /
  `scopeQueryUsing()`; `reset()` in `afterEach`. Static state is per class.
- `Resources/Rel{Student,School,Grade,Note}Resource` (route keys `rel-student`, …) and
  `Rel{CustomKeysSchool,CustomKeysGrade}Resource`.
- `Models/CustomKeysSchool` (HasManyThrough with a non-`id` second local key), `Models/CustomKeysGrade`
  (same for HasOneThrough), `Models/OwnedNote` (morphTo whose name differs from its columns). All reuse the `fx_*` tables.

## Pending (and why)
- **Attach/detach/sync/attachable and field-options endpoints**: owned by the options/attachments agent; only the URLs
  and flags the relation fields emit are tested here.
- **Filter classes** (`RelationFilter`, `DynamicSelectFilter`, `MorphToFilter`) beyond "which filters a relation field
  exposes" and one end-to-end use each (BelongsTo FK, MorphTo type+id): owned by the filters agent.
- **Export routes**: only field-level `resolveForExport()` and `getExportableFields()` are covered here.
- **MySQL-only failures** (REL-004 ambiguous/unknown columns, REL-009 FK violations) are asserted on the generated SQL
  or on SQLite behaviour; a MySQL run would turn them into 500s.

## Missing
- `MorphOne` / `HasOneThrough` with `latestOfMany`/`ofMany` variants beyond the fixture `latestNote`.
- Relation fields inside `Section`s and `DynamicField` nested relations (`getNestedRelationFields()` path of
  `ManagesRelationLoading`) — belongs with the composite-field tests.
- Soft-deleted related rows (`deletedAt` filled, `withTrashed` on relations): no soft-deleting related fixture model.
- Real-policy tests for `canCreate`/`canAttach` with field-specific policy methods (`attachCourses`) — only a
  deny-all binding is used here.

## Coverage

| Relation | Test files | # tests (relation-specific) | Coverage |
|----------|-----------|----------------------------:|----------|
| BelongsTo | `Unit/Fields/Relations/BelongsToTest`, `ServiceIntegration/Relations/BelongsToTest`, `Feature/Http/Relations/StudentRelationsTest`, `ThroughRelationsTest` | 40 + 9 + ~6 | full |
| HasOne | `Unit/…/HasOneTest`, `ServiceIntegration/Relations/HasOneTest`, `StudentRelationsTest` | 22 + 5 + ~3 | full |
| HasMany | `Unit/…/HasManyTest`, `RelationInlineLimitTest`, `RelationIndexServiceTest`, `ThroughRelationsTest`, `RelationPaginationEndpointTest` | 33 + 8 + many | full |
| BelongsToMany | `Unit/…/BelongsToManyTest`, `BelongsToManyCreateContextTest`, `RelationInlineLimitTest`, `StudentRelationsTest`, `RelationPaginationEndpointTest` | 35 + 5 + 2 + ~10 + ~10 | full |
| HasManyThrough | `Unit/…/HasManyThroughTest`, `RelationInlineLimitTest`, `ThroughRelationsTest`, `RelationPaginationEndpointTest` | 19 + 1 + 3 + 1 | full (export: REL-008) |
| HasOneThrough | `Unit/…/HasOneThroughTest`, `ThroughRelationsTest` | 16 + 4 | full (key: REL-001) |
| MorphMany | `Unit/…/MorphManyTest`, `RelationInlineLimitTest`, `StudentRelationsTest`, `ThroughRelationsTest`, `RelationPaginationEndpointTest` | 19 + 1 + ~3 + 2 + ~4 | full |
| MorphOne | `Unit/…/MorphOneTest`, `StudentRelationsTest` | 16 + 2 | full (key: REL-001) |
| MorphTo | `Unit/…/MorphToTest`, `NoteMorphToTest` | 30 + 12 | full |
| MorphToMany | `Unit/…/MorphToManyTest`, `RelationInlineLimitTest`, `StudentRelationsTest`, `RelationPaginationEndpointTest` | 19 + 1 + ~4 + 1 | full |
| MorphedByMany | `Unit/…/MorphedByManyTest`, `RelationInlineLimitTest`, `RelationPaginationEndpointTest` | 18 + 1 + 1 | full |
| RelationIndexService / `/relation/{field}` | `Unit/Services/RelationIndexServiceTest`, `RelationPaginationEndpointTest` | 31 + 32 | full |
| RelationHandler | `Unit/Services/Handlers/RelationHandlerTest` | 2 | partial (dead code, REL-018) |
| ManagesRelationLoading / BuildsSelectColumns / LimitsInlineRelation (as used by relations) | the unit files above, `RelationInlineLimitTest`, show/index query-count tests | — | partial (nested DynamicField relations not covered) |

## Full-suite run (end of session)
- `./vendor/bin/pest` (default `memory_limit=128M`): aborts with a PHP fatal *Allowed memory size exhausted* in
  `vendor/maennchen/zipstream-php/src/File.php:334` during an Excel export test. Every export test file passes on its
  own, so this is cumulative memory over ~2,900 tests, not a relation test. Owner: export/infra.
- `php -d memory_limit=1G vendor/bin/pest`: **2601 passed, 339 skipped, 0 failed**. No failure comes from the relation files.
