# Action Events

Automatic audit logging for everything that happens inside the Nadota panel — creates, updates, deletes, restores, attachments, and custom [actions](actions.md) — plus a context-free entry point for logging from jobs, commands, and your own controllers.

This feature is fully implemented. Events are stored in the `action_events` table via the `ActionEvent` model, optionally queued, and broadcast through the `ActionLogged` event for external listeners.

## What gets tracked

The panel logs the following automatically (when enabled):

| Action name | Logged from | `original` | `changes` |
|-------------|-------------|------------|-----------|
| `create` | Store service | — | new model attributes |
| `update` | Update service | original values | changed values |
| `delete` | Destroy service | model attributes | — |
| `restore` | Restore (soft deletes) | — | — |
| `attach` / `detach` / `sync` | [Attachment](attachments.md) services | (sync: prior IDs) | attach/detach payload |
| `action:<key>` | Custom action execution | optional | optional |

Each row records the acting user, a batch UUID, the affected model (polymorphic), a sanitized `fields` payload, a `status`, and any `exception` message.

### The `ActionEvent` model

`SchoolAid\Nadota\Models\ActionEvent` (table `action_events`) stores:

| Column | Notes |
|--------|-------|
| `batch_id` | UUID grouping related events; auto-generated on create |
| `user_id` | Acting user, or the configured system user, or `null` |
| `name` | Action name (`create`, `update`, `attach`, `action:...`, etc.) |
| `actionable_type` / `actionable_id` | Origin (resource class for panel events; `actionable_id` defaults to `0`) |
| `target_type` / `target_id` | The targeted model |
| `model_type` / `model_id` | The affected model |
| `fields` | Arbitrary context payload (JSON, sanitized) |
| `status` | `running`, `finished`, or `failed` |
| `exception` | Error message when `failed` |
| `original` / `changes` | JSON before/after snapshots (sanitized) |

Casts: `fields`, `original`, `changes` are arrays; the ID columns are integers.

Helpers on the model: `markAsFinished()`, `markAsFailed($e)`, `isRunning()`, `isFinished()`, `isFailed()`, `getActionDisplayName()`, and `getChangedFields()` (returns a `key => [old, new]` diff). Query scopes: `byUser`, `byStatus`, `byBatch`, `byActionableType`, `byActionName`, `recent`. Relations: `user()`, `actionable()`, `target()`, `model()`.

## Enabling and configuring

Tracking is controlled in `config/nadota.php` under `action_events` (and the `NADOTA_TRACK_ACTIONS` env toggle):

```php
'track_actions' => env('NADOTA_TRACK_ACTIONS', true),

'action_events' => [
    'enabled' => env('NADOTA_TRACK_ACTIONS', true),
    'table'   => 'action_events',

    // User attributed to events with no authenticated user
    // (registrations, jobs, artisan commands). null leaves user_id null.
    'system_user_id' => env('NADOTA_SYSTEM_USER_ID', null),

    // Keys redacted from fields/original/changes (substring match, case-insensitive)
    'exclude_fields' => ['password', 'remember_token', 'api_token', 'token', 'secret', 'api_key', 'private_key'],

    'track_fields'   => true,
    'track_original' => true,
    'track_changes'  => true,

    // Fire the ActionLogged event for external listeners
    'dispatch_events' => env('NADOTA_DISPATCH_ACTION_EVENTS', true),

    // Async logging via queue
    'queue'      => env('NADOTA_ACTION_EVENTS_QUEUE', false),
    'queue_name' => env('NADOTA_ACTION_EVENTS_QUEUE_NAME', 'default'),
],
```

Notes:

- Setting `enabled` (or `NADOTA_TRACK_ACTIONS`) to `false` disables all panel tracking — every `track*` call in `TracksActionEvents` short-circuits.
- Any matching `exclude_fields` substring in a key replaces the value with `***REDACTED***` before persisting.
- When `queue` is `true`, persistence is dispatched to the `LogActionEvent` job on `queue_name`; the immediate return value is a non-persisted `ActionEvent` with `status = running`.

### Sensitive-data sanitization

`ActionEventService` sanitizes both the `fields` payload and the `original`/`changes` snapshots. Matching is a case-insensitive substring check against each key, so `api_token`, `user_password`, etc. are all redacted.

## How panel tracking works

Services include the `SchoolAid\Nadota\Http\Traits\TracksActionEvents` trait, which exposes:

- `trackCreate($model, $request, $fields)`
- `trackUpdate($model, $request, $fields, $originalData)`
- `trackDelete($model, $request)`
- `trackRestore($model, $request)`
- `trackCustomAction($action, $model, $request, $fields, $metadata)`

Each one checks `shouldTrackActions()` (reads `nadota.action_events.enabled`) and delegates to `ActionEventService`. The attachment services use the same trait via their abstract base to log `attach`/`detach`/`sync`.

## Logging from outside the panel

For jobs, artisan commands, your own API endpoints, or any controller outside Nadota, use the context-free `record()` method on `ActionEventService`. It needs only the affected model — the user is resolved from `Auth::id()` or the configured `system_user_id`, so it works without an HTTP request:

```php
use SchoolAid\Nadota\Http\Services\ActionEventService;

app(ActionEventService::class)->record(
    action: 'sync:roster',
    model: $student,
    changes: ['grade' => '10'],
    original: ['grade' => '9'],
    fields: ['source' => 'sis-import'],
);
```

Signature:

```php
public function record(
    string $action,
    Model $model,
    ?array $changes = null,
    ?array $original = null,
    array $fields = [],
    ?string $actionableType = null,
    ?int $actionableId = null
): ActionEvent
```

Do not instantiate `ActionEvent` directly — go through the service so sanitization, batching, queuing, and event dispatch all apply.

### Reading history programmatically

`ActionEventService` also offers read helpers:

- `getModelHistory(Model $model, int $limit = 50)`
- `getUserActivity(int $userId, int $limit = 50)`
- `getResourceActivity(string $resourceClass, int $limit = 50)`

## Reacting to events

When `dispatch_events` is enabled, every persisted event fires `SchoolAid\Nadota\Events\ActionLogged`, carrying the `ActionEvent` and the action name. The event exposes `getActionEvent()`, `getAction()`, and convenience predicates `isCreate()`, `isUpdate()`, `isDelete()`, `isRestore()`, `isForceDelete()`.

```php
Event::listen(ActionLogged::class, function (ActionLogged $event) {
    if ($event->isDelete()) {
        // notify, mirror to another store, etc.
    }
});
```

The queued `LogActionEvent` job dispatches the same event after it persists, so listeners fire whether logging is sync or async.

## The ActionEvent resource

Nadota ships `SchoolAid\Nadota\Resources\ActionEventResource`, a read-only resource over `ActionEvent`:

- Title: `Action Events`. Hidden from the menu (`displayInMenu()` returns `false`).
- All fields are `readonly()`. Searchable on `batch_id`, `name`, `actionable_type`, `model_type`, `status`.
- Eager-loads `user`. Soft deletes disabled.
- Filters: action name, status, model type, model ID, user ID, created-at date.

Because it is a normal resource, it is reachable through the standard resource endpoints once registered/discovered — you simply won't see it in the navigation [menu](menu.md).

## Endpoints

A dedicated, lightweight controller exposes per-model history for frontend timelines. Routes live under the `{resourceKey}/resource` group (API prefix `nadota-api` by default).

### List a model's events

```http
GET /nadota-api/{resourceKey}/resource/{id}/action-events
```

Query parameters: `per_page` (default 15, max 100), `page` (default 1), and optional `name`, `status`, `user_id` filters. Ordered newest first.

```json
{
  "data": [
    {
      "id": 12,
      "batchId": "9b1c...",
      "name": "update",
      "nameLabel": "Updated",
      "status": "finished",
      "user": { "id": 1, "name": "Ada", "email": "ada@example.com" },
      "modelType": "App\\Models\\Post",
      "modelId": 5,
      "fields": { "...": "..." },
      "original": { "title": "Old" },
      "changes": { "title": "New" },
      "exception": null,
      "createdAt": "2026-06-02 10:30:00",
      "updatedAt": "2026-06-02 10:30:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 1 }
}
```

`nameLabel` humanizes known names (`create` → `Created`, `update` → `Updated`, `delete` → `Deleted`, `restore` → `Restored`, `forceDelete` → `Permanently Deleted`; otherwise `ucfirst`).

### Get a single event

```http
GET /nadota-api/{resourceKey}/resource/{id}/action-events/{eventId}
```

Returns `{ "data": { ...same shape as above... } }`, or `404` if the event does not belong to that model. See [API responses](../api/responses.md).

## Roadmap items not implemented

The roadmap at `docs/roadmaps/ACTION_EVENTS.md` describes the design; the following remain notes rather than current behavior:

- It mentions adding a `show` method to `ActionEventController` — this is **already implemented** (see above).
- A `forceDelete` action with a `{permanently_deleted: true}` change payload is described in the roadmap's table; the panel logs deletes via `logDelete` (`name = 'delete'`). Force-delete logging beyond that is not provided as a distinct built-in tracker.

## Related

- [Actions](actions.md) — custom actions that emit `action:<key>` events
- [Attachments](attachments.md) — attach/detach/sync events
- [Authorization](authorization.md)
- [API routes](../api/routes.md), [API responses](../api/responses.md)
