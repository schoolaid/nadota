# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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