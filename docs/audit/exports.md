# Audit — Exports

Functional audit of CSV/XLSX exports (Nadota 1.3.0, 2026-11-02): the full request flow, every
configuration key and per-field option with its real effect, what is tested, and the issues
found. Issues are described in full in [`docs/testing/findings/audit.md`](../testing/findings/audit.md).
HTTP tests for the export routes belong to the OPS area (`tests/Feature/Http/Export`); this audit adds
service-level and exporter-level tests only.

## Purpose

Let a user download what the index shows (same search, filters and sort, no pagination) as CSV or
XLSX, with selectable columns.

## Pieces

| Piece | File | Role |
|-------|------|------|
| Routes | `routes/api.php:22-23` | `GET /{resourceKey}/resource/export`, `GET /{resourceKey}/resource/export/config` |
| Controller | `src/Http/Controllers/ExportController.php` | `export()` → `ResourceExportInterface::handle()`; `config()` → `Resource::getExportConfig()` |
| Service | `src/Http/Services/ResourceExportService.php` | Authorization, format validation, query pipeline, lazy paging, exporter dispatch |
| DTO | `src/Http/DataTransferObjects/ExportRequestDTO.php` | Extends `IndexRequestDTO` with format, columns, filename |
| Resource trait | `src/Http/Traits/ResourceExportable.php` | Exportable fields, headers, row transform, value formatting, config payload |
| Exporters | `src/Http/Services/Exporters/{AbstractExporter,CsvExporter,ExcelExporter}.php` | Write the file as a `StreamedResponse` |
| Field API | `src/Http/Fields/Field.php:405-485, 833` + overrides | `exportable()`, `exportUsing()`, `exportSeparator()`, `exportLimit()`, `resolveForExport()` |

`/info` and `/config` also embed `getExportConfig()` (`ResourceIndexController.php:32, 138`).

## Flow

```mermaid
flowchart TD
    A[GET /export?format&columns[]&filename&search/filters/sort] --> B[ExportController ctor: validateResource → 404]
    B --> C[ResourceExportService::handle]
    C --> D["authorized('viewAny') → 403<br/>(the 'export' ability is NOT checked, AUD-013)"]
    D --> E{resource->isExportEnabled()?}
    E -- no --> E1[abort 403]
    E -- yes --> F["format = input('format') ?? config('nadota.export.default_format')"]
    F --> G{format in getAllowedExportFormats()?}
    G -- no --> G1[abort 422]
    G -- yes --> H[ExportRequestDTO(request, resource, format, columns, filename)]
    H --> I[Pipeline: BuildQueryPipe → ApplySearchPipe → ApplyFiltersPipe → ApplySortingPipe]
    I --> J["BuildQueryPipe selects the INDEX fields' columns<br/>and eager-loads their relations (AUD-015)"]
    J --> K["fields = getExportableFields() filtered by columns[]<br/>headers = getExportHeaders() filtered by columns[]"]
    K --> L["LazyCollection: (clone query)->forPage(page, 500)->get()<br/>→ transformForExport(model) per row (AUD-019)"]
    L --> M{exporter}
    M -- csv --> N[CsvExporter: StreamedResponse, rows written while streaming]
    M -- excel --> O[ExcelExporter: whole workbook built in memory, then streamed]
```

### Which fields are exported

`ResourceExportable::getExportableFields()`:

1. `flattenFields($request)` (sections flattened).
2. keep `isShowOnIndex()` **called without the request** (AUD-016) `|| isExportable()`.
3. keep `isFieldExportable()`: explicitly exportable → yes; relation → only `BelongsTo`, `MorphTo`, `HasOne`
   (by `isBelongsTo()` / `isMorphTo()` / `isHasOne()`); everything else → yes.

Then `columns[]` (if any) narrows by field key. Headers are `__($field->getName())`.

### Row values

`transformForExport()` → `$field->resolveForExport($request, $model, $resource)` → `formatExportValue()`:

| Value | Written as |
|-------|------------|
| `null` | `''` |
| array / object | `json_encode()` |
| bool | `Yes` / `No` (not translated) |
| anything else | as is — strings starting with `=`, `+`, `-`, `@` are **not** neutralised (AUD-017) |

Field-specific `resolveForExport()`:

| Field | Export value |
|-------|--------------|
| default `Field` | `resolve()` (the index value) |
| `Select`, `Status` | option label |
| `Currency` | formatted value |
| `BelongsTo`, `HasOne`, `MorphTo` | display label of the related model (via the related resource) |
| `HasMany`, `BelongsToMany`, `MorphMany`, `MorphToMany` | related values joined by `exportSeparator` — one query per row, falsy values dropped (AUD-020) |

## Configuration — real effect

| Key | Default | Read by | Real effect |
|-----|---------|---------|-------------|
| `export.enabled` | `env('NADOTA_EXPORT_ENABLED', true)` | nobody | **none** (AUD-014) |
| `export.formats` | `['excel', 'csv']` | `ResourceExportable.php:48` | default for `getAllowedExportFormats()` when the resource leaves `$allowedExportFormats` null |
| `export.default_format` | `'excel'` | `ResourceExportService.php:40`, `ExportRequestDTO.php:23` | format when the request names none. Not checked against the resource's formats: a resource limited to `['csv']` answers 422 to a request without `format` |
| `export.sync_limit` | `env('NADOTA_EXPORT_SYNC_LIMIT', 1000)` | nobody | **none**; `syncLimit` in the payload is `$syncExportLimit` (hard-coded 1000), never enforced (AUD-014, AUD-019) |
| `export.chunk_size` | `env('NADOTA_EXPORT_CHUNK_SIZE', 500)` | nobody | **none**; the service pages by its own `$chunkSize = 500`; `AbstractExporter::chunkSize()` has no consumer (AUD-014) |
| `api_prefix` (undeclared) | — | `ResourceExportable.php:216` | prefix of the `url` in the export config (CORE-001) |

## Per-resource options (`ResourceExportable`)

| Property / method | Default | Effect |
|-------------------|---------|--------|
| `$exportEnabled` / `isExportEnabled()` | `true` | 403 from `/export` when false; `enabled` in the config payload |
| `$allowedExportFormats` / `getAllowedExportFormats()` | `null` → `export.formats` | 422 for other formats; listed in the payload with extensions (`excel` → `xlsx`) |
| `$defaultExportColumns` / `getDefaultExportColumns()` | `null` (all) | `selected` flag per column in the payload only — the endpoint exports all columns unless `columns[]` is sent |
| `$syncExportLimit` / `getSyncExportLimit()` | `1000` | reported as `syncLimit`; not enforced |
| `getExportableFields()`, `isFieldExportable()` | see above | override to choose columns |
| `getExportHeaders()`, `translateExportHeader()` | `__($name)` | override labels |
| `transformForExport()`, `formatExportValue()` | see above | override row/value formatting |
| `getExportFormatExtensions()` | `excel → xlsx`, `csv → csv` | extension list in the payload |
| policy ability `export` | — | only turns `enabled` false in the payload (AUD-013) |

## Per-field options

```php
HasMany::make('Courses', 'courses', CourseResource::class)
    ->exportable()              // include although relations are excluded / hidden from index
    ->exportUsing('code')       // attribute of each related item (also implies exportable())
    ->exportSeparator(' | ')    // join string (default ', ')
    ->exportLimit(10);          // at most N related items per row

URL::make('Website', 'website')->hideFromIndex()->exportable(); // header exported, value empty (AUD-015)
```

`exportUsing` / `exportSeparator` / `exportLimit` are only used by the four to-many relations.

## Extension points

### Register a format

```php
use SchoolAid\Nadota\Contracts\ResourceExportInterface;

$this->app->afterResolving(ResourceExportInterface::class, function ($service) {
    $service->registerExporter('json', JsonExporter::class);   // implements ExporterInterface
});

// and allow it, globally or per resource:
config(['nadota.export.formats' => ['excel', 'csv', 'json']]);
protected ?array $allowedExportFormats = ['csv', 'json'];
```

`registerExporter()` mutates the singleton bound in `ServiceBindingServiceProvider`. The payload extension
for an unknown format falls back to the format name unless `getExportFormatExtensions()` is overridden.

### Replace the service

Bind another `ResourceExportInterface` (single method `handle(NadotaRequest): Response`).

### Customise exporters

`CsvExporter::delimiter()`, `enclosure()`, `withoutBom()`; `ExcelExporter::withoutAutoSize()`,
`withoutFreezeHeader()`, `withoutBoldHeaders()`. The service resolves exporters with `app($class)`, so
configure them with `$this->app->bind(CsvExporter::class, fn () => (new CsvExporter())->delimiter(';'))`.

## Response

- CSV: `text/csv; charset=UTF-8`, UTF-8 BOM, `,`/`"`, rows written while the response streams (database
  queries run after the controller returns; an exception mid-stream truncates a 200 response).
- XLSX: workbook built in memory before the response is returned, then written to `php://output`.
- `Content-Disposition: attachment; filename="<filename>.<ext>"` with `<filename>` taken verbatim from
  the request (AUD-018). Default filename `<resourceKey>_<Y-m-d_His>`.

## Test coverage

| Behaviour | Test |
|-----------|------|
| CSV body (headers, rows, Select label), default format from config, 422 format, 403 disabled resource, 403 `viewAny`, `columns[]` + `filename`, index search applied | `tests/Unit/Export/ResourceExportServiceTest.php` |
| `export` ability (AUD-013), `export.enabled` / `sync_limit` / `chunk_size` (AUD-014), hidden exportable column (AUD-015), request-aware visibility (AUD-016) | same file, skipped |
| Formula injection XLSX/CSV (AUD-017), Content-Disposition quoting (AUD-018), unused exporter chunk size | `tests/Unit/Export/ExporterHardeningTest.php` |
| Exporters basics, trait defaults, HasOne exportability (pre-existing) | `tests/Unit/Export/{CsvExporter,ExcelExporter,ResourceExportable}Test.php` |
| HTTP routes | OPS area: `tests/Feature/Http/Export/` |

Fixtures: `tests/Fixtures/Export/AuditExportStudentResource.php` (name, status Select, website hidden but
exportable, email visible on index only to user 7), `DenyExportPolicy.php`.

Not covered by tests (code reading only): AUD-019 (paging determinism needs MySQL/PostgreSQL), AUD-020 (N+1).

## Issues

| ID | Summary | Severity |
|----|---------|----------|
| [AUD-013](../testing/findings/audit.md#aud-013--export-ignores-the-export-ability-that-exportconfig-reports) | `/export` ignores the `export` ability | high |
| [AUD-014](../testing/findings/audit.md#aud-014--exportenabled-exportsync_limit-exportchunk_size-are-never-read) | Three export config keys are never read | medium |
| [AUD-015](../testing/findings/audit.md#aud-015--fields-hidden-from-index-but-exportable-are-exported-empty) | Hidden `exportable()` fields exported empty | medium |
| [AUD-016](../testing/findings/audit.md#aud-016--export-evaluates-index-visibility-without-the-request) | Visibility callbacks get `null` instead of the request | medium |
| [AUD-017](../testing/findings/audit.md#aud-017--spreadsheet-formula-injection-in-xlsx-and-csv-exports) | Formula injection | medium |
| [AUD-018](../testing/findings/audit.md#aud-018--filename-is-put-into-content-disposition-unescaped) | Unescaped filename in header | low |
| [AUD-019](../testing/findings/audit.md#aud-019--no-size-guard-xlsx-built-fully-in-memory-offset-paging-without-a-tiebreaker) | No size guard, in-memory XLSX, offset paging | medium |
| [AUD-020](../testing/findings/audit.md#aud-020--to-many-exportable-relations-query-once-per-row-and-drop-falsy-values) | To-many relations: N+1, falsy values dropped | low |
| Related | CORE-001 (`api_prefix` in the export URL) | |

### Doc contradictions in `docs/guides/exports.md`

- "Both [endpoints] require the `viewAny` ability" is accurate, but listing the `export` ability under
  Authorization suggests it gates the download; it does not (AUD-013).
- The `export` config block is presented as effective; three of its five keys are not (AUD-014).
- "re-running the eager-load constraints … to avoid N+1 queries on relation fields" holds for
  single-value relations only (AUD-020); "memory-efficient" holds for CSV only (AUD-019).
- Examples use plural keys (`/nadota-api/users/resource/export`); keys are singular (`user`).
