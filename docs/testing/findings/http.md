# Findings — HTTP CRUD & index routes (2026-11-02)

Area: every route of `routes/api.php` served by `ResourceController` and `ResourceIndexController`
(index, create, store, show, edit, update PUT/PATCH, destroy, force, restore, permissions, config,
info, fields, filters, lens, data) and the services behind them (`Resource{Index,Create,Store,Show,
Edit,Update,Destroy,ForceDelete,Restore}Service`, `AbstractResourcePersistService`, `Pipes/*`,
`Handlers/DefaultValueHandler`, `ResourceAuthorizationService` as those routes use it).

IDs use the `HTTP-` prefix. Recon items already logged in [core.md](./core.md) (CORE-002 lens,
CORE-003 permissions `int $id`, CORE-004 `/data` scoping, CORE-006 default title) are not repeated;
they have skipped regression tests in this area. Where an item matches the read-only security audit
in [`docs/roadmaps/security-audit.md`](../../roadmaps/security-audit.md) its id is given (A10, M4...):
those were known, but they had no test.

Every issue below has at least one test that asserts the correct behaviour and is skipped with
`->skip('ISSUE HTTP-0NN: ...')`. Each skipped test was run un-skipped once and fails today.
Environment: Laravel 11.54, SQLite `:memory:`, PHP 8.4.

---

### HTTP-001 — Index `meta` / `links` values are duplicated into two-element arrays
- **Location:** `src/Http/Resources/Index/IndexResource.php::toArray()`; Laravel's
  `PaginatedResourceResponse::toArray()` / `ResourceResponse::wrap()`.
- **Observed:** `IndexResource` builds its own `meta` and `links` and returns them next to `data`.
  Because the resource wraps a `LengthAwarePaginator`, Laravel still appends its own pagination
  block and merges both with `array_merge_recursive()`. Every scalar becomes an array:
  `"meta": {"current_page": [1, 1], "per_page": [20, 20], "total": [2, 2], ..., "links": [...], "path": "..."}`,
  `"links": {"first": ["http://...?page=1", "http://...?page=1"], "prev": [null, null], ...}`.
  On mobile, `links` is still present (Laravel's copy), and `meta` still gets `links`/`path`.
- **Expected:** the documented contract (`docs/api/responses.md`): scalar meta values, no Laravel
  `meta.links`/`meta.path`, and no `links` on mobile unless `include_links=1`.
- **Verified:** HTTP probe; `ResourceIndexTest` › "returns pagination meta and links as plain
  values" and "omits links on mobile unless include_links is requested" (skipped). Non-skipped
  index tests read meta through a helper that works before and after the fix.
- **Fix hint:** override `paginationInformation($request, $paginated, $default)` in `IndexResource`
  (return `[]`) so Laravel adds nothing.
- **Severity:** high (every list response). **Kind:** bug.

### HTTP-002 — `perPage` is not validated: `allowedPerPage` is never enforced, bad values are 500s
- **Location:** `src/Http/Services/Pipes/PaginateAndTransformPipe.php` (`paginate($perPage)` on the
  raw input). Matches audit **M10**.
- **Observed:** `perPage=7` and `perPage=100000` are honoured although `allowedPerPage` is
  `[10, 20, 50, 100]` (or `[5, 15]` on the fixture); `perPage=0` falls back to Eloquent's model
  default **15**, not the resource `perPage` (20); `perPage=-1` and `perPage=abc` end in a 500.
- **Expected:** a value outside `allowedPerPage` (and any non-positive / non-numeric one) falls back
  to the resource default (or is rejected with 422). `docs/guides/resources.md` describes
  `allowedPerPage` as "sizes the client may request".
- **Verified:** probes; `ResourceIndexTest` › pagination (5 skipped dataset rows).
- **Severity:** medium (unbounded page = full table dump / DoS). **Kind:** bug.

### HTTP-003 — An invalid `sortDirection` is a 500
- **Location:** `src/Http/Services/Pipes/ApplySortingPipe.php` (`orderBy($attribute, $sortDirection)`,
  also the default-sort direction). Matches audit **B8**.
- **Observed:** `?sortField=age&sortDirection=sideways` → `Builder::orderBy()` throws
  `InvalidArgumentException` → 500. There is **no SQL injection**: Laravel validates the direction,
  and `sortField` only matches declared sortable index field keys (tested with `;DROP TABLE`,
  subqueries and `CASE` expressions — all ignored, default sort applied).
- **Expected:** fall back to `desc`/the default sort (or 422).
- **Verified:** `ResourceIndexTest` › sorting › "falls back to a valid direction..." (skipped);
  injection attempts covered by non-skipped tests.
- **Severity:** low. **Kind:** bug.

### HTTP-004 — Global search term handling
- **Location:** `src/Http/Services/Pipes/ApplySearchPipe.php`.
- **Observed:**
  1. `empty($search)` treats the term `"0"` as "no search", so `?globalSearch=0` returns everything.
  2. `%` and `_` are not escaped, so `?globalSearch=%` matches every row (*doubt*: may be accepted).
  3. `?globalSearch[]=x` (array) reaches string interpolation / the `string $search` parameter of
     `applyRelationSearch()` → 500 (audit **B8**).
  The term itself is bound, not concatenated (`' OR '1'='1` matches nothing — tested).
- **Expected:** search for `"0"`; treat wildcards literally (or document that they are honoured);
  ignore or 422 a non-string term.
- **Verified:** `ResourceIndexTest` › global search (3 skipped).
- **Severity:** low. **Kind:** bug (1, 3) / doubt (2).

### HTTP-005 — `/data` (compact): the column allow-list is inverted, and no `fields` selects `""`
- **Location:** `src/Http/Controllers/ResourceIndexController.php::compact()`. Confirms the
  "to be confirmed" part of **CORE-004**.
- **Observed:** the allow-list is `['id'] + attributes of fields where ! isAppliedInIndexQuery()`.
  `isAppliedInIndexQuery()` is `true` for every scalar field and `false` only for multi-record
  relations (HasMany, BelongsToMany, MorphMany, MorphOne, MorphToMany, MorphedByMany, HasOneThrough),
  whose attribute is empty. So:
  - `?fields=id,name` returns only `id` — no scalar column can ever be requested;
  - without `fields`, `explode(',', null)` is `['']`, which matches the empty attribute, so the
    query is `select "" from fx_students`: SQLite answers rows like `{"\"\"": ""}`, MySQL would
    fail on an unknown column;
  - when no requested column matches (`?fields=name,email`), there is no `select` at all and every
    non-`$hidden` column of every row is returned (the CORE-004 "returns everything").
- **Expected:** `?fields=` limited to index-visible scalar fields + key; a sane default column set.
- **Verified:** probes; `ResourceMetaEndpointsTest` › data (compact) (2 skipped for HTTP-005,
  2 skipped for CORE-004).
- **Severity:** medium. **Kind:** bug.
- **Status:** ◐ partly fixed by `ac69d7f`: the empty attribute of relation fields is no longer a selectable column, so a request without `?fields` returns the key. The inverted allow-list (scalar columns such as `name` are not selectable) is still open.

### HTTP-006 — CRUD services mishandle exceptions thrown inside hooks
- **Location:** `AbstractResourcePersistService::handle()`, `ResourceDestroyService`,
  `ResourceRestoreService`, `ResourceForceDeleteService` (`try { DB::beginTransaction(); ... }
  catch (\Exception $e) { DB::rollBack(); return 500; }`).
- **Observed:**
  1. **HTTP exceptions lose their status.** `abort(403, '...')` in `beforeUpdate` (or any hook) is a
     `HttpException` → caught → `500 {"message": "Failed to update resource", "error": "..."}`.
     `docs/guides/lifecycle-hooks.md` shows `abort(403, ...)` in `beforeForceDelete` as the way to
     refuse.
  2. **ValidationException is a 422 only in store/update.** The persist service rethrows it (→ 422),
     the attachment services too, but destroy/restore/force turn it into a 500.
  3. **`\Error` leaves the transaction open.** Only `\Exception` is caught; a `TypeError`/`Error`
     thrown after `DB::beginTransaction()` escapes without `rollBack()` (verified:
     `DB::transactionLevel()` goes from 1 to 2 after the request). Under Octane / queue workers the
     connection keeps the open transaction for later work; `onDeleteFailed` is not called either.
- **Expected:** rethrow `HttpExceptionInterface` and `ValidationException` after rolling back; roll
  back on any `\Throwable` (e.g. use `DB::transaction()`).
- **Verified:** `ResourceLifecycleHooksTest` (3 skipped: abort 403, `\Error`, ValidationException
  on delete).
- **Severity:** medium. **Kind:** bug.

### HTTP-007 — `$canDelete` / `$canRestore` / `$canForceDelete` are not enforced by the endpoints
- **Location:** `src/Resource.php::canDelete()/canForceDelete()/canRestore()` are read only by
  `getPermissionsForResource()`; `ResourceDestroyService`, `ResourceRestoreService` and
  `ResourceForceDeleteService` check the policy only. Matches audit **A10**.
- **Observed:** on `LockedStudentResource` (all three flags `false`) `/permissions` reports
  `delete: false` and `urls.delete: null`, but `DELETE /{id}` soft-deletes the record (200), and
  restore / force behave the same.
- **Expected:** 403. `docs/guides/authorization.md`: "To disable a destructive action for the whole
  resource regardless of policy, set the property".
- **Verified:** `ResourceDestroyTest`, `ResourceRestoreForceDeleteTest` (3 skipped).
- **Severity:** high (authorization bypass of a documented control). **Kind:** bug / security.
- **Status:** ✅ fixed by `c03a612` (2026-11-17): the destroy, restore and force-delete services answer 403 when `canDelete()`/`canRestore()`/`canForceDelete()` is false, after the policy check. The regression tests are un-skipped.

### HTTP-008 — `disabled()` fields are written on store and update
- **Location:** `src/Http/Services/Traits/ProcessesFields.php::filterFieldsForStore()/
  filterFieldsForUpdate()` (filter `!isReadonly() && !isComputed()` only); simple fields are filled
  from `$validatedData`, never through `Field::fill()` (which does skip disabled). Audit **M4**.
- **Observed:** `favorite_color` declared `->disabled()` is stored from the payload; `readonly()`
  `website` is correctly ignored.
- **Expected:** `docs/fields/README.md`: "Readonly and disabled fields are skipped by the default
  `fill()`".
- **Verified:** `ResourceStoreTest`, `ResourceUpdateTest` (2 skipped).
- **Severity:** medium. **Kind:** bug + doc contradiction.

### HTTP-009 — Field defaults on store: omitted attributes get none, falsy values get overwritten
- **Location:** `AbstractResourcePersistService::fillField()` (only fills keys present in the
  validated data); `src/Http/Fields/Traits/FieldResolveTrait.php::resolveForStore()/
  resolveForUpdate()` (`if ($value == null)`); `src/Http/Services/Handlers/DefaultValueHandler.php`
  (never called — dead code, audit **B9**).
- **Observed:**
  1. `POST` without `age`/`status` stores `null` although both fields declare `->default(...)`; the
     default is applied only when the key is present with a null value.
  2. Loose `== null`: an explicit `0`, `"0"`, `""` or `false` is replaced by the default
     (`age: 0` is stored as `7`). Same in `resolveForUpdate()`: setting a field to 0 on update resets
     it to its default.
  3. `DefaultValueHandler::applyDefaults()` itself reads `$validatedData[$attribute]` for a missing
     attribute without default ("Undefined array key").
  `docs/internals/pipeline.md` says DefaultValueHandler "fills in field defaults before persistence"
  and is "used by the store/update services".
- **Expected:** defaults applied to omitted attributes on store; strict `=== null` check.
- **Verified:** probes; `ResourceStoreTest` (2 skipped), `tests/ServiceIntegration/
  DefaultValueHandlerTest.php` (1 skipped). Item 2 lives in a field trait and may also be logged by
  the field/trait audit.
- **Severity:** medium. **Kind:** bug.

### HTTP-010 — PATCH is a PUT: a partial update must resend every required field
- **Location:** `routes/api.php` (`PATCH /{id}` → `ResourceController::update`), validation in
  `AbstractResourcePersistService::handle()` uses the full rule set.
- **Observed:** `PATCH {"age": 12}` → 422 "The name field is required.".
- **Expected:** PATCH validates only the attributes sent (e.g. prefix rules with `sometimes`).
  `docs/api/routes.md` documents "same handler as PUT", so this is a design question.
- **Verified:** `ResourceUpdateTest` › "validates only the attributes sent on PATCH" (skipped).
- **Severity:** low. **Kind:** doubt / design.

### HTTP-011 — Routes disagree on which query scope loads a record
- **Location:** `ResourceUpdateService::getModel()` uses `getQuery()` + `queryIndex()`;
  `ResourceShowService`, `ResourceEditService`, `ResourceDestroyService`, `ResourceRestoreService`,
  `ResourceForceDeleteService`, `ResourceController::permissions()` use `getQuery()` only;
  `compact()` uses neither (CORE-004). The index uses both.
- **Observed (IndexScopedStudentResource):** a record that `queryIndex()` leaves out of the list can
  be shown, its edit form loads, it can be deleted, restored and force-deleted, but `PUT` answers 404.
  `getQuery()` itself is honoured everywhere except `/data`.
- **Expected:** one rule. Either `queryIndex()` is a listing scope (then update should not apply
  it) or a security scope (then every route should). `docs/guides/resources.md` says
  `queryIndex()` scopes "index/update queries".
- **Verified:** `ResourceUpdateTest` (skipped) plus non-skipped scoping tests per route.
- **Severity:** medium. **Kind:** contradiction / doubt.

### HTTP-012 — Trashed records cannot be shown, edited or asked for permissions
- **Location:** show/edit/permissions load with `getQuery()->findOrFail()` (default SoftDeletes
  scope, no `withTrashed`).
- **Observed:** the index (`withTrashed=only`) lists trashed rows with `deletedAt` and
  `permissions.restore: true`, but `GET /{id}`, `/{id}/edit` and `/{id}/permissions` are 404 for
  them. So the `deletedAt` key of the show/edit payload is always `null`, `permissions.restore` is
  always `false` there, and `/permissions` never returns the restore URL it knows how to build.
- **Expected:** show (and permissions) can load a trashed record (Nova does), or the payload keys
  are dropped.
- **Verified:** `ResourceShowTest`, `ResourcePermissionsTest` (2 skipped).
- **Severity:** low. **Kind:** contradiction / doubt.

### HTTP-013 — Store / update / restore answer with the raw Eloquent model
- **Location:** `AbstractResourcePersistService::successResponse()` (`'data' => $model`),
  `ResourceRestoreService`. Audit **M7**.
- **Observed:** update/restore return every non-`$hidden` column, including columns no field
  exposes (`avatar`, `document`, `photos`, `signature`, `uuid`...). Store returns only what was set
  during the request plus id/timestamps, so DB defaults (`is_active: false`) are missing. The shape
  differs from show (`attributes` list).
- **Expected:** the saved record serialized through its fields (show envelope), or at least
  id + visible fields. `docs/api/responses.md` documents the raw model, so this is a decision.
- **Verified:** `ResourceStoreTest` › "answers with the saved fields serialized like show"
  (skipped).
- **Severity:** low-medium (exposure of non-field columns). **Kind:** doubt / design.

### HTTP-014 — 500 responses echo the exception message whatever `app.debug` says
- **Location:** `AbstractResourcePersistService::errorResponse()`, `ResourceDestroyService`,
  `ResourceRestoreService`, `ResourceForceDeleteService` (`'error' => $e->getMessage()`). Audit **B1**.
- **Observed:** with `app.debug = false` the body still contains the internal message (SQL errors
  would include table/column names).
- **Expected:** generic message unless debug.
- **Verified:** `ResourceLifecycleHooksTest` › "does not echo the exception message..." (skipped).
- **Severity:** low-medium. **Kind:** bug / security.

### HTTP-015 — Documentation drift (docs/api, guides) against the behaviour
Each item verified by an HTTP test or probe.
- `docs/api/routes.md`: the default search key is said to be `search`; it is `globalSearch`
  (`?search=` is ignored — tested). `responses.md` info example shows `"search": {"key": "search"}`.
- `routes.md`: "Soft-delete scope (`trashed`/`withTrashed`)" — only `withTrashed` exists (values in
  `guides/soft-deletes.md` are right, all tested).
- `routes.md`: force/restore, permissions, relations, attachments and action events "require
  numeric `{id}`" and "Notes on path ordering ... constrain `{id}` to digits" — no route constrains
  `{id}` (`routes/api.php` says so explicitly; only `{eventId}` is `[0-9]+`). Only `permissions()`
  breaks on non-numeric ids (CORE-003); show answers 404 for `abc` (tested).
- `docs/api/responses.md`: "The `info` endpoint returns this object directly (not wrapped in
  `data`)" — it is wrapped in `data` (`InfoResource` is a `JsonResource`); `config` is unwrapped.
- `responses.md` create envelope shows `"value": null` per attribute — create attributes have no
  `value` key.
- `responses.md` index meta / mobile links — see HTTP-001.
- `docs/guides/lifecycle-hooks.md`: the `beforeStore` / `beforeUpdate` sections say they run "after
  validation and field filling"; the same guide's "Execution order" and the code run them **before**
  the fill (`beforeStore` sees `name = null`, pinned by `ResourceLifecycleHooksTest`). A value set in
  `beforeStore` on an attribute that is also a field is overwritten when the request carries it.
- `lifecycle-hooks.md`: `abort(403, ...)` in `beforeForceDelete` produces a 500 (HTTP-006).
- `docs/guides/authorization.md`: `$canForceDelete = false` "regardless of policy" (HTTP-007).
- `docs/fields/README.md`: disabled fields skipped (HTTP-008).
- `docs/internals/pipeline.md`: DefaultValueHandler used by store/update (HTTP-009); the
  "`BuildQueryPipe 2.php` duplicate" note is stale, the file no longer exists.
- `docs/guides/resources.md`: `allowedPerPage` "sizes the client may request" (HTTP-002).
- `src/Http/Traits/ResourceSearchable.php` docblock: "If no searchable attributes are configured,
  the system falls back to ['name', 'title', ...]" — the index search has no fallback
  (`isSearchable()` is false and the term is ignored); that list belongs to `displayLabel()`.
- **Severity:** low. **Kind:** docs.

### HTTP-016 — Each index row rebuilds the resource's `fields()`
- **Location:** `TransformsFieldData::transformForIndex()` → `Resource::getPermissionsForResource()`
  → `getFieldPermissions()` → `flattenFields()` → `fields($request)`; `IndexRequestDTO::getFields()`
  also rebuilds the list in every pipe.
- **Observed:** for an index page of N rows `fields()` runs 4 + N times (10 rows → 14, 20 → 24,
  measured with a counting fixture). Each row also runs 7 + 2 × (attachable fields) policy checks.
  The SQL query count is constant (no N+1 — tested).
- **Expected:** build the field list once per request.
- **Verified:** `ResourceIndexTest` › "builds the field list a fixed number of times..." (skipped).
- **Severity:** low (CPU per row; grows with heavy `fields()` definitions). **Kind:** performance.

### HTTP-017 — The edit envelope always includes `id`
- **Location:** `ResourceEditService::buildDefaultResponse()`.
- **Observed:** index and show drop `id` when the resource sets `$includeIdInResponse = false`
  (`withoutId()`); edit always sends it.
- **Expected:** to be decided — the form arguably needs it, but then the flag's scope should be
  documented.
- **Verified:** probe on `PagedStudentResource`; no test asserts either way.
- **Severity:** low. **Kind:** doubt.

---

## Observations that are not issues

Pinned by tests unless marked *(code reading)*.

- Unknown resource key → 404 (`{"message": ""}`) on every CRUD / index route. `ResourceIndexController`
  only calls `validateResource()` when **not** running in console, so under tests (and Octane, which
  runs in console) the 404 comes from `ResourceManager::getResourceByKey()`; for `/lens` an unknown
  key is therefore a 500 instead of a 404 (CORE-002) *(code reading for Octane)*.
- Existence is checked before authorization on show/edit/update/destroy/permissions: a user denied
  `view` gets 404 for a missing id and 403 for an existing one (same as Nova).
- Authorization is checked before validation on store/update (denied + invalid payload → 403).
- A policy without the ability method, or no policy at all, allows the action (documented
  permissive fallback, audit A1). A guest is denied when the policy needs a user.
- `restore` on a non-trashed record → 404. `force`/`restore` on a resource without soft deletes →
  400, even for a missing id because that check runs first *(probe, not pinned)*.
- Unknown attributes, `id`, `deleted_at`, `created_at` and non-field columns in a store/update
  payload are ignored (`$validator->safe()->only($fieldAttributes)`), and `readonly()` fields are
  never written.
- The `?action=update` query parameter switches show to the update context and authorizes `update`.
