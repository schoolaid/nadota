# Progress — traits (2026-11-02)

Area: field traits, resource/request traits, `ProcessesFields`, FieldOptions traits.
Issues: [traits.md](./traits.md) (TRT-001 … TRT-023).

Result for the trait paths: **363 passed, 47 skipped, 0 failed** (stable over 3 random-order runs).

```bash
./vendor/bin/pest tests/Unit/Fields/Traits tests/Unit/Traits tests/Unit/Services/Traits \
  tests/Unit/Services/FieldOptions tests/ServiceIntegration/DefaultValueTraitIntegrationTest.php \
  tests/ServiceIntegration/VisibilityCallbackPersistTest.php
```

## Done

- **New fixture** `tests/Fixtures/Resources/AdHocResource.php`: a resource whose fields and protected
  instance knobs (`perPage`, `defaultSort`, `searchableAttributes`, `searchKey`, …) are injected
  (`AdHocResource::withFields([...], [...])`). Model defaults to the fixture `Student`. Not registered
  with `ResourceManager`; static `$attributeKey` deliberately not configurable (use an anonymous subclass).
- **New test files** (all follow docs/testing/README.md: `it()` style, one class per file, file-unique helper names):
  - `tests/Unit/Fields/Traits/`: `BuildsSelectColumnsTest`, `FieldDataAccessorsTraitTest`, `FieldResolveTraitTest`,
    `FilterableTraitTest`, `InteractsWithFieldsTest`, `LimitsInlineRelationTest`, `ManagesAttachmentsTest`,
    `ManagesFieldVisibilityTest`, `ManagesRelationLoadingTest`, `RelationshipTraitTest`, `ResourceFrontUtilsTest`,
    `SortableTraitTest`, `TransformsFieldDataTest`.
  - `tests/Unit/Traits/`: `AuthorizesResourcesTest`, `MakeableTest`, `PrepareResourceTest`, `ResourcePaginationTest`,
    `ResourceRelatableTest`, `ResourceSearchableTest`, `TracksActionEventsTest`, `VisibleWhenTest`.
  - `tests/Unit/Services/Traits/ProcessesFieldsTest.php` and
    `tests/Unit/Services/FieldOptions/Traits/{SearchesOptionsTest,SelectsDisplayAttributeTest}.php`
    (placed where they mirror `src/`, per the README layout).
- **Audited and extended** (counts are test cases after the change): `DefaultValueTraitTest` (10 → 25),
  `VisibilityTraitTest` (12 → 21), `ValidationTraitTest` (13 → 23), `DependsOnTraitTest` (40 → 47),
  `ScopesOptionsTest` (15 → 17), `SearchableTraitTest` (2 misleading tests rewritten, 8 → 9),
  `VisibilityCallbackPersistTest` (3 → 5, record-dependent visibility).
- **Rewritten**: `tests/ServiceIntegration/DefaultValueTraitIntegrationTest.php` was a copy of the unit test
  (TRT-022); now 5 real service tests (create/edit/show/store). Its one unique test moved to the unit file.
- **Every skipped test was verified** by running it with the skip removed (it fails for the stated reason).
- Duplicates with other areas use the other area's ID in the skip (HTTP-002/003/008/009, AUD-011, FLT-028)
  instead of a new TRT id.

## Pending (and why)

- `ResourceExportable`: audit only (export routes belong to the export agent). Findings in TRT-022; the test
  file was not moved or edited.
- `ResourceMenuOptions`: owned by the menu agent, skipped.
- `FilterableTrait::filters()` (which Filter class each field type produces): owned by the filters agent; only
  flags/keys tested here.
- `ManagesAttachments` end to end (`attachableQuery()` through the attachment services, TRT-023): attachments
  area; only the trait API, `props.attachment` and resource field permissions are tested here.
- `TracksActionEvents`: only trait-level gaps (gate, override, lazy service, forwarding); persistence is already
  covered by `ActionEventServiceTest` / `ActionEventIntegrationTest`.
- Section visibility inside `flattenFields()` (FLD-011) is tested by the fields agent; not duplicated.

## Missing / follow-ups

- TRT-001 and TRT-013 are invisible on SQLite: it reads an unknown double-quoted identifier as a string
  literal, and PDO exposes no switch to turn that off. Only a MySQL/PostgreSQL CI job would catch this class
  of bug at runtime; the tests here assert on the generated SQL instead.
- Full suite at the default 128M `memory_limit` dies in an export test (see the note in traits.md); passes at 1G.

## Coverage

| Trait | Test file(s) | # tests (pass + skip) | Coverage |
|-------|--------------|----------------------:|----------|
| **Field traits** (`src/Http/Fields/Traits`) | | | |
| BuildsSelectColumns | Unit/Fields/Traits/BuildsSelectColumnsTest | 12 (9 + 3) | full |
| DefaultValueTrait | Unit/Fields/Traits/DefaultValueTraitTest, ServiceIntegration/DefaultValueTraitIntegrationTest | 25 (21 + 4), 5 | full |
| DependsOnTrait | Unit/Fields/Traits/DependsOnTraitTest | 47 (45 + 2) | full |
| FieldDataAccessorsTrait | Unit/Fields/Traits/FieldDataAccessorsTraitTest | 12 (10 + 2) | full |
| FieldResolveTrait | Unit/Fields/Traits/FieldResolveTraitTest | 33 (18 + 15) | full |
| FilterableTrait (flags/keys) | Unit/Fields/Traits/FilterableTraitTest | 6 | partial (filters() → filters agent) |
| InteractsWithFields | Unit/Fields/Traits/InteractsWithFieldsTest (+ the four composed traits) | 3 | full |
| LimitsInlineRelation | Unit/Fields/Traits/LimitsInlineRelationTest, ServiceIntegration/RelationInlineLimitTest | 7, 6 | full |
| ManagesAttachments | Unit/Fields/Traits/ManagesAttachmentsTest | 12 | partial (service consumers → attachments) |
| ManagesFieldVisibility | Unit/Fields/Traits/ManagesFieldVisibilityTest, ServiceIntegration/SectionIntegrationTest | 16 (15 + 1), 8 | full |
| ManagesRelationLoading | Unit/Fields/Traits/ManagesRelationLoadingTest | 13 (11 + 2) | full (DynamicField nested relations only indirectly) |
| RelationshipTrait | Unit/Fields/Traits/RelationshipTraitTest | 15 | full |
| ResourceFrontUtils | Unit/Fields/Traits/ResourceFrontUtilsTest | 5 | full |
| ScopesOptions | Unit/Fields/Traits/ScopesOptionsTest (+ OptionScopeResolver/Scoped* integration tests) | 17 | full |
| SearchableTrait | Unit/Fields/Traits/SearchableTraitTest | 9 | full |
| SortableTrait | Unit/Fields/Traits/SortableTraitTest | 12 (10 + 2) | full |
| TransformsFieldData | Unit/Fields/Traits/TransformsFieldDataTest | 7 | full |
| ValidationTrait | Unit/Fields/Traits/ValidationTraitTest | 23 (18 + 5) | full |
| VisibilityTrait | Unit/Fields/Traits/VisibilityTraitTest, ServiceIntegration/VisibilityCallbackPersistTest | 21, 5 (4 + 1) | full |
| **Resource / request traits** (`src/Http/Traits`) | | | |
| AuthorizesResources | Unit/Traits/AuthorizesResourcesTest | 7 | full |
| Makeable | Unit/Traits/MakeableTest | 6 | full |
| PrepareResource | Unit/Traits/PrepareResourceTest | 7 | full |
| ResourceExportable | Unit/Export/ResourceExportableTest (not modified) | 13 | partial (audit only, TRT-022) |
| ResourceMenuOptions | — | — | none here (menu agent) |
| ResourcePagination | Unit/Traits/ResourcePaginationTest | 9 (5 + 4) | full |
| ResourceRelatable | Unit/Traits/ResourceRelatableTest | 4 | full |
| ResourceSearchable | Unit/Traits/ResourceSearchableTest | 14 (13 + 1) | full |
| TracksActionEvents | Unit/Traits/TracksActionEventsTest (+ ActionEventService/Integration tests) | 7 | full |
| VisibleWhen | Unit/Traits/VisibleWhenTest | 8 (7 + 1) | full |
| **Services** | | | |
| ProcessesFields | Unit/Services/Traits/ProcessesFieldsTest | 17 (14 + 3) | full |
| SearchesOptions | Unit/Services/FieldOptions/Traits/SearchesOptionsTest | 20 (19 + 1) | full |
| SelectsDisplayAttribute | Unit/Services/FieldOptions/Traits/SelectsDisplayAttributeTest | 6 | full |
