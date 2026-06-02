# Exports

Stream a resource's records to CSV or Excel, honoring the active search, filters, and sorting, with selectable columns.

Export is enabled by default on every resource via the `ResourceExportable` trait. The frontend fetches an export configuration, then hits the export endpoint with a chosen format and optional column selection. Data is streamed in memory-efficient chunks.

## Quick start

Export is on by default — no setup required. The endpoint reuses the same query pipeline as the index, so any `search`, `filter`, or `sort` query parameters apply to the export:

```http
GET /nadota-api/users/resource/export?format=csv
```

The response is a streamed download (`Content-Disposition: attachment`). With no `filename`, the default is `<resourceKey>_<Y-m-d_His>` plus the format extension.

## Supported formats

Two exporters ship with the package:

| Format | Exporter | Extension | Content type |
|--------|----------|-----------|--------------|
| `excel` (default) | `ExcelExporter` | `.xlsx` | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` |
| `csv` | `CsvExporter` | `.csv` | `text/csv; charset=UTF-8` |

Defaults come from `config/nadota.php`:

```php
'export' => [
    'enabled'        => env('NADOTA_EXPORT_ENABLED', true),
    'formats'        => ['excel', 'csv'],
    'default_format' => 'excel',
    'sync_limit'     => env('NADOTA_EXPORT_SYNC_LIMIT', 1000),
    'chunk_size'     => env('NADOTA_EXPORT_CHUNK_SIZE', 500),
],
```

The CSV exporter writes a UTF-8 BOM by default (for Excel compatibility) and uses `,` / `"` as delimiter/enclosure. The Excel exporter bold-styles and freezes the header row and auto-sizes columns.

## Configuring a resource

The `ResourceExportable` trait exposes properties you can override on your resource:

```php
class UserResource extends Resource
{
    protected bool $exportEnabled = true;

    // Limit formats for this resource (defaults to config('nadota.export.formats'))
    protected ?array $allowedExportFormats = ['csv'];

    // Default selected columns (null = all exportable columns selected)
    protected ?array $defaultExportColumns = ['name', 'email'];

    // Threshold for synchronous export (informational; see roadmap note)
    protected int $syncExportLimit = 1000;
}
```

| Property / method | Purpose |
|-------------------|---------|
| `$exportEnabled` / `isExportEnabled()` | Toggle export for the resource |
| `$allowedExportFormats` / `getAllowedExportFormats()` | Restrict formats (falls back to config) |
| `$defaultExportColumns` / `getDefaultExportColumns()` | Pre-selected columns; `null` = all |
| `$syncExportLimit` / `getSyncExportLimit()` | Reported in the config payload |

### Which fields are exported

`getExportableFields()` selects fields that are shown on index or explicitly marked exportable, then filters with `isFieldExportable()`:

- Non-relationship fields are exportable.
- Relationship fields are excluded **except** single-value relations: `BelongsTo`, `MorphTo`, and `HasOne`.
- A field explicitly marked exportable is always included.

Override these methods to customize. Row values come from each field's `resolveForExport()` (so, e.g., a `Select` exports its label, not its raw value), then `formatExportValue()` normalizes them: `null` → `''`, arrays/objects → JSON, booleans → `Yes`/`No`.

Headers come from `getExportHeaders()`, which runs each field name through `translateExportHeader()` (Laravel `__()` translation). Override either to customize labels.

## Endpoints

Routes live under the API prefix (`nadota-api` by default) within the `{resourceKey}/resource` group. Both require the `viewAny` ability. See [API routes](../api/routes.md).

### Export config

```http
GET /nadota-api/{resourceKey}/resource/export/config
```

```json
{
  "data": {
    "enabled": true,
    "url": "/nadota-api/users/resource/export",
    "formats": [
      { "format": "excel", "extension": "xlsx" },
      { "format": "csv", "extension": "csv" }
    ],
    "syncLimit": 1000,
    "defaultColumns": ["name", "email"],
    "columns": [
      { "key": "name", "label": "Name", "selected": true },
      { "key": "email", "label": "Email", "selected": true },
      { "key": "created_at", "label": "Created At", "selected": false }
    ]
  }
}
```

`enabled` reflects both `isExportEnabled()` and the `export` ability. Each column's `selected` flag is `true` when `defaultColumns` is `null` (all) or the key is listed.

### Export

```http
GET /nadota-api/{resourceKey}/resource/export?format=csv&columns[]=name&columns[]=email&filename=my-users
```

| Parameter | Type | Default | Notes |
|-----------|------|---------|-------|
| `format` | string | config default (`excel`) | Must be in the resource's allowed formats |
| `columns` | array | all exportable | Subset of field keys to include |
| `filename` | string | `<key>_<timestamp>` | Without extension |

Plus any index query parameters (`search`, filters, sorting) — they flow through the `BuildQuery → ApplySearch → ApplyFilters → ApplySorting` pipeline (no pagination), so the export matches what the user is viewing.

Error responses:

- `403` — export not enabled for the resource (`Export is not enabled for this resource.`) or the `viewAny` ability is denied.
- `422` — disallowed format (`Export format '<format>' is not allowed. Allowed formats: ...`).

## Memory efficiency

`ResourceExportService` builds a `LazyCollection` that pages through the query in chunks of 500 (matching `AbstractExporter`), re-running the eager-load constraints from the query builder per batch to avoid N+1 queries on relation fields. Both exporters write to `php://output` as a `StreamedResponse`.

## Registering a custom exporter

`ResourceExportService::registerExporter($format, $exporterClass)` adds a format at runtime. Custom exporters implement `SchoolAid\Nadota\Contracts\ExporterInterface`:

```php
public function export(LazyCollection $data, array $headers, string $filename): Response;
public function getContentType(): string;
public function getExtension(): string;
```

Extending `AbstractExporter` gives you `streamResponse()`, `formatRow()`, and a configurable `chunkSize()`.

## Roadmap items not implemented

The roadmap at `docs/export-feature-roadmap.md` describes features that are **not** part of the current implementation:

- **PDF export** (`PdfExporter`, `barryvdh/laravel-dompdf`) — only `csv` and `excel` exist.
- **Asynchronous / queued exports** (`ProcessExportJob`, "export queued" responses) — all exports run synchronously and stream directly. The `sync_limit` / `syncExportLimit` value is surfaced in the config payload but is not enforced server-side.

## Related

- [Authorization](authorization.md) — the `viewAny`/`export` abilities
- [Resources](resources.md) — field visibility that drives exportable columns
- [API routes](../api/routes.md), [API responses](../api/responses.md)
