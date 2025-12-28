<?php

namespace SchoolAid\Nadota\Http\DataTransferObjects;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;

class ExportRequestDTO extends IndexRequestDTO
{
    public string $format;
    public ?array $columns;
    public string $filename;

    public function __construct(
        NadotaRequest $request,
        ?Resource $resource,
        string $format = 'csv',
        ?array $columns = null,
        ?string $filename = null
    ) {
        parent::__construct($request, $resource);

        $this->format = $format;
        $this->columns = $columns;
        $this->filename = $filename ?? $this->generateFilename();
    }

    /**
     * Generate default filename based on resource and date.
     */
    protected function generateFilename(): string
    {
        $resourceKey = $this->resource ? $this->resource::getKey() : 'export';
        $date = now()->format('Y-m-d_His');

        return "{$resourceKey}_{$date}";
    }

    /**
     * Get exportable fields based on columns selection or default.
     */
    public function getExportableFields(): array
    {
        $fields = $this->resource->getExportableFields($this->request);

        // If specific columns requested, filter to only those
        if ($this->columns) {
            $fields = $fields->filter(function ($field) {
                return in_array($field->key(), $this->columns);
            })->values();
        }

        return $fields->all();
    }

    /**
     * Get headers for export based on fields.
     */
    public function getHeaders(): array
    {
        $fields = $this->getExportableFields();

        return collect($fields)->mapWithKeys(function ($field) {
            return [$field->key() => $field->getName()];
        })->all();
    }
}
