# Findings — secondary HTTP routes (options, attachments, actions, action events, export)

Found while writing the HTTP tests under `tests/Feature/Http/{Options,Attachments,Actions,Export,ActionEvents}/`
on 2026-11-02. IDs use the `OPS-` prefix. **Nothing under `src/`, `config/` or `routes/` was changed.**

Every issue with a test has a test that asserts the *correct* behaviour and is skipped with
`->skip('ISSUE OPS-0NN: …')`. Each skipped test was run once without its `skip` to make sure it
fails today, so it turns into a regression test when the bug is fixed.

Several items were already listed in the read-only security audit `docs/roadmaps/security-audit.md`
(2026-10-30, IDs `A*`, `M*`, `B*`). They are logged again here only because this pass **reproduced them
over real HTTP** and gave them a regression test; the cross-reference is in each entry. CORE-001
(export URL reads `nadota.api_prefix`) and CORE-008 (MorphToMany/MorphedByMany report `belongsToMany`)
are not logged again.

Fixtures added for these tests (new files only): `tests/Fixtures/Resources/{ScopedStudentResource,
EnrollmentFormResource,StudentActionsResource}.php`, `tests/Fixtures/Actions/{ArchiveStudents,
SendGuardianEmail,ReplyWithResponseType}.php`, `tests/Fixtures/Policies/OptionsAwareStudentPolicy{,WithOptions}.php`.

Severity: **high** = data written or read across a boundary, or a feature that cannot work;
**medium** = wrong data / broken behaviour with a workaround; **low** = 500 instead of 4xx,
cosmetic, documentation.

---

### OPS-001 — Every MorphMany attachment call is a 500 (`FieldType::MORPH_TO_MANY` does not exist)
- **Status:** ✅ fixed by `dfdb766` (2026-11-14); the two MorphMany attach / attachable tests are un-skipped. MorphMany
  attach is now opt-in (`->attachable()`), like HasMany.
- **Location:** `src/Http/Controllers/AttachmentController.php:238`, `getServiceForField()`.
- **Observed:** the `match` arms are evaluated in order; for any field type other than `hasMany` or
  `belongsToMany` PHP reaches `FieldType::MORPH_TO_MANY->value` and throws
  `Error: Undefined constant FieldType::MORPH_TO_MANY`. `MorphMany` fields (`type = morphMany`) therefore
  answer **500 on attachable, attach and detach** (`POST /student/resource/1/attach/notes`,
  `/grade/resource/1/attach/notes`). The enum has no `MORPH_TO_MANY` case; MorphToMany and MorphedByMany
  report `belongsToMany` (CORE-008), so the arm and the `'morphedByMany'` arm below it are dead anyway.
- **Expected:** MorphMany served by `MorphManyAttachmentService`, as `docs/guides/attachments.md` documents
  ("MorphMany is supported for attach/detach by setting the foreign key and morph-type columns").
- **Verified:** HTTP, `AttachmentControllerTest` › "lists MorphMany items…", "attaches MorphMany children…"
  (skipped). Exception captured with `withoutExceptionHandling()`.
- **Why the existing suite missed it:** `tests/ServiceIntegration/AttachmentEventIntegrationTest.php` calls
  the services directly, never the controller's dispatch; it has no MorphMany case.
- **Severity:** high (documented feature unusable). **Kind:** bug.

### OPS-002 — Attaching to a non-to-many field is a 500, not 422
- **Location:** `AttachmentController.php:46,92,137,184` call `$fieldInstance->isAttachable()`, which only
  exists on fields using `ManagesAttachments` (HasMany, BelongsToMany, MorphMany, MorphToMany, MorphedByMany).
- **Observed:** `POST /student/resource/1/attach/grade` (BelongsTo), `/attach/profile` (HasOne),
  `/attach/name` (Input) → `Error: Call to undefined method …::isAttachable()` → 500.
- **Expected:** `422 { success: false, message: "Field is not attachable" }` (the documented error).
- **Verified:** HTTP, `AttachmentControllerTest` › "answers 422, not 500, for a field that cannot be attached at all" (skipped, 3 cases).
- **Severity:** low. **Kind:** bug.

### OPS-003 — Pivot payload can override the pivot foreign key: attach/sync write rows for another parent
- **Location:** `src/Http/Services/Attachments/BelongsToManyAttachmentService.php` — `attach()` (lines ~141-157),
  `sync()` (~266-285), `validatePivotData()` (~333-359). Inherited by `MorphToManyAttachmentService`.
- **Observed:** `POST /student/resource/{ana}/attach/courses` with
  `{"items":[1],"pivot":{"student_id":2}}` (or keyed `{"pivot":{"1":{"student_id":2}}}`) answers
  `200 "Items attached successfully", attached:[1]` but the pivot row is written for **student 2 (Ben)**:
  Laravel merges the pivot attributes over the base record, so a pivot key equal to the foreign key wins.
  Authorization (`attach`) was checked against Ana only. Contributing causes:
  - `validatePivotData()` returns the payload unchanged when the field declares no pivot columns
    (the fixture's `courses` field declares none, which is the default).
  - With pivot data keyed by id, `attach()` uses the raw `$pivotData[$id]`, not `$validatedPivot[$id]`,
    so even a declared allow-list is bypassed (audit A6).
  - `sync()` never calls `validatePivotData()` at all.
  - Even a declared allow-list includes `created_at`/`updated_at` when timestamps are on (`getPivotColumns()`).
- **Expected:** the parent and related keys of a pivot row always come from the URL and `items`; unknown
  pivot keys rejected (or dropped) unless declared. `docs/guides/attachments.md` promises "Pivot data is
  filtered to the field's declared pivot columns".
- **Verified:** HTTP + DB inspection, `AttachmentControllerTest` › "writes the pivot row for the parent of
  the URL…" (skipped, flat + keyed) and "cannot write pivot rows for another parent through the pivot
  payload" (sync, skipped).
- **Severity:** high (cross-record write that bypasses the parent's authorization). **Kind:** security bug.
  Extends audit A6 (which only names the keyed-by-id allow-list bypass).

### OPS-004 — Action fields are never validated
- **Location:** `src/Http/Controllers/ActionController.php::execute()` / `ActionExecutionService::execute()`.
  Nothing collects `$action->fields($request)` rules or validates the request.
- **Observed:** `ArchiveStudents` declares `Input::make('Reason','reason')->required()->rules(['string','min:5'])`;
  `POST /student-actions/resource/actions/archive-students` with no `reason` (or `"no"`) runs the action and
  archives the student (200). The fields endpoint advertises `required: true`, so only the frontend enforces it.
- **Expected:** `422` with `errors.reason`, nothing run. `docs/guides/actions.md`: "Validation is driven by the
  field's own `rules()`."
- **Verified:** HTTP, `ActionControllerTest` › "validates the action fields before running it" (skipped, 2 cases).
- **Severity:** medium (any client can run destructive actions without the inputs they require). **Kind:** bug / doc contradiction.

### OPS-005 — Attaching an id that matches no related record inserts a dangling pivot row
- **Location:** `BelongsToManyAttachmentService::attach()` — `items` are only diffed against the attached ids,
  never checked against the related table.
- **Observed:** `POST /student/resource/1/attach/courses {"items":[999]}` → `200 attached:[999], count:1` and a
  `fx_course_student` row with `course_id = 999`. On a database with foreign keys this becomes a 500 `QueryException`.
  HasMany/MorphMany do look the items up (`whereIn` on the related model), so they skip unknown ids.
- **Expected:** unknown ids refused with 422 (or at least skipped and not reported as attached).
- **Verified:** HTTP + DB, `AttachmentControllerTest` › "refuses ids that do not belong to any related record" (skipped).
- **Severity:** medium (data integrity). **Kind:** bug.

### OPS-006 — `resourceId` auto-exclusion silently does nothing when the pivot table has an `id` column
- **Location:** `src/Http/Services/FieldOptionsService.php::getAttachedIds()` (line ~604):
  `$model->{$relationName}()->pluck($relatedKeyName)`.
- **Observed:** for BelongsToMany / MorphToMany / MorphedByMany the pluck runs
  `select "id" from fx_courses inner join fx_course_student …` → `ambiguous column name: id`; the surrounding
  `try/catch (\Throwable)` swallows it and returns `[]`, so `?resourceId=` excludes nothing on both
  `/options` and `/options/paginated`. Any pivot created with `$table->id()` triggers it. The attachment
  services already qualify the column (`"{$relatedTable}.{$relatedKeyName}"`); this copy does not.
- **Expected:** already-attached records excluded (docs/fields/relation-fields.md, scoped-options guide).
- **Verified:** HTTP (`FieldOptionsControllerTest` › to-many options ×3, paginated ›
  "auto-excludes the records attached to resourceId", all skipped); SQL error reproduced directly.
- **Severity:** medium (feature silently broken for the most common pivot shape). **Kind:** bug.

### OPS-007 — Options `filters[]` accept any column and operator: oracle on hidden columns
- **Location:** `FieldOptions/Traits/SearchesOptions::applyFilters()`, its copy
  `FieldOptionsService::applyFilters()` (paginated), `ResourceOptionsService::applyFilters()`.
  `getAllowedOptionsFilters()` is not defined anywhere, so the allow-list is always `null`.
- **Observed:** `GET /enrollment-form/resource/field/student/options?filters[password][operator]=like&filters[password][value]=hash-a%`
  returns only the students whose password hash starts with `hash-a`; `GET /student/resource/options?filters[password]=hash-ana`
  is an equality oracle. `orderBy` is equally unrestricted (sort-by-hidden-column oracle).
- **Expected:** filter keys limited to declared/allowed columns; undeclared keys ignored or 422.
- **Verified:** HTTP, `FieldOptionsControllerTest` › data isolation (skipped ×2), `ResourceOptionsControllerTest`
  › "ignores filters on columns that are not allowed" (skipped). Same as audit **A8**.
- **Severity:** high. **Kind:** security.

### OPS-008 — Field options ignore the target resource's `getQuery()`
- **Location:** `AbstractOptionsStrategy::buildAndExecuteQuery()` (`$fieldModel::query()`),
  `MorphToOptionsStrategy::fetchOptions()` (`$modelClass::query()`), `FieldOptionsService::getPaginatedOptions()`.
- **Observed:** `ScopedStudentResource::getQuery()` hides archived students (stand-in for tenant scoping).
  `GET /enrollment-form/resource/field/student/options` (BelongsTo → ScopedStudentResource) still offers the
  archived student, on both the plain and the paginated route. `GET /scoped-student/resource/options` (resource
  options) and the export **do** go through `getQuery()` — only field options bypass it. Only `optionsQuery()`
  is applied.
- **Expected:** base query from the target resource's `getQuery($request)`, then `optionsQuery()`.
- **Verified:** HTTP, `FieldOptionsControllerTest` › "leaves out the records the target resource getQuery() hides" (skipped ×2). Audit **A8**.
- **Severity:** high for apps that scope in `getQuery()` (documented place for row-level scoping). **Kind:** security.

### OPS-009 — Denying `viewAny` never protects options when the policy has no `viewOptions()`
- **Location:** `FieldOptionsController::authorizeOptions()`, `ResourceOptionsController::index()`
  (`viewAny || viewOptions`) + `ResourceAuthorizationService::authorizedTo()` returning `true` when the
  policy lacks the method.
- **Observed:** with a policy denying `viewAny` and silent on `viewOptions`, `/student/resource/options` and
  `/student/resource/field/grade/options` answer 200 with every record. 403 only happens when the policy
  defines `viewOptions()` and denies it (tested, passing).
- **Expected (proposed):** a missing `viewOptions()` falls back to `viewAny` (or denies). Today's behaviour is
  intentional per the controller docblock ("viewOptions returns true by default if not defined"), so this is a
  design decision to revisit, not an accident.
- **Verified:** HTTP, `FieldOptionsControllerTest` and `ResourceOptionsControllerTest` › "denies … when viewAny is
  denied and the policy says nothing about viewOptions" (skipped). Audit **A2**.
- **Severity:** high (record enumeration by users who cannot list). **Kind:** security / design.

### OPS-010 — Actions can target records outside the resource's `getQuery()`
- **Location:** `ActionExecutionService::getModels()` — `$modelClass::query()->whereIn('id', $modelIds)`
  (also hard-codes `id` instead of the model key).
- **Observed:** `POST /scoped-student/resource/actions/activate-students {"resources":[<archived id>]}` activates the
  archived student that the resource hides everywhere else (200, `Activated 1 students`).
- **Expected:** records loaded through `getQuery($request)->whereKey($ids)`; hidden ids behave as unknown ids.
- **Verified:** HTTP, `ActionControllerTest` › "cannot reach records the resource getQuery() hides" (skipped). Audit **A5**.
- **Severity:** high. **Kind:** security.

### OPS-011 — Page-size / limit clamps can be bypassed (unbounded rows or 500)
- **Locations and observed behaviour:**
  - `SearchesOptions::getCommonParams()` (field options): `limit=-1` → `min(-1,100) = -1` → `limit(-1)` is
    ignored by the builder → **all rows** (110 of 110 in the test); `limit=` (empty → `null`) → no limit at all.
  - `ResourceOptionsService::getOptions()`: `limit=-1` → all rows.
  - `FieldOptionsService::getPaginatedOptions()`: `per_page=-1` → `paginate(-1)` → `select … offset 0` →
    SQLite/MySQL syntax error → **500** (on PostgreSQL: every row).
  - `AbstractAttachmentService::getPaginationParams()`: `per_page=-1` → same 500 on `attachable`.
  - `ActionEventController::index()`: `min(per_page, 100)` has the same shape (not exercised).
- **Expected:** `max(1, min((int) $value, MAX))`, empty → default.
- **Verified:** HTTP, skipped tests in `FieldOptionsControllerTest` (×3), `ResourceOptionsControllerTest`,
  `AttachmentControllerTest`. Audit **M10**.
- **Severity:** medium (DoS / bulk dump). **Kind:** bug / security.

### OPS-012 — Field options `meta.limit` reports the requested value, not the applied one
- **Location:** `FieldOptionsService::getFieldOptions()` builds `meta.limit` from `$request->get('limit')`;
  the strategy clamps later.
- **Observed:** `?limit=500` returns 100 options with `meta.limit = "500"` (a string). Resource options do
  report the clamped integer.
- **Expected:** `meta.limit = 100`.
- **Verified:** HTTP, `FieldOptionsControllerTest` › "reports the limit it actually applied in meta" (skipped).
- **Severity:** low. **Kind:** bug.

### OPS-013 — Invalid `orderDirection` on options is a 500
- **Location:** `SearchesOptions::applyOrdering()`, `ResourceOptionsService::getDefaultResults()`, paginated copy.
- **Observed:** `?orderBy=name&orderDirection=sideways` → `InvalidArgumentException: Order direction must be "asc" or "desc"` → 500.
- **Expected:** 422, or fall back to `asc`.
- **Verified:** HTTP, `FieldOptionsControllerTest` › "answers 422, not 500, for an invalid order direction" (skipped). Audit **B8**.
- **Severity:** low. **Kind:** bug.

### OPS-014 — Field options answer `200 {success:false}` where attachments answer 404
- **Location:** `FieldOptionsService::getFieldOptions()` / `getPaginatedOptions()`; controller always `response()->json($options)`.
- **Observed:** unknown field → `200 {"success":false,"message":"Field not found","options":[]}`; a field without
  related resource → `200 … "No strategy found for field type"` (plain) / `"Field has no associated resource"`
  (paginated — the two routes word the same case differently). The attachment endpoints answer
  `404 {"success":false,"message":"Field not found"}` for the same mistake. `docs/api/responses.md` documents
  the `success:false` body but not the status. The "Resource not found" branches are unreachable
  (`ResourceManager::getResourceByKey()` already aborts 404).
- **Expected (doubt):** 404 for an unknown field, consistent across endpoints.
- **Verified:** HTTP; current behaviour asserted in `FieldOptionsControllerTest` › fields without options; the 404
  expectation is a skipped test.
- **Severity:** low. **Kind:** doubt / inconsistency.

### OPS-015 — Action failures echo the exception message to the client
- **Location:** `ActionController::execute()` catch block: `['type' => 'danger', 'message' => $e->getMessage()]`, 500.
- **Observed:** `send-guardian-emails` throws `SMTP connection refused by mail.internal:2525`; the client receives
  exactly that. SQL errors, file paths and hosts leak the same way. The failed event **is** recorded correctly
  (`status=failed`, `exception=<message>`), so the detail is not lost if the response is made generic.
- **Expected:** generic message to the client; detail stays in the action event / log. Note that
  `docs/guides/actions.md` and `docs/api/responses.md` document today's behaviour.
- **Verified:** HTTP, `ActionControllerTest` › "does not echo the internal exception message to the client" (skipped).
  Audit **B1**.
- **Severity:** low. **Kind:** security hardening (documented behaviour).

### OPS-016 — Scalar `resources` / `columns` values are a 500 (TypeError)
- **Location:** `ActionController::execute()` passes `$request->input('resources', [])` to
  `ActionExecutionService::execute(array $modelIds)`; `ResourceExportService::handle()` passes `columns` to
  `ExportRequestDTO(?array $columns)`.
- **Observed:** `{"resources":"1"}` → `TypeError` — not caught, because the controller only catches `\Exception`
  → generic 500. `export?columns=name` → `TypeError` → 500.
- **Expected:** 422 (validate as arrays).
- **Verified:** HTTP, skipped tests in `ActionControllerTest` and `ExportControllerTest`. Audit **B8** family.
- **Severity:** low. **Kind:** bug.

### OPS-017 — Action-events endpoints type `{id}` as `int`
- **Location:** `ActionEventController::index(…, int $id)`, `show(…, int $id, int $eventId)`, `resolveAuthorizedModel(…, int $id)`.
- **Observed:** `GET /student/resource/abc/action-events` → `TypeError` → 500. `{id}` is deliberately unconstrained in
  `routes/api.php` for string/UUID keys, so string-keyed resources can never show their audit trail. Same
  pattern as CORE-003 (`permissions`).
- **Expected:** `string $id`; 404 for a key that matches no record.
- **Verified:** HTTP, `ActionEventControllerTest` › "answers 404, not 500, for a non-numeric record id" (skipped).
- **Severity:** medium for string-keyed resources. **Kind:** bug.

### OPS-018 — An `exportable()` field hidden from index is always exported empty
- **Location:** `ResourceExportService::handle()` reuses `BuildQueryPipe`, which selects
  `getSelectColumns($request, $data->getFields())` where `getFields()` = `fieldsForIndex()`;
  `ResourceExportable::getExportableFields()` additionally includes `isExportable()` fields that are not on index.
- **Observed:** `ScopedStudentResource` has `Textarea::make('Bio','bio')->hideFromIndex()->exportable()`.
  Export config lists the `bio` column; the CSV has a `Bio` header but the value is always `''` because `bio` was
  never selected.
- **Expected:** the export selects the columns of the exported fields.
- **Verified:** HTTP + CSV parse, `ExportControllerTest` › "fills the value of a field exported although hidden from index" (skipped).
- **Severity:** medium (silent data loss in exports). **Kind:** bug.

### OPS-019 — `nadota.export.enabled`, `sync_limit` and `chunk_size` are never read
- **Location:** `config/nadota.php` `export.*`; readers: only `formats` and `default_format` are read
  (`ResourceExportable::getAllowedExportFormats()`, `ExportRequestDTO`, `ResourceExportService`).
  `ResourceExportable::$syncExportLimit = 1000` and `ResourceExportService::$chunkSize = 500` are hard-coded.
- **Observed:** `config(['nadota.export.enabled' => false])` → export still streams; `nadota.export.sync_limit = 250`
  → `export/config` still reports `syncLimit: 1000`.
- **Expected:** config comment "Enable/disable export globally" and `docs/guides/exports.md` ("Defaults come from
  `config/nadota.php`") honoured, or the keys removed from config and docs.
- **Verified:** HTTP, skipped tests in `ExportControllerTest` (config + refusals). The sync limit itself is
  documented as informational and the test "exports past the sync limit" confirms it is not enforced.
  May overlap with the functional audit in `docs/audit/exports.md` / `configuration.md` (other agent).
- **Severity:** low-medium (a documented kill switch that does nothing). **Kind:** bug / doc contradiction.

### OPS-020 — Export filename is pasted unescaped into `Content-Disposition`
- **Location:** `AbstractExporter::streamResponse()` and `ExcelExporter::createStreamedResponse()`:
  `"attachment; filename=\"{$fullFilename}\""` with `filename` taken from the query string.
- **Observed:** `?filename=a"; filename*=UTF-8''evil.html` produces
  `attachment; filename="a"; filename*=UTF-8''evil.html.csv"` — a crafted link controls the download name/extension
  (browsers prefer `filename*`). CR/LF are rejected by PHP's `header()`, so no response splitting.
- **Expected:** `HeaderUtils::makeDisposition()` (or a slug of the filename).
- **Verified:** HTTP, `ExportControllerTest` › "keeps a quote in the requested filename from breaking the Content-Disposition header" (skipped).
- **Severity:** low. **Kind:** security hardening.

### OPS-021 — CSV / formula injection in exports
- **Location:** `CsvExporter::export()` (`fputcsv` of raw values), `ExcelExporter::export()` (`setValue()`).
- **Observed:** a student named `=HYPERLINK("http://evil.test","x")` is exported verbatim in the CSV; in the XLSX
  `=1+1` is stored as a real formula (`isFormula() === true`).
- **Expected:** cells starting with `= + - @` (and tab/CR) neutralised (prefix `'`) / `setValueExplicit(..., TYPE_STRING)`.
- **Verified:** HTTP + CSV/XLSX parse, two skipped tests in `ExportControllerTest`. Audit **M11**.
- **Severity:** medium. **Kind:** security.

### OPS-022 — The export endpoint does not check the `export` ability
- **Location:** `ResourceExportService::handle()` only calls `$request->authorized('viewAny')`;
  `ResourceExportable::getExportConfig()` reports `enabled = isExportEnabled() && authorizedTo('export')`.
- **Observed:** with a policy whose `export()` returns false, `export/config` says `enabled: false` (tested, passing)
  but `GET /student/resource/export?format=csv` streams the file (200).
- **Expected:** 403 from the export endpoint too. `docs/guides/exports.md` says both routes "require the `viewAny`
  ability" and that `enabled` "reflects … the `export` ability" — i.e. it documents the gap rather than closing it.
- **Verified:** HTTP, `ExportControllerTest` › "answers 403 when the policy denies the export ability" (skipped). Audit **A9**.
- **Severity:** high (the only control the UI exposes is client-side). **Kind:** security.

### OPS-023 — Documentation contradictions found while testing these routes
- `docs/guides/actions.md` "Action key and name": says the key is derived from the **fully-qualified** class name
  (`App\Nadota\Actions\ActivateUsers` → `app-nadota-actions-activate-users`). `Action::getKey()` =
  `Helpers::toUri()` = plural kebab of the **basename**: `activate-users`, and `SendGuardianEmail` →
  `send-guardian-emails`. Two actions with the same basename in different namespaces get the same key; the first one
  registered on the resource wins in `findAction()`.
- `docs/guides/actions.md`: "Validation is driven by the field's own `rules()`" — not implemented (OPS-004).
- `docs/api/routes.md` options parameters: `page / perPage` — the code reads `per_page`; `perPage` is ignored
  (tested: `?perPage=5` → `meta.per_page = 15`).
- `docs/api/routes.md` "Notes on path ordering": says specific routes "constrain `{id}` to digits" and the action-events
  section says "Numeric `{id}`" — `routes/api.php` leaves `{id}` unconstrained on purpose (only `{eventId}` is
  constrained); see OPS-017.
- `docs/guides/exports.md` ("any `search` … query parameters apply") and `docs/api/routes.md` (search key "default
  `search`"): the default search key is `globalSearch` (`ResourceSearchable::$searchKey`); `?search=` is ignored by
  the export (and, presumably, by the index — left to the index/CRUD findings).
- `docs/api/responses.md`: attachable meta example shows `per_page: 15` (default is 25); resource options meta example
  shows `keys: ["name"]` — `keys` echoes the requested record keys (ids), not attribute names.
- `docs/guides/attachments.md`: lists MorphMany as supported (it is a 500, OPS-001) and promises pivot filtering by
  declared columns (bypassed, OPS-003).
- **Severity:** low. **Kind:** doc contradiction.

### OPS-024 — Field options are authorized against the owning resource, never the related one
- **Location:** `FieldOptionsController::authorizeOptions()` uses `$request->getResource()` (the resource in the URL).
- **Observed:** a policy on `Grade` denying `viewAny` and `viewOptions` makes `/grade/resource/options` 403, yet
  `/student/resource/field/grade/options` lists every grade. The attachable lists are built the same way (by construction, not tested).
- **Expected (doubt):** probably intended (you may pick a grade for a student without browsing grades), but then
  `optionsQuery()`/`getQuery()` of the related resource are the only protection — and `getQuery()` is ignored (OPS-008).
  Worth an explicit decision and a sentence in `docs/guides/authorization.md`.
- **Verified:** HTTP, `FieldOptionsControllerTest` › "authorizes against the resource that owns the field, not the related one" (passing, documents today's behaviour).
- **Severity:** medium if unintended. **Kind:** doubt.

### OPS-025 — HasMany / MorphMany attach moves children away from their current parent
- **Status:** ✅ fixed by `dfdb766` (2026-11-14). Attach/detach on HasMany and MorphMany is off by default
  (`->attachable()`); attach only adopts children without a parent (`->reassignable()` allows taking them from another
  record); candidates go through the related resource's `optionsQuery()` plus `attachableQuery()`; an id outside them
  rejects the whole request with 422 and `rejected: [ids]` without writing; detach is only offered when the owner
  columns are nullable. Tests: `AttachmentControllerTest` › "lists only HasMany children without a parent", "refuses to
  move a HasMany child away from another parent and writes nothing".
- **Location:** `HasManyAttachmentService::getAttachableItems()/attach()` (`whereNull(fk)->orWhere(fk, '!=', parent)`);
  same in `MorphManyAttachmentService`. Related records are loaded with `$relatedClass::query()` (no target `getQuery()`).
- **Observed:** `attachable` on `grade/1/students` lists students of other grades; attaching them re-parents them
  (`grade_id` overwritten) — tested and passing. The guide only says "sets each child's foreign key to the parent ID".
  Combined with the missing scope, a user can pull another tenant's rows under their own parent (audit A6).
- **Expected (doubt):** decide whether "attach" may steal children of another parent; if yes, document it and require
  authorization on the old parent; in any case load candidates through the related resource's `getQuery()`.
- **Severity:** medium. **Kind:** doubt / security.

### OPS-026 — Global options endpoint is public and runs resource code for anonymous callers
- **Location:** `routes/public.php` + `RouteServiceProvider::publicRouteConfiguration()` (middleware `api` only),
  `GlobalOptionsController::index()`.
- **Observed:** with `nadota.middlewares = ['api','auth']`, `/scoped-student/resource/options` is 401 while
  `/nadota-api/options` still answers to a guest with every opted-in resource key and its filter keys/labels (tested).
  No records and no filter option values are exposed (tested). The controller instantiates each opted-in resource and
  calls `flattenFields()`/`filters()` with no user, so resource code that assumes `$request->user()` would 500 for guests.
- **Expected:** acceptable while `availableInGlobalOptions` defaults to `false` (audit B10); document that opting in
  publishes the resource key and filter names, and that `fields()`/`filters()` must tolerate a guest.
- **Severity:** low. **Kind:** security note / doubt.

### OPS-027 — Minor behaviours worth a decision (low, no fix required)
- `morph-options/{type}` for a type the field does not declare, and the plain `/options` route on a MorphTo field,
  answer `200 {success:true, options:[]}` instead of 404/422 (tested as is).
- When `canRun()` rejects every selected record **or the ids match no record**, execute answers
  `200 {type:"danger","You are not authorized to run this action on the selected resources."}` — misleading for
  unknown/deleted ids (tested as is; documented for the canRun case).
- Actions include soft-deleted records whenever the resource uses soft deletes (documented; tested). A bulk action
  on trashed rows is easy to trigger by id.
- `nameLabel` of custom action events is `ucfirst('action:activate-students')` = `"Action:activate-students"`.
- Export with `columns[]` that match no field streams a BOM, an empty header line and one empty line per record.
- `getServiceForField()` keeps a `'morphedByMany'` arm that can never match (MorphedByMany reports `belongsToMany`).
- **Kind:** doubt / cosmetic.

### OPS-028 — HasMany / MorphMany attach answers `rejected` ids as strings
- **Location:** `src/Http/Services/Attachments/OwnedChildrenAttachmentService.php:99` (introduced by `dfdb766`):
  `array_diff()` over the requested ids and the candidates' keys cast to `(string)`.
- **Observed:** `POST grade/1/attach/students {items:[2]}` for a student of another grade answers
  `422 {"rejected":["2"]}`, while a successful attach answers `"attached":[2]`.
- **Expected:** the same type in both lists (the ids as the model keys them), so a client can compare them with what it sent.
- **Verified:** HTTP, `AttachmentControllerTest` › "reports rejected ids with the same type as attached ids" (skipped).
- **Severity:** low. **Kind:** bug (response shape).
