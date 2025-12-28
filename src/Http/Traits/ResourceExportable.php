<?php

namespace SchoolAid\Nadota\Http\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

trait ResourceExportable
{
    /**
     * Whether export is enabled for this resource.
     */
    protected bool $exportEnabled = true;

    /**
     * Allowed export formats for this resource.
     * Defaults to config value if not set.
     */
    protected ?array $allowedExportFormats = null;

    /**
     * Default columns to export.
     * If null, all exportable columns are selected by default.
     * Set to array of field keys to limit default selection.
     */
    protected ?array $defaultExportColumns = null;

    /**
     * Maximum records for synchronous export.
     * Above this threshold, export should be async.
     */
    protected int $syncExportLimit = 1000;

    /**
     * Check if export is enabled for this resource.
     */
    public function isExportEnabled(): bool
    {
        return $this->exportEnabled;
    }

    /**
     * Get allowed export formats.
     */
    public function getAllowedExportFormats(): array
    {
        return $this->allowedExportFormats ?? config('nadota.export.formats', ['excel', 'csv']);
    }

    /**
     * Get sync export limit.
     */
    public function getSyncExportLimit(): int
    {
        return $this->syncExportLimit;
    }

    /**
     * Get default export columns.
     * Returns null if all columns should be selected by default.
     */
    public function getDefaultExportColumns(): ?array
    {
        return $this->defaultExportColumns;
    }

    /**
     * Get fields that can be exported.
     * By default, uses fields visible on index.
     * Override to customize exportable fields.
     */
    public function getExportableFields(NadotaRequest $request): Collection
    {
        return $this->flattenFields($request)
            ->filter(fn($field) => $field->isShowOnIndex())
            ->filter(fn($field) => $this->isFieldExportable($field));
    }

    /**
     * Determine if a field should be exportable.
     * Override to add custom logic.
     */
    protected function isFieldExportable($field): bool
    {
        // Exclude relationship fields by default (can be overridden)
        if ($field->isRelationship()) {
            // Allow BelongsTo as it's a single value
            return method_exists($field, 'isBelongsTo') && $field->isBelongsTo();
        }

        return true;
    }

    /**
     * Get custom headers for export.
     * Override to customize column headers.
     *
     * @return array<string, string> [field_key => header_label]
     */
    public function getExportHeaders(NadotaRequest $request): array
    {
        return $this->getExportableFields($request)
            ->mapWithKeys(fn($field) => [$field->key() => $field->getName()])
            ->all();
    }

    /**
     * Transform a model instance for export.
     * Override to customize row data.
     *
     * @param Model $model
     * @param NadotaRequest $request
     * @param array $fields Exportable fields
     * @return array
     */
    public function transformForExport(Model $model, NadotaRequest $request, array $fields): array
    {
        $row = [];

        foreach ($fields as $field) {
            $value = $field->resolve($request, $model, $this);
            $row[$field->key()] = $this->formatExportValue($value, $field);
        }

        return $row;
    }

    /**
     * Format a value for export.
     * Override to customize value formatting.
     */
    protected function formatExportValue(mixed $value, $field): mixed
    {
        // Handle null
        if ($value === null) {
            return '';
        }

        // Handle arrays/objects
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        // Handle booleans
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return $value;
    }

    /**
     * Get export format extensions mapping.
     */
    protected function getExportFormatExtensions(): array
    {
        return [
            'excel' => 'xlsx',
            'csv' => 'csv',
        ];
    }

    /**
     * Get allowed formats with their extensions.
     */
    public function getAllowedExportFormatsWithExtensions(): array
    {
        $extensions = $this->getExportFormatExtensions();
        $formats = $this->getAllowedExportFormats();

        return collect($formats)->map(function ($format) use ($extensions) {
            return [
                'format' => $format,
                'extension' => $extensions[$format] ?? $format,
            ];
        })->all();
    }

    /**
     * Get export configuration for frontend.
     */
    public function getExportConfig(NadotaRequest $request): array
    {
        $defaultColumns = $this->getDefaultExportColumns();

        return [
            'enabled' => $this->isExportEnabled() && $this->authorizedTo($request, 'export'),
            'formats' => $this->getAllowedExportFormatsWithExtensions(),
            'syncLimit' => $this->getSyncExportLimit(),
            'defaultColumns' => $defaultColumns,
            'columns' => $this->getExportableFields($request)
                ->map(function ($field) use ($defaultColumns) {
                    $key = $field->key();
                    return [
                        'key' => $key,
                        'label' => $field->getName(),
                        'selected' => $defaultColumns === null || in_array($key, $defaultColumns),
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
