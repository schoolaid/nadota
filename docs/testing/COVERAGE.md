# Coverage matrix

Which tests cover each field, relation, filter, trait, action, route and
subsystem after the 2026-11-02 normalisation. Each section is copied from
the area's progress file in [findings/](./findings/); "full" means
construction, serialisation, resolve and persistence/application were all
asserted, "partial" means some aspect is left, and the reason is in that area's
progress file.

Guard rails:

- `tests/Feature/Http/Fields/FixtureResourcesUseEveryFieldTest.php` fails if a
  class in `src/Http/Fields` is not used by any fixture resource or has no unit
  test file. Add the field to a fixture and write its test when you add a field.
- `tests/Feature/Http/ResourceAuthorizationTest.php` checks 403 and unknown-key
  404 on every CRUD/meta route. Extend its dataset when you add a route.

## Totals

| | Before | After |
|-|--------|-------|
| Test files | 79 | 183 |
| Tests | 859 | 2940 (2601 passing + 339 skipped bug regressions) |
| Assertions | 2067 | 7294 |
| Routes with a real HTTP test | 0 | all routes in `routes/api.php` and `routes/public.php` |

## Fields


Unit test counts include skipped tests. HTTP column: **R** = `StudentFieldsRoundTripTest`,
**M** = `StudentMediaRoundTripTest`, **S** = `StudentShowcaseRoundTripTest` (all also counted by the
coverage guard).

| Field class | Unit test file(s) | Unit tests (skipped) | HTTP | Status |
|---|---|---:|---|---|
| `Field` (abstract) | `FieldTest` | 40 (1) | R, M, S | full |
| `ArrayField` | `ArrayFieldTest` | 48 (4) | R, S | full — FLD-004/005/009/018 open |
| `Checkbox` | `CheckboxTest` | 24 | R, S (via DynamicField) | full |
| `CheckboxList` | `CheckboxListTest` | 23 (1) | R | full — FLD-009/023 open |
| `Code` | `CodeTest` | 17 | R | full |
| `Color` | `ColorTest` | 7 | R | full |
| `Count` | `CountTest` | 10 | R | full (constraints: TRT-002) |
| `Currency` | `CurrencyTest` | 17 | R | full |
| `CustomComponent` | `CustomComponentTest` | 14 (2) | S | full — FLD-019/020 open |
| `Date` | `DateTest` | 13 (3) | R | full — FLD-013/014 open |
| `DateTime` | `DateTimeTest` | 29 (4) | R | full — FLD-013/014/021 open |
| `DynamicField` | `DynamicFieldTest` | 35 (3) | S | partial — relation types not at HTTP level; FLD-006/007/008 open |
| `Email` | `EmailTest` | 11 | R | full |
| `Exists` | `ExistsTest` | 16 | R | full |
| `File` | `FileTest`, `FileCachedTemporaryUrlTest` | 51 (1) + 3 | M | full — FLD-028 open |
| `Hidden` | `HiddenTest` | 10 | R, S | full |
| `Html` | `HtmlTest` | 6 (1) | R | full — FLD-012 open |
| `Image` | `ImageTest` | 36 (1) | M | full (no thumbnail generation exists) — FLD-024 open |
| `Input` | `InputTest` | 21 (1) | R, M, S | full |
| `Json` | `JsonTest` | 18 (3) | R | full — FLD-003 open |
| `KeyValue` | `KeyValueTest` | 26 (2) | R, S | full — FLD-002/017/025 open |
| `Lookup` | `LookupTest` | 12 | S | full (options endpoint: ops area) |
| `MultiImage` | `MultiImageTest` | 19 (1) | M | full — FLD-016 open |
| `Number` | `NumberTest` | 23 | R, S | full |
| `Password` | `PasswordTest` | 17 | R | full |
| `Radio` | `RadioTest` | 13 (1) | R | full — FLD-023 open |
| `RichText` | `RichTextTest` | 7 (1) | R | full — FLD-012 open |
| `Section` | `SectionTest` | 19 (1) | S | full — FLD-011 open |
| `Select` | `SelectTest` | 25 (2) | R, S | full — FLD-023/032 open |
| `Signature` | `SignatureTest` | 46 (2) | M (disk), S (base64) | full — FLD-001/027 open |
| `Status` | `StatusTest` | 13 (1) | R | full — FLD-023/033 open |
| `Textarea` | `TextareaTest` | 12 | R, S (DynamicField default) | full |
| `Time` | `TimeTest` | 12 (1) | R | full — FLD-029 open |
| `Toggle` | `ToggleTest` | 21 (4) | R | full — FLD-010 open |
| `URL` | `URLTest` | 10 | R, S | full |
| `VariableText` | `VariableTextTest` | 13 (3) | R, S | full — FLD-022 open |

## Relation fields


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


## Filters


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

## Traits


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

## CRUD & index routes

| Route | Test file(s) | # tests (≈) | Coverage |
|-------|--------------|--------:|----------|
| `GET /{key}/resource` (index) | ResourceIndexTest, ResourceAuthorizationTest, ResourceLifecycleFlowTest | 64 | full (filters: Filters audit) |
| `GET /{key}/resource/create` | ResourceStoreTest, ResourceAuthorizationTest | 6 | full |
| `POST /{key}/resource` (store) | ResourceStoreTest, ResourceLifecycleHooksTest, ResourceAuthorizationTest, ResourceLifecycleFlowTest | 28 | full |
| `GET /{key}/resource/{id}` (show) | ResourceShowTest, ResourceAuthorizationTest | 19 | full |
| `GET /{key}/resource/{id}/edit` | ResourceShowTest, ResourceAuthorizationTest | 9 | full |
| `PUT /{key}/resource/{id}` | ResourceUpdateTest, ResourceLifecycleHooksTest, ResourceAuthorizationTest | 25 | full |
| `PATCH /{key}/resource/{id}` | ResourceUpdateTest, ResourceAuthorizationTest | 5 | partial (PATCH semantics: HTTP-010) |
| `DELETE /{key}/resource/{id}` | ResourceDestroyTest, ResourceLifecycleHooksTest, ResourceAuthorizationTest | 18 | full |
| `DELETE /{key}/resource/{id}/force` | ResourceRestoreForceDeleteTest, ResourceLifecycleHooksTest, ResourceAuthorizationTest | 14 | full |
| `POST /{key}/resource/{id}/restore` | ResourceRestoreForceDeleteTest, ResourceLifecycleHooksTest, ResourceAuthorizationTest | 14 | full |
| `GET /{key}/resource/{id}/permissions` | ResourcePermissionsTest, ResourceAuthorizationTest | 11 | full |
| `GET /{key}/resource/config` | ResourceMetaEndpointsTest, ResourceAuthorizationTest | 7 | full |
| `GET /{key}/resource/info` | ResourceMetaEndpointsTest, ResourceAuthorizationTest | 7 | full |
| `GET /{key}/resource/fields` | ResourceMetaEndpointsTest, ResourceAuthorizationTest | 5 | full |
| `GET /{key}/resource/filters` | ResourceMetaEndpointsTest, ResourceAuthorizationTest | 5 | partial (payload only) |
| `GET /{key}/resource/lens` | ResourceMetaEndpointsTest | 1 (skipped) | none — dead route (CORE-002) |
| `GET /{key}/resource/data` | ResourceMetaEndpointsTest, ResourceAuthorizationTest | 8 | partial (mostly skipped: HTTP-005, CORE-004) |

| Service / pipe | Covered through | Coverage |
|----------------|-----------------|----------|
| `ResourceIndexService`, `BuildQueryPipe`, `ApplySearchPipe`, `ApplySortingPipe`, `PaginateAndTransformPipe` | index tests | full |
| `ApplyFiltersPipe` | Filters audit | — |
| `ApplyFieldsPipe` | — | none (dead code, not in any pipeline; audit B9) |
| `ResourceCreateService`, `ResourceShowService`, `ResourceEditService` | show / store tests | full |
| `AbstractResourcePersistService`, `ResourceStoreService`, `ResourceUpdateService`, `ProcessesFields` | store / update / hooks tests | full |
| `ResourceDestroyService`, `ResourceRestoreService`, `ResourceForceDeleteService` | destroy / restore-force / hooks tests | full |
| `Handlers/DefaultValueHandler` | `ServiceIntegration/DefaultValueHandlerTest` | full (unused by the services: HTTP-009) |
| `ResourceAuthorizationService` (route usage) | ResourceAuthorizationTest, ResourcePermissionsTest | full for viewAny/view/create/update/delete/restore/forceDelete; attach/detach only through the permissions block |

## Options, attachments, action events, actions, export routes


| Route | Test file | # tests (pass + skip) | Coverage |
|-------|-----------|----------------------:|----------|
| GET `/{r}/resource/field/{f}/options` | Options/FieldOptionsControllerTest.php | ~31 (21 + 10) | full |
| GET `/{r}/resource/field/{f}/options/paginated` | Options/FieldOptionsControllerTest.php | ~13 (9 + 4) | full |
| GET `/{r}/resource/field/{f}/morph-options/{type}` | Options/FieldOptionsControllerTest.php | 6 (6 + 0) | partial (no `scope[]` over HTTP) |
| GET `/{r}/resource/options` | Options/ResourceOptionsControllerTest.php | 15 (12 + 3) | full |
| GET `/options` (public) | Options/GlobalOptionsControllerTest.php | 4 (4 + 0) | full |
| GET `/{r}/resource/{id}/attachable/{f}` | Attachments/AttachmentControllerTest.php | ~11 (8 + 3) | full (MorphMany skipped, OPS-001) |
| POST `/{r}/resource/{id}/attach/{f}` | Attachments/AttachmentControllerTest.php | ~25 (19 + 6) | full |
| POST `/{r}/resource/{id}/detach/{f}` | Attachments/AttachmentControllerTest.php | ~9 (9 + 0) | full (MorphMany detach blocked by OPS-001) |
| POST `/{r}/resource/{id}/sync/{f}` | Attachments/AttachmentControllerTest.php | ~13 (12 + 1) | full |
| GET `/{r}/resource/{id}/action-events` | ActionEvents/ActionEventControllerTest.php | ~14 (13 + 1) | full |
| GET `/{r}/resource/{id}/action-events/{eventId}` | ActionEvents/ActionEventControllerTest.php | ~6 (6 + 0) | full |
| GET `/{r}/resource/actions` | Actions/ActionControllerTest.php | 5 (5 + 0) | full |
| GET `/{r}/resource/actions/{key}/fields` | Actions/ActionControllerTest.php | 4 (4 + 0) | full |
| POST `/{r}/resource/actions/{key}` | Actions/ActionControllerTest.php | 25 (20 + 5) | full |
| GET `/{r}/resource/export/config` | Export/ExportControllerTest.php | 6 (5 + 1) | full |
| GET `/{r}/resource/export` | Export/ExportControllerTest.php | 26 (19 + 7) | full |

Counts with `~` are split by hand from shared `describe()` blocks (errors/authorization datasets hit several routes).

## Menu, exports, actions, configuration


| Area | File | Tests | Skipped (issues) |
|------|------|------:|------------------|
| Menu item | `tests/Unit/Menu/MenuItemTest.php` | 8 | 2 (AUD-007, AUD-008) |
| Menu section | `tests/Unit/Menu/MenuSectionTest.php` | 5 | 0 |
| Section definition | `tests/Unit/Menu/MenuSectionDefinitionTest.php` | 5 | 1 (AUD-006) |
| Menu serializers | `tests/Unit/Menu/MenuResourceTest.php` | 4 | 1 (AUD-004) |
| Menu builder | `tests/Unit/Menu/MenuServiceTest.php` | 21 | 6 (AUD-001, 002, 003×3, 005) |
| Static registry | `tests/Unit/Menu/NadotaServiceTest.php` | 5 | 1 (AUD-009) |
| Resource menu options | `tests/Unit/Menu/ResourceMenuOptionsTest.php` | 3 | 1 (AUD-011) |
| `GET /menu` | `tests/Feature/Http/Menu/MenuEndpointTest.php` | 8 | 0 (AUD-010, AUD-012 pinned) |
| Action base class | `tests/Unit/Actions/ActionTest.php` | 12 | 2 (AUD-023×2) |
| Destructive action | `tests/Unit/Actions/DestructiveActionTest.php` | 3 | 0 |
| Action response | `tests/Unit/Actions/ActionResponseTest.php` | 9 | 0 |
| Action controller | `tests/Unit/Actions/ActionControllerTest.php` | 10 | 3 (AUD-021, AUD-022×2) |
| Action execution | `tests/Unit/Actions/ActionExecutionTest.php` | 8 | 3 (AUD-024, AUD-025×2) |
| Export service | `tests/Unit/Export/ResourceExportServiceTest.php` | 12 | 5 (AUD-013, 014×2, 015, 016) |
| Exporter hardening | `tests/Unit/Export/ExporterHardeningTest.php` | 4 (5 runs) | 3 (AUD-017×2, AUD-018 ×2 datasets) |
| Config keys | `tests/Unit/Config/ConfigurationKeysTest.php` | 9 | 5 (AUD-014, AUD-035, CORE-007×2, CORE-001) |
| Service provider | `tests/Unit/Config/NadotaServiceProviderTest.php` | 12 | 5 (AUD-030, 031, 033, 034×2) |
| Discovery | `tests/Unit/Config/ResourceManagerDiscoveryTest.php` | 10 | 3 (AUD-031×2, AUD-032) |
| Built-in ActionEventResource | `tests/Feature/Http/BuiltIn/ActionEventResourceTest.php` | 5 | 4 (AUD-028×3, AUD-029) |

Issues verified by reading only (no test): AUD-019, AUD-020, AUD-026, AUD-027 (partly pinned in `ActionTest`),
AUD-036 (pinned), AUD-037.

