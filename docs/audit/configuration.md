# Audit — Configuration and service providers

Functional audit of `config/nadota.php` and of what the package registers at boot (Nadota 1.3.0,
2026-11-02). For every key: declared default, who reads it, real effect, and whether
[`docs/getting-started/configuration.md`](../getting-started/configuration.md) documents it correctly.
Issues are described in full in [`docs/testing/findings/audit.md`](../testing/findings/audit.md).
This audit extends CORE-001 (prefix keys) and CORE-007 (`fields.*`) from
[`findings/core.md`](../testing/findings/core.md).

The key inventory below is enforced by `tests/Unit/Config/ConfigurationKeysTest.php`: it scans `src/`
for `config()/safeConfig()/Config::get()` calls on `nadota.*` and fails when a new key is declared but
not read, or read but not declared, beyond the gaps listed here.

## How the config reaches the code

- `ResourceServiceProvider::boot()` calls `mergeConfigFrom(config/nadota.php, 'nadota')` unless the
  configuration is cached (`src/Providers/ResourceServiceProvider.php:23-25`).
- The merge is **shallow** (Laravel's `array_merge` on the top-level keys): a published file that
  contains `'export' => ['formats' => ['csv']]` loses the package's `export.default_format`, `sync_limit`…
  for that block; readers fall back to their hard-coded defaults. Verified in
  `NadotaServiceProviderTest` › "merges the package defaults under the keys the application did not set".
- The merge runs in `boot()`, **after** `RouteServiceProvider::boot()` has read `nadota.middlewares`
  (AUD-030).

## Key inventory

Legend for the last column: **yes** = documented and accurate; **partial** = documented, missing an
important caveat; **contradicts** = documented with an effect the code does not have; **no** = not documented.

### Core

| Key | Default | Read by | Real effect | Doc |
|-----|---------|---------|-------------|-----|
| `path` | `'said'` | — | none (AUD-035) | contradicts ("base path segment used by the panel") |
| `namespace` | `'said'` | — | none (AUD-035) | contradicts ("panel namespace identifier") |
| `key_resources_cache` | `'said_nadota_class_file_map'` | `Providers/ResourceServiceProvider.php:68` (`Cache::has` only) | in production with the key absent (always — never written) the directory is scanned twice (AUD-033) | contradicts ("cache key … used in production") |
| `path_resources` | `'app/Nadota'` | `Providers/ResourceServiceProvider.php:65` | directory scanned for `*Resource.php`, **relative to `base_path()`**, must exist (AUD-031), class names derived from the path (AUD-032) | partial |
| `middlewares` | `['api']` | `Providers/RouteServiceProvider.php:45` | middleware of the protected route group. No authentication by default (AUD-012); ignored when the config is not published (AUD-030). Public `/options` always uses `['api']` | partial |
| `prefix` *(undeclared)* | — | `Providers/RouteServiceProvider.php:32, 42` (default `'nadota-api'`) | the prefix the routes are actually mounted on (CORE-001) | no |

### Filters

| Key | Default | Read by | Real effect | Doc |
|-----|---------|---------|-------------|-----|
| `filters.boolean.true_label` | `'Sí'` | `Http/Filters/BooleanFilter.php:62` (as `"nadota.filters.boolean.{$key}"`) | label of the true option of boolean/checkbox filters | no |
| `filters.boolean.false_label` | `'No'` | same | label of the false option | no |

### Fields

Every field reads only `nadota.fields.<name>.component` (through `Field::safeConfig()`, which returns
the fallback when the container is unavailable). `type` values are never read; the `type` sent to the
frontend comes from the `FieldType` enum (CORE-007).

| Key | Default | Read by | Real effect | Doc |
|-----|---------|---------|-------------|-----|
| `fields.<name>.type` (31 entries) | e.g. `'toggle'` | — | none (CORE-007) | contradicts ("maps each field type") |
| `fields.text.component` | `'FieldText'` | — | none: `Input` has no constructor and `Field::__construct` reads `fields.input.component` (CORE-007) | contradicts |
| `fields.input.component` | `'FieldText'` | `Http/Fields/Field.php:140` | component of `Input` and of any field without its own key | partial |
| `fields.{number,currency,email,url,password,textarea,hidden,datetime,checkbox,toggle,select,lookup,radio,checkboxList,status,keyvalue,file,image,multi_image}.component` | `Field<Name>` | the matching class in `src/Http/Fields/` | component name sent in the field payload | partial ("consumed by the frontend, not by validation") |
| `fields.{belongsTo,hasOne,hasMany,belongsToMany,hasManyThrough,morphTo,morphMany,morphOne,morphToMany,morphedByMany}.component` | `Field<Name>` | the matching class in `src/Http/Fields/Relations/` | same | partial |
| `fields.multi_image.max_size` | `5 * 1024 * 1024` | `Http/Fields/MultiImage.php:32` | default max upload size per image | no |
| `fields.file.max_size` *(undeclared)* | — | `Http/Fields/File.php:52` (fallback 10 MB) | default max upload size | no |
| `fields.image.max_size` *(undeclared)* | — | `Http/Fields/Image.php:35` (fallback 5 MB) | default max upload size | no |
| `fields.{array,code,color,count,date,dynamic,exists,hasOneThrough,html,json,rich_text,signature,time,variableText}.component` *(undeclared)* | — | the matching classes (fallback `Field<Name>`) | component name; customisable only by adding the key yourself | no |

Every declared component equals the fallback in its class, so publishing the file changes nothing until
it is edited (`ConfigurationKeysTest` › "ships config component defaults identical…").

### API and frontend prefixes

| Key | Default | Read by | Real effect | Doc |
|-----|---------|---------|-------------|-----|
| `api.prefix` | `'nadota-api'` | `Resource.php:481`; `Http/Controllers/ResourceController.php:89`; `Http/Fields/Traits/ResourceFrontUtils.php:9` (no default); `Http/Fields/Traits/RelationshipTrait.php:155`; `Http/Filters/DynamicSelectFilter.php:269, 279, 288`; `Http/Filters/RelationFilter.php:87`; relation fields `BelongsToMany.php:668`, `HasMany.php:510, 640`, `HasManyThrough.php:374`, `HasOne.php:290`, `MorphedByMany.php:401`, `MorphMany.php:471`, `MorphOne.php:297`, `MorphTo.php:447, 465, 634`, `MorphToMany.php:515` | prefix of every URL handed to the frontend (menu `apiUrl`, relation/option/action-event URLs). **Not** the prefix the routes are mounted on (CORE-001) | contradicts ("all API endpoints … are served under `nadota-api`" via this key) |
| `api_prefix` *(undeclared)* | — | `Http/Traits/ResourceExportable.php:216` | prefix of the export URL (CORE-001) | no |
| `frontend.prefix` | `'resources'` | `ResourceFrontUtils.php:14` (no default); `BelongsToMany.php:695`, `HasMany.php:511`, `HasOne.php:291`, `MorphMany.php:472`, `MorphOne.php:298` | prefix of `frontendUrl` in the menu and of the SPA links of relation items | yes |

### Action events

| Key | Default | Read by | Real effect | Doc |
|-----|---------|---------|-------------|-----|
| `action_events.enabled` | `env('NADOTA_TRACK_ACTIONS', true)` | `Providers/ResourceServiceProvider.php:42` (migrations), `:92` (registers `ActionEventResource`); `Http/Traits/TracksActionEvents.php:121` (CRUD services) | switches migrations, the built-in resource and CRUD logging. **Not** action runs (AUD-024) | partial ("master switch") |
| `action_events.system_user_id` | `env('NADOTA_SYSTEM_USER_ID', null)` | `Http/Services/ActionEventService.php:57` | `user_id` when nobody is authenticated. From `.env` it is a string, coerced to `int` by the `?int` return type (a non-numeric value throws) | yes |
| `action_events.exclude_fields` | 7 sensitive keys | `ActionEventService.php:319` (cached per service instance) | keys stripped from `fields`/`original`/`changes` | yes |
| `action_events.track_fields` / `track_original` / `track_changes` | `true` | `ActionEventService.php:310` (as `"…track_{$column}"`) | a disabled column is stored empty | yes |
| `action_events.dispatch_events` | `env('NADOTA_DISPATCH_ACTION_EVENTS', true)` | `ActionEventService.php:292`; `Jobs/LogActionEvent.php:45` | fire `ActionLogged` | yes |
| `action_events.queue` | `env('NADOTA_ACTION_EVENTS_QUEUE', false)` | `ActionEventService.php:302` | write rows from the `LogActionEvent` job | yes |
| `action_events.queue_name` | `env('NADOTA_ACTION_EVENTS_QUEUE_NAME', 'default')` | `ActionEventService.php:277` | queue of that job | yes |

### Export

| Key | Default | Read by | Real effect | Doc |
|-----|---------|---------|-------------|-----|
| `export.enabled` | `env('NADOTA_EXPORT_ENABLED', true)` | — | none (AUD-014) | contradicts ("global export switch") |
| `export.formats` | `['excel', 'csv']` | `Http/Traits/ResourceExportable.php:48` | allowed formats when the resource does not set `$allowedExportFormats` | yes |
| `export.default_format` | `'excel'` | `Http/Services/ResourceExportService.php:40`; `Http/DataTransferObjects/ExportRequestDTO.php:23` | format when the request names none (not intersected with the resource formats) | yes |
| `export.sync_limit` | `env('NADOTA_EXPORT_SYNC_LIMIT', 1000)` | — | none; `syncLimit` comes from `$syncExportLimit` (AUD-014) | contradicts ("surfaced in the export config payload") |
| `export.chunk_size` | `env('NADOTA_EXPORT_CHUNK_SIZE', 500)` | — | none; hard-coded 500 (AUD-014) | contradicts ("chunk size used when iterating") |

## Service providers

```mermaid
flowchart LR
    A[Package discovery<br/>composer.json extra.laravel.providers] --> B[NadotaServiceProvider]
    B -- register --> C[ServiceBindingServiceProvider::register<br/>contract → service singletons]
    B -- boot, 1st --> D[RouteServiceProvider::boot<br/>reads nadota.prefix, nadota.middlewares]
    B -- boot, 2nd --> E[ResourceServiceProvider::boot]
    E --> E1[console: publishes nadota-config, nadota-provider]
    E --> E2["mergeConfigFrom (after D, AUD-030)"]
    E --> E3[loadMigrationsFrom database/migrations<br/>if action_events.enabled]
    E --> E4[registerResources]
    E4 --> F1["production && !Cache::has(key) → scan (never cached, AUD-033)"]
    E4 --> F2[ResourceManager::registerResource(path_resources) — always]
    E4 --> F3[registerResourceClass(ActionEventResource) if enabled]
    E4 --> F4[removeResourcesByClass(NadotaService::getExcludedResources())]
```

### `ServiceBindingServiceProvider`

Singletons: `ResourceAuthorizationInterface → ResourceAuthorizationService`, `MenuServiceInterface →
MenuService`, and the Index/Create/Store/Show/Edit/Update/Destroy/ForceDelete/Restore/Export contracts →
their services; plus `ActionEventService` (shared batch id per process) and `RelationIndexService`.
`ActionExecutionService` is not bound (auto-resolved). Note: the authorization singleton is stateful
(`setModel()` then `authorizedTo()`), and every `Resource` keeps the instance it received in its
constructor — safe in a synchronous request, fragile if two checks ever interleave.

### `RouteServiceProvider`

- Public group: `routes/public.php` (`GET /options`), prefix `nadota.prefix` (default `nadota-api`),
  middleware `['api']`, names `nadota.api.*`.
- Protected group: `routes/api.php`, same prefix and names, middleware `nadota.middlewares`.
- Both exclude `SubstituteBindings`.

### `ResourceServiceProvider`

- **Publishing** (console only): tag `nadota-config` → `config_path('nadota.php')`; tag `nadota-provider`
  → `app_path('Providers/NadotaServiceProvider.php')` from `Console/stubs/NadotaServiceProvider.stub`,
  which does not exist. Because `publishes()` is called here, `--provider="SchoolAid\Nadota\NadotaServiceProvider"`
  (README, installation guide) finds nothing; `--tag=nadota-config` alone works; `CLAUDE.md`'s
  `--tag="config"` is not a tag (AUD-034).
- **Migrations**: `database/migrations` (create `action_events`, fix `exception` column, make `user_id`
  nullable) loaded only while `action_events.enabled` is true. Disabling tracking after migrating leaves
  the table in place.
- **Resource discovery** (`src/ResourceManager.php:17-58`):
  1. `Finder` over `base_path($path)` for `*Resource.php`, recursively — throws when the directory is
     missing; absolute paths are prefixed with `base_path()` (AUD-031).
  2. Class name = path minus `base_path()`, slashes → backslashes, `ucfirst` — only `app/…` ↔ `App\…`
     style mappings work; others are skipped silently (AUD-032).
  3. Kept when `is_subclass_of(Resource::class)`; key `Helpers::toUri()` (singular, `-resources` stripped);
     duplicate key → `Exception`; missing `$model` → `Exception`.
  4. The registry is **replaced** by the scan result (AUD-032), then the built-in resource is added and
     exclusions applied.
- **Exclusions**: `NadotaService::excludeResources()` must run before this boot — from an application
  provider's `register()` (AUD-036).

## Test coverage

| Behaviour | Test |
|-----------|------|
| Declared vs read keys (drift guards), component defaults = fallbacks, boolean labels declared | `tests/Unit/Config/ConfigurationKeysTest.php` |
| Gaps: export keys (AUD-014), `path`/`namespace` (AUD-035), `fields.*` (CORE-007), prefix keys (CORE-001) | same file, skipped |
| Shallow merge, route prefix, migrations + built-in resource per `action_events.enabled`, exclusions before/after boot, `nadota-config` tag | `tests/Unit/Config/NadotaServiceProviderTest.php` |
| Middleware before merge (AUD-030), publishing (AUD-034), production cache (AUD-033), missing directory (AUD-031) | same file, skipped |
| Discovery: namespace mirrors path, subdirectories, non-matching files/classes, mismatched namespace (pinned), duplicate key, missing model, `registerResourceClass`, `removeResourcesByClass`, 404 key | `tests/Unit/Config/ResourceManagerDiscoveryTest.php` |
| Missing / absolute path (AUD-031), registry replaced (AUD-032) | same file, skipped |

The discovery tests build a temporary `app/Nadota` tree in the system temp dir and point `base_path()`
at it; `NadotaServiceProviderTest` boots the real provider after replacing the `nadota` config.

## Corrected reference for `configuration.md`

Proposed content once the issues are decided (not applied — docs under `docs/getting-started/` are owned
elsewhere):

- Mark `path`, `namespace` as unused (or remove them) — AUD-035.
- `key_resources_cache`: "reserved; discovery is not cached" — AUD-033.
- `path_resources`: relative to `base_path()`, must exist, namespace must mirror the path — AUD-031/032.
- `middlewares`: add a warning that the default has no authentication and that the routes use the value
  only when the config file is published — AUD-012/030.
- Add `filters.boolean.*`, `fields.multi_image.max_size`, and the undeclared `fields.file.max_size`,
  `fields.image.max_size`.
- `fields`: "only `component` is read, by the backend, to fill the field payload; `type` is ignored" — CORE-007.
- `api.prefix`: "prefix of generated URLs; routes are mounted on `nadota.prefix`" — CORE-001.
- `action_events.enabled`: "does not affect action runs" — AUD-024; move the stray sentence about the
  table name out of the table.
- `export.enabled`, `export.sync_limit`, `export.chunk_size`: "not read in 1.3.0" — AUD-014.
- Replace the `1.1.4` version stamps.

## Issues

| ID | Summary | Severity |
|----|---------|----------|
| [AUD-030](../testing/findings/audit.md#aud-030--routes-read-nadotamiddlewares-before-the-package-config-is-merged) | Routes read middlewares before the merge | medium |
| [AUD-031](../testing/findings/audit.md#aud-031--boot-crashes-when-path_resources-is-missing-or-absolute) | Boot crashes on missing/absolute `path_resources` | high |
| [AUD-032](../testing/findings/audit.md#aud-032--discovery-derives-class-names-from-paths-and-wipes-earlier-registrations) | Path-derived class names; registry replaced | medium |
| [AUD-033](../testing/findings/audit.md#aud-033--key_resources_cache-is-checked-but-never-written) | Resource cache never written | low |
| [AUD-034](../testing/findings/audit.md#aud-034--documented-publish-commands-publish-nothing-provider-stub-missing) | Publish commands broken, stub missing | medium |
| [AUD-035](../testing/findings/audit.md#aud-035--path-and-namespace-config-keys-are-never-read) | `path`, `namespace` unused | low |
| [AUD-036](../testing/findings/audit.md#aud-036--excluderesources-only-works-if-called-before-the-package-boots) | Exclusion timing | low |
| [AUD-037](../testing/findings/audit.md#aud-037--configurationmd-is-incomplete-and-partly-wrong) | `configuration.md` incomplete / wrong | low |
| Also | AUD-012 (insecure default middleware), AUD-014 (export keys), AUD-024 (`enabled` vs actions), AUD-028 (built-in resource), CORE-001, CORE-007 | |
