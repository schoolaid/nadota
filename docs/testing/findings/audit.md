# Findings — functional audit: menu, exports, actions, configuration (2026-11-02)

Found while auditing the menu, export, action and configuration subsystems against
their code and docs. IDs use the `AUD-` prefix. Nothing under `src/`, `config/` or
`routes/` was changed: each issue is documented here and, where it can be expressed
as a test, covered by a skipped test that asserts the correct behaviour
(`->skip('ISSUE AUD-0NN: …')`). Every skipped test was checked to fail when un-skipped.

The narrative audits live in [`docs/audit/`](../../audit/): [menu](../../audit/menu.md),
[exports](../../audit/exports.md), [actions](../../audit/actions.md),
[configuration](../../audit/configuration.md).

Severity: **high** (data exposure, broken install, silent wrong data), **medium**
(wrong behaviour a user will hit), **low** (edge case, cosmetic, docs).
Kind: bug · security · contradiction (code vs docs) · doubt (needs a decision) · dead code · perf.

| ID | Title | Severity | Kind |
|----|-------|----------|------|
| AUD-001 | Resources sharing a title collapse into one menu item | medium | bug |
| AUD-002 | Top-level custom `MenuSection`s overwrite each other | medium | bug |
| AUD-003 | Section placement loses resources in three edge cases | medium | bug |
| AUD-004 | `MenuItemResource` never serializes a `MenuItem`'s children | low | bug |
| AUD-005 | Children of a custom section are not visibility-filtered | medium | bug |
| AUD-006 | `collapsible` / `defaultCollapsed` never reach the menu JSON | low | bug, contradiction |
| AUD-007 | `MenuItem::fromResource()` ignores order and `isResource` | low | bug |
| AUD-008 | `MenuItem` constructor: implicit nullable label/key on non-nullable properties | low | bug |
| AUD-009 | Menu section definitions are cached forever after the first read | low | bug |
| AUD-010 | Menu docs contradict the endpoint (wrapper, keys, comments) | low | contradiction |
| AUD-011 | `Resource::canSee()` has no effect on the menu | low | dead code, doubt |
| AUD-012 | Default install serves the menu and CRUD to guests | medium | security, doubt |
| AUD-013 | `/export` ignores the `export` ability that `/export/config` reports | high | security |
| AUD-014 | `export.enabled`, `export.sync_limit`, `export.chunk_size` are never read | medium | bug, contradiction |
| AUD-015 | Fields hidden from index but `exportable()` are exported empty | medium | bug |
| AUD-016 | Export evaluates index visibility without the request | medium | bug |
| AUD-017 | Spreadsheet formula injection in XLSX and CSV exports | medium | security |
| AUD-018 | `filename` is put into `Content-Disposition` unescaped | low | security |
| AUD-019 | No size guard; XLSX built fully in memory; offset paging without a tiebreaker | medium | perf, doubt |
| AUD-020 | To-many exportable relations query once per row and drop falsy values | low | perf, bug |
| AUD-021 | Action field `rules()` are never validated | high | bug, contradiction |
| AUD-022 | `ActionController` turns every exception into a 500 with the raw message | medium | bug |
| AUD-023 | Action keys are pluralised and can collide; docs describe FQCN keys | low | bug, contradiction |
| AUD-024 | Action runs are logged even with `action_events.enabled = false` | medium | bug |
| AUD-025 | Action targets are loaded by raw `id`, bypassing the key name and `getQuery()` scoping | high | security |
| AUD-026 | `ActionInterface` misses methods the service and controller call | low | bug |
| AUD-027 | Action API naming doubts (`showOnTableRow`, `make()`, `executeBatched`) | low | doubt |
| AUD-028 | Built-in `ActionEventResource` exposes and lets anyone delete the audit log | high | security |
| AUD-029 | `ActionEventResource` declares properties the base `Resource` never reads | low | bug |
| AUD-030 | Routes read `nadota.middlewares` before the package config is merged | medium | bug |
| AUD-031 | Boot crashes when `path_resources` is missing or absolute | high | bug |
| AUD-032 | Discovery derives class names from paths and wipes earlier registrations | medium | bug, doubt |
| AUD-033 | `key_resources_cache` is checked but never written | low | bug, contradiction |
| AUD-034 | Documented publish commands publish nothing; provider stub missing | medium | bug, contradiction |
| AUD-035 | `path` and `namespace` config keys are never read | low | dead code, contradiction |
| AUD-036 | `excludeResources()` only works if called before the package boots | low | doubt |
| AUD-037 | `configuration.md` is incomplete and partly wrong | low | contradiction |

---

## Menu

### AUD-001 — Resources sharing a title collapse into one menu item
- **Location:** `src/Http/Services/MenuService.php:61` (`$menuStructure[$resourceInstance->title()] = $menuItem`).
- **Observed:** top-level items are keyed by `title()`. Two resources with the same title (two
  `$title = 'Users'` in different modules, or two classes whose default title matches) keep only
  the one registered last; the other disappears from `/menu` without any error.
- **Expected:** every authorized resource appears; key the structure by `getKey()` (already unique,
  `ResourceManager` refuses duplicates).
- **Verified:** `tests/Unit/Menu/MenuServiceTest.php` › "keeps two resources that share a title" (skipped).
- **Severity / kind:** medium · bug.

### AUD-002 — Top-level custom `MenuSection`s overwrite each other
- **Location:** `src/Http/Services/MenuService.php:83` keys custom items by `getKey()`;
  `src/Menu/MenuSection.php:38-41` returns `''` for every section.
- **Observed:** `NadotaService::addMenuItems(fn () => [new MenuSection('One', …), new MenuSection('Two', …)])`
  yields only "Two". A custom item whose key equals a resource *title* also overwrites that resource.
- **Expected:** all custom sections survive; give sections a key (the definition key or the title) or
  append custom items instead of keying them.
- **Verified:** `MenuServiceTest` › "keeps every top-level custom section" (skipped); probe output.
- **Severity / kind:** medium · bug.

### AUD-003 — Section placement loses resources in three edge cases
- **Location:** `src/Http/Services/MenuService.php:58-65, 108-151`.
- **Observed:**
  1. Resource A has `displayInSubMenu() = 'Beta'` and resource B (registered later) has title "Beta":
     B's top-level assignment overwrites the fallback section, A vanishes. Registered the other way
     round, B is converted into a section that contains B itself plus A. The tree depends on
     registration order.
  2. A configured section key equals the title of a top-level resource: `isset($menuStructure[$key])`
     finds the `MenuItem`, the nested resource is appended to that item's children, which are never
     serialized (AUD-004) — the resource vanishes.
  3. `displayInSubMenu()` returns a blank string: `addMenuItemToSection()` trims it and returns early,
     the resource vanishes instead of falling back to the top level.
- **Expected:** sections and items live in separate namespaces; no resource silently disappears; a
  blank key means top level.
- **Verified:** three skipped tests in `MenuServiceTest` ("…whose key equals the title of a resource
  registered later", "…configured section whose key equals the title…", "…section key is blank…").
- **Severity / kind:** medium · bug.

### AUD-004 — `MenuItemResource` never serializes a `MenuItem`'s children
- **Location:** `src/Http/Resources/Menu/MenuItemResource.php:23` (`'children' => []`).
- **Observed:** `MenuItem::addChild()`/`setChildren()` and the `$children` constructor argument are
  public API, the service even sorts them, but the JSON always shows `children: []`.
- **Expected:** serialize children with `MenuResource` (as `MenuSectionResource` does), or drop the
  children API from `MenuItem`.
- **Verified:** `tests/Unit/Menu/MenuResourceTest.php` › "serializes the children of a plain menu item" (skipped).
- **Severity / kind:** low · bug.

### AUD-005 — Children of a custom section are not visibility-filtered
- **Location:** `src/Http/Services/MenuService.php:72-85` — only top-level custom items are checked
  with `isVisible()`.
- **Observed:** `new MenuSection('Tools', null, [MenuItem::make('Secret','secret')->canSee(fn () => false)])`
  still emits "Secret". Links meant for admins are shown to everyone (the target endpoints keep their
  own authorization, but the menu leaks their existence).
- **Expected:** `canSee` is honoured at every depth.
- **Verified:** `MenuServiceTest` › "hides the children of a custom section whose canSee refuses" (skipped).
- **Severity / kind:** medium · bug.

### AUD-006 — `collapsible` / `defaultCollapsed` never reach the menu JSON
- **Location:** `src/Menu/MenuSectionDefinition.php:95-104` (`toMenuSection()` drops them),
  `src/Http/Resources/Menu/MenuSectionResource.php:15-25`.
- **Observed:** the setters exist and `docs/guides/menu.md` lists them as features; the frontend never
  receives them. (`docs/internals/menu.md` already notes the gap.)
- **Expected:** emitted as `collapsible` / `defaultCollapsed` on sections, or removed from the API and guide.
- **Verified:** `tests/Unit/Menu/MenuSectionDefinitionTest.php` (skipped test).
- **Severity / kind:** low · bug, contradiction.

### AUD-007 — `MenuItem::fromResource()` ignores order and `isResource`
- **Location:** `src/Menu/MenuItem.php:46-58`.
- **Observed:** items built with `fromResource()` get order `2` (not `orderInMenu()`, default 1) and
  `isResource = false`, unlike the items `MenuService` builds for the same resource.
- **Expected:** same item as the service builds.
- **Verified:** `tests/Unit/Menu/MenuItemTest.php` (skipped test).
- **Severity / kind:** low · bug.

### AUD-008 — `MenuItem` constructor: implicit nullable label/key on non-nullable properties
- **Location:** `src/Menu/MenuItem.php:24-34` (`string $label = null, string $key = null`), `:64`
  (`order($order)` untyped while the property is `int`).
- **Observed:** `MenuItem::make()` throws `TypeError: Cannot assign null to property …$label`. The
  signature also uses the implicitly-nullable form deprecated in PHP 8.4 (same family as CORE-010).
- **Expected:** `string $label, string $key` required; `order(int $order)`.
- **Verified:** `MenuItemTest` › "declares label and key as required constructor arguments" (skipped); probe.
- **Severity / kind:** low · bug.

### AUD-009 — Menu section definitions are cached forever after the first read
- **Location:** `src/NadotaService.php:28-39`.
- **Observed:** `getMenuSections()` fills the static `$menuSections` once; calling
  `configureMenuSections()` again afterwards is ignored. Under Octane/queue workers (or tenant-specific
  sections) the first request's sections stick for the process lifetime.
- **Expected:** a new callback invalidates the cache (or definitions are evaluated per request).
- **Verified:** `tests/Unit/Menu/NadotaServiceTest.php` (skipped test).
- **Severity / kind:** low · bug.

### AUD-010 — Menu docs contradict the endpoint
- **Location:** `docs/guides/menu.md`, `docs/internals/menu.md`.
- **Observed:**
  - The guide says "the top-level response is an array"; `/menu` answers `{"data": [ … ]}`
    (`MenuResource` is a `ResourceCollection`, wrapped by default).
  - Examples use plural keys/URLs (`posts`, `nadota-api/posts/resource`); `Helpers::toUri()` produces
    the singular `post` for `PostResource`.
  - The `displayInMenu()` example is commented "Hide this resource from the menu entirely" but returns `true`.
  - The guide advertises `collapsible()`/`defaultCollapsed()` (AUD-006) and `MenuItem::fromResource()` (AUD-007) without caveats.
  - `docs/internals/menu.md` is stamped "Version 1.1.4"; the package is 1.3.0.
- **Expected:** docs match the code.
- **Verified:** `tests/Feature/Http/Menu/MenuEndpointTest.php` › "answers GET /menu … wrapped in a data key".
- **Severity / kind:** low · contradiction.

### AUD-011 — `Resource::canSee()` has no effect on the menu
- **Location:** `src/Resource.php:28` (`use VisibleWhen`), `src/Http/Services/MenuService.php:39-44`.
- **Observed:** resources expose `canSee()`/`isVisible()`, but the menu never calls `isVisible()` and
  instantiates a fresh resource, so a callback could only be set in a constructor — and is still
  ignored. Nothing else in `src/` reads it on resources. Only `displayInMenu()` hides a resource.
- **Expected (decision):** either honour `isVisible()` in the menu or drop `VisibleWhen` from `Resource`.
- **Verified:** `tests/Unit/Menu/ResourceMenuOptionsTest.php` (skipped test).
- **Severity / kind:** low · dead code, doubt.

### AUD-012 — Default install serves the menu and CRUD to guests
- **Location:** `config/nadota.php:8-10` (`'middlewares' => ['api']`),
  `src/Http/Services/ResourceAuthorizationService.php:56` (no policy ⇒ `true`).
- **Observed:** out of the box no route requires authentication and every resource without a policy is
  fully authorized, so an anonymous client gets `/menu` and the whole CRUD API. Neither
  `docs/getting-started/configuration.md` nor the installation guide warns about it.
- **Expected (decision):** ship an auth middleware in the default (e.g. `['api', 'auth:sanctum']`) or
  document prominently that the app must add one; consider deny-by-default when no policy exists.
- **Verified:** `MenuEndpointTest` › "serves the menu to guests under the default middleware stack"
  (passing test that pins the current behaviour); probe: guest `GET /nadota-api/student/resource` → 200.
- **Severity / kind:** medium · security, doubt.
- **Status:** ✅ fixed by `c12db48` (2026-11-17): decided 2026-11-17: the default `middlewares` is `['api', 'auth']`, so an app that does not publish the config requires its default guard. Apps that publish it keep their own stack. No deny-by-default when a resource has no policy. The regression tests are un-skipped.

## Exports

### AUD-013 — `/export` ignores the `export` ability that `/export/config` reports
- **Location:** `src/Http/Services/ResourceExportService.php:30` (only `viewAny`) vs
  `src/Http/Traits/ResourceExportable.php:219` (`'enabled' => … && authorizedTo($request, 'export')`).
- **Observed:** with a policy whose `export()` returns false, `/export/config` (and `/info`) report
  `enabled: false`, yet `GET /export` streams the file (200). The ability only hides the button.
- **Expected:** `handle()` aborts 403 when `authorizedTo('export')` is false (as `docs/guides/exports.md`
  implies by listing the `export` ability).
- **Verified:** `tests/Unit/Export/ResourceExportServiceTest.php` › "refuses the export when the policy
  refuses the export ability" (skipped); HTTP probe → 200.
- **Severity / kind:** high · security.
- **Status:** ✅ fixed by `af36502` (2026-11-17): same fix as OPS-022. The regression tests are un-skipped.

### AUD-014 — `export.enabled`, `export.sync_limit`, `export.chunk_size` are never read
- **Location:** `config/nadota.php:200-213`; `src/Http/Traits/ResourceExportable.php:33`
  (`$syncExportLimit = 1000`), `src/Http/Services/ResourceExportService.php:100` (`$chunkSize = 500`),
  `src/Http/Services/Exporters/AbstractExporter.php:15` (`chunkSize()` setter with no consumer).
- **Observed:** `NADOTA_EXPORT_ENABLED=false` does not disable anything; `syncLimit` in the export config
  is always the resource property; the chunk size is fixed. `docs/getting-started/configuration.md`
  describes all three as effective.
- **Expected:** the global switch aborts exports (and reports `enabled: false`); the resource default
  reads `export.sync_limit`; the service pages with `export.chunk_size`.
- **Verified:** two skipped tests in `ResourceExportServiceTest`; `tests/Unit/Config/ConfigurationKeysTest.php`.
- **Severity / kind:** medium · bug, contradiction.

### AUD-015 — Fields hidden from index but `exportable()` are exported empty
- **Location:** `src/Http/Traits/ResourceExportable.php:76` includes them;
  `src/Http/Services/Pipes/BuildQueryPipe.php:21-27` selects only the columns of the *index* fields.
- **Observed:** `URL::make('Website','website')->hideFromIndex()->exportable()` produces a "Website"
  header with an empty value in every row.
- **Expected:** the export query selects the columns of the exportable fields.
- **Verified:** `ResourceExportServiceTest` › "exports the value of a field hidden from index but marked
  exportable()" (skipped); HTTP probe.
- **Severity / kind:** medium · bug.

### AUD-016 — Export evaluates index visibility without the request
- **Location:** `src/Http/Traits/ResourceExportable.php:76` (`$field->isShowOnIndex()`); the same call
  without arguments is in `ResourceIndexController::fields()` (`:46`) and `config()` (`:110`).
- **Observed:** `showOnIndex(fn ($request) => …)`, `showWhen()` and `hideWhen()` callbacks receive `null`.
  The index (which passes the request through `fieldsForIndex($request)`) shows a column the export
  omits, or the reverse; a callback that dereferences `$request` without `?->` crashes the export.
- **Expected:** `isShowOnIndex($request)` everywhere.
- **Verified:** `ResourceExportServiceTest` › "exports the columns the index shows to the current user…"
  (skipped); HTTP probe (`/fields` also omits the column).
- **Severity / kind:** medium · bug.

### AUD-017 — Spreadsheet formula injection in XLSX and CSV exports
- **Location:** `src/Http/Services/Exporters/ExcelExporter.php:89` (`setValue()`),
  `src/Http/Services/Exporters/CsvExporter.php:71`.
- **Observed:** a record whose name is `=HYPERLINK("https://evil.test","x")` is stored in the XLSX as a
  live formula (data type `f`); the CSV writes it verbatim. Any user who can write a record can plant a
  formula that runs when an admin opens the export.
- **Expected:** write strings with `setValueExplicit(..., DataType::TYPE_STRING)` in XLSX; prefix values
  starting with `= + - @` (and tab/CR) with `'` in CSV (OWASP CSV-injection guidance).
- **Verified:** `tests/Unit/Export/ExporterHardeningTest.php` (two skipped tests); probe read the XLSX back.
- **Severity / kind:** medium · security.
- **Status:** ✅ fixed by `af36502` (2026-11-17): same fix as OPS-021. The regression tests are un-skipped.

### AUD-018 — `filename` is put into `Content-Disposition` unescaped
- **Location:** `src/Http/Services/Exporters/AbstractExporter.php:43`, `ExcelExporter.php:118`.
- **Observed:** `?filename=a"; filename=evil.exe` yields
  `attachment; filename="a"; filename=evil.exe.csv"` (a second parameter). Non-ASCII names are not
  RFC 6266/5987-encoded either.
- **Expected:** `HeaderUtils::makeDisposition('attachment', $name, $asciiFallback)`.
- **Verified:** `ExporterHardeningTest` › "keeps a filename containing quotes inside one … parameter"
  (skipped, both exporters).
- **Severity / kind:** low · security.

### AUD-019 — No size guard; XLSX built fully in memory; offset paging without a tiebreaker
- **Location:** `src/Http/Services/ResourceExportService.php:109-134`, `ExcelExporter.php:58-103`.
- **Observed:** nothing caps the row count (`sync_limit` is informational, AUD-014). `ExcelExporter`
  consumes the whole `LazyCollection` into a PhpSpreadsheet workbook before streaming, so memory grows
  with rows × columns (the "memory efficient" claim in the guide only holds for CSV). Paging uses
  `forPage()` (LIMIT/OFFSET) over the user's sort, with no primary-key tiebreaker: on MySQL/PostgreSQL a
  non-unique sort can repeat or skip rows across pages, and rows inserted during the export shift pages.
- **Expected (decision):** enforce `sync_limit` (or queue), add `orderBy(key)` as a tiebreaker / use
  `lazyById()`, and consider a streaming XLSX writer.
- **Verified:** code reading (not reproducible on SQLite).
- **Severity / kind:** medium · perf, doubt.

### AUD-020 — To-many exportable relations query once per row and drop falsy values
- **Location:** `src/Http/Fields/Relations/HasMany.php:212-240`; same code in `BelongsToMany.php:408`,
  `MorphMany.php:200`, `MorphToMany.php:267`.
- **Observed:** `resolveForExport()` runs `$model->{$relation}()->get()` for every exported row (N+1,
  contrary to the guide's "avoid N+1" note, which only holds for eager-loaded single relations), and
  `->filter()` without a callback drops values such as `"0"`. Items have no defined order.
- **Expected:** eager-load the relation for the chunk; filter only `null`.
- **Verified:** code reading.
- **Severity / kind:** low · perf, bug.

## Actions

### AUD-021 — Action field `rules()` are never validated
- **Location:** `src/Http/Controllers/ActionController.php:78-110`, `src/Http/Services/ActionExecutionService.php:41-69`.
- **Observed:** a field declared `Input::make('Subject','subject')->rules(['required','max:5'])` is only
  serialized; `handle()` runs with `subject = 'far too long'` and the call answers 200.
  `docs/guides/actions.md` says "Validation is driven by the field's own rules()".
- **Expected:** validate the request against the action fields' rules (422) before `handle()`.
- **Verified:** `tests/Unit/Actions/ActionControllerTest.php` › "validates the action field rules before
  handle() runs" (skipped); HTTP probe → 200. (HTTP-level regression tests belong to the OPS area.)
- **Severity / kind:** high · bug, contradiction.

### AUD-022 — `ActionController` turns every exception into a 500 with the raw message
- **Location:** `src/Http/Controllers/ActionController.php:100-109`.
- **Observed:** `catch (\Exception $e)` answers `500 {"type":"danger","message": $e->getMessage()}` for
  everything: a `ValidationException` thrown by `handle()` becomes 500 instead of 422, `abort(403)`
  becomes 500, and internal messages (SQL errors, paths) are sent to the client. (`\Error`s are not
  caught and go to the default handler.)
- **Expected:** let `ValidationException` / `HttpExceptionInterface` through; report unexpected
  exceptions and answer a generic message unless `app.debug`.
- **Verified:** two skipped tests in `ActionControllerTest`; HTTP probe (422 → 500, 403 → 500).
- **Severity / kind:** medium · bug.

### AUD-023 — Action keys are pluralised and can collide; docs describe FQCN keys
- **Location:** `src/Http/Actions/Action.php:73-76` → `Helpers::toUri()` (`src/Http/Helpers/Helpers.php:9-13`),
  `src/Http/Services/ActionExecutionService.php:29-33`.
- **Observed:** `SendReminder` → `send-reminders`, `SendEmail` → `send-emails` (the resource helper
  pluralises and strips `-resources`). `SendReminder` and `SendReminders` share a key and `findAction()`
  returns the first. `docs/guides/actions.md` documents FQCN-based keys
  (`app-nadota-actions-activate-users`), which is not what the code does.
- **Expected:** `Str::kebab(class_basename(static::class))` (or an explicit `$key`), unique per resource.
- **Verified:** two skipped tests in `tests/Unit/Actions/ActionTest.php`; HTTP probe listed
  `validating-actions`, `send-emails`.
- **Severity / kind:** low · bug, contradiction.

### AUD-024 — Action runs are logged even with `action_events.enabled = false`
- **Location:** `src/Http/Services/ActionExecutionService.php:131-154` calls
  `ActionEventService::logAction()` directly; the CRUD services go through
  `TracksActionEvents::shouldTrackActions()` (`src/Http/Traits/TracksActionEvents.php:121`).
- **Observed:** with tracking disabled an event row is still written. In a real app with tracking
  disabled the migrations are not loaded (`ResourceServiceProvider::registerMigrations()`), so every
  action run hits a missing table and `logSync()` writes two error-log entries per model.
- **Expected:** honour `action_events.enabled`.
- **Verified:** `tests/Unit/Actions/ActionExecutionTest.php` (skipped test); HTTP probe.
- **Severity / kind:** medium · bug.

### AUD-025 — Action targets are loaded by raw `id`, bypassing the key name and `getQuery()` scoping
- **Location:** `src/Http/Services/ActionExecutionService.php:74-87`.
- **Observed:** `$modelClass::query()->whereIn('id', $modelIds)`:
  - models whose key is not `id` (UUID/string keys) are never found;
  - `Resource::getQuery()` / `queryIndex()` — where apps put tenant or ownership scoping — are
    skipped, so any id posted in `resources[]` is handed to `handle()` unless the action's own
    `canRun()` re-checks it. No policy ability is checked per model either (only `viewAny` + `canRun`).
- **Expected:** load through `getQuery($request)` with `whereKey($ids)`.
- **Verified:** two skipped tests in `ActionExecutionTest`.
- **Severity / kind:** high · security.
- **Status:** ✅ fixed by `d6f7437` (2026-11-17): same fix as OPS-010: `whereKey()` uses the model's key name and the query comes from `getQuery()`. The regression tests are un-skipped.

### AUD-026 — `ActionInterface` misses methods the service and controller call
- **Location:** `src/Contracts/ActionInterface.php` vs `ActionExecutionService.php:55` and
  `ActionController.php:94` (`isStandalone()`), `Action::toArray()` (`getComponent`, `getIcon`).
- **Observed:** a class implementing `ActionInterface` without extending `Action` fatals on `isStandalone()`.
- **Expected:** add `isStandalone()` (and the other serialized getters) to the interface.
- **Verified:** code reading.
- **Severity / kind:** low · bug.

### AUD-027 — Action API naming doubts
- **Location:** `src/Http/Actions/Action.php:166-172, 377-380`; `ActionExecutionService.php:159-187`.
- **Observed:** `showOnTableRow()` only means "index + detail" (in Nova it means an inline row action);
  `Action::make()` takes no arguments, so actions with constructor dependencies cannot use it;
  `executeBatched()` exists but no route uses it.
- **Expected (decision):** rename/document, forward `make(...$args)`, wire or drop `executeBatched()`.
- **Verified:** `ActionTest` (current behaviour); code reading.
- **Severity / kind:** low · doubt.

## Built-in ActionEventResource

### AUD-028 — Built-in `ActionEventResource` exposes and lets anyone delete the audit log
- **Location:** `src/Providers/ResourceServiceProvider.php:89-95` registers
  `src/Resources/ActionEventResource.php` whenever tracking is enabled; authorization falls back to
  "allowed" without a policy (`ResourceAuthorizationService.php:56`).
- **Observed:** with no `ActionEvent` policy (the default), any user who passes the middleware can
  `GET /nadota-api/action-event/resource` (every event of every resource, with `fields`/`original`/`changes`
  snapshots — bypassing the per-record `view` check `ActionEventController` enforces for
  `/{id}/action-events`), `PUT` an event (200 "updated"; the fields are readonly so only `updated_at`
  changes) and `DELETE` it (200, row gone). The resource is only hidden from the menu.
- **Expected:** read-only by design (no store/update/delete), and deny listing unless the app grants it
  (a shipped policy, or `canDelete = false` + an explicit opt-in).
- **Verified:** `tests/Feature/Http/BuiltIn/ActionEventResourceTest.php` (three skipped tests); HTTP probe.
- **Severity / kind:** high · security.
- **Status:** ✅ fixed by `20942f9` (2026-11-17): decided 2026-11-17: `ActionEventResource::authorizedTo()` refuses every ability except `viewAny`, `view`, `viewOptions` and `export`, and those only when the app registers a policy for the `ActionEvent` model. The regression tests are un-skipped.

### AUD-029 — `ActionEventResource` declares properties the base `Resource` never reads
- **Location:** `src/Resources/ActionEventResource.php:45-60`.
- **Observed:** `public static array $search` (the base reads `$searchableAttributes`), `protected bool $softDelete`
  (the base uses `$usesSoftDeletes`) and `public array $with` (the base uses `$withOnIndex`) have no effect:
  the audit log is not searchable.
- **Expected:** use the base property names.
- **Verified:** `ActionEventResourceTest` › "searches the audit log…" (skipped).
- **Severity / kind:** low · bug.

## Configuration and providers

### AUD-030 — Routes read `nadota.middlewares` before the package config is merged
- **Location:** `src/NadotaServiceProvider.php:19-20` registers `RouteServiceProvider` (boots and reads
  `config('nadota.middlewares', [])`, `RouteServiceProvider.php:45`) **before** `ResourceServiceProvider`,
  whose `boot()` calls `mergeConfigFrom()` (`ResourceServiceProvider.php:23-25`).
- **Observed:** an app that did not publish `config/nadota.php` gets the protected routes with **no**
  middleware at all (not even `api`), although `config('nadota.middlewares')` reads `['api']` afterwards.
  `mergeConfigFrom()` belongs in `register()`.
- **Expected:** merge in `register()`, so every reader sees the defaults.
- **Verified:** `tests/Unit/Config/NadotaServiceProviderTest.php` (skipped test); probe.
- **Severity / kind:** medium · bug.
- **Status:** ✅ fixed by `0bb193d` (2026-11-17): `NadotaServiceProvider::register()` merges the package config before any provider reads it. The regression tests are un-skipped.

### AUD-031 — Boot crashes when `path_resources` is missing or absolute
- **Location:** `src/ResourceManager.php:19-24`.
- **Observed:** `Finder::in(base_path($path))` throws `DirectoryNotFoundException` when the directory does
  not exist — a fresh install without `app/Nadota` cannot boot, including `php artisan vendor:publish`.
  An absolute `path_resources` is turned into `<base>/<absolute>` and fails the same way.
- **Expected:** skip a missing directory; accept absolute paths.
- **Verified:** two skipped tests in `tests/Unit/Config/ResourceManagerDiscoveryTest.php` and one in
  `NadotaServiceProviderTest`.
- **Severity / kind:** high · bug.

### AUD-032 — Discovery derives class names from paths and wipes earlier registrations
- **Location:** `src/ResourceManager.php:26-56`.
- **Observed:** the class name is the file path minus `base_path()`, with only the first letter
  upper-cased: it works for `app/Nadota` ↔ `App\Nadota`, and silently registers nothing for any other
  PSR-4 mapping (`modules/Billing` → `Modules\Billing` works only by luck of casing; `src/Admin` mapped to
  `Acme\Admin` never works). No error is raised. `registerResource()` also replaces the registry
  (`static::$resources = collect(...)`), dropping classes registered earlier with `registerResourceClass()`.
- **Expected (decision):** resolve the class from the file (token parse or Composer's class map) or
  configure a namespace; merge instead of replace.
- **Verified:** `ResourceManagerDiscoveryTest` (pinned passing test + skipped test).
- **Severity / kind:** medium · bug, doubt.

### AUD-033 — `key_resources_cache` is checked but never written
- **Location:** `src/Providers/ResourceServiceProvider.php:67-73`.
- **Observed:** in production, when the cache key is missing (always — nothing writes it) the directory
  is scanned, then scanned again unconditionally. `docs/guides/resources.md:49` says discovery "can be
  cached"; `docs/internals/architecture.md:106` already notes the dead branch.
- **Expected:** write/read the class map, or remove the branch and the key.
- **Verified:** `NadotaServiceProviderTest` (skipped test).
- **Severity / kind:** low · bug, contradiction.

### AUD-034 — Documented publish commands publish nothing; provider stub missing
- **Location:** `src/Providers/ResourceServiceProvider.php:49-58`; README.md:41,
  `docs/getting-started/installation.md` steps 2-3, `CLAUDE.md`.
- **Observed:** `publishes()` is called from `ResourceServiceProvider`, so
  `vendor:publish --provider="SchoolAid\Nadota\NadotaServiceProvider" --tag="nadota-config"` finds no
  paths (only `--tag=nadota-config` alone works). The `nadota-provider` tag points at
  `Console/stubs/NadotaServiceProvider.stub`, which does not exist. `CLAUDE.md` uses `--tag="config"`,
  which is not a tag at all.
- **Expected:** register the publishables from `NadotaServiceProvider` (or document the right command)
  and ship the stub.
- **Verified:** two skipped tests + one passing test in `NadotaServiceProviderTest`.
- **Severity / kind:** medium · bug, contradiction.

### AUD-035 — `path` and `namespace` config keys are never read
- **Location:** `config/nadota.php:4-5`; documented in `docs/getting-started/configuration.md` as
  "base path segment used by the panel" / "panel namespace identifier".
- **Observed:** no reader in `src/`.
- **Expected:** remove them, or implement what the docs describe.
- **Verified:** `tests/Unit/Config/ConfigurationKeysTest.php` (skipped test + drift guard).
- **Severity / kind:** low · dead code, contradiction.

### AUD-036 — `excludeResources()` only works if called before the package boots
- **Location:** `src/Providers/ResourceServiceProvider.php:78-82`.
- **Observed:** exclusions are applied once during the package's `boot()`. Package providers boot before
  application providers, so a call from `AppServiceProvider::boot()` has no effect; it must be in
  `register()`. The menu does not consult the list either. No doc says when to call it.
- **Expected (decision):** document "call from `register()`", or apply exclusions lazily when reading the registry.
- **Verified:** `NadotaServiceProviderTest` › "only applies exclusions registered before the package boots" (pinned).
- **Severity / kind:** low · doubt.

### AUD-037 — `configuration.md` is incomplete and partly wrong
- **Location:** `docs/getting-started/configuration.md`.
- **Observed:** `filters.boolean.true_label/false_label` and `fields.multi_image.max_size` (plus the read
  but undeclared `fields.file.max_size` / `fields.image.max_size`) are not documented; a stray sentence
  ("The table name is always `action_events`.") splits the Action Events table; `fields` is described as
  "consumed by the frontend, not by validation" while the backend reads `component` and ignores `type`
  (CORE-007); `path`/`namespace` (AUD-035), `key_resources_cache` (AUD-033) and the three export keys
  (AUD-014) are described as effective; `middlewares` has no warning about authentication (AUD-012);
  versions are stamped `1.1.4`.
- **Expected:** see the corrected table in [`docs/audit/configuration.md`](../../audit/configuration.md).
- **Verified:** comparison with `ConfigurationKeysTest`.
- **Severity / kind:** low · contradiction.

---

## Extension of CORE-007 (not a new ID)

The configuration audit completes CORE-007 (`fields.*` partly unused / partly missing):

- **Declared, never read:** `fields.text` (Input reads `fields.input.component` through `Field::__construct`)
  and every `fields.*.type` (31 entries).
- **Read with a fallback, not declared:** `array`, `code`, `color`, `count`, `date`, `dynamic`, `exists`,
  `hasOneThrough`, `html`, `json`, `rich_text`, `signature`, `time`, `variableText` (`.component`), and
  `fields.file.max_size`, `fields.image.max_size`.
- **Corrections to the recon list:** `lookup` *is* declared (`config/nadota.php:80`); the key read is
  `variableText`, not `variable_text`.
- Every declared component equals the fallback hard-coded in its field class, so publishing the config
  changes nothing unless edited (`ConfigurationKeysTest` › "ships config component defaults identical…").
