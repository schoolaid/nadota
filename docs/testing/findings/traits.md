# Findings — traits (2026-11-02)

Scope: field traits (`src/Http/Fields/Traits/*`), resource/request traits (`src/Http/Traits/*`,
except `ResourceMenuOptions`), `src/Http/Services/Traits/ProcessesFields.php` and
`src/Http/Services/FieldOptions/Traits/*`. IDs use the `TRT-` prefix. Source code was **not** changed.

Every bug below has a regression test that asserts the correct behaviour and is skipped with
`ISSUE TRT-0NN`. "Verified" means the skipped test was run with the skip removed and failed for
the reason given.

## Found here, logged by another area first (not re-logged)

These came up while testing the traits; my skipped tests carry the other area's ID.

| ID | What | Where my tests hit it |
|----|------|-----------------------|
| HTTP-002 | `perPage` reaches `paginate()` unchecked, `allowedPerPage` is only advertised | `tests/Unit/Traits/ResourcePaginationTest.php` |
| HTTP-003 | an invalid `sortDirection` reaches `orderBy()` and throws (500) | `tests/Unit/Fields/Traits/SortableTraitTest.php` |
| HTTP-008 | `ProcessesFields` filters readonly/computed fields but not disabled ones, so a disabled field is validated and written | `FieldDataAccessorsTraitTest.php`, `ProcessesFieldsTest.php` |
| HTTP-009 | `resolveForStore/Update()` use `$value == null`: a submitted `0`/`false` becomes the default; omitted keys get no default | `FieldResolveTraitTest.php`. **Correction to HTTP-009:** the string `"0"` is *not* affected (`"0" == null` is false in PHP 8); only `0`, `false`, `""` and `[]` are. Verified with a dataset. |
| HTTP-001 | Laravel re-merges pagination meta into `IndexResource`, every meta value arrives as `[x, x]` | worked around in `ResourcePaginationTest.php` |
| AUD-011 | `Resource` uses `VisibleWhen` but nothing calls `isVisible()` on a resource | noted in `VisibleWhenTest.php` |
| AUD-016 | `getExportableFields()` evaluates `isShowOnIndex()` without the request | same root cause as TRT-003 |
| FLD-014 / FLD-030 | `Date`/`DateTime::resolve()` ignore `default()`/`displayUsing()`; `displayUsing()` docblock vs behaviour | TRT-005 generalises FLD-014 |
| FLD-011 | `flattenFields()` ignores Section visibility | not duplicated |
| FLT-028 | `getFilterKeys()` uses the attribute, the filters use `key()` | a test pinning the attribute behaviour was removed from `FilterableTraitTest.php` to avoid contradicting FLT-028 |

---

### TRT-001 — Computed (`displayUsing`) fields put their attribute in the SELECT
- **Location:** `BuildsSelectColumns::getSelectColumns()` → `Field::getColumnsForSelect()` (`src/Http/Fields/Field.php:633`).
- **Observed:** `getColumnsForSelect()` only skips `virtual` fields. `Input::make('Shout', 'shout')->displayUsing(fn ($m) => …)`
  (the documented way to add a computed column) returns `['shout']`, so index, show and edit send
  `select "name", "shout", "id" from "fx_students"`. Show/Edit use `flattenFields()` on purpose, so
  even a computed field hidden from every view is selected.
- **Expected:** computed fields (`isComputed()`) contribute no column.
- **Verified:** `BuildsSelectColumnsTest` (3 skipped tests): unit assertion on `getSelectColumns()` and the
  SQL captured from `ResourceShowService` / `ResourceIndexService`. The service calls *succeed on SQLite*
  because SQLite reads an unknown double-quoted identifier as a string literal (`'shout'`); MySQL and
  PostgreSQL fail with "unknown column". This is why the suite never caught it.
- **Severity:** high (500 on index/show/edit on production databases for any computed field). **Kind:** bug.

### TRT-002 — A constrained `Count`/`Exists` breaks `withCount()`/`withExists()`
- **Location:** `ManagesRelationLoading::getWithCountRelations()` / `getWithExistsRelations()` (`:170`, `:198`).
- **Observed:** with `->constraint(fn …)` the method maps each field to `[$relation => $constraint]` and
  then `->values()`, producing `[ ['notes' => Closure] ]`. Eloquent needs `['notes' => Closure]`; with the
  nested array `prepareNestedWithRelationships()` keys it as `0` and `getRelationWithoutConstraints()`
  throws `Error: Method name must be a string`. Unconstrained Count/Exists (plain strings) work.
- **Expected:** a keyed map `relation => constraint` (mixed with plain names) that Eloquent accepts.
- **Verified:** `ManagesRelationLoadingTest` — 2 skipped tests, both throw the error above.
- **Severity:** high (every resource using `Count::constraint()` / `Exists::constraint()` fails on index and show). **Kind:** bug.

### TRT-003 — Section layout (and `/fields`, `/config` field lists) evaluate visibility without the request
- **Location:** `ManagesFieldVisibility::getSectionsLayout()` (`:182`, `:188`, `:207`) calls
  `$field->isShowOnDetail()` etc. with no arguments; the same pattern is in
  `ResourceIndexController::fields()`/`config()` (`:46`, `:110`) and `ResourceExportable::getExportableFields()` (AUD-016).
- **Observed:** a `showWhen(fn ($request) => $request?->user()->isAdmin())` callback receives `null`
  there, while `fieldsForIndex/Show/Form()` pass the request. The `/config` layout and field list can
  disagree with what `/index`, `/show` and the forms return. A callback that does not null-guard
  (`$request->user()`) is a 500 on `/config`.
- **Expected:** pass `$request` (and `null` record) like the other field lists do.
- **Verified:** `ManagesFieldVisibilityTest` › "hands the request to visibility callbacks…" (skipped; fails with an empty layout).
- **Severity:** medium. **Kind:** bug. Same class of bug as the one fixed for persist in `VisibilityCallbackPersistTest`.

### TRT-004 — A string that names a PHP function is called instead of used
- **Location:** `DefaultValueTrait::resolveDefault()` (`:68`) and `ValidationTrait::rules()` (`:17`) test `is_callable()` on the value.
- **Observed:** `->default('date')`, `'time'`, `'file'`, `'max'` call that function with `($request, $item, $resource)`
  (`ArgumentCountError` / `ErrorException`); `->rules('date')` and `->rules('file')` call `date()` / `file()`
  with no arguments (`ArgumentCountError`). `'date'` and `'file'` are common Laravel rule names and
  plausible select defaults.
- **Expected:** only `Closure` (or invokable objects) are treated as callbacks; strings are literals.
- **Verified:** `DefaultValueTraitTest` (dataset of 4, skipped), `ValidationTraitTest` (dataset of 2, skipped).
- **Severity:** medium (500 at form render / boot of the field). **Kind:** bug.

### TRT-005 — `resolve()` overrides drop the trait's display callback and default
- **Location:** `FieldResolveTrait::resolve()` defines the contract "display callback first, then the column,
  then the default". `Select`, `Status`, `Toggle`, `Checkbox`, `Date`, `DateTime`, `Time`, `Json`
  (and `CheckboxList`, `MultiImage`, `Password`, `Lookup`, `DynamicField`) replace `resolve()` without
  calling the parent or checking `hasDisplayCallback()`/`hasDefault()`.
- **Observed:** `Select::make(...)->displayUsing(fn () => 'computed')` resolves the raw column; `->default('draft')`
  on a Select shows `null` on detail for a null column. `displayUsing()` still marks the field computed, so
  it is not filled either — the field silently shows raw data.
- **Expected:** every field honours `displayUsing()` and the default fallback (e.g. a shared
  `resolveRaw()` hook the overrides implement).
- **Verified:** `FieldResolveTraitTest` › "fields that override resolve()" — 9 skipped dataset rows, all fail.
- **Severity:** medium. **Kind:** bug / contract. FLD-014 reports the Date/DateTime instances.

### TRT-006 — Update persist evaluates record-dependent visibility without the record
- **Location:** `ProcessesFields::filterFieldsForUpdate()` (`:49`) calls `isShowOnUpdate($request)`.
- **Observed:** the edit form uses `fieldsForForm($request, $model)`, so
  `showOnUpdate(fn ($request, $model) => $model?->is_active)` shows the field for an active record, but on
  submit the callback sees `null`, the field is dropped and the edited value is silently not saved.
  (The request-side version of this bug was fixed and is covered by the first tests of the same file.)
- **Expected:** pass the loaded model (the service has it before filtering).
- **Verified:** `tests/ServiceIntegration/VisibilityCallbackPersistTest.php` › "persists a field whose showOnUpdate
  callback depends on the record" (skipped; `email` stays null). A companion passing test checks the hidden case stays unwritten.
- **Severity:** medium (silent data loss). **Kind:** bug.

### TRT-007 — `requiredIf()` / `requiredUnless()` serialise booleans so Laravel never matches them
- **Location:** `ValidationTrait::requiredIf()` / `requiredUnless()` (`:108`, `:114`) interpolate the value.
- **Observed:** `requiredIf('is_business', true)` → `required_if:is_business,1`. For a JSON boolean `true`
  Laravel compares strictly (`in_array(true, ['1'], true)`), so the field is never required.
  `requiredIf('approved', false)` → `required_if:approved,` (empty value).
- **Expected:** booleans rendered as `true`/`false` (Laravel converts those for boolean inputs).
- **Verified:** `ValidationTraitTest` — 2 skipped tests running a real `Validator`.
- **Severity:** medium (validation silently not applied). **Kind:** bug.

### TRT-008 — `isRequired()` without context ignores a `required` added through `rules()`
- **Location:** `ValidationTrait::isRequired()` (`:194`).
- **Observed:** `->rules(['required'])` → `isRequired()` false and `toArray()['required']` false on
  index/show/no-action requests, but `isRequired(false)` (create context) true. The frontend flag
  depends on whether `?action=` was sent.
- **Expected:** the base check also reads the rules (as the context check does).
- **Verified:** `ValidationTraitTest` › "reports required when the rule was added through rules()" (skipped).
- **Severity:** low. **Kind:** bug / inconsistency.

### TRT-009 — A field cannot customise its own sorting
- **Location:** `ApplySortingPipe::handle()` (`:30`) calls `$field->applySorting()` only when `isRelationship()`;
  plain fields get `orderBy($attribute)` directly. `SortableTrait::applySorting()` is therefore only reachable through relation fields (which all override it).
- **Observed:** a custom (non-relation) field overriding `applySorting()` is ignored.
- **Expected:** the pipe always delegates to `applySorting()` (the trait's default already does `orderBy(attribute)`).
- **Verified:** `SortableTraitTest` › "lets a custom field decide how it is sorted" (skipped).
- **Severity:** low. **Kind:** design gap.

### TRT-010 — `dependsOn()` can serialise `fields` as a JSON object
- **Location:** `DependsOnTrait::dependsOn()` (`:37`) uses `array_unique()` (keeps keys); `DependencyDTO::toArray()` emits it as is.
- **Observed:** `dependsOn('a')->dependsOn(['b', 'a', 'c'])` → `[0 => 'a', 1 => 'b', 3 => 'c']` →
  `{"0":"a","1":"b","3":"c"}` in the API. Any frontend iterating an array breaks.
- **Expected:** `array_values(array_unique(...))`, always a JSON list.
- **Verified:** `DependsOnTraitTest` › "serialises the dependency fields as a JSON list…" (skipped).
- **Severity:** low/medium. **Kind:** bug.

### TRT-011 — `optionsFromEndpoint()` after `cascadeFrom()` drops `cascadeFrom`
- **Location:** `DependencyDTO::setOptionsConfig()` (`:133`) replaces the whole `options` array; `setCascadeFrom()` merges.
- **Observed:** the result depends on call order: `cascadeFrom()->optionsFromEndpoint()` loses `cascadeFrom`,
  the reverse order keeps both.
- **Expected:** both setters merge into `options`.
- **Verified:** `DependsOnTraitTest` (skipped test + a passing test for the other order).
- **Severity:** low. **Kind:** bug.

### TRT-012 — Field-level search flags are not read by any search
- **Location:** `SearchableTrait` (`searchable()`, `searchableGlobally()`, `searchWeight()`); consumers:
  `ApplySearchPipe`, `SearchesOptions`, `FieldOptionsService`, `ResourceOptionsService` all read the
  resource's `$searchableAttributes` / `$searchableRelations` only. `tests/Resources/RelatedModelResource.php` already hints at it.
- **Observed:**
  - `Input::make('Name')->searchable()` alone: the index ignores `?globalSearch=` (every row comes back) and
    `/info` reports `search.enabled = false`; the field still reports `searchable: true` to the frontend.
  - `isSearchableGlobally()` and `getSearchWeight()` have no caller in `src/`.
  - `searchable()` takes no parameters; the old `SearchableTraitTest` passed a closure to it, which PHP
    silently drops — the test read as "custom search callbacks are supported" (rewritten).
  - `notSearchable()` clears `searchable` but leaves `searchableGlobally` true.
- **Expected:** a decision: either fields feed the resource search (e.g. default `getSearchableAttributes()`
  from searchable fields) or the field flags are removed/documented as frontend-only.
- **Verified:** `ResourceSearchableTest` (2 passing tests pin today's behaviour), `SearchableTraitTest`.
- **Severity:** medium (configuration that looks right and does nothing). **Kind:** contradiction.

### TRT-013 — "Nothing searchable configured" behaves three different ways, none as documented
- **Location:** `ResourceSearchable` docblock ("falls back to `['name', 'title', 'label', 'display_name', 'full_name', 'description']`");
  `ApplySearchPipe`; `SearchesOptions::applyResourceSearch()`; `FieldOptionsService::applySearch()` (`:381`); `ResourceOptionsService::applySearch()` (`:110`).
- **Observed:**
  - index (`ApplySearchPipe`): the term is ignored, every row returned;
  - field options through the strategies (`SearchesOptions`): an empty `where(fn)` group, every row matches;
  - `FieldOptionsService::applySearch()` and global `/options` (`ResourceOptionsService`): fall back to
    all six `FALLBACK_SEARCH_ATTRIBUTES` with `orWhere`, which on MySQL/PostgreSQL fails with "unknown column"
    unless the table has every one of them (SQLite again masks it by treating `"title"` as a literal).
- **Expected:** one rule, matching the docblock, and limited to columns that exist (or no fallback and a
  clear "search disabled").
- **Verified:** `ResourceSearchableTest` › "falls back to the common display attributes…" (skipped),
  `SearchesOptionsTest` › "still filters by the term…" (skipped); the fallback SQL is asserted by a passing test.
- **Severity:** medium. **Kind:** contradiction / bug.

### TRT-014 — `VisibleWhen::isVisible()` throws on a null callback result
- **Location:** `src/Http/Traits/VisibleWhen.php:23` returns the raw callback result from a `: bool` method.
- **Observed:** `canSee(fn ($request) => $request->user()?->isAdmin())` for a guest returns `null` →
  `TypeError` (return types are not coerced from null even in non-strict mode). Affects menu items/sections
  (the only callers, see AUD-011 for resources).
- **Expected:** `(bool)` cast, like `VisibilityTrait::evaluateVisibility()` does.
- **Verified:** `VisibleWhenTest` › "treats a null callback result as hidden" (skipped, `TypeError`).
- **Severity:** low/medium (500 on `/menu`). **Kind:** bug.

### TRT-015 — The default sort is ignored when its field is hidden from the index (doubt)
- **Location:** `ApplySortingPipe::applyDefaultSort()` looks the field up in `fieldsForIndex()` only.
- **Observed:** `$defaultSort = ['field' => 'age']` with `age` `->hideFromIndex()` silently falls back to
  `created_at desc`. A default sort on a column the list does not show is a normal need.
- **Expected:** decide; at least fall back to ordering by the column when it is a real attribute.
- **Verified:** `SortableTraitTest` › "ignores a default sort on a field hidden from the index" (passing, pins today's behaviour).
- **Severity:** low. **Kind:** doubt.

### TRT-016 — A hidden section splits the loose fields around it into two groups (doubt)
- **Location:** `ManagesFieldVisibility::getStructuredFieldsFilteredByVisibility()` / `getSectionsLayout()` flush
  the loose-field buffer before checking the section's visibility.
- **Observed:** `[A, Section(hidden), C]` → two `default` groups `[A]`, `[C]` instead of one `[A, C]`.
- **Expected:** probably one group; harmless if the frontend renders default groups seamlessly.
- **Verified:** `ManagesFieldVisibilityTest` (passing, pins today's behaviour).
- **Severity:** low. **Kind:** doubt.

### TRT-017 — `apiUrl()`/`frontendUrl()` are the only URLs without a leading slash (doubt)
- **Location:** `ResourceFrontUtils` (`nadota-api/student/resource`), versus `RelationshipTrait::getOptionsUrl()`,
  `Resource::buildActionEventsUrl()` and `ResourceExportable::getExportConfig()` (`/nadota-api/...`).
- **Expected:** one convention. (The config-key mismatch itself is CORE-001.)
- **Verified:** `ResourceFrontUtilsTest` (passing, pins today's behaviour).
- **Severity:** low. **Kind:** inconsistency.

### TRT-018 — MorphTo validation: keyed rules and context rules never apply
- **Location:** `ValidationTrait::uniqueRules()` (`:176`) rebuilds the rule list from values, dropping
  string keys; `ProcessesFields::addMorphToRules()` (`:122`) reads `getRules()`, not `getRulesFor()`.
- **Observed:**
  - `MorphTo::make(...)->rules(['notable_type' => 'required|in:student,grade', 'notable_id' => 'required|integer'])`:
    `getRules()` returns `['required|in:student,grade', 'required|integer']`, so the keyed branch of
    `addMorphToRules()` is dead and **both** strings are applied to `notable_id` — a numeric id then fails
    `in:student,grade` and the record cannot be saved.
  - `creationRules()` / `updateRules()` on a MorphTo are ignored.
- **Expected:** keyed MorphTo rules reach `{type}`/`{id}`; context rules apply as for every other field.
- **Verified:** `ProcessesFieldsTest` — 2 skipped tests.
- **Severity:** medium. **Kind:** bug.

### TRT-019 — Dead or one-way state in the traits (low)
- `ResourcePagination::$page`, `setPage()`, `getPagination()`, `setPagination()` — never read; the page comes
  from Laravel's paginator. `setPagination()` also throws on a missing key.
- `ResourceRelatable::$with` (public) — never read; eager loading uses `$withOnIndex` / `$withOnShow`.
- `FilterableTrait::$filterableType` — written by `filterable()`, never read (and not set by `filterableRange()`).
- `RelationshipTrait::applyInIndexQuery()` / `applyInShowQuery()` can only set `true`; there is no way to take a
  field out of a query (only subclasses overriding the property can).
- **Verified:** grep over `src/`; `ResourceRelatableTest`, `RelationshipTraitTest`, `ResourcePaginationTest`.
- **Severity:** low. **Kind:** cleanup / doubt.

### TRT-020 — `TracksActionEvents::trackUpdate()` has an implicitly nullable parameter
- **Location:** `src/Http/Traits/TracksActionEvents.php:44` `array $originalData = null`.
- Same PHP 8.4 deprecation as CORE-010 (fatal in PHP 9). Not testable at runtime (Reflection reports `?array` either way).
- **Severity:** low. **Kind:** compatibility.

### TRT-021 — `FieldDataAccessorsTrait::__get()` exposes every protected property
- **Location:** `src/Http/Fields/Traits/FieldDataAccessorsTrait.php:185`, documented as "for accessing protected properties in tests".
- **Observed:** production fields answer `$field->rules`, `$field->defaultValue`, `$field->displayCallback`…;
  there is no matching `__isset()`, so `isset($field->helpText)` is false while `$field->helpText` returns a value.
- **Expected:** remove it, or restrict it to `fieldData`.
- **Verified:** `FieldDataAccessorsTraitTest` › "reads protected properties through the magic getter…".
- **Severity:** low. **Kind:** design.

### TRT-022 — Test-suite quality issues found in the audited files
- `tests/ServiceIntegration/DefaultValueTraitIntegrationTest.php` was a verbatim copy of
  `tests/Unit/Fields/Traits/DefaultValueTraitTest.php` (9 of 10 tests identical, no service, no DB).
  **Fixed:** rewritten as a real integration test (create/edit/show/store services on the fixture domain);
  its one unique test moved to the unit file.
- `SearchableTraitTest` › "can set search callback" suggested an API that does not exist (TRT-012). **Fixed.**
- `SearchableTraitTest` › "can be globally searchable" never asserted `isSearchableGlobally()`. **Fixed.**
- `tests/Unit/Export/ResourceExportableTest.php` (audit only, not modified):
  - "excludes hasOne fields not marked exportable" passes because `HasOne` is hidden from the index by
    default, not because `isFieldExportable()` excludes it — `isFieldExportable()` explicitly *allows* HasOne.
    A HasOne made visible on index is exported without `exportable()`. The test name states a rule the code does not have.
  - it declares a named global class `TestExportableResource` (convention 3) and lives in `tests/Unit/Export/`
    instead of mirroring `src/Http/Traits/` (`tests/Unit/Traits/ResourceExportableTest.php`).
- **Kind:** test quality.

### TRT-023 — `attachableQuery()` is ignored for pivot relations (doubt, attachments area)
- **Location:** `ManagesAttachments::getAttachableQueryCallback()` is read by `HasManyAttachmentService` and
  `MorphManyAttachmentService` only; `BelongsToManyAttachmentService` (also used for MorphToMany/MorphedByMany)
  applies `optionsQuery()` but never the field's `attachableQuery()`.
- Also: `BelongsToMany`/`HasMany`/`MorphMany` expose `props.attachment`, `MorphToMany`/`MorphedByMany` do not (see REL-012 for their URLs).
- **Verified:** code reading + `ManagesAttachmentsTest` (props exposure). Not tested end to end here; for the attachments owner to confirm.
- **Severity:** medium if confirmed (the documented scope of attachable records is not applied). **Kind:** doubt.

---

## Suite infrastructure note
The full suite now dies at PHP's default `memory_limit` (128M) inside `zipstream-php` during an export test,
in two random-order runs. Each export test file passes on its own, and the whole suite passes with
`php -d memory_limit=1G vendor/bin/pest` (2576 passed, 326 skipped). Memory grows across the ~2,900 tests
of the run; nothing in the trait tests holds static state between tests. Suggest
`<ini name="memory_limit" value="512M"/>` in `phpunit.xml` or finding the retained references.
