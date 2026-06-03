# Installation

Install Nadota into a Laravel 11 or 12 application via Composer and publish its configuration.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP         | `^8.2 \| ^8.3` |
| Laravel     | `^11.0 \| ^12.0` |
| PhpSpreadsheet (exports) | `^1.30 \| ^2.0 \| ^3.0` (pulled in automatically) |

## 1. Require the package

```bash
composer require schoolaid/nadota
```

The service provider `SchoolAid\Nadota\NadotaServiceProvider` is auto-discovered through Laravel's package discovery — no manual registration is required.

## 2. Publish the configuration

```bash
php artisan vendor:publish --provider="SchoolAid\Nadota\NadotaServiceProvider" --tag="nadota-config"
```

This copies `config/nadota.php` into your application. See [Configuration](configuration.md) for every option.

> The config is also merged automatically, so publishing is optional — do it when you need to override defaults.

## 3. (Optional) Publish the application provider stub

Nadota can publish a starter `App\Providers\NadotaServiceProvider` where you can register or exclude resources programmatically:

```bash
php artisan vendor:publish --provider="SchoolAid\Nadota\NadotaServiceProvider" --tag="nadota-provider"
```

## 4. Run migrations

[Action Events](../guides/action-events.md) (enabled by default) require an `action_events` table. The package registers its migrations automatically, so you only need to run:

```bash
php artisan migrate
```

To disable Action Events entirely and skip the table, set `NADOTA_TRACK_ACTIONS=false` in your `.env` **before** migrating — the migrations are only registered while tracking is enabled.

## 5. Verify

Resources placed in `app/Nadota` are auto-discovered. Once you create your first resource (see the [Quick Start](quick-start.md)), the API is served under the `nadota-api` prefix:

```
GET /nadota-api/menu
```

## Next steps

- [Quick Start](quick-start.md) — build your first resource in a few minutes
- [Configuration](configuration.md) — full reference for `config/nadota.php`
- [Defining Resources](../guides/resources.md)
