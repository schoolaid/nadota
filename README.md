# Nadota

> Laravel admin panel package for resource-based CRUD interfaces, similar to Laravel Nova — API-driven, built for an Inertia/SPA frontend.

[![Latest Stable Version](https://img.shields.io/packagist/v/schoolaid/nadota.svg)](https://packagist.org/packages/schoolaid/nadota)
[![License](https://img.shields.io/packagist/l/schoolaid/nadota.svg)](https://packagist.org/packages/schoolaid/nadota)

Nadota lets you build administrative interfaces for Eloquent models through **Resource** classes that declare how data is displayed, validated, filtered, related, and authorized. It exposes everything as a REST/JSON API your frontend consumes.

- **Package:** `schoolaid/nadota`
- **Version:** 1.1.4
- **Namespace:** `SchoolAid\Nadota`

## Features

- **Resource-based architecture** — define admin panels declaratively
- **35+ field types** — text, numeric, date/time, boolean, selection, file/media, code/JSON, signature, dynamic, computed, and layout fields
- **11 relationship types** — BelongsTo, HasOne, HasMany, BelongsToMany, HasManyThrough, HasOneThrough, MorphTo, MorphOne, MorphMany, MorphToMany, MorphedByMany
- **Advanced filtering** — built-in filters (boolean, date, range, select, relation, exists, morph) plus custom filters
- **Sorting & searching** — per-field configuration
- **Authorization** — Laravel policy integration with granular per-field gates
- **Actions & Action Events** — bulk/row operations with built-in audit logging
- **Attachments** — attach/detach/sync for relation fields
- **Exports** — CSV and Excel
- **Soft deletes** — trashed listing, restore, force delete

## Requirements

- PHP `^8.2 | ^8.3`
- Laravel `^11.0 | ^12.0`

## Installation

```bash
composer require schoolaid/nadota
```

Publish the configuration (optional — it is also merged automatically):

```bash
php artisan vendor:publish --provider="SchoolAid\Nadota\NadotaServiceProvider" --tag="nadota-config"
```

See the [Installation guide](docs/getting-started/installation.md) for migrations and the optional provider stub.

## Quick Start

Create a resource in `app/Nadota` (auto-discovered):

```php
<?php

namespace App\Nadota;

use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Email;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;

class UserResource extends Resource
{
    public string $model = \App\Models\User::class;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->sortable()->searchable()->required(),
            Email::make('Email', 'email')->rules(['email', 'unique:users,email'])->searchable(),
            BelongsTo::make('Role', 'role')
                ->relatedModel(\App\Models\Role::class)
                ->relationAttribute('name')
                ->filterable(),
        ];
    }
}
```

The resource is served under the `nadota-api` prefix:

```http
GET    /nadota-api/users
POST   /nadota-api/users
GET    /nadota-api/users/{id}
PUT    /nadota-api/users/{id}
DELETE /nadota-api/users/{id}
```

Full walkthrough: [Quick Start](docs/getting-started/quick-start.md).

## Documentation

Complete documentation lives in [`docs/`](docs/README.md):

- **Getting Started** — [Installation](docs/getting-started/installation.md) · [Quick Start](docs/getting-started/quick-start.md) · [Configuration](docs/getting-started/configuration.md)
- **Guides** — [Resources](docs/guides/resources.md) · [Authorization](docs/guides/authorization.md) · [Lifecycle Hooks](docs/guides/lifecycle-hooks.md) · [Actions](docs/guides/actions.md) · [Action Events](docs/guides/action-events.md) · [Attachments](docs/guides/attachments.md) · [Exports](docs/guides/exports.md) · [Soft Deletes](docs/guides/soft-deletes.md) · [Menu](docs/guides/menu.md)
- **Fields** — [Overview](docs/fields/README.md) · [Basic](docs/fields/basic-fields.md) · [Relations](docs/fields/relation-fields.md) · [Dynamic](docs/fields/dynamic-fields.md) · [Depends On](docs/fields/depends-on.md) · [Custom](docs/fields/custom-fields.md)
- **Filters** — [Overview](docs/filters/README.md) · [Built-in](docs/filters/built-in-filters.md) · [Morph](docs/filters/morph-filters.md)
- **API** — [Overview](docs/api/README.md) · [Routes](docs/api/routes.md) · [Responses](docs/api/responses.md) · [Filtering](docs/api/filtering.md)
- **Internals** — [Architecture](docs/internals/architecture.md) · [Pipeline](docs/internals/pipeline.md)

## Testing

```bash
composer test            # run the Pest suite
composer test-coverage   # with coverage
./vendor/bin/pest tests/Unit/Fields/InputTest.php   # a single file
```

## Code Quality

```bash
composer analyse   # PHPStan static analysis
composer format    # Laravel Pint
```

## License

The MIT License (MIT). See the `license` field in [`composer.json`](composer.json).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
