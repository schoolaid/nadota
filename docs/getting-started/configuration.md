# Configuration

Reference for `config/nadota.php`. Publish it with `--tag="nadota-config"` (see [Installation](installation.md)). The config is also merged automatically, so you only need to publish the keys you want to override.

## Core

```php
'path'                => 'said',          // base path segment for the panel
'namespace'           => 'said',          // panel namespace identifier
'key_resources_cache' => 'said_nadota_class_file_map', // cache key for the discovered resource map
'path_resources'      => 'app/Nadota',    // directory scanned for Resource classes
'middlewares'         => ['api'],         // middleware applied to Nadota routes
```

| Key | Default | Description |
|-----|---------|-------------|
| `path` | `said` | Base path segment used by the panel. |
| `namespace` | `said` | Panel namespace identifier. |
| `key_resources_cache` | `said_nadota_class_file_map` | Cache key for the class→file resource map (used in production). |
| `path_resources` | `app/Nadota` | Directory auto-scanned for `Resource` classes. |
| `middlewares` | `['api']` | Middleware stack applied to the generated routes. |

## API & frontend prefixes

```php
'api'      => ['prefix' => 'nadota-api'],   // REST/JSON API prefix
'frontend' => ['prefix' => 'resources'],     // frontend (SPA) route prefix
```

All API endpoints documented in [api/routes.md](../api/routes.md) are served under `nadota-api`.

## Fields

The `fields` array maps each field `type` to the frontend component name the SPA should render. It is consumed by the frontend, not by validation. Example:

```php
'fields' => [
    'text'     => ['type' => 'text',     'component' => 'FieldText'],
    'select'   => ['type' => 'select',   'component' => 'FieldSelect'],
    'belongsTo'=> ['type' => 'belongsTo','component' => 'FieldBelongsTo'],
    // ...one entry per field/relation type
],
```

See the [Fields reference](../fields/README.md) for the full catalog of field classes.

## Action Events

Controls audit logging. See the [Action Events guide](../guides/action-events.md).

```php
'track_actions' => env('NADOTA_TRACK_ACTIONS', true),
'action_events' => [
    'enabled'         => env('NADOTA_TRACK_ACTIONS', true),
    'table'           => 'action_events',
    'system_user_id'  => env('NADOTA_SYSTEM_USER_ID', null), // user id for unauthenticated actions
    'exclude_fields'  => ['password', 'remember_token', 'api_token', 'token', 'secret', 'api_key', 'private_key'],
    'track_fields'    => true,
    'track_original'  => true,
    'track_changes'   => true,
    'dispatch_events' => env('NADOTA_DISPATCH_ACTION_EVENTS', true), // fire ActionLogged events
    'queue'           => env('NADOTA_ACTION_EVENTS_QUEUE', false),   // log asynchronously
    'queue_name'      => env('NADOTA_ACTION_EVENTS_QUEUE_NAME', 'default'),
],
```

| Key | Default | Description |
|-----|---------|-------------|
| `track_actions` / `action_events.enabled` | `true` | Master switch for audit logging. |
| `system_user_id` | `null` | User id attributed to actions with no authenticated user (jobs, registrations, console). `null` allows a null `user_id`. |
| `exclude_fields` | sensitive keys | Field names stripped before logging. |
| `track_fields` / `track_original` / `track_changes` | `true` | What payload is captured per event. |
| `dispatch_events` | `true` | Dispatch the `ActionLogged` event for external listeners. |
| `queue` / `queue_name` | `false` / `default` | Log via a queued job instead of synchronously. |

## Export

Controls CSV/Excel exports. See the [Exports guide](../guides/exports.md).

```php
'export' => [
    'enabled'        => env('NADOTA_EXPORT_ENABLED', true),
    'formats'        => ['excel', 'csv'],
    'default_format' => 'excel',
    'sync_limit'     => env('NADOTA_EXPORT_SYNC_LIMIT', 1000),
    'chunk_size'     => env('NADOTA_EXPORT_CHUNK_SIZE', 500),
],
```

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Global export switch. |
| `formats` | `['excel', 'csv']` | Available formats. PDF/async are **not** implemented in `1.1.4`. |
| `default_format` | `excel` | Format used when the request omits one. |
| `sync_limit` | `1000` | Surfaced in the export config payload; not enforced server-side in `1.1.4` (exports stream synchronously). |
| `chunk_size` | `500` | Chunk size used when iterating large datasets. |

## Related

- [Installation](installation.md)
- [Architecture](../internals/architecture.md)
