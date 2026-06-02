# API Routes

Complete endpoint reference for the Nadota REST API, grouped by feature. Validated against `routes/api.php`, `routes/public.php` and the controllers.

Base prefix: `/nadota-api` (configurable — see [README](./README.md#base-url--prefix)). All examples below omit the prefix; prepend it to every path. Resource routes use the pattern `/{resourceKey}/resource/...` where `{resourceKey}` is the resource key (e.g. `users`, `posts`).

For response shapes see [responses.md](./responses.md). For filter/search query format see [filtering.md](./filtering.md).

## Navigation / Menu

| Method | Path | Route name | Purpose |
|--------|------|-----------|---------|
| GET | `/menu` | `menu` | Build the navigation tree (sections and resource items the user can view). |

See the [menu guide](../guides/menu.md). Menu items are filtered by the `viewAny` policy and the resource's `displayInMenu()`.

## Resource config

| Method | Path | Route name | Purpose |
|--------|------|-----------|---------|
| GET | `/{resourceKey}/resource/config` | `resource.config` | Everything to render the resource in one call: `resource`, `fields`, `filters`, `actions`, `sections`, `export`. |
| GET | `/{resourceKey}/resource/info` | `resource.info` | Resource info object (`toInfoArray`) plus `export` config. |
| GET | `/{resourceKey}/resource/fields` | `resource.fields` | Fields shown on index. |
| GET | `/{resourceKey}/resource/filters` | `resource.filters` | Available filters (field-based + resource-level). See [filtering.md](./filtering.md). |
| GET | `/{resourceKey}/resource/lens` | `resource.lens` | Lens configuration. |
| GET | `/{resourceKey}/resource/data` | `resource.compact` | Compact, unpaginated records. Query: `fields` (comma-separated column list). Returns a plain array. |

The `config` endpoint's `sections` key contains `detail`, `create` and `update` layouts. Each section has `type` (`default`/`section`), optional `title`/`icon`/`description`/`collapsible`/`collapsed`, and `fieldKeys`.

## CRUD

| Method | Path | Route name | Purpose |
|--------|------|-----------|---------|
| GET | `/{resourceKey}/resource` | `resource.index` | List records (paginated, searchable, filterable, sortable). |
| GET | `/{resourceKey}/resource/create` | `resource.create` | Fields with defaults for the create form. |
| POST | `/{resourceKey}/resource` | `resource.store` | Create a record (201 on success). |
| GET | `/{resourceKey}/resource/{id}` | `resource.show` | Show a single record. |
| GET | `/{resourceKey}/resource/{id}/edit` | `resource.edit` | Fields with current values for the edit form. |
| PUT | `/{resourceKey}/resource/{id}` | `resource.update` | Update a record. |
| PATCH | `/{resourceKey}/resource/{id}` | `resource.patch` | Update a record (same handler as PUT). |
| DELETE | `/{resourceKey}/resource/{id}` | `resource.destroy` | Delete a record (soft delete if the model supports it). |

### Index query parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | int | 1 | Page number (standard Laravel pagination). |
| `perPage` | int | resource default | Items per page. |
| `sortField` | string | — | Field key to sort by (must be a sortable field). |
| `sortDirection` | string | `desc` | `asc` or `desc`. |
| `<searchKey>` | string | — | Global search term. The query key is the resource's search key (default `search`); read it from `info.search.key`. |
| `filters` | object | — | Filter values sent as `filters[key]=value`. See [filtering.md](./filtering.md). |
| `mobile` | bool | auto | Force the mobile-shaped meta (also auto-detected via User-Agent). |
| `include_links` | bool | false | Include pagination `links` even on mobile. |

> Sorting falls back to the resource default sort, then to `created_at desc` if the model uses timestamps.

> Soft-delete scope (`trashed`/`withTrashed`) is applied by the resource's query when soft deletes are enabled; see [Soft deletes](#soft-deletes) and the [soft deletes guide](../guides/soft-deletes.md).

### Store / Update request body

Send field attributes as a flat JSON object keyed by attribute name:

```json
{
  "name": "John Doe",
  "email": "john@example.com"
}
```

Validation rules come from the resource's fields. On failure the response is `422` with `message` + `errors`.

## Soft deletes

Available only when the resource's model uses soft deletes (`allowedSoftDeletes` is `true` in info). Both routes require numeric `{id}`.

| Method | Path | Route name | Purpose |
|--------|------|-----------|---------|
| DELETE | `/{resourceKey}/resource/{id}/force` | `resource.forceDelete` | Permanently delete a (possibly trashed) record. |
| POST | `/{resourceKey}/resource/{id}/restore` | `resource.restore` | Restore a trashed record. |

If the resource does not support soft deletes, these return `400` with a message. See the [soft deletes guide](../guides/soft-deletes.md).

## Permissions

| Method | Path | Route name | Purpose |
|--------|------|-----------|---------|
| GET | `/{resourceKey}/resource/{id}/permissions` | `resource.permissions` | Per-record permission flags plus action URLs. Numeric `{id}`. |

Returns `data.id`, `data.permissions` (e.g. `view`, `update`, `delete`, `forceDelete`, `restore`) and `data.urls` (`show`, `edit`, `update`, `delete`, `forceDelete`, `restore`, `actionEvents`) where allowed entries are URLs and disallowed ones are `null`.

## Relations

| Method | Path | Route name | Purpose |
|--------|------|-----------|---------|
| GET | `/{resourceKey}/resource/{id}/relation/{field}` | `resource.relation.index` | Paginated items of a multi-record relation field. Numeric `{id}`. |

Supported relation field types: `HasMany`, `BelongsToMany`, `MorphMany`, `MorphToMany`, `MorphedByMany`, `HasManyThrough`. The `{field}` segment may be the field key or the relation name. Used when a relation field is configured with `->paginated()` (such fields are not loaded on the parent's show response).

### Query parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | int | 1 | Page number. |
| `per_page` | int | 15 | Items per page. |
| `search` | string | — | Searches the related resource's searchable attributes. |
| `sort_field` | string | field default | Column to order by. |
| `sort_direction` | string | `desc` | `asc` or `desc`. |
| `filters` | object | — | Filters from the related resource's filterable fields. |

The response `meta` includes `relation_type`, `has_pivot`, available `filters` and `actions`. See [relation fields](../fields/relation-fields.md).

## Actions

| Method | Path | Route name | Purpose |
|--------|------|-----------|---------|
| GET | `/{resourceKey}/resource/actions` | `resource.actions` | List actions. Query `context` = `index` (default) or `detail`. |
| GET | `/{resourceKey}/resource/actions/{actionKey}/fields` | `resource.actions.fields` | Fields of a specific action. `404` if the action is not found. |
| POST | `/{resourceKey}/resource/actions/{actionKey}` | `resource.actions.execute` | Execute an action. |

### Execute request body

```json
{
  "resources": [1, 2, 3],
  "subject": "Hello",
  "message": "Body text"
}
```

`resources` is the list of selected record IDs plus any action field values. If `resources` is empty and the action is not standalone, the response is `422` (`No resources selected.`). The execute response shape (message/danger/redirect/download/openInNewTab) is documented in [responses.md](./responses.md#action-execution) and the [actions guide](../guides/actions.md).

## Action events

Per-record audit history (when action tracking is enabled). Numeric `{id}` / `{eventId}`.

| Method | Path | Route name | Purpose |
|--------|------|-----------|---------|
| GET | `/{resourceKey}/resource/{id}/action-events` | `resource.action-events` | Paginated audit events for a record. |
| GET | `/{resourceKey}/resource/{id}/action-events/{eventId}` | `resource.action-events.show` | A single audit event. |

### List query parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | int | 1 | Page number. |
| `per_page` | int | 15 (max 100) | Events per page. |
| `name` | string | — | Filter by action name. |
| `status` | string | — | Filter by status. |
| `user_id` | int | — | Filter by acting user. |

## Attachments

For many-to-many style relations (`HasMany`, `BelongsToMany`, `MorphToMany`, `MorphMany`, `MorphedByMany`). All require numeric `{id}`. The `{field}` may be the field key or the relation name.

| Method | Path | Route name | Purpose |
|--------|------|-----------|---------|
| GET | `/{resourceKey}/resource/{id}/attachable/{field}` | `resource.attachable` | List items available to attach (paginated). |
| POST | `/{resourceKey}/resource/{id}/attach/{field}` | `resource.attach` | Attach items to the relation. |
| POST | `/{resourceKey}/resource/{id}/detach/{field}` | `resource.detach` | Detach items from the relation. |
| POST | `/{resourceKey}/resource/{id}/sync/{field}` | `resource.sync` | Sync the relation (only relation types that support sync). |

### Support by relation type

| Relation | attachable | attach | detach | sync | pivot |
|----------|:---------:|:------:|:------:|:----:|:-----:|
| HasMany | yes | yes | yes | no | no |
| BelongsToMany | yes | yes | yes | yes | yes |
| MorphToMany | yes | yes | yes | yes | yes |
| MorphedByMany | yes | yes | yes | yes | yes |
| MorphMany | yes | yes | yes | no | no |

### Request bodies

Attach uses `items` (and optional `pivot`):

```json
{
  "items": [1, 2, 3],
  "pivot": { "role": "editor", "expires_at": "2026-12-31" }
}
```

Detach:

```json
{ "items": [1, 2] }
```

Sync (optional per-id pivot, optional `detaching`):

```json
{
  "items": [1, 2, 3],
  "pivot": { "1": { "role": "admin" }, "2": { "role": "user" } },
  "detaching": true
}
```

> The request key is `items`, not `resources`. Authorization uses the `attach`/`detach` policy abilities (sync uses `attach`). Unsupported field types return `422`; missing fields `404`; unauthorized `403`. See the [attachments guide](../guides/attachments.md).

## Field options

For select / relation fields. The `{fieldName}` is the field name.

| Method | Path | Route name | Purpose |
|--------|------|-----------|---------|
| GET | `/{resourceKey}/resource/field/{fieldName}/options` | `resource.field.options` | Non-paginated options. |
| GET | `/{resourceKey}/resource/field/{fieldName}/options/paginated` | `resource.field.options.paginated` | Paginated options. |
| GET | `/{resourceKey}/resource/field/{fieldName}/morph-options/{morphType}` | `resource.field.morph.options` | Options for a specific morph type of a morph field. |
| GET | `/{resourceKey}/resource/options` | `resource.options` | Records of the resource itself as options (uses `displayLabel`). |
| GET | `/options` | `global.options` (public) | All registered resources with their filter keys/labels. No resource key. |

### Options query parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `search` | string | `''` | Search term. |
| `limit` | int | 15 | Max results (non-paginated). |
| `exclude` | array | `[]` | IDs to exclude. |
| `orderBy` | string | — | Column to order by. |
| `orderDirection` | string | `asc` | `asc` or `desc`. |
| `filters` | object | — | Additional filters (used for dependent/morph options). |
| `page` / `perPage` | int | 1 / 15 | For the paginated endpoint. |

Authorization for field/resource options checks `viewAny`, falling back to `viewOptions`.

## Export

| Method | Path | Route name | Purpose |
|--------|------|-----------|---------|
| GET | `/{resourceKey}/resource/export` | `resource.export` | Download resource data as a file (binary response). |
| GET | `/{resourceKey}/resource/export/config` | `resource.export.config` | Export configuration for the resource. |

### Export query parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `format` | string | `excel` | Export format; must be allowed (`excel`, `csv` by default). |
| `columns` | array | all | Column keys to export. |
| `filename` | string | auto | File name without extension. |
| `<searchKey>` | string | — | Apply search (same key as index). |
| `sortField` / `sortDirection` | string | — | Apply sorting. |
| `filters` | object | — | Apply filters. |

Export shares the index query pipeline (search, filters, sorting) without pagination. Returns a file download; disabled exports return `403`, invalid formats `422`.

## Notes on path ordering

Specific routes (attachments, relations, action-events, permissions) are declared before the generic `/{id}` routes and constrain `{id}` to digits, so they take precedence. `{resourceKey}` and `{fieldName}`/`{field}` are string segments.
