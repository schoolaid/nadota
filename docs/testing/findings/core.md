# Findings — core / infrastructure (recon, 2026-11-02)

Found while reading the package before writing tests. IDs use the `CORE-` prefix.

### CORE-001 — Three different config keys for the API prefix
- `src/Providers/RouteServiceProvider.php:32,42` registers the routes with `config('nadota.prefix', 'nadota-api')`.
- `config/nadota.php` declares the prefix as `api.prefix`, which is what `Resource.php:481`,
  `ResourceController::permissions()`, `DynamicSelectFilter`, `RelationFilter`, `HasManyThrough`
  and `MorphToMany` read to *build URLs*.
- `src/Http/Traits/ResourceExportable.php:216` reads a third key, `nadota.api_prefix`.
- Effect: changing `api.prefix` in the published config changes the URLs the API hands to the
  frontend but **not** the URLs the routes are mounted on (and not the export URL). The three only
  agree while everybody keeps the default.
- Severity: medium (silent breakage on customisation).

### CORE-002 — `/lens` route points to a method that does not exist
- `routes/api.php` declares `GET /{resourceKey}/resource/lens` → `ResourceIndexController::lens`.
- `ResourceIndexController` has no `lens()` method → any call is a 500 (`BadMethodCallException`).
- Severity: low (dead route), but it is reachable.

### CORE-003 — `permissions` endpoint types `$id` as `int`
- `ResourceController::permissions(NadotaRequest $request, string $resourceKey, int $id)`.
- `routes/api.php` deliberately leaves `{id}` unconstrained so string/UUID keys work
  (see `tests/Feature/RelationRouteStringIdTest.php`), and the route test already matches
  `/antiguainternationalschool/permissions`. The controller then throws a `TypeError` for any
  non-numeric id (strict_types is off, but `"abc"` cannot be coerced to int).
- Severity: medium for string-keyed resources.

### CORE-004 — `/data` (compact) bypasses `getQuery()`/`queryIndex()` and authorization scoping
- `ResourceIndexController::compact()` builds `$resource->model::query()` directly and returns
  `->get()` of **every row**, unpaginated. Any tenant/ownership scoping a resource adds in
  `getQuery()` or `queryIndex()` is ignored here; only `viewAny` is checked.
- It also filters columns with `! $field->isAppliedInIndexQuery()` — the naming suggests the
  opposite condition (to be confirmed by the HTTP tests).
- Marked `TODO: New service` in the code.
- Severity: high if a resource relies on `getQuery()` for data isolation.
- **Status:** ✅ fixed by `ac69d7f` (2026-11-17): `compact()` builds on `getQuery()` + `queryIndex()` like the index and falls back to the key alone, never to every column. The regression tests are un-skipped.

### CORE-005 — `ResourceManager::getModelByResource()` cannot work
- Calls `array_search($resource, $resources)` on a `Collection` of `['class' => …, 'model' => …]`
  arrays; `array_search()` needs an array and would compare a string against arrays anyway.
  `abort_if(is_null(...))` never fires because `array_search` returns `false`, not `null`.
- No caller in `src/` (dead code).
- Severity: low (dead code).

### CORE-006 — Default resource title keeps the word "Resource"
- `Resource::title()` = `Str::plural(Str::title(Str::snake(class_basename(static::class), ' ')))`.
  For `StudentResource` that yields **"Student Resources"**, which is what `/menu`, `/info` and
  `/config` show unless the resource sets `$title`. `Helpers::toUri()` strips `-resources` for
  the key, so the key and the title disagree on the convention.
- Severity: low (cosmetic, but visible in every menu).

### CORE-007 — `config/nadota.php` `fields.*` entries are partly unused and partly missing
- Every field reads only `nadota.fields.<name>.component`; the `type` value in the config is never
  read — types come from the `FieldType` enum. E.g. config says `toggle.type = 'toggle'` but
  `Toggle` reports `FieldType::BOOLEAN` (`'boolean'`); config says `checkboxList.type = 'checkboxList'`
  but the enum value is `'checkbox_list'`.
- Fields that read a config key the default config does not declare (they fall back to a hard-coded
  component): `date`, `time`, `color`, `code`, `json`, `html`, `rich_text`, `array`, `count`,
  `exists`, `signature`, `dynamic`, `variable_text`, `lookup`, `hasOneThrough` (to be completed by the
  configuration audit).
- Severity: low (documentation/consistency).

### CORE-008 — Several relation fields report another relation's `type`
- `HasManyThrough` reports `FieldType::HAS_MANY`; `HasOneThrough` reports `HAS_ONE`;
  `MorphToMany` and `MorphedByMany` report `BELONGS_TO_MANY`. Only the component differs. The
  frontend therefore cannot tell them apart by `type`. May be intentional (same UI); flagged so it
  is a decision, not an accident.

### CORE-009 — `tests/ServiceIntegration/README.md` is stale
- Says those tests "should be run in a full Laravel application context". They run (and pass) under
  Testbench with the rest of the suite.

### CORE-010 — PHP 8.4 implicit-nullable parameters in filters
- `Filter::__construct(string $name = null, string $field = null, …)` and every subclass use
  implicitly nullable typed parameters, deprecated since PHP 8.4 (the dev machine runs 8.4.27).
  Not reported today because the deprecation is emitted at compile time of the class, but it will
  show up as soon as deprecations are surfaced (`failOnDeprecation`) and is a fatal error in PHP 9.
