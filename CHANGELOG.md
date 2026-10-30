# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Versions not listed below shipped without changelog entries; see the git tags for their contents.

## [Unreleased]

### Security
- `File` and `Image` uploads are validated on the server again. Since 3228915 both `getRules()` bodies were commented out, so `accept()`, `maxSize()` and `maxImageDimensions()` only reached the frontend: any file of any type and size was stored — including a `.php` on a field that accepted only `pdf`. `File` now emits `file`, `max:<KB>` and a type rule; `Image` adds `image` and `dimensions:`. `MultiImage` is unchanged (it validates each item through its nested rules). **Behavior change:** clients that send anything other than an uploaded file or `null`/`''` for a file field (e.g. the stored path on edit) now get a 422.

### Fixed
- Accepted extensions are passed to `mimes:` as-is. The old (commented-out) conversion turned `docx`, `xlsx`, `xls` and `txt` into MIME subtypes that `mimes:` never matches, which would have rejected those files. A list that mixes extensions and MIME types becomes one `mimetypes:` rule, because `mimes` and `mimetypes` together would require a file to pass both.
- `Image` reports type `image` and component `FieldImage` (or `nadota.fields.image.component`), as documented. Its constructor passed both to `File::__construct()`, which takes only `(name, attribute)`, so PHP dropped them and every `Image` reported `file` / `FieldFile`. **Behavior change:** frontends that pick the component by type now render `Image` fields with their `FieldImage` components instead of `FieldFile`; the SchoolAid frontend needs the matching `show/FieldImage.vue` fix (preview `src` was bound to the `thumbnails` array/object).
- Non-required `File`/`Image` fields get `nullable`, so an edit that leaves the file untouched (the form sends `null`) passes validation and keeps the stored file.
- Relation options are labeled with the field's `displayAttribute` even when the related resource has no field for that column. The options query selected only the related resource's field columns plus the key, so `->displayAttribute('name')` on a resource without a `name` field labeled every option with its primary key. The column is now added to the select on all three options code paths (strategy pipeline, paginated endpoint, morph options). An accessor (`getXAttribute()` or an `Attribute` cast) is not a column and is not added.
- `Input` (and any field built directly on `Field`) falls back to component `FieldText`, and `BelongsTo` to `FieldBelongsTo`, when `nadota.fields.input.component` / `nadota.fields.belongsTo.component` is missing from the published config. Both fell back to `field`, a component no frontend registers.
- Attachment action events: a `sync` stored `changes.detached` as a JSON object (`{"1": 2}`) whenever an item other than the first was detached, because Laravel's `sync()` keeps `array_diff()` keys. All three lists are re-indexed before they are logged and returned, so the sync response's `detached` is a JSON list too.
- Attachment action events: a `BelongsToMany`/`MorphToMany` `detach` recorded every requested ID in `changes.detached`, including IDs that were never attached. It now records only the IDs that were attached, as `HasMany`/`MorphMany` already did.
- The attachment services no longer fail when called with a request that has no resource set. `trackAttachmentAction()` guarded against `getResource()` returning `null`, but it never does: it resolves the resource from the route and aborts with a 404. The 404 escaped after the attach had already been written; the call now runs inside the `try`, so the attach succeeds and nothing is logged.

### Changed
- The 105 tests that had been failing since the field refactors of late 2025 now pass. They called APIs that had since been renamed or removed (`relatedModel()` → `model()`, `relationAttribute()` → `displayAttribute()`, `maxDimensions()` → `maxImageDimensions()`, `field-x` → `FieldX` component names, string `type`). Tests covering removed behavior (`HasMany::collapsible()`, HasOne options and validation) were deleted. The 16 tests that expose real bugs are marked `skip('BUG: …')` instead of being bent to match the current behavior.
- `storage/framework/testing/` is no longer tracked; test runs wrote upload fixtures there.
- `tests/ServiceIntegration/AttachmentEventIntegrationTest.php` covers the attach/detach/sync action events on `BelongsToMany`, `MorphToMany` and `HasMany`, closing the last item of the attachment-events roadmap.

## [1.2.6] - 2026-09-28

### Security
- The action-events endpoints (`GET {resourceKey}/resource/{id}/action-events` and `.../action-events/{eventId}`) now resolve the record through the resource's `getQuery()` and require `view` on it, exactly like the show endpoint. They used to answer `200` to any authenticated user — including one with no permission on the resource — and handed over the record's attribute snapshots (`original`, `changes`, `fields`). Now: `403` when the user cannot view the record, `404` when the record does not exist or the resource query does not expose it. **Behavior change:** the history of a record that no longer exists (hard-deleted) is no longer reachable through these endpoints.

### Changed
- The action-events endpoints match events by the model's class name **and** by its morph alias (`getMorphClass()`). Events written by the host app or by Nova for a model registered in a morph map were stored under the alias and never showed up in the record's history.
- The action-events list breaks ties on `id` (`created_at desc, id desc`). Events written in the same second — a detach followed by an attach in one request — came back in arbitrary order and could shuffle between pages.
- The action-events endpoints compare `model_id` as a string. Host apps that widened `action_events.model_id` to a string column (UUID support) only get an index lookup that way; an integer binding made MySQL cast every row. Integer columns are unaffected.

## [1.2.5] - 2026-09-10

### Added
- `Lookup` field (`SchoolAid\Nadota\Http\Fields\Lookup`, type `lookup`, default component `FieldLookup`) — a form-only field that is never persisted. Virtual from its constructor, so it cannot be misconfigured by forgetting `->virtual()`: it contributes no SELECT column, never fills the model, resolves to `null`, and is hidden from index and detail. Its options are served from a Nadota Resource via `->resource()`, using the existing options endpoint and `DefaultOptionsStrategy` — no new strategy is registered. New `nadota.fields.lookup` config entry.
- `Field::scopedBy(string $field, string|Closure|null $target = null, bool $optional = false)` — available on every field. Narrows that field's options query using the current value of another field in the same form, sent by the client as `scope[<field>]`. `$target` is a column on the related model (defaulting to the observed field name in snake_case plus `_id`) or a `fn(Builder $query, mixed $value): Builder` for links through an intermediate relation. With `$optional` false (the default) an observed field with no value makes the options query return nothing. Declaring a scope also registers `dependsOn()` and turns on `clearOnDependencyChange()`, so a stale selection is cleared when the dependency changes. Scopes are keyed by observed field, so re-declaring replaces. Serialized to the client under `dependencies.options.scope` as `{field, optional}` entries; the column is server-side only and never leaves the backend. Applied on all three options code paths (strategy pipeline, paginated endpoint, morph options). See [docs/fields/lookup.md](docs/fields/lookup.md) and [docs/guides/scoped-options.md](docs/guides/scoped-options.md).

  Unlike the generic `filters[]` parameter, scope values are compared exactly (`=` / `whereIn`), never with `like` — `filters[grade_id]=5` also matches 15, 51 and 105 — and the filtered column comes from the resource declaration rather than the request, so a client cannot filter by an arbitrary column. Enforcement is limited to the options query: scopes are **not** checked on store or update.

### Changed
- `tests/ServiceIntegration` is now declared in the `phpunit.xml` / `phpunit.xml.dist` testsuites. The directory existed but was never executed, so its tests gave false assurance. This surfaces 13 pre-existing failures in that directory; they are stale tests, unrelated to any behavior change, and are not fixed here.

## [1.2.0] - 2026-07-14

### Added
- `nadota.filters.boolean.true_label` / `false_label` config — the option labels a `BooleanFilter` (i.e. any filterable `Checkbox`/`Boolean` field) hands to the frontend. Apps whose frontend translates option labels can point them at i18n keys (`'common.yes'`), the same contract the `Status` and `Select` fields already use via `translateLabels()`. Previously the labels were hardcoded to `Sí`/`No`, so an English UI showed Spanish. Defaults keep `Sí`/`No`, so apps that render labels as-is are unaffected.

## [1.1.6] - 2026-06-19

### Fixed
- Relation/action routes (`attach`, `detach`, `sync`, `attachable`, `relation`, `action-events`, `permissions`, `restore`, `force`) no longer constrain the resource `{id}` to `[0-9]+`. That constraint made every request to these endpoints 404 for resources keyed by a string/UUID (e.g. a tenant id like `antiguainternationalschool`), even though the controllers resolve the model via `findOrFail($id)` and handle any key type. Disambiguation is provided by each route's distinct literal segment and declaration order, not by a numeric constraint. The action-event record id (`{eventId}`) remains integer-constrained.

## [1.1.4] - 2026-05-30

### Added
- `ActionEventService::record()` now accepts an optional `?int $actionableId` parameter (appended last, fully backward compatible). It is stored in the `action_events.actionable_id` column, letting callers link an event to the originating record (e.g. the parent route a stop was created from). Defaults to `0` when omitted, preserving previous behavior. The degraded "failed" fallback row also preserves the provided id.

## [1.1.0] - 2026-05-28

### Added
- `ActionEventService::record()` — public, context-free entry point to log action events from jobs, console commands, API endpoints, or controllers outside the Nadota panel. Requires only the affected model; resolves the user via `Auth::id()` or the configured `system_user_id`, so no HTTP request or Nadota resource is needed. Respects queue mode and sensitive-field redaction.

### Changed
- Action event tracking added to attachment services (`BelongsToMany`, `HasMany`, `MorphMany`, `MorphToMany`): `attach`, `detach`, and `sync` operations are now audited. `sync` also captures the previously attached IDs in `original`.
- Attachment tracking is wrapped in a guard + try/catch so a missing resource or logging failure never breaks the attach/detach/sync operation itself.
- Internal `ActionEventService::log()` refactored into a context-free `persist()` core (the vestigial, unused `NadotaRequest` dependency was removed). All existing public methods (`logCreate`, `logUpdate`, `logDelete`, `logRestore`, `logAction`) keep their signatures — fully backward compatible.

## [1.0.26] - 2026-04-25

### Fixed
- `AbstractResourcePersistService::handle()` now re-throws `Illuminate\Validation\ValidationException` instead of swallowing it into a generic 500 response. This lets resources throw `ValidationException::withMessages([...])` from `beforeStore`/`beforeUpdate` hooks and have Laravel render the standard 422 response with field-level errors. The transaction is still rolled back before the exception propagates.

## [1.0.0] - 2025-08-22

### Added
- Initial stable release of SchoolAid Nadota admin panel package
- Complete field system with 18 field types:
  - Basic fields: Input, Hidden, Textarea, Number, Email, URL
  - Selection fields: Select, Radio, Checkbox, CheckboxList, Toggle
  - Date/Time fields: DateTime
  - File upload fields: File, Image
  - Relationship fields: BelongsTo, HasOne, HasMany, BelongsToMany
- Comprehensive trait system for field behaviors:
  - Sortable, Searchable, Filterable traits for data management
  - Validation trait with Laravel validation rules integration
  - Visibility trait for conditional field display
  - DefaultValue trait for setting default values
- Resource-based CRUD interface architecture
- Service-oriented architecture with dependency injection
- Menu system for admin panel navigation
- Filter system for data filtering
- Authorization service integration
- Full test coverage with 294 passing tests
- Laravel 11 and PHP 8.2+ compatibility
- Inertia.js integration for SPA-like functionality

### Security
- MIME type validation for file uploads
- File size limits and extension checking
- Image dimension validation
- Comprehensive input validation
- Safe file metadata extraction

### Changed
- Vendor namespace changed from `said/nadota` to `schoolaid/nadota` for Packagist compatibility

[1.0.0]: https://github.com/schoolaid/nadota/releases/tag/v1.0.0