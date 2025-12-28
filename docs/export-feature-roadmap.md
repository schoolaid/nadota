# Export Feature - Roadmap de Implementación

## Objetivo
Permitir exportar los datos del index de un resource, respetando los filtros, búsqueda y ordenamiento aplicados.

## Arquitectura Propuesta

### Flujo de Exportación
```
Request → ResourceExportService → Pipeline (reutilizar pipes existentes) → ExportPipe → Response/Job
```

### Formatos Soportados
- **CSV** - Ligero, universal
- **XLSX** - Excel con formato (usando `maatwebsite/excel` o `openspout/openspout`)
- **PDF** - Para reportes formales (usando `barryvdh/laravel-dompdf`)

---

## Fases de Implementación

### Fase 1: Infraestructura Base

#### 1.1 Crear Contratos/Interfaces
```
src/Contracts/ExportInterface.php
src/Contracts/ExporterInterface.php
```

#### 1.2 Crear DTO para Export
```php
// src/Http/DataTransferObjects/ExportRequestDTO.php
class ExportRequestDTO extends IndexRequestDTO
{
    public string $format;      // csv, xlsx, pdf
    public ?array $columns;     // columnas específicas a exportar
    public ?string $filename;   // nombre personalizado
    public bool $async;         // exportar en background
}
```

#### 1.3 Crear Trait Exportable en Resource
```php
// src/Http/Traits/ResourceExportable.php
trait ResourceExportable
{
    // Columnas exportables (por defecto: campos de index)
    public function exportableFields(NadotaRequest $request): Collection

    // Formatos permitidos
    public function allowedExportFormats(): array

    // Límite de registros para export síncrono
    public function syncExportLimit(): int

    // Personalizar headers de columnas
    public function exportHeaders(): array

    // Hook para transformar fila antes de exportar
    public function transformForExport(Model $model, array $row): array
}
```

### Fase 2: Servicio de Exportación

#### 2.1 ResourceExportService
```php
// src/Http/Services/ResourceExportService.php
class ResourceExportService implements ExportInterface
{
    public function handle(NadotaRequest $request): Response|PendingExport
    {
        // 1. Validar permisos (viewAny + export)
        // 2. Determinar si async o sync según cantidad
        // 3. Ejecutar pipeline sin PaginateAndTransformPipe
        // 4. Pasar a ExportPipe con formato seleccionado
    }
}
```

#### 2.2 Export Pipe (reemplaza PaginateAndTransformPipe)
```php
// src/Http/Services/Pipes/ExportPipe.php
class ExportPipe
{
    public function handle(ExportRequestDTO $data, Closure $next)
    {
        // Sin paginación - obtener todos los registros
        // Aplicar chunk para memoria eficiente
        // Transformar y escribir al formato seleccionado
    }
}
```

### Fase 3: Exporters por Formato

#### 3.1 Estructura de Exporters
```
src/Http/Services/Exporters/
├── AbstractExporter.php
├── CsvExporter.php
├── XlsxExporter.php
└── PdfExporter.php
```

#### 3.2 AbstractExporter
```php
abstract class AbstractExporter implements ExporterInterface
{
    abstract public function export(Collection $data, array $headers): mixed;
    abstract public function getContentType(): string;
    abstract public function getExtension(): string;

    protected function streamResponse(string $filename): StreamedResponse;
}
```

#### 3.3 CsvExporter (nativo, sin dependencias)
```php
class CsvExporter extends AbstractExporter
{
    public function export(Collection $data, array $headers): StreamedResponse
    {
        return response()->streamDownload(function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($data->lazy()->chunk(1000) as $chunk) {
                foreach ($chunk as $row) {
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        }, $this->filename, ['Content-Type' => 'text/csv']);
    }
}
```

### Fase 4: Exportación Asíncrona (Background Jobs)

#### 4.1 Job de Exportación
```php
// src/Jobs/ProcessExportJob.php
class ProcessExportJob implements ShouldQueue
{
    public function handle(): void
    {
        // 1. Ejecutar query con chunks
        // 2. Escribir archivo temporal
        // 3. Mover a storage (local/s3)
        // 4. Notificar al usuario (evento/notificación)
        // 5. Crear registro en exports table
    }
}
```

#### 4.2 Modelo Export (tracking)
```php
// src/Models/Export.php
// Campos: id, user_id, resource, filename, format, status, path, created_at, expires_at
```

#### 4.3 Endpoints para Async
```
GET  /nadota-api/{resource}/exports          → Lista exports del usuario
POST /nadota-api/{resource}/export           → Iniciar export
GET  /nadota-api/{resource}/exports/{id}     → Estado del export
GET  /nadota-api/{resource}/exports/{id}/download → Descargar archivo
```

### Fase 5: Controller y Rutas

#### 5.1 ExportController
```php
// src/Http/Controllers/ExportController.php
class ExportController extends Controller
{
    public function export(NadotaRequest $request): Response
    {
        // Validar formato
        // Determinar sync/async
        // Retornar archivo o job status
    }

    public function status(NadotaRequest $request, string $exportId): JsonResponse
    public function download(NadotaRequest $request, string $exportId): BinaryFileResponse
    public function index(NadotaRequest $request): JsonResponse
}
```

#### 5.2 Rutas
```php
// routes/api.php
Route::get('{resource}/resource/export', [ExportController::class, 'export']);
Route::get('{resource}/resource/exports', [ExportController::class, 'index']);
Route::get('{resource}/resource/exports/{id}', [ExportController::class, 'status']);
Route::get('{resource}/resource/exports/{id}/download', [ExportController::class, 'download']);
```

### Fase 6: Configuración

#### 6.1 Config nadota.php
```php
'export' => [
    'enabled' => true,
    'formats' => ['csv', 'xlsx', 'pdf'],
    'default_format' => 'csv',
    'sync_limit' => 1000,           // Más de esto → async
    'chunk_size' => 500,
    'storage_disk' => 'local',
    'storage_path' => 'exports',
    'expiration_hours' => 24,
    'queue' => 'exports',
],
```

### Fase 7: Autorización

#### 7.1 Agregar permiso `export` a ResourceAuthorizationService
```php
// Nuevo método en policies
public function export(User $user): bool
```

#### 7.2 En Resource
```php
public function canExport(NadotaRequest $request): bool
{
    return $this->authorizedTo($request, 'export');
}
```

### Fase 8: Frontend Integration

#### 8.1 Response de /config incluir export config
```php
'export' => [
    'enabled' => $resource->canExport($request),
    'formats' => $resource->allowedExportFormats(),
    'syncLimit' => $resource->syncExportLimit(),
],
```

---

## Estructura de Archivos Final

```
src/
├── Contracts/
│   ├── ExportInterface.php
│   └── ExporterInterface.php
├── Http/
│   ├── Controllers/
│   │   └── ExportController.php
│   ├── DataTransferObjects/
│   │   └── ExportRequestDTO.php
│   ├── Services/
│   │   ├── ResourceExportService.php
│   │   ├── Pipes/
│   │   │   └── ExportPipe.php
│   │   └── Exporters/
│   │       ├── AbstractExporter.php
│   │       ├── CsvExporter.php
│   │       ├── XlsxExporter.php
│   │       └── PdfExporter.php
│   └── Traits/
│       └── ResourceExportable.php
├── Jobs/
│   └── ProcessExportJob.php
└── Models/
    └── Export.php

database/migrations/
└── create_nadota_exports_table.php

tests/
├── Unit/
│   └── Exporters/
│       ├── CsvExporterTest.php
│       └── XlsxExporterTest.php
└── Feature/
    └── ExportTest.php
```

---

## Request/Response Examples

### Export Síncrono (< sync_limit)
```http
GET /nadota-api/users/resource/export?format=csv&filters[status]=active&search=john

Response: Binary file download
```

### Export Asíncrono (> sync_limit)
```http
POST /nadota-api/users/resource/export
{
    "format": "xlsx",
    "filters": {"status": "active"},
    "columns": ["name", "email", "created_at"]
}

Response:
{
    "data": {
        "id": "exp_123",
        "status": "processing",
        "message": "Export queued. You will be notified when ready."
    }
}
```

### Status de Export
```http
GET /nadota-api/users/resource/exports/exp_123

Response:
{
    "data": {
        "id": "exp_123",
        "status": "completed",  // pending, processing, completed, failed
        "filename": "users_2024-01-15.xlsx",
        "download_url": "/nadota-api/users/resource/exports/exp_123/download",
        "expires_at": "2024-01-16T12:00:00Z",
        "records_count": 5432
    }
}
```

---

## Prioridad de Implementación

| Fase | Prioridad | Dependencias |
|------|-----------|--------------|
| 1. Infraestructura Base | Alta | - |
| 2. ResourceExportService | Alta | Fase 1 |
| 3. CsvExporter | Alta | Fase 2 |
| 5. Controller y Rutas | Alta | Fase 3 |
| 6. Configuración | Media | Fase 5 |
| 7. Autorización | Media | Fase 5 |
| 3. XlsxExporter | Media | Fase 2 + dependencia externa |
| 4. Export Asíncrono | Media | Fase 3 |
| 3. PdfExporter | Baja | Fase 2 + dependencia externa |
| 8. Frontend Integration | Baja | Todas |

---

## Dependencias Externas (Opcionales)

| Paquete | Uso | Notas |
|---------|-----|-------|
| `openspout/openspout` | XLSX | Ligero, sin PhpSpreadsheet |
| `maatwebsite/excel` | XLSX | Más features, más pesado |
| `barryvdh/laravel-dompdf` | PDF | Para reportes PDF |

**Recomendación:** Iniciar solo con CSV (nativo), agregar XLSX/PDF según necesidad.
