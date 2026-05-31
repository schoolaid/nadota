# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `ActionEventService::record()` — public, context-free entry point to log action events from jobs, console commands, API endpoints, or controllers outside the Nadota panel. Requires only the affected model; resolves the user via `Auth::id()` or the configured `system_user_id`, so no HTTP request or Nadota resource is needed. Respects queue mode and sensitive-field redaction.
- `ActionEventService::record()` now accepts an optional `?int $actionableId` parameter (appended last, fully backward compatible). It is stored in the `action_events.actionable_id` column, letting callers link an event to the originating record (e.g. the parent route a stop was created from). Defaults to `0` when omitted, preserving previous behavior. The degraded "failed" fallback row also preserves the provided id.

### Changed
- Action event tracking added to attachment services (`BelongsToMany`, `HasMany`, `MorphMany`, `MorphToMany`): `attach`, `detach`, and `sync` operations are now audited. `sync` also captures the previously attached IDs in `original`.
- Attachment tracking is wrapped in a guard + try/catch so a missing resource or logging failure never breaks the attach/detach/sync operation itself.
- Internal `ActionEventService::log()` refactored into a context-free `persist()` core (the vestigial, unused `NadotaRequest` dependency was removed). All existing public methods (`logCreate`, `logUpdate`, `logDelete`, `logRestore`, `logAction`) keep their signatures — fully backward compatible.

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