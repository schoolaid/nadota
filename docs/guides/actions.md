# Actions

Run custom operations against one or more selected resource records (or no records at all) from the index and detail views.

Actions are PHP classes registered on a resource. The frontend lists them, optionally collects field input, then posts back to execute. The server authorizes each model, runs your `handle()` method, logs an [action event](action-events.md), and returns a typed response telling the frontend what to do next.

## Defining an action

Create a class that extends `SchoolAid\Nadota\Http\Actions\Action` and implement `handle()`:

```php
<?php

namespace App\Nadota\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Actions\Action;
use SchoolAid\Nadota\Http\Actions\ActionResponse;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ActivateUsers extends Action
{
    protected ?string $name = 'Activate';

    public function handle(Collection $models, NadotaRequest $request): mixed
    {
        $models->each->update(['active' => true]);

        return Action::message("Activated {$models->count()} users.");
    }
}
```

`handle()` receives a `Collection` of the authorized models and the current request. Whatever you return is normalized into an [`ActionResponse`](#response-types); returning `null` yields a default success message (`Action executed successfully.`).

### Registering actions on a resource

Override `actions()` on your resource and return action instances built with `make()`:

```php
public function actions(NadotaRequest $request): array
{
    return [
        ActivateUsers::make(),
        SendEmail::make()->onlyOnDetail(),
        DeleteForever::make(),
    ];
}
```

### Action key and name

- **Key** — `getKey()` returns a URI-safe slug derived from the fully-qualified class name (e.g. `App\Nadota\Actions\ActivateUsers` → `app-nadota-actions-activate-users`). This is the `{actionKey}` used in URLs.
- **Name** — `name()` returns the `$name` property, or a humanized class basename when unset (`ActivateUsers` → `Activate Users`).

### Visibility

By default an action shows on both index and detail. Control this fluently:

| Method | Effect |
|--------|--------|
| `onlyOnIndex()` | Show on index only |
| `onlyOnDetail()` | Show on detail only |
| `showOnTableRow()` | Show on both (default) |

The actions listing endpoint filters by the `context` query parameter (`index` or `detail`) using `showOnIndex()` / `showOnDetail()`.

### Confirmation and buttons

```php
ActivateUsers::make()
    ->withConfirmation('Activate the selected users?')
    ->setConfirmButtonText('Yes, activate')
    ->setCancelButtonText('No');
```

| Method | Property | Default |
|--------|----------|---------|
| `withConfirmation(string)` | `confirmText` | `null` |
| `setConfirmButtonText(string)` | `confirmButtonText` | `Run Action` |
| `setCancelButtonText(string)` | `cancelButtonText` | `Cancel` |

When `confirmText` is `null` the frontend can skip the confirmation step.

### Icon

```php
ActivateUsers::make()->icon('CheckCircle');
```

`icon` is only included in the serialized payload when explicitly set.

### Authorization

By default every action authorizes for every model. Provide a closure with `canRun()` to filter:

```php
DeleteForever::make()->canRun(function (NadotaRequest $request, Model $model) {
    return $request->user()->can('forceDelete', $model);
});
```

During execution the service calls `authorizedToRun($request, $model)` per model and only passes authorized models to `handle()`. If no model is authorized and the action is not standalone, a `danger` response is returned (`You are not authorized to run this action on the selected resources.`).

## Action fields

Actions can collect input before running. Override `fields()` and return Nadota field instances:

```php
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Textarea;

public function fields(NadotaRequest $request): array
{
    return [
        Input::make('Subject', 'subject')->rules('required', 'string', 'max:255'),
        Textarea::make('Message', 'message')->rules('required'),
    ];
}
```

The submitted values arrive in the request and can be read inside `handle()`:

```php
public function handle(Collection $models, NadotaRequest $request): mixed
{
    $subject = $request->input('subject');
    $message = $request->input('message');
    // ...
}
```

Fields are serialized via each field's `toArray()` both in the actions listing (`fields` key) and through the dedicated [fields endpoint](#endpoints). Validation is driven by the field's own `rules()`.

## Destructive actions

Extend `SchoolAid\Nadota\Http\Actions\DestructiveAction` for operations that the frontend should style as dangerous and confirm by default:

```php
use SchoolAid\Nadota\Http\Actions\DestructiveAction;

class DeleteForever extends DestructiveAction
{
    public function handle(Collection $models, NadotaRequest $request): mixed
    {
        $models->each->forceDelete();

        return Action::message('Records permanently deleted.');
    }
}
```

`DestructiveAction` simply presets:

- `destructive = true`
- `confirmButtonText = 'Delete'`
- `confirmText = 'Are you sure you want to run this action?'`

You can also mark any action destructive at runtime with `->destructive()`.

## Standalone actions

Standalone actions run without any selected records — useful for imports, global report generation, or seeding.

```php
ImportData::make()->standalone();
```

When standalone, the execute endpoint accepts an empty `resources` array. `handle()` then receives an empty collection (no models to authorize), so drive the logic from the request payload instead.

## Custom-component actions

Point the frontend at a custom UI component (wizard, upload, etc.) instead of the default action modal:

```php
ImportData::make()
    ->standalone()
    ->component('ActionImportWizard');
```

The `component` key is only present in the payload when explicitly set; otherwise the frontend should fall back to its default action component.

## Response types

Return one of the static `ActionResponse` factories (also exposed as static helpers on `Action`). The `type` field tells the frontend how to react.

| Helper | `type` | Extra fields | Frontend behavior |
|--------|--------|--------------|-------------------|
| `Action::message($text)` | `message` | `message` | Success toast; refresh list |
| `Action::danger($text)` | `danger` | `message` | Error toast; do not refresh |
| `Action::redirect($url)` | `redirect` | `url` | Navigate to URL |
| `Action::download($url, $name)` | `download` | `url`, `filename` | Trigger file download |
| `Action::openInNewTab($url)` | `openInNewTab` | `url`, `openInNewTab: true` | Open URL in a new tab |

Attach arbitrary payload with `withData()`:

```php
return Action::message('Done')->withData(['processed' => 42]);
```

`ActionResponse::toArray()` filters out null/empty keys, so only the relevant fields appear:

```json
{
  "type": "download",
  "url": "/storage/exports/report.csv",
  "filename": "report.csv"
}
```

## Execution flow

`ActionExecutionService` orchestrates execution:

1. Resolve the action by key via `findAction()` (matches `getKey()` against the resource's registered actions).
2. Load the models for the submitted IDs (`whereIn('id', ...)`), including trashed rows when the resource uses soft deletes.
3. Filter to authorized models via `authorizedToRun()`.
4. If nothing is authorized and the action is not standalone → `danger` response.
5. Call `handle($authorizedModels, $request)`.
6. Log an [action event](action-events.md) per affected model (`action:<key>`).
7. Normalize the return value to an `ActionResponse`.

A batched variant, `executeBatched()`, chunks the IDs (default 100) for large datasets and logs per batch. It is available on the service but is not wired to the default execute endpoint.

## Endpoints

All routes are under the API prefix (`nadota-api` by default) and the `{resourceKey}/resource` group. See [API routes](../api/routes.md) and [API responses](../api/responses.md).

### List actions

```http
GET /nadota-api/{resourceKey}/resource/actions?context=index
```

`context` is `index` (default) or `detail`. Requires the `viewAny` ability.

```json
{
  "actions": [
    {
      "key": "app-nadota-actions-activate-users",
      "name": "Activate",
      "fields": [],
      "showOnIndex": true,
      "showOnDetail": true,
      "destructive": false,
      "standalone": false,
      "confirmText": null,
      "confirmButtonText": "Run Action",
      "cancelButtonText": "Cancel"
    }
  ]
}
```

### Get action fields

```http
GET /nadota-api/{resourceKey}/resource/actions/{actionKey}/fields
```

```json
{
  "fields": [
    { "label": "Subject", "attribute": "subject", "type": "text", "rules": ["required", "string", "max:255"] }
  ]
}
```

Returns `404 { "message": "Action not found." }` for an unknown key.

### Execute an action

```http
POST /nadota-api/{resourceKey}/resource/actions/{actionKey}
Content-Type: application/json

{
  "resources": [1, 2, 3],
  "subject": "Important update",
  "message": "..."
}
```

- `resources` — array of record IDs. Required unless the action is standalone; otherwise responds `422 { "message": "No resources selected." }`.
- Any action field values are sent alongside `resources`.

Success returns the `ActionResponse` body (HTTP 200). Unexpected exceptions are caught and returned as `500 { "type": "danger", "message": "<exception message>" }`. An unknown key returns `404 { "message": "Action not found." }`.

## Related

- [Action events](action-events.md) — what gets logged when actions run
- [Authorization](authorization.md) — resource and action abilities
- [API routes](../api/routes.md) and [API responses](../api/responses.md)
