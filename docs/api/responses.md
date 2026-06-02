# API Responses

Response envelopes and structures returned by the Nadota REST API, validated against `src/Http/Resources/**` and the service classes. Field shapes inside `attributes`/`fields` are produced by each field's `toArray()` and vary by field type; the keys below are the stable envelope keys.

For routes see [routes.md](./routes.md). For filter shapes see [filtering.md](./filtering.md).

## Index (list) envelope

`GET /{resourceKey}/resource` returns a paginated collection (`IndexResource`):

```json
{
  "data": [
    {
      "id": 1,
      "attributes": [
        { "label": "Name", "attribute": "name", "value": "John", "type": "text" }
      ],
      "deletedAt": null,
      "permissions": { "view": true, "update": true, "delete": true }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73,
    "from": 1,
    "to": 15
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

Notes:

- Each item: `id` (only if the resource includes IDs), `attributes` (array of transformed fields), `deletedAt`, `permissions`.
- `attributes` is an **array** of field objects, not a keyed map.
- Actions are **not** embedded per item — fetch them once via `/config` or `/actions`.

### Pagination meta (mobile)

For mobile requests (`?mobile=true` or a mobile User-Agent), `meta` adds simplified flags and `links` is omitted unless `?include_links=true`:

```json
{
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73,
    "from": 1,
    "to": 15,
    "has_more": true,
    "has_previous": false
  }
}
```

## Show envelope

`GET /{resourceKey}/resource/{id}`:

```json
{
  "data": {
    "id": 1,
    "key": "users",
    "attributes": [ { "label": "Name", "attribute": "name", "value": "John", "type": "text" } ],
    "permissions": { "view": true, "update": true, "delete": true },
    "title": "Users",
    "deletedAt": null,
    "detailCardWidth": "full",
    "tools": [],
    "actionEventsUrl": "/nadota-api/users/resource/1/action-events"
  }
}
```

`id` is included only if the resource includes IDs. `tools` and `actionEventsUrl` are present only for the `show` action (not when the show service is invoked with `action=update`). If the resource defines a custom show/edit response resource, that resource's output is returned instead of this envelope.

## Edit form envelope

`GET /{resourceKey}/resource/{id}/edit`:

```json
{
  "data": {
    "id": 1,
    "key": "users",
    "attributes": [ { "label": "Name", "attribute": "name", "value": "John", "type": "text", "rules": ["required"] } ],
    "permissions": { "update": true },
    "title": "Users",
    "deletedAt": null
  }
}
```

## Create form envelope

`GET /{resourceKey}/resource/create`:

```json
{
  "data": {
    "key": "users",
    "attributes": [ { "label": "Name", "attribute": "name", "value": null, "type": "text", "rules": ["required", "string", "max:255"] } ],
    "title": "Users"
  }
}
```

## Store / Update response

`POST /{resourceKey}/resource` (201) and `PUT|PATCH /{resourceKey}/resource/{id}` (200):

```json
{
  "message": "Resource created successfully",
  "data": { "id": 1, "name": "John Doe", "email": "john@example.com" }
}
```

`data` is the saved model. The update message is `Resource updated successfully`. There is **no** `success` boolean on these responses.

## Delete / Restore / Force delete

`DELETE /{resourceKey}/resource/{id}` (200):

```json
{ "message": "Resource deleted successfully" }
```

`POST /{resourceKey}/resource/{id}/restore` (200):

```json
{ "message": "Resource restored successfully", "data": { "id": 1 } }
```

`DELETE /{resourceKey}/resource/{id}/force` (200):

```json
{ "message": "Resource permanently deleted" }
```

If a resource does not support soft deletes, restore/force return `400` with a message. Operation failures inside the transaction return `500` with `message` + `error`.

## Permissions

`GET /{resourceKey}/resource/{id}/permissions`:

```json
{
  "data": {
    "id": 1,
    "permissions": { "view": true, "update": true, "delete": true, "forceDelete": false, "restore": false },
    "urls": {
      "show": "/nadota-api/users/resource/1",
      "edit": "/nadota-api/users/resource/1/edit",
      "update": "/nadota-api/users/resource/1",
      "delete": "/nadota-api/users/resource/1",
      "forceDelete": null,
      "restore": null,
      "actionEvents": "/nadota-api/users/resource/1/action-events"
    }
  }
}
```

Each URL is `null` when the corresponding permission is denied. The exact permission keys depend on the resource's authorization configuration.

## Info resource

`GET /{resourceKey}/resource/info` returns the resource info object (`toInfoArray`) merged with extra data:

```json
{
  "key": "users",
  "title": "Users",
  "description": "Manage system users",
  "perPage": 15,
  "allowedPerPage": [15, 25, 50, 100],
  "allowedSoftDeletes": false,
  "canCreate": true,
  "components": {
    "index": "ResourceIndex",
    "show": "ResourceShow",
    "create": "ResourceCreate",
    "update": "ResourceUpdate",
    "delete": "ResourceDelete"
  },
  "detailCardWidth": "full",
  "mainCard": { "collapsible": false, "defaultCollapsed": false, "title": null },
  "search": { "key": "search", "enabled": true },
  "selection": { "showRowCheckbox": false, "showSelectAll": false },
  "pollingInterval": null,
  "export": { "enabled": true, "url": "...", "formats": [], "syncLimit": 1000, "defaultColumns": null, "columns": [] }
}
```

> The `info` endpoint returns this object **directly** (not wrapped in `data`).

## Config envelope

`GET /{resourceKey}/resource/config` returns a single object (not wrapped in `data`):

```json
{
  "resource": { "...": "same shape as info (toInfoArray)" },
  "fields": [ { "...": "field shape" } ],
  "filters": [ { "...": "filter shape" } ],
  "actions": [ { "...": "action shape" } ],
  "sections": {
    "detail": [ { "type": "section", "title": "Personal", "icon": "user", "collapsible": true, "collapsed": false, "fieldKeys": ["name"] } ],
    "create": [],
    "update": []
  },
  "export": { "enabled": true, "url": "...", "formats": [], "syncLimit": 1000, "defaultColumns": null, "columns": [] }
}
```

## Fields / Filters list

`GET /{resourceKey}/resource/fields` and `/filters` return collections wrapped in `data`:

```json
{
  "data": [
    { "key": "name", "label": "Name", "attribute": "name", "component": "FieldText", "sortable": true, "filterable": false }
  ]
}
```

Filter object shape is documented in [filtering.md](./filtering.md).

## Menu

`GET /menu` returns an array (not wrapped). Resource items and section items have different shapes:

Resource item:

```json
{
  "label": "Users",
  "key": "users",
  "icon": "user",
  "apiUrl": "/nadota-api/users/resource",
  "frontendUrl": "/resources/users",
  "children": [],
  "order": 0,
  "isResource": true
}
```

Section:

```json
{
  "isSection": true,
  "title": "Administration",
  "icon": "cog",
  "enableSearch": true,
  "children": [ { "...": "resource items" } ],
  "order": 0
}
```

## Relation listing

`GET /{resourceKey}/resource/{id}/relation/{field}`:

```json
{
  "data": [
    {
      "id": 1,
      "key": 1,
      "label": "Jane",
      "resource": "students",
      "attributes": [ { "key": "name", "value": "Jane", "type": "text" } ],
      "deletedAt": null,
      "pivot": { "enrolled_at": "2026-01-15" },
      "permissions": { "view": true }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 120,
    "from": 1,
    "to": 15,
    "resource": "students",
    "relation_type": "belongsToMany",
    "has_pivot": true,
    "filters": [],
    "actions": []
  },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

Per-item notes:

- `attributes` appears only when the field is configured with `->withFields()`.
- `pivot` appears only for pivot relations configured with `->withPivot([...])`.
- `id`/`key` are omitted when the field uses `->withoutId()`.

Errors: field not found `404`; not a multi-record relation `422`; relation method missing `404`.

## Action execution

`POST /{resourceKey}/resource/actions/{actionKey}` returns the action's result `toArray()`. The frontend should branch on `type`:

```json
{ "type": "message", "message": "Email sent to 3 users." }
```

```json
{ "type": "danger", "message": "Could not send email." }
```

```json
{ "type": "redirect", "url": "/resources/users/1" }
```

```json
{ "type": "download", "url": "/storage/exports/report.pdf", "filename": "report.pdf" }
```

```json
{ "type": "openInNewTab", "url": "https://example.com/report", "openInNewTab": true }
```

Unhandled exceptions during execution return `500` with `{ "type": "danger", "message": "..." }`. See the [actions guide](../guides/actions.md).

## Action events

`GET /{resourceKey}/resource/{id}/action-events`:

```json
{
  "data": [
    {
      "id": 1,
      "batchId": "uuid",
      "name": "update",
      "nameLabel": "Updated",
      "status": "finished",
      "user": { "id": 1, "name": "Admin", "email": "admin@example.com" },
      "modelType": "App\\Models\\User",
      "modelId": 1,
      "fields": {},
      "original": {},
      "changes": {},
      "exception": null,
      "createdAt": "2026-01-15 10:30:00",
      "updatedAt": "2026-01-15 10:30:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 5 }
}
```

`GET /{resourceKey}/resource/{id}/action-events/{eventId}` returns a single event under `data`. `user` is `null` for system/unattributed actions.

## Field options

Non-paginated (`/field/{fieldName}/options` and morph options):

```json
{
  "success": true,
  "options": [ { "value": 1, "label": "Option A" } ],
  "meta": { "total": 2, "search": "", "limit": 15, "fieldType": "belongsTo" }
}
```

Paginated (`/field/{fieldName}/options/paginated`):

```json
{
  "success": true,
  "data": [ { "value": 1, "label": "Option A" } ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 150,
    "last_page": 10,
    "from": 1,
    "to": 15,
    "search": "",
    "fieldType": "belongsTo"
  }
}
```

Resource options (`/options`):

```json
{
  "success": true,
  "options": [ { "value": 1, "label": "User 1" } ],
  "meta": { "total": 2, "search": "", "keys": ["name"], "limit": 15 }
}
```

On error these endpoints return `success: false` with a `message` and empty `options`/`data`.

## Attachments

Attachable (`GET /{id}/attachable/{field}`):

```json
{
  "success": true,
  "data": [ { "id": 1, "label": "Option 1" } ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 2, "attached_count": 0 }
}
```

Attach (`POST /{id}/attach/{field}`):

```json
{ "success": true, "message": "Items attached successfully", "attached": [1, 2, 3], "count": 3 }
```

If all items are already attached: `{ "success": true, "message": "All items are already attached", "attached": [], "already_attached": 3 }`.

Detach:

```json
{ "success": true, "message": "Items detached successfully", "detached": 2 }
```

Sync:

```json
{ "success": true, "message": "...", "attached": [3], "detached": [4, 5], "updated": [1, 2] }
```

Attachment errors: field not found `404`; not attachable / unsupported type / sync not supported `422`; unauthorized `403`.

## Compact data

`GET /{resourceKey}/resource/data` returns a plain array (no envelope):

```json
[
  { "id": 1, "name": "John" },
  { "id": 2, "name": "Jane" }
]
```

## Export config

`GET /{resourceKey}/resource/export/config`:

```json
{
  "data": {
    "enabled": true,
    "url": "/nadota-api/users/resource/export",
    "formats": [ { "...": "format with extension" } ],
    "syncLimit": 1000,
    "defaultColumns": null,
    "columns": [ { "key": "name", "label": "Name", "selected": true } ]
  }
}
```

`GET /{resourceKey}/resource/export` returns a binary file download (Excel/CSV), with `Content-Disposition: attachment` headers — not JSON.

## Error responses

| Code | Body | When |
|------|------|------|
| 401 | `{ "message": "Unauthenticated." }` | Missing/invalid auth (from your middleware). |
| 403 | `{ "message": "This action is unauthorized." }` | Policy denial. Attachment/options endpoints may return `{ "success": false, "message": "Unauthorized" }` or abort with `403`. |
| 404 | `{ "message": "..." }` | Record not found (`findOrFail`). Action/field lookups return `{ "message": "Action not found." }` or `{ "error": "Field not found", "field": "..." }`. |
| 422 | `{ "message": "Validation failed", "errors": { "email": ["..."] } }` | Validation failure on store/update. Also used for invalid export format, no resources selected for an action, and unsupported attachment operations. |
| 500 | `{ "message": "Failed to ...", "error": "..." }` | Transaction failure in a CRUD operation. Action execution returns `{ "type": "danger", "message": "..." }`. |

## HTTP status codes

| Code | Meaning |
|------|---------|
| 200 | Success. |
| 201 | Resource created. |
| 400 | Operation not supported (e.g. restore/force on a non-soft-delete resource). |
| 403 | Forbidden / unauthorized. |
| 404 | Not found. |
| 422 | Validation / unprocessable. |
| 500 | Server error. |
