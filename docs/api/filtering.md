# Filtering & Search

The wire format for filters and search: what the backend sends to describe available filters, and what the frontend sends to apply them. Validated against the index pipeline (`ApplyFiltersPipe`, `ApplySearchPipe`) and `GlobalOptionsController`.

For filter concepts (defining filters, custom filters, morph filters) see [filters/README.md](../filters/README.md). For routes see [routes.md](./routes.md); for the full filter object shape inside `data` see [responses.md](./responses.md).

## Backend → Frontend: filter definitions

Available filters come from:

- `GET /{resourceKey}/resource/filters` (or inside `GET /{resourceKey}/resource/config` under `filters`).
- For paginated relations, in the listing response under `meta.filters`.
- For all resources at once: `GET /options` (public) returns each resource with `key` + `filters` (filter `key`/`label` only).

Each filter object (produced by the filter's `toArray()`) typically looks like:

```json
{
  "key": "title",
  "label": "Title",
  "component": "FilterText",
  "type": "text",
  "options": [],
  "value": "",
  "props": {},
  "isRange": false,
  "filterKeys": { "value": "title" }
}
```

Key fields:

| Field | Description |
|-------|-------------|
| `key` | Unique filter key. Use it as `filters[key]` when applying. |
| `label` | Display label (may be a translation key). |
| `component` | Frontend component to render. |
| `type` | Filter type (`text`, `select`, `boolean`, `dateRange`, `dynamicSelect`, ...). |
| `options` | Options for select-style filters. |
| `value` | Default value. |
| `props` | Extra rendering/behavior props (endpoints, dependencies, ...). |
| `isRange` | Whether it is a range filter (`from`/`to`). |
| `filterKeys` | Maps the filter to request keys (`value`, or `from`/`to` for ranges). |

### Select filter

```json
{
  "key": "category_id",
  "label": "Category",
  "component": "FilterSelect",
  "type": "select",
  "options": [ { "label": "News", "value": 1 }, { "label": "Blog", "value": 2 } ],
  "isRange": false,
  "filterKeys": { "value": "category_id" }
}
```

### Boolean filter

```json
{
  "key": "is_published",
  "label": "Published",
  "component": "FilterBoolean",
  "type": "boolean",
  "options": [ { "label": "Yes", "value": "true" }, { "label": "No", "value": "false" } ],
  "filterKeys": { "value": "is_published" }
}
```

### Range filter

```json
{
  "key": "created_at",
  "label": "Created At",
  "component": "FilterDateRange",
  "type": "dateRange",
  "isRange": true,
  "filterKeys": { "from": "created_at[from]", "to": "created_at[to]" }
}
```

### Dynamic / morph filters

Dynamic-select filters carry an `endpoint` (and `endpointTemplate`) in `props`, with `dependsOn` / `filtersToSend` for dependencies:

```json
{
  "key": "commentable_id",
  "label": "Commentable",
  "component": "FilterDynamicSelect",
  "type": "dynamicSelect",
  "props": {
    "endpointTemplate": "/nadota-api/comments/resource/field/commentable/morph-options/{morphType}",
    "isMorphEndpoint": true,
    "dependsOn": ["commentable_type"],
    "filtersToSend": ["commentable_type"],
    "applyToQuery": true
  }
}
```

- `isMorphEndpoint: true` → replace `{morphType}` in the endpoint with the selected morph type value before fetching options.
- `dependsOn` → reset/refetch when those filters change.
- `filtersToSend` → include those filter values when fetching options.
- `applyToQuery: false` → the filter is auxiliary (used only to drive other filters' options) and is ignored when building the final query.

See [morph filters](../filters/README.md) for the type/entity pairing.

## Frontend → Backend: applying filters

Filters are sent as a `filters` object in the query string, keyed by each filter's `key`:

```http
GET /nadota-api/posts/resource?filters[title]=Laravel&filters[category_id]=1&filters[is_published]=true
```

Range filters use nested `from`/`to`:

```http
GET /nadota-api/posts/resource?filters[created_at][from]=2026-01-01&filters[created_at][to]=2026-12-31
```

> The index pipeline normalizes range filters: it reads the field's `filterKeys` (`from`/`to`) and rebuilds an internal `{ "start": ..., "end": ... }` payload. You send `from`/`to`; the backend maps them.

Morph filters send the type and entity together:

```http
GET /nadota-api/comments/resource?filters[commentable_type]=post&filters[commentable_id]=1
```

On the backend, `request->get('filters')` is an associative array where values are strings (or `{from, to}` arrays for ranges). Each filter converts its value to the appropriate type when applying it to the query.

### Combining filters

```http
GET /nadota-api/posts/resource?filters[title]=Laravel&filters[category_id]=1&filters[created_at][from]=2026-01-01&filters[created_at][to]=2026-12-31
```

## Search

Search is separate from filters. The query parameter name is the resource's **search key** — read it from `info.search.key` (default `search`) and whether search is `enabled`:

```http
GET /nadota-api/users/resource?search=jane
```

Behavior (from `ApplySearchPipe`):

- The term is matched with `LIKE %term%` against the resource's searchable attributes.
- Searchable relations are matched via `orWhereHas`, supporting dotted paths (`user.name`, `category.parent.title`).
- If the resource defines `applySearch()`, that custom logic is added too.
- If nothing is searchable and there is no custom search, the term is ignored.

For paginated relation listings, search uses the `search` query parameter and matches the related resource's searchable attributes:

```http
GET /nadota-api/schools/resource/1/relation/students?search=jane&page=1&per_page=25
```

## Worked example (morph filter flow)

1. Load filter definitions:

   ```http
   GET /nadota-api/comments/resource/config
   ```

   The `commentable_id` filter is a `FilterDynamicSelect` with `isMorphEndpoint` and `dependsOn: ["commentable_type"]`.

2. User selects a type (`commentable_type = "post"`). Frontend builds the endpoint by replacing `{morphType}`:

   ```http
   GET /nadota-api/comments/resource/field/commentable/morph-options/post
   ```

   Response:

   ```json
   { "success": true, "options": [ { "value": 1, "label": "Post 1" } ] }
   ```

3. User selects an entity and applies:

   ```http
   GET /nadota-api/comments/resource?filters[commentable_type]=post&filters[commentable_id]=1
   ```

   The response is the standard [index envelope](./responses.md#index-list-envelope).

## Mapping summary

| Filter `key` in definition | Request | Result |
|----------------------------|---------|--------|
| `title` | `filters[title]=value` | `WHERE title LIKE '%value%'` |
| `category_id` | `filters[category_id]=1` | `WHERE category_id = 1` |
| `is_published` | `filters[is_published]=true` | `WHERE is_published = 1` |
| `created_at` (range) | `filters[created_at][from]=...&filters[created_at][to]=...` | `WHERE created_at BETWEEN ...` |
| `commentable_type` | `filters[commentable_type]=post` | morph type resolved to the model class |
| `commentable_id` | `filters[commentable_id]=1` | `WHERE commentable_id = 1` |

The exact SQL depends on each filter's implementation — see [filters/README.md](../filters/README.md).
