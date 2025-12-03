<?php

namespace SchoolAid\Nadota\Http\Filters;

use Closure;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Filtro compuesto para relaciones MorphTo que genera dos filtros:
 * 1. Un filtro para seleccionar el tipo (morph type)
 * 2. Un filtro dinámico para seleccionar la entidad según el tipo
 */
class MorphToFilter
{
    protected string $name;
    protected string $morphTypeField;
    protected string $morphIdField;
    protected array $morphTypes = [];
    protected ?string $resourceKey = null;
    protected bool $searchable = true;
    protected bool $multiple = false;

    public function __construct(
        string $name,
        string $morphTypeField,
        string $morphIdField,
        array $morphTypes = [],
        ?string $resourceKey = null
    ) {
        $this->name = $name;
        $this->morphTypeField = $morphTypeField;
        $this->morphIdField = $morphIdField;
        $this->morphTypes = $morphTypes;
        $this->resourceKey = $resourceKey;
    }

    /**
     * Set morph types
     */
    public function morphTypes(array $morphTypes): static
    {
        $this->morphTypes = $morphTypes;
        return $this;
    }

    /**
     * Set resource key
     */
    public function resourceKey(string $resourceKey): static
    {
        $this->resourceKey = $resourceKey;
        return $this;
    }

    /**
     * Enable searchable
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;
        return $this;
    }

    /**
     * Enable multiple selections
     */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;
        return $this;
    }

    /**
     * Generate the two filters needed for morph relationships
     * 
     * @return array Returns [MorphTypeFilter, MorphEntityFilter]
     */
    public function generateFilters(): array
    {
        // Filter 1: Select morph type
        $typeFilter = new SelectFilter(
            $this->name . ' - Tipo',
            $this->morphTypeField,
            'select'
        );
        $typeFilter->options($this->formatMorphTypes());

        // Filter 2: Select entity based on type
        $entityFilter = new DynamicSelectFilter(
            $this->name,
            $this->morphIdField,
            'dynamicSelect'
        );
        
        // Configure entity filter
        $entityFilter
            ->searchable($this->searchable)
            ->multiple($this->multiple)
            ->dependsOn([$this->morphTypeField]) // Hard dependency on type
            ->filtersToSend([$this->morphTypeField]); // Send type to endpoint

        // Set dynamic endpoint based on selected type
        if ($this->resourceKey) {
            $fieldName = str_replace('_id', '', $this->morphIdField);
            $apiPrefix = config('nadota.api.prefix', 'nadota-api');
            // Use placeholder {morphType} that frontend will replace with actual type
            $baseEndpoint = "/{$apiPrefix}/{$this->resourceKey}/resource/field/{$fieldName}/morph-options/{morphType}";
            
            // The endpoint will be constructed dynamically in the frontend
            // Frontend should replace {morphType} with the selected type value
            $entityFilter->endpoint($baseEndpoint);
        }

        return [$typeFilter, $entityFilter];
    }

    /**
     * Format morph types for SelectFilter options
     */
    protected function formatMorphTypes(): array
    {
        $options = [];
        
        foreach ($this->morphTypes as $alias => $config) {
            if (is_array($config)) {
                $label = $config['label'] ?? ucfirst(str_replace(['_', '-'], ' ', $alias));
                $options[$alias] = $label;
            } else {
                // If it's just a string (model class), use alias as a label
                $options[$alias] = ucfirst(str_replace(['_', '-'], ' ', $alias));
            }
        }

        return $options;
    }

    /**
     * Apply the morph filter to the query
     * This is a helper method that can be used if you want to apply both filters together
     */
    public function apply(NadotaRequest $request, $query, array $values): void
    {
        $typeValue = $values[$this->morphTypeField] ?? null;
        $idValue = $values[$this->morphIdField] ?? null;

        if (!$typeValue || !$idValue) {
            return;
        }

        // Get the model class for the selected type
        $modelClass = null;
        if (isset($this->morphTypes[$typeValue])) {
            $config = $this->morphTypes[$typeValue];
            $modelClass = is_array($config) ? $config['model'] : $config;
        }

        if (!$modelClass) {
            return;
        }

        // Apply both filters
        $query->where($this->morphTypeField, $modelClass);
        
        if ($this->multiple && is_array($idValue)) {
            $query->whereIn($this->morphIdField, $idValue);
        } else {
            $query->where($this->morphIdField, $idValue);
        }
    }
}

