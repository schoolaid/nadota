# Progress — HTTP CRUD & index routes (2026-11-02)

Issues: [http.md](./http.md) (HTTP-001 … HTTP-017). Regression tests for CORE-002, CORE-003,
CORE-004 and CORE-006 are included here as skipped tests.

Result for this area: **251 tests — 215 passed, 36 skipped** (every skipped test was run
un-skipped once and fails today). Stable over several random seeds.

## Done

### Test files
| File | Tests | Skipped | What it covers |
|------|------:|--------:|----------------|
| `tests/Feature/Http/ResourceIndexTest.php` | 60 | 12 | envelope, mobile meta, withoutId, composite kitchen-sink row (every index field type + Count/Exists + BelongsTo), hidden fields never sent, per-row permissions, no N+1, pagination, perPage, sorting (default, direction, unsortable/unknown fields, SQL-injection attempts, created_at fallback), global search (attributes, relation path, custom key + `applySearch()`, bindings), soft-delete views (`withTrashed` values), getQuery + queryIndex scoping, `withOnIndex` eager load, column selection |
| `tests/Feature/Http/ResourceShowTest.php` | 19 | 1 | show envelope, detail fields + values, relations payload, password/hidden never exposed, 404 (missing, non-numeric, trashed), scoping, withoutId / detailCardWidth, `?action=update`, custom show resource; edit envelope, update fields + values, password empty, 404s, scoping, custom edit resource |
| `tests/Feature/Http/ResourceStoreTest.php` | 18 | 4 | create form (fields, no values, defaults, flags); store 201, many field types + relations, hashed password, 422 messages, type rules, unknown/guarded attributes ignored, readonly / disabled, defaults, unique rule, action event |
| `tests/Feature/Http/ResourceUpdateTest.php` | 17 | 3 | PUT and PATCH, untouched attributes, null clears, many-to-many sync, password keep/rehash, 422 + unchanged, required on PUT, PATCH partial, `:id` placeholder in unique, ignored attributes, readonly / disabled, 404s, scoping, queryIndex mismatch, action event |
| `tests/Feature/Http/ResourceDestroyTest.php` | 8 | 1 | soft vs hard delete, 404 (missing / already trashed), scoping, single-record, action event, `$canDelete` |
| `tests/Feature/Http/ResourceRestoreForceDeleteTest.php` | 14 | 2 | restore (trashed, not trashed → 404, missing, 400 without soft deletes, scoping, action event, `$canRestore`); force (trashed, active, missing, 400, scoping, action event survives the record, `$canForceDelete`) |
| `tests/Feature/Http/ResourcePermissionsTest.php` | 8 | 2 | exact payload + URLs, policy denials null the URLs, non-soft-delete resource, can* flags, 404, scoping, trashed record, non-numeric id (CORE-003) |
| `tests/Feature/Http/ResourceMetaEndpointsTest.php` | 15 | 6 | info (every knob + defaults, CORE-006), config (keys, agreement with info/fields/filters, section layouts), fields, filters payload, lens (CORE-002), data/compact (HTTP-005, CORE-004) |
| `tests/Feature/Http/ResourceAuthorizationTest.php` | 63 | 0 | 403 for each of 17 routes when its ability is denied (record untouched), pass-through when allowed, 404 for an unknown key on each route, authorization before validation, canCreate in info/config, row permissions, existence before policy, guest, permissive fallback, no policy, exact key resolution |
| `tests/Feature/Http/ResourceLifecycleHooksTest.php` | 25 | 4 | order and arguments of all store/update/delete/restore/force hooks, no hook on 422, rollback of record + hook writes + action event on every hook failure, ValidationException → 422, onDeleteFailed, perform* returning false, HTTP exceptions / `\Error` / ValidationException in delete / message leak (HTTP-006, HTTP-014) |
| `tests/Feature/Http/ResourceLifecycleFlowTest.php` | 1 | 0 | composite: create form → store → index + search → show → edit → update → destroy → trashed index → restore → force delete, with the audit trail |
| `tests/ServiceIntegration/DefaultValueHandlerTest.php` | 3 | 1 | the (unused) handler on its own |

### Fixtures added (all new files, nothing existing modified)
- `tests/Fixtures/Policies/ConfigurableAbilityPolicy.php` — allows everything except
  `config('nadota-tests.denied_abilities')`.
- `tests/Fixtures/Resources/LifecycleStudentResource.php` (`lifecycle-student`) — every lifecycle
  hook, logged into a container binding, failures injected through config; readonly / disabled /
  default / unique-with-`:id` fields.
- `tests/Fixtures/Resources/IndexScopedStudentResource.php` (`index-scoped-student`) — `getQuery()`
  + `queryIndex()` scopes.
- `tests/Fixtures/Resources/PagedStudentResource.php` (`paged-student`) — perPage 5, allowedPerPage,
  withoutId, withOnIndex, custom search key + `applySearch()`, title/description/components/card/
  selection/polling; counts `fields()` builds through a container binding.
- `tests/Fixtures/Resources/LockedStudentResource.php` (`locked-student`) — `$canDelete`,
  `$canRestore`, `$canForceDelete` false.
- `tests/Fixtures/Resources/CustomResponseStudentResource.php` (`custom-response-student`) +
  `tests/Fixtures/Http/StudentSummaryJsonResource.php` — custom show/edit responses.

> Naming collision: this area first created `ScopedStudentResource.php`; another agent wrote its own
> `ScopedStudentResource` (key `scoped-student`, export / global-options fixture) over it. This area
> moved to `IndexScopedStudentResource` and does not use `scoped-student`.

## Pending (and why)
- **Index with filters** — owned by the filters audit (`tests/Feature/Http/Filters`); only the
  `/filters` payload is checked here.
- **Relation sorting** (`sortable()` BelongsTo, `applySorting()` of relation fields) — relations
  audit; no fixture field here is a sortable relation.
- **Deep action-event payloads** (sanitising, batch ids, queue) — action-events audit; here only the
  event name, `original` / `changes` and rollback are checked.
- **File uploads in store/update** — field audit + existing `FileUploadRollbackTest`.

## Missing (not covered by anyone in this area)
- Section visibility in store/update/show (`flattenFields()` ignores `Section` visibility, audit M3)
  and `showWhen/hideWhen` evaluated without the model on update.
- Query string overriding the validated body (audit M1).
- Resources keyed by string / UUID through the CRUD routes (only the route matching is tested in
  `RelationRouteStringIdTest`).
- Database-specific behaviour: everything runs on SQLite (`/data` without `fields`, `perPage=-1`,
  LIKE case-sensitivity would differ on MySQL / Postgres).
- `ResourceIndexController::__construct()` `validateResource()` branch (skipped when running in
  console, so never exercised by the suite).
- The real middleware stack (`nadota.middlewares`, `auth:*`); tests act as a user directly.

## Coverage by route
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
