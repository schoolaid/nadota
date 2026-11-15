# Test normalisation & audit — progress log

Started 2026-11-02 on branch `fix/relation-limit-order`. Goal: normalise the test
suite; create, update or validate a test for every field, filter, relation, trait
and action; build composite resources that exercise many fields at once; test
every process/route (index, index with filters, show, store…); document every
step; log bugs/contradictions **without fixing them**; then audit and document
the menu, exports, actions and configuration.

Rule followed throughout: **no file under `src/`, `config/`, `routes/` or
`database/` was changed** (verified with `git status` at the end). Tests that
expose a bug assert the correct behaviour and are skipped with the issue id (see
[README.md](./README.md#conventions)).

## Result

| | Before | After |
|-|--------|-------|
| Tests | 859 | **2940** |
| Passing | 859 | **2601** |
| Skipped (bug regressions, one per issue reproduction) | 0 | **339** (covering 160 issue IDs) |
| Failing | 0 | **0** — three consecutive `composer test` runs, random seeds 1793605167, 1793605196, 1793605224 |
| Assertions | 2067 | 7294 |
| Test files | 79 | 183 |
| Issues documented | — | **197** (24 high · 82 medium · 91 low) → [ISSUES.md](./ISSUES.md) |

Every one of the 339 skips was run once without its skip and failed for its
stated reason; every issue id referenced by a skip exists in `findings/`
(cross-checked by script).

---

## Step 0 — Recon and shared infrastructure ✅

**Observed state of the suite:** consistent Pest `it()` style; good unit coverage
of the basic fields and of action events; thin or no coverage of 11 field
classes, 7 of 11 relation fields, 9 of 12 filters, most traits, the Action base
classes, the menu, and **no real HTTP request against any route** (only route
matching and direct controller calls).

**Built** (details in [README.md](./README.md)): `tests/Concerns/InteractsWithNadotaApi.php`
(boots bindings + routes + resources so tests call the real endpoints),
`tests/Fixtures/` school domain (schema, 7 models covering every relation type,
7 resources incl. the kitchen-sink `StudentResource`, 2 actions), conventions
in `docs/testing/README.md`, first findings CORE-001…010.

## Steps 1–7 — one area each ✅

| Step | Area | Tests in area (pass / skip) | Issues | Progress & coverage | Findings |
|------|------|-----------------------------|--------|---------------------|----------|
| 1 | Fields — 37 classes, composite media/showcase resources, per-field HTTP round trip | 866 (799 / 67), was 356 | FLD-001…033 | [fields-progress](findings/fields-progress.md) | [fields](findings/fields.md) |
| 2 | Relation fields — all 11, show / `/relation/{field}` over HTTP | 405 (357 / 48) | REL-001…021 | [relations-progress](findings/relations-progress.md) | [relations](findings/relations.md) |
| 3 | Filters — all 12 classes, FilterCriteria, ApplyFiltersPipe, `/filters`, **index with filters** | 380 (327 / 53), was 17 | FLT-001…029 | [filters-progress](findings/filters-progress.md) | [filters](findings/filters.md) |
| 4 | Field & resource traits | 410 (363 / 47) | TRT-001…023 | [traits-progress](findings/traits-progress.md) | [traits](findings/traits.md) |
| 5 | CRUD & index routes: index (pagination, sort, search), show, create, store, edit, update (PUT/PATCH), destroy, restore, force delete, permissions, config/info/fields/filters/lens/data, 403/404 on every route, full lifecycle | 251 (215 / 36) | HTTP-001…017 | [http-progress](findings/http-progress.md) | [http](findings/http.md) |
| 6 | Field/resource/global options, attachments, action events, actions (index/fields/execute), export (config, CSV, XLSX) | 208 (166 / 42) | OPS-001…027 | [ops-progress](findings/ops-progress.md) | [ops](findings/ops.md) |
| 7 | Audit + tests: menu, exports, actions, configuration, built-in ActionEventResource | 154 (108 / 46) | AUD-001…037 | [audit-progress](findings/audit-progress.md) | [audit](findings/audit.md) |

Functional audit documents: [../audit/menu.md](../audit/menu.md) ·
[../audit/exports.md](../audit/exports.md) · [../audit/actions.md](../audit/actions.md) ·
[../audit/configuration.md](../audit/configuration.md).

### Normalisation applied to existing tests

- Duplicated tests rewritten into real ones: `ServiceIntegration/DefaultValueTraitIntegrationTest`,
  `ServiceIntegration/Relations/BelongsToTest` and `HasOneTest` were verbatim copies of the unit tests;
  they now drive the store/update/show services.
- Tests that passed for the wrong reason fixed: a Checkbox custom-value test (boolean cast hid it),
  Select/CheckboxList JSON-string tests (passed through accidental double encoding), a MultiImage test
  (passed because of an unrelated `max:5`), two misleading tests in `SearchableTraitTest`.
- `RelationIndexServiceFiltersTest` rewritten: it declared a global class, read an unset `$GLOBALS`
  entry and never exercised `applyFilters()`.
- Generic global helpers renamed (e.g. `labels()` in `RelationInlineLimitTest`).
- Storage isolation: field tests use dedicated fake disks (`fld-*`, `fx-media`) instead of
  `Storage::fake('public')`, whose directory is shared by concurrently running processes.
- `phpunit.xml` and `phpunit.xml.dist`: `memory_limit=512M`. The grown suite peaks around 160 MB
  — a profile of per-test memory showed one-time class loading (PhpSpreadsheet ≈ 3.8 MB on the
  first export test), not a per-test leak — and PHP's default 128 MB killed the run.
- Convention added after a verified Pest pitfall: `->not->toThrow(Throwable::class)` passes even
  when the closure throws (README, rule 8). The one existing negated `toThrow` uses `Exception::class`
  and is correct.

### Follow-up 2026-11-15 — integration with the fixes of the field-audit session (`0aaf986..96fa4bf`)

The suite was rebased onto the fixes published after the audit (CheckboxList, `filters[id]`, BelongsTo with a
soft-deleted related record, HasMany/MorphMany attach opt-in, File `clear`, Json input).

- **Closed:** FLD-003, OPS-001, OPS-025, OPS-005, OPS-006, OPS-028, FLD-019, FLD-020, and REL-009 in part (see the
  "Fixed since the audit" table in [ISSUES.md](./ISSUES.md)); their skipped tests were un-skipped or rewritten to the
  new contract. Checking them also caught a regression of `3e462bc` (MorphToMany attachable list → 500), fixed by `96fa4bf`
  in the same push.
- **Adapted to an intended behaviour change:** attach/detach on HasMany and MorphMany is now opt-in. The fixture fields
  `Grade.students`, `Grade.notes` and `Student.notes` declare `->attachable()`, and the unit tests of `HasMany`,
  `MorphMany` and `ManagesAttachments` assert the new default (off) next to the opted-in behaviour.
- **New:** OPS-028 (low), found while testing the new attach rejection.
- **Guard fix:** `ConfigurationKeysTest` ignores comments and understands concatenated keys
  (`'nadota.mcp.descriptions.'.$name`), which the MCP release introduced.
- `BelongsToManyTest` expects the new `urls.attachable`. `CustomComponentTest` is the field-audit session's version
  (ours without the skips, plus their tests).
- Result: 2781 passed, 325 skipped (154 open issue IDs), 0 failed; every skipped test still fails without its skip.

### Follow-up 2026-11-15 / 17 — v1.5.0 and v1.5.1 (`96fa4bf..1d95559`)

Rebased onto the nine field fixes released as v1.5.0 (URL/Email/Time format rules, File required on edit, action
events for uploads, Code over a JSON cast, create-form context, Toggle labels, empty RichText, KeyValue `fill()`).

- **Closed:** FLD-002 (`785ccae`); both regression tests un-skipped. **Partly closed:** FLD-029 (`28451c6` validates the
  time shape, `c86ec9f` in v1.5.1 normalizes it to `H:i:s`); its skipped test is narrowed to `min()`/`max()`, which
  are still not enforced, and normalizes the input first as the persist service does.
- **Kept both sides** of the five test files the two efforts had both written (`EmailTest`, `URLTest`, `TimeTest`,
  `ToggleTest`, `RichTextTest`). Two of our expectations follow intended changes: Email puts its format rule last
  (`['nullable', 'email']`) and Toggle sends no default labels.
- **Flaky test fixed:** `StudentRelationsTest` › "runs a bounded number of queries on show…" compared two requests
  of which only the first paid the once-per-process schema lookup that decides whether `Student.notes` (attachable
  since the previous follow-up) offers detach. It flushes that cache before each request now.
- Result: 2853 passed, 323 skipped (153 open issue IDs), 0 failed on three random seeds; every skipped test still fails
  without its skip.

---

## Pending (and why)

Consolidated from the areas; each item's detail is in that area's progress file.

- **MySQL / PostgreSQL runs.** Everything runs on SQLite, which accepts invalid identifiers as
  string literals. TRT-001, TRT-013, REL-004, REL-009, FLT-003, FLT-011 and AUD-019 are asserted
  on the generated SQL or inferred; a real engine would turn several of them into 500s.
- **Decisions needed before tests can be written** for the doubts: FLD-015 (default shown for a null
  column), FLD-023 (automatic `in:` rule for option fields), FLD-028 (file removal contract),
  FLD-029 (Time validation), TRT-015/016/017, CORE-008 (relation `type` values), OPS-027.
- **Fixtures not built on purpose** (to keep the fixture set small): string/UUID-keyed models through
  CRUD, attachments and actions (route matching is tested, controllers are not); a soft-deleting
  related model (`withTrashed` on relations); a MorphTo whose every target has the scoped column
  (`scope[]` over HTTP for MorphTo).
- **Behaviour that lives in the frontend** and is only asserted as props: `dependsOn` /
  `softDependsOn` / `filtersToSend`, image thumbnails/`convertTo()`/`quality()`.
- **Existing docs were not edited** — contradictions are logged instead (HTTP-015, OPS-023, REL-014,
  FLT-002, FLT-021, AUD-010, AUD-023, AUD-037). `docs/audit/configuration.md` contains a proposed
  corrected configuration reference.
- **Global helper functions inside older test files** (38 of them) were left in place: names are
  unique and renaming them is churn without behavioural gain. New tests follow the convention.

## Missing (not attempted)

- Real middleware stack (`nadota.middlewares`, `auth:*` guards): tests act as a user directly.
- The `validateResource()` branch of the controllers: skipped whenever `App::runningInConsole()`,
  which is always true under Testbench. The 404 for unknown keys is still asserted (it comes from
  `ResourceManager`).
- Real S3 (ACLs, signed URLs), queued action-event logging end to end, Octane/long-running
  processes, custom `MenuServiceInterface` / `ResourceExportInterface` bindings.

## My recommendations (beyond the brief)

1. **Fix in the order given in [ISSUES.md](./ISSUES.md#suggested-fix-order)**, starting with P0 (data
   isolation). Most of the 197 issues trace back to ten root causes listed there. Fixing root causes
   1 and 2 (one record-loading + authorization helper, and delegating persistence to `Field::fill()`)
   should close about 25 IDs.
2. **Add a MySQL job to CI** (or run the HTTP suite against MySQL once per release). A whole class of
   SQL bugs is invisible on SQLite.
3. **Keep the two guard tests green** (`FixtureResourcesUseEveryFieldTest`, the route dataset in
   `ResourceAuthorizationTest`): they turn "add a field / route" into "add a fixture and a test".
4. **When fixing an issue, un-skip its tests in the same commit** (`grep -rn "ISSUE <ID>" tests`).
   Open issues that still have a skipped test (153 on 2026-11-15):
   `grep -rhoE "ISSUE [A-Z]+-[0-9]+" tests | sort -u | wc -l`.
5. Align `phpunit.xml` and `phpunit.xml.dist` (bootstrap file, exclusions, display flags still
   differ) and decide which one is canonical.
