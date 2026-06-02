# Nadota REST API

The JSON/HTTP contract that a frontend (Inertia/SPA) consumes to drive Nadota resources.

Nadota exposes a resource-oriented REST API. A single set of routes serves every resource you register under `app/Nadota`; the resource is identified by the `{resourceKey}` path segment. The frontend reads configuration (fields, filters, actions, sections) and then performs CRUD, relation, action, attachment, export and audit operations against the same resource.

This is documentation for **Nadota 1.1.4**, namespace `SchoolAid\Nadota`.

## Overview

### Base URL / prefix

All routes are registered under a single prefix, resolved from config:

```php
// config/nadota.php
'api' => [
    'prefix' => 'nadota-api',
],
```

The default prefix is `nadota-api`, so the base URL is:

```
/nadota-api
```

> Note: the route group prefix is read with `config('nadota.prefix', 'nadota-api')` while URLs built inside responses (permissions, export) use `config('nadota.api.prefix', 'nadota-api')`. Both fall back to `nadota-api`, which is the effective default. If you change the prefix, set it consistently.

### Authentication / middleware

Protected routes apply the middleware stack configured in `config/nadota.php`:

```php
'middlewares' => [
    'api',
],
```

Add your auth middleware (e.g. `auth:sanctum`) to this array to protect the API. Authorization for each resource action is then enforced through Laravel policies (see the granular authorization in the resource layer).

There is one **public** route group (no resource middleware, `SubstituteBindings` excluded):

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/options` | Global options: every registered resource that opts in, with its filter keys/labels. |

All other routes live in the protected group.

### Recommended headers

```http
Accept: application/json
Content-Type: application/json
```

For file uploads:

```http
Content-Type: multipart/form-data
```

### The resource concept

A resource is a class extending `SchoolAid\Nadota\Resource` that maps a model to fields, filters, actions and authorization. Each resource has a `key` (its `{resourceKey}` in URLs). Resource routes follow the pattern:

```
/{prefix}/{resourceKey}/resource/...
```

The typical frontend flow:

1. `GET /menu` — build navigation.
2. `GET /{resourceKey}/resource/config` — fetch everything needed to render a resource (info, fields, filters, actions, sections, export config) in one call.
3. `GET /{resourceKey}/resource` — list records (paginated), applying search/filters/sorting.
4. CRUD + relation + action + export endpoints as the user interacts.

### Endpoint groups

| Group | Description | Reference |
|-------|-------------|-----------|
| Navigation / Menu | Build the admin navigation tree. | [routes.md#navigation--menu](./routes.md#navigation--menu) |
| Resource config | Info, fields, filters, sections, lens, compact data, export config. | [routes.md#resource-config](./routes.md#resource-config) |
| CRUD | Index, create form, store, show, edit form, update, destroy. | [routes.md#crud](./routes.md#crud) |
| Soft deletes | Restore and force-delete trashed records. | [routes.md#soft-deletes](./routes.md#soft-deletes) |
| Relations | Paginated multi-record relation listings. | [routes.md#relations](./routes.md#relations) |
| Actions | List actions, fetch action fields, execute actions. | [routes.md#actions](./routes.md#actions) |
| Action events | Per-record audit history. | [routes.md#action-events](./routes.md#action-events) |
| Attachments | Attachable items, attach, detach, sync for many-to-many relations. | [routes.md#attachments](./routes.md#attachments) |
| Field & resource options | Options for selects/relations, paginated options, morph options, resource options. | [routes.md#field-options](./routes.md#field-options) |
| Export | Download resource data (excel/csv) and export config. | [routes.md#export](./routes.md#export) |
| Permissions | Per-record permission flags and action URLs. | [routes.md#permissions](./routes.md#permissions) |

See also:

- [routes.md](./routes.md) — complete endpoint reference.
- [responses.md](./responses.md) — response envelopes, pagination meta, field/info shapes, error responses, status codes.
- [filtering.md](./filtering.md) — wire format for filters and search.
- [Filter concepts](../filters/README.md)
- [Actions guide](../guides/actions.md)
- [Attachments guide](../guides/attachments.md)
- [Menu guide](../guides/menu.md)
- [Soft deletes guide](../guides/soft-deletes.md)
- [Relation fields](../fields/relation-fields.md)
