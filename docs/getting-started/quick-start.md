# Quick Start

Build your first admin resource and expose it through the Nadota API.

## 1. Create a resource

Resources live in `app/Nadota` and are auto-discovered. Create `app/Nadota/UserResource.php`:

```php
<?php

namespace App\Nadota;

use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Email;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\DateTime;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;

class UserResource extends Resource
{
    public string $model = \App\Models\User::class;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')
                ->sortable()
                ->searchable()
                ->required(),

            Email::make('Email', 'email')
                ->rules(['email', 'unique:users,email'])
                ->sortable()
                ->searchable(),

            Select::make('Status', 'status')
                ->options([
                    'active'   => 'Active',
                    'inactive' => 'Inactive',
                    'pending'  => 'Pending',
                ])
                ->filterable(),

            BelongsTo::make('Role', 'role')
                ->relatedModel(\App\Models\Role::class)
                ->relationAttribute('name')
                ->filterable(),

            DateTime::make('Created At', 'created_at')
                ->sortable()
                ->exceptOnForms(),
        ];
    }
}
```

> Verify the exact `$model` declaration and available field options against [Defining Resources](../guides/resources.md) and the [Fields reference](../fields/README.md).

## 2. Consume the API

Nadota auto-generates a REST/JSON API under the `nadota-api` prefix. The frontend (Inertia/SPA) talks to these endpoints:

```http
GET    /nadota-api/menu                      # navigation tree
GET    /nadota-api/{resource}                # index (paginated, filterable)
GET    /nadota-api/{resource}/create         # create form schema
POST   /nadota-api/{resource}                # store
GET    /nadota-api/{resource}/{id}           # show
GET    /nadota-api/{resource}/{id}/edit       # edit form schema
PUT    /nadota-api/{resource}/{id}           # update
DELETE /nadota-api/{resource}/{id}           # destroy
```

See the [full route reference](../api/routes.md) and [response shapes](../api/responses.md).

## 3. Add behavior

From here you can layer in:

- [Filters](../filters/README.md) — built-in and custom query filters
- [Authorization](../guides/authorization.md) — policy integration and per-field gates
- [Actions](../guides/actions.md) — bulk/row operations
- [Relationships](../fields/relation-fields.md) — BelongsTo, HasMany, MorphTo, and more
- [Lifecycle hooks](../guides/lifecycle-hooks.md) — customize store/update/delete
- [Exports](../guides/exports.md) — CSV/Excel

## Next steps

- [Configuration](configuration.md)
- [Architecture overview](../internals/architecture.md)
