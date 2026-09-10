# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `Lookup` field (`SchoolAid\Nadota\Http\Fields\Lookup`, type `lookup`, default component `FieldLookup`) — a form-only field that is never persisted. Virtual from its constructor, so it cannot be misconfigured by forgetting `->virtual()`: it contributes no SELECT column, never fills the model, resolves to `null`, and is hidden from index and detail. Its options are served from a Nadota Resource via `->resource()`, using the existing options endpoint and `DefaultOptionsStrategy` — no new strategy is registered. New `nadota.fields.lookup` config entry.
- `Field::scopedBy(string $field, string|Closure|null $target = null, bool $optional = false)` — available on every field. Narrows that field's options query using the current value of another field in the same form, sent by the client as `scope[<field>]`. `$target` is a column on the related model (defaulting to the observed field name in snake_case plus `_id`) or a `fn(Builder $query, mixed $value): Builder` for links through an intermediate relation. With `$optional` false (the default) an observed field with no value makes the options query return nothing. Declaring a scope also registers `dependsOn()` and turns on `clearOnDependencyChange()`, so a stale selection is cleared when the dependency changes. Scopes are keyed by observed field, so re-declaring replaces. Serialized to the client under `dependencies.options.scope` as `{field, optional}` entries; the column is server-side only and never leaves the backend. Applied on all three options code paths (strategy pipeline, paginated endpoint, morph options). See [docs/fields/lookup.md](docs/fields/lookup.md) and [docs/guides/scoped-options.md](docs/guides/scoped-options.md).

  Unlike the generic `filters[]` parameter, scope values are compared exactly (`=` / `whereIn`), never with `like` — `filters[grade_id]=5` also matches 15, 51 and 105 — and the filtered column comes from the resource declaration rather than the request, so a client cannot filter by an arbitrary column. Enforcement is limited to the options query: scopes are **not** checked on store or update.
- `nadota.filters.boolean.true_label` / `false_label` config — the option labels a `BooleanFilter` (i.e. any filterable `Checkbox`/`Boolean` field) hands to the frontend. Apps whose frontend translates option labels can point them at i18n keys (`'common.yes'`), the same contract the `Status` and `Select` fields already use via `translateLabels()`. Previously the labels were hardcoded to `Sí`/`No`, so an English UI showed Spanish. Defaults keep `Sí`/`No`, so apps that render labels as-is are unaffected.
- `ActionEventService::record()` — public, context-free entry point to log action events from jobs, console commands, API endpoints, or controllers outside the Nadota panel. Requires only the affected model; resolves the user via `Auth::id()` or the configured `system_user_id`, so no HTTP request or Nadota resource is needed. Respects queue mode and sensitive-field redaction.
- `ActionEventService::record()` now accepts an optional `?int $actionableId` parameter (appended last, fully backward compatible). It is stored in the `action_events.actionable_id` column, letting callers link an event to the originating record (e.g. the parent route a stop was created from). Defaults to `0` when omitted, preserving previous behavior. The degraded "failed" fallback row also preserves the provided id.

### Changed
- `tests/ServiceIntegration` is now declared in the `phpunit.xml` / `phpunit.xml.dist` testsuites. The directory existed but was never executed, so its tests gave false assurance. This surfaces 13 pre-existing failures in that directory; they are stale tests, unrelated to any behavior change, and are not fixed here.
- Action event tracking added to attachment services (`BelongsToMany`, `HasMany`, `MorphMany`, `MorphToMany`): `attach`, `detach`, and `sync` operations are now audited. `sync` also captures the previously attached IDs in `original`.
- Attachment tracking is wrapped in a guard + try/catch so a missing resource or logging failure never breaks the attach/detach/sync operation itself.
- Internal `ActionEventService::log()` refactored into a context-free `persist()` core (the vestigial, unused `NadotaRequest` dependency was removed). All existing public methods (`logCreate`, `logUpdate`, `logDelete`, `logRestore`, `logAction`) keep their signatures — fully backward compatible.

### Fixed
- Relation/action routes (`attach`, `detach`, `sync`, `attachable`, `relation`, `action-events`, `permissions`, `restore`, `force`) no longer constrain the resource `{id}` to `[0-9]+`. That constraint made every request to these endpoints 404 for resources keyed by a string/UUID (e.g. a tenant id like `antiguainternationalschool`), even though the controllers resolve the model via `findOrFail($id)` and handle any key type. Disambiguation is provided by each route's distinct literal segment and declaration order, not by a numeric constraint. The action-event record id (`{eventId}`) remains integer-constrained.
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