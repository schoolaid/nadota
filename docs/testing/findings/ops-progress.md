# Progress — secondary HTTP routes (OPS)

Area: field options, resource options, global options, attachments, action events, actions, export.
Findings: [ops.md](./ops.md). Session: 2026-11-02.

Result for these paths: **208 tests — 166 passed, 42 skipped** (every skip is an `ISSUE OPS-0NN` and was
checked to fail when un-skipped). Ran 3× in random order with no state leaks.

```bash
./vendor/bin/pest tests/Feature/Http/Options tests/Feature/Http/Attachments tests/Feature/Http/Actions \
                  tests/Feature/Http/Export tests/Feature/Http/ActionEvents
```

## Done

- `tests/Feature/Http/Options/FieldOptionsControllerTest.php` — BelongsTo, BelongsToMany, MorphToMany,
  MorphedByMany and MorphTo (per morph type) options; lookup by key / attribute / relation; search, limit and
  the 100 cap, exclude (array and CSV), orderBy; `resourceId` auto-exclusion; paginated shape, page, per_page cap,
  `perPage` vs `per_page`; scoped options (`scope[grade]`, IN list, optional scope, Lookup options) on both routes;
  unknown field, fields without options, unknown resource; data isolation (target `getQuery()`, `filters[]` oracle);
  authorization (viewAny/viewOptions combinations, morph route, related-resource policy).
- `tests/Feature/Http/Options/ResourceOptionsControllerTest.php` — shape, search on attributes and relations, `keys`,
  exclude, limit/orderBy, cap, soft deletes, `getQuery()`, `filters[]` oracle, 404, viewAny/viewOptions.
- `tests/Feature/Http/Options/GlobalOptionsControllerTest.php` — only opted-in resources, filter key/label merge,
  empty list, public even when resource routes require `auth`, no records or option values exposed.
- `tests/Feature/Http/Attachments/AttachmentControllerTest.php` — attachable (BelongsToMany, MorphToMany,
  MorphedByMany, HasMany, MorphMany*), search/pagination; attach with flat and per-id pivot data, already-attached,
  invalid items, dangling ids*, pivot FK override*, morph types, HasMany re-parenting, MorphMany*, attachable limit,
  action event; detach (BelongsToMany, MorphToMany, HasMany own-children-only, empty, event); sync (pivot update,
  `detaching=false`, empty list, MorphToMany, invalid items, unsupported type, limit, pivot override*, event);
  errors (unknown field/parent, parent hidden by `getQuery()`, non-attachable field, non-to-many field*);
  authorization (attach/detach/sync/attachable denied, attach allowed + detach denied). (* = skipped issue test)
- `tests/Feature/Http/Actions/ActionControllerTest.php` — index payload and flags, `context` filtering; fields
  endpoint (with/without fields, 404); execute on ids with events and payload logging + redaction, 422 without
  resources, standalone run, every `ActionResponse` type, default message, field validation*, `canRun()` partial and
  total denial, unknown ids, soft-deleted rows, out-of-scope ids*, failure (500 + failed event), message leak*,
  scalar `resources`*, unknown action; viewAny 403 on all three routes.
- `tests/Feature/Http/ActionEvents/ActionEventControllerTest.php` — index shape from a real action run, attachment
  events in the timeline, record isolation + ordering, pagination and cap, `name`/`status`/`user_id` filters, null
  user; show (own event, other record / unknown / non-numeric event → 404); 404 unknown or out-of-scope record,
  string id*, 403 on index and show.
- `tests/Feature/Http/Export/ExportControllerTest.php` — config (defaults, per-resource formats/default columns/sync
  limit/exportable hidden field, disabled resource, export ability, config key*); CSV headers, default and custom
  filename, BOM + parsed rows with export formatting, password never exported, column selection, search/filter/sort/
  default sort, soft deletes, `getQuery()`, exportable hidden field*, sync limit informational, formula injection*,
  filename injection*; XLSX headers and parsed sheet, default format, live formula*; refusals (unknown and
  not-allowed format 422, disabled 403, global config*, viewAny 403 ×2, export ability*, scalar `columns`*).
- New fixtures (new files only): `ScopedStudentResource` (getQuery hides archived; export/global-options/limits
  fixture), `EnrollmentFormResource` (Lookup + scoped BelongsTo → ScopedStudentResource; export disabled),
  `StudentActionsResource` + actions `ArchiveStudents`, `SendGuardianEmail`, `ReplyWithResponseType`; policies
  `OptionsAwareStudentPolicy` / `…WithOptions` (deny list in `config('nadota-tests.ops_denied')`).
- Audit of the existing service-level tests: kept, not duplicated. Their HTTP gaps are what the files above add
  (notably the controller dispatch that hides OPS-001, and the `resourceId` path that hides OPS-006).

## Pending (and why)

- **MorphTo `scope[]` over HTTP** — `MorphToScopedOptionsTest` covers it at strategy level; an HTTP test needs a
  morph fixture whose every morph type has the scoped column. Not added to keep the fixture set small.
- **Strict-scope empty page over HTTP** — covered by `ScopedPaginatedOptionsTest`; the HTTP fixture uses an optional
  scope so it can also serve the getQuery() test.
- **`attachable` scoped by the related resource's `getQuery()`** — no fixture relation targets `ScopedStudentResource`
  from a to-many field; noted in OPS-025, not tested.
- **`attachableQuery()` callback and `attachableDisplayFields` / item `meta`** over HTTP — service behaviour, low risk.
- **String / UUID-keyed parents for attachments and actions** — needs a string-keyed fixture model (the route side is
  covered by `tests/Feature/RelationRouteStringIdTest.php`); `whereIn('id')` in actions (OPS-010) would show there too.
- **`executeBatched()`** — not wired to any route (documented); stays covered by `ActionExecutionServiceTest`.
- **Queued action-event logging / `track_*` flags over HTTP** — covered at service level; no HTTP-specific behaviour.

## Missing (not testable without changing src/)

- 404 for an unknown resource key relies on `ResourceManager::getResourceByKey()` aborting; the controllers'
  `validateResource()` is skipped whenever `App::runningInConsole()` (always true under Testbench), so that branch is
  untested here. Behaviour is the same (404) either way.

## Coverage

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
