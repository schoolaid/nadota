<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\LazyCollection;
use SchoolAid\Nadota\Contracts\ExporterInterface;
use SchoolAid\Nadota\Contracts\ResourceExportInterface;
use SchoolAid\Nadota\Http\DataTransferObjects\ExportRequestDTO;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\Exporters\CsvExporter;
use Symfony\Component\HttpFoundation\Response;

class ResourceExportService implements ResourceExportInterface
{
    /**
     * Available exporters by format.
     */
    protected array $exporters = [
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
        $format = $request->input('format', 'csv');
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

        // Get headers and fields
        $headers = $resource->getExportHeaders($request);
        $fields = $dto->getExportableFields();

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
     * Create lazy collection from query for memory-efficient export.
     */
    protected function createLazyData(ExportRequestDTO $dto, array $fields): LazyCollection
    {
        $chunkSize = config('nadota.export.chunk_size', 500);

        return LazyCollection::make(function () use ($dto, $fields, $chunkSize) {
            $dto->query->chunk($chunkSize, function ($models) use ($dto, $fields, &$rows) {
                foreach ($models as $model) {
                    yield $dto->resource->transformForExport($model, $dto->request, $fields);
                }
            });
        })->chunk($chunkSize)->flatMap(fn($chunk) => $chunk);
    }

    /**
     * Register a custom exporter.
     */
    public function registerExporter(string $format, string $exporterClass): void
    {
        $this->exporters[$format] = $exporterClass;
    }
}
