<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\LazyCollection;
use SchoolAid\Nadota\Contracts\ExporterInterface;
use SchoolAid\Nadota\Contracts\ResourceExportInterface;
use SchoolAid\Nadota\Http\DataTransferObjects\ExportRequestDTO;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\Exporters\CsvExporter;
use SchoolAid\Nadota\Http\Services\Exporters\ExcelExporter;
use Symfony\Component\HttpFoundation\Response;

class ResourceExportService implements ResourceExportInterface
{
    /**
     * Available exporters by format.
     */
    protected array $exporters = [
        'excel' => ExcelExporter::class,
        'csv' => CsvExporter::class,
    ];

    /**
     * Handle export request.
     */
    public function handle(NadotaRequest $request): Response
    {
        $request->authorized('viewAny');

        $resource = $request->getResource();

        // Validate export is enabled
        if (!$resource->isExportEnabled()) {
            abort(403, 'Export is not enabled for this resource.');
        }

        // Validate format
        $format = $request->input('format', config('nadota.export.default_format', 'excel'));
        $allowedFormats = $resource->getAllowedExportFormats();

        if (!in_array($format, $allowedFormats)) {
            abort(422, "Export format '{$format}' is not allowed. Allowed formats: " . implode(', ', $allowedFormats));
        }

        // Create export DTO
        $dto = new ExportRequestDTO(
            $request,
            $resource,
            $format,
            $request->input('columns'),
            $request->input('filename')
        );

        // Build query using existing pipes (without pagination)
        $pipes = [
            Pipes\BuildQueryPipe::class,
            Pipes\ApplySearchPipe::class,
            Pipes\ApplyFiltersPipe::class,
            Pipes\ApplySortingPipe::class,
        ];

        /** @var ExportRequestDTO $processedDto */
        $processedDto = app(Pipeline::class)
            ->send($dto)
            ->through($pipes)
            ->thenReturn();

        // Get exporter
        $exporter = $this->getExporter($format);

        // Get fields and headers (filtered by selected columns)
        $fields = $dto->getExportableFields();
        $headers = $dto->getHeaders();

        // Create lazy collection for memory efficiency
        $data = $this->createLazyData($processedDto, $fields);

        return $exporter->export($data, $headers, $dto->filename);
    }

    /**
     * Get exporter instance for format.
     */
    protected function getExporter(string $format): ExporterInterface
    {
        $exporterClass = $this->exporters[$format] ?? null;

        if (!$exporterClass) {
            throw new \InvalidArgumentException("No exporter available for format: {$format}");
        }

        return app($exporterClass);
    }

    /**
     * Chunk size for batch loading — matches AbstractExporter default.
     */
    protected int $chunkSize = 500;

    /**
     * Create lazy collection from query for memory-efficient export.
     *
     * Uses forPage() chunks instead of cursor() so that the eager-load
     * constraints set by BuildQueryPipe (with/withCount) are executed per
     * batch, eliminating N+1 queries on relation fields.
     */
    protected function createLazyData(ExportRequestDTO $dto, array $fields): LazyCollection
    {
        $chunkSize = $this->chunkSize;

        return LazyCollection::make(function () use ($dto, $fields, $chunkSize) {
            $page = 1;

            while (true) {
                $models = (clone $dto->query)->forPage($page, $chunkSize)->get();

                if ($models->isEmpty()) {
                    break;
                }

                foreach ($models as $model) {
                    yield $dto->resource->transformForExport($model, $dto->request, $fields);
                }

                if ($models->count() < $chunkSize) {
                    break;
                }

                $page++;
            }
        });
    }

    /**
     * Register a custom exporter.
     */
    public function registerExporter(string $format, string $exporterClass): void
    {
        $this->exporters[$format] = $exporterClass;
    }
}
