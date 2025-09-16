# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Testing
```bash
# Run all tests
composer test

# Run tests with coverage report
composer test-coverage

# Run a specific test file
./vendor/bin/pest tests/Unit/Fields/InputTest.php

# Run tests for a specific class or group
./vendor/bin/pest --filter="InputTest"
```

### Code Quality
```bash
# Static analysis (if configured)
composer analyse

# Code formatting (if configured)
composer format
```

### Package Development
```bash
# Install dependencies
composer install

# Update autoloader
composer dump-autoload

# Publish package configuration (for testing in a Laravel app)
php artisan vendor:publish --provider="SchoolAid\Nadota\NadotaServiceProvider" --tag="config"
```

## Architecture

Nadota is a Laravel package that provides resource-based CRUD interfaces similar to Laravel Nova. The architecture follows a service-oriented pattern with clear separation of concerns.

### Core Components

**Resource System** (`src/Resource.php`)
- Base abstract class that all resources extend
- Defines fields, filters, actions, and authorization for each model
- Resources are automatically discovered in `app/Nadota` directory
- Uses traits for pagination, menu options, visibility, and field interactions

**Field System** (`src/Http/Fields/`)
- Each field type extends the base `Field` class
- Implements `FieldInterface` for consistent behavior
- Traits provide reusable functionality: validation, visibility, sorting, filtering, searching
- Relationship fields (BelongsTo, HasOne, HasMany, BelongsToMany) handle Eloquent relationships

**Service Layer** (`src/Http/Services/`)
- Each CRUD operation has a dedicated service (Index, Create, Store, Show, Edit, Update, Destroy)
- Services implement corresponding interfaces from `src/Contracts/`
- Pipeline pattern for index operations: BuildQuery → ApplyFilters → ApplyFields → ApplySorting → PaginateAndTransform
- Handlers manage specific concerns like default values and relationships

**Controller Layer** (`src/Http/Controllers/`)
- `ResourceController` handles all CRUD operations
- `MenuController` manages admin panel navigation
- Controllers delegate to services for business logic

**Authorization** (`src/Http/Services/ResourceAuthorizationService.php`)
- Integrates with Laravel policies
- Each resource action checks authorization through the service
- Supports fine-grained permissions per resource and action

### Key Design Patterns

1. **Service Pattern**: Each CRUD operation has a dedicated service with single responsibility
2. **Pipeline Pattern**: Index operations use sequential pipes for query building
3. **Repository Pattern**: Resources act as repositories for their models
4. **Strategy Pattern**: Fields define their own rendering and validation strategies
5. **Trait Composition**: Shared functionality is extracted into focused traits

### Namespace Structure
- Root namespace: `SchoolAid\Nadota`
- All imports use absolute namespace paths
- Service provider auto-discovers and registers resources

### Configuration
- Main config: `config/nadota.php`
- API prefix: `nadota-api`
- Frontend prefix: `resources`
- Resource discovery path: `app/Nadota`

### Testing Approach
- Tests use Pest PHP framework
- Unit tests for individual components in `tests/Unit/`
- Service integration tests in `tests/ServiceIntegration/`
- Test models and factories in `tests/Models/` and `tests/Database/Factories/`
- SQLite in-memory database for testing