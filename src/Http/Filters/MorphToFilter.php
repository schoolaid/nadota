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
        // Filter 1: Select morph type (converts alias to model class)
        // Use the morphTypeField as label base for translation
        // If name is "fields.targetable", type label will be "fields.targetable_type"
        $typeLabel = $this->name;

        // If the name looks like a translation key (contains dots), append _type
        // Otherwise, keep the original name for backward compatibility
        if (str_contains($this->name, '.')) {
            // Extract the base key without the prefix
            $parts = explode('.', $this->name);
            $lastPart = array_pop($parts);
            $typeLabel = implode('.', $parts) . '.' . $this->morphTypeField;
        }

        $typeFilter = new MorphTypeFilter(
            $typeLabel,
            $this->morphTypeField,
            'select'
        );
        $typeFilter->options($this->formatMorphTypes())
            ->morphTypes($this->morphTypes);

        // Filter 2: Select entity based on type
        $entityFilter = new DynamicSelectFilter(
            $this->name,
            $this->morphIdField,
            'dynamicSelect'
        );

        // Configure entity filter
        $fieldName = str_replace('_id', '', $this->morphIdField);
        $entityFilter
            ->searchable($this->searchable)
            ->multiple($this->multiple)
            ->dependsOn([$this->morphTypeField]) // Hard dependency on type
            ->filtersToSend([$this->morphTypeField]) // Send type to endpoint
            ->asMorphFilter($fieldName); // Mark as morph filter for dynamic endpoint building

        return [$typeFilter, $entityFilter];
    }

    /**
     * Format morph types for SelectFilter options
     * Returns array with label as key and alias as value for Filter::toArray() processing
     */
    protected function formatMorphTypes(): array
    {
        $options = [];

        foreach ($this->morphTypes as $alias => $config) {
            if (is_array($config)) {
                $label = $config['label'] ?? ucfirst(str_replace(['_', '-'], ' ', $alias));
                $options[$label] = $alias;
            } else {
                // If it's just a string (model class), use alias as a label
                $label = ucfirst(str_replace(['_', '-'], ' ', $alias));
                $options[$label] = $alias;
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

