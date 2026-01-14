<?php

namespace SchoolAid\Nadota\Http\Filters;

use Closure;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class DynamicSelectFilter extends Filter
{
    protected ?string $endpoint = null;
    protected string $valueField = 'id';
    protected string $labelField = 'name';
    protected bool $multiple = false;
    protected bool $searchable = true;
    protected mixed $defaultValue = null;
    protected bool $applyToQuery = true;
    protected ?array $dependsOn = null;
    protected ?array $softDependsOn = null;
    protected ?array $filtersToSend = null;
    protected array|Closure|null $options = null;
    protected ?string $relation = null;
    protected ?string $resourceKey = null;
    protected bool $isMorphFilter = false;
    protected ?string $morphFieldName = null;

    public function __construct(
        string $name = null,
        string $field = null,
        string $type = null,
        string $component = null,
        $id = null
    ) {
        parent::__construct($name, $field, $type ?? 'dynamicSelect', $component ?? 'FilterDynamicSelect', $id);
    }

    /**
     * Set the API endpoint to fetch options dynamically
     */
    public function endpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    /**
     * Set the field name for the value in options (default: 'id')
     */
    public function valueField(string $valueField): static
    {
        $this->valueField = $valueField;
        return $this;
    }

    /**
     * Set the field name for the label in options (default: 'name')
     */
    public function labelField(string $labelField): static
    {
        $this->labelField = $labelField;
        return $this;
    }

    /**
     * Set static options array or closure returning options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;
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
     * Enable searchable select
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;
        return $this;
    }

    /**
     * Set default selected value(s)
     */
    public function withDefault(mixed $defaultValue): static
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }

    /**
     * Set whether this filter should be applied to the final query
     */
    public function applyToQuery(bool $applyToQuery = true): static
    {
        $this->applyToQuery = $applyToQuery;
        return $this;
    }

    /**
     * Set hard dependencies - resets when parent changes
     */
    public function dependsOn(array $dependsOn): static
    {
        $this->dependsOn = $dependsOn;
        return $this;
    }

    /**
     * Set soft dependencies - re-filters but keeps value if valid
     */
    public function softDependsOn(array $softDependsOn): static
    {
        $this->softDependsOn = $softDependsOn;
        return $this;
    }

    /**
     * Set filter keys to send when fetching options from endpoint
     */
    public function filtersToSend(array $filtersToSend): static
    {
        $this->filtersToSend = $filtersToSend;
        return $this;
    }

    /**
     * Set relation path for whereHas (e.g., 'stop.route', 'student')
     */
    public function relation(string $relation): static
    {
        $this->relation = $relation;
        return $this;
    }

    /**
     * Set the resource key for building the endpoint URL
     */
    public function resourceKey(string $resourceKey): static
    {
        $this->resourceKey = $resourceKey;
        return $this;
    }

    /**
     * Mark this filter as a morph filter
     *
     * @param string $fieldName The base field name (without _id) for the morph relation
     * @return static
     */
    public function asMorphFilter(string $fieldName): static
    {
        $this->isMorphFilter = true;
        $this->morphFieldName = $fieldName;
        return $this;
    }

    /**
     * Apply the filter to the query
     */
    public function apply(NadotaRequest $request, $query, $value)
    {
        // If this filter should not be applied to the query, return unchanged
        if (!$this->applyToQuery) {
            return $query;
        }

        // If no value is provided, return the query unchanged
        if (empty($value)) {
            return $query;
        }

        // If a relation is specified, use whereHas with the related model's primary key
        if ($this->relation) {
            return $query->whereHas($this->relation, function ($q) use ($value) {
                // Get the related model's primary key (usually 'id')
                $relatedKeyName = $q->getModel()->getKeyName();

                if ($this->multiple && is_array($value)) {
                    return $q->whereIn($relatedKeyName, $value);
                }
                return $q->where($relatedKeyName, $value);
            });
        }

        // Direct query on the main table - resolve the actual FK from the relation
        $filterColumn = $this->resolveFilterColumn($query);

        if ($this->multiple && is_array($value)) {
            return $query->whereIn($filterColumn, $value);
        }

        return $query->when($value, function ($query, $value) use ($filterColumn) {
            return $query->where($filterColumn, $value);
        });
    }

    /**
     * Resolve the actual column to filter on.
     * For BelongsTo relations, this resolves the actual FK from the Eloquent relationship.
     */
    protected function resolveFilterColumn($query): string
    {
        // If we have a relation name, try to resolve the FK from Eloquent
        if ($this->relation) {
            try {
                $model = $query->getModel();
                $relationName = $this->relation;

                if (method_exists($model, $relationName)) {
                    $relation = $model->{$relationName}();

                    if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                        return $relation->getForeignKeyName();
                    }
                }
            } catch (\Throwable $e) {
                // Fall back to the field attribute
            }
        }

        return $this->field;
    }

    /**
     * Get options for the filter
     */
    public function resources(NadotaRequest $request): array
    {
        // If static options are provided (not a closure), return them
        if (is_array($this->options)) {
            return $this->options;
        }

        // If options is a closure, execute it with current filter values
        if ($this->options instanceof Closure) {
            $filters = $request->get('filters', []);
            return call_user_func($this->options, $filters);
        }

        // No static options - will be fetched dynamically from endpoint
        return [];
    }

    /**
     * Get the endpoint URL for fetching options
     */
    protected function getEndpointUrl(NadotaRequest $request): ?string
    {
        // If endpoint is explicitly set, use it
        if ($this->endpoint) {
            return $this->endpoint;
        }

        // Build morph endpoint if this is a morph filter
        if ($this->isMorphFilter && $this->morphFieldName) {
            try {
                $resource = $request->getResource();
                if ($resource) {
                    $resourceKey = $resource::getKey();
                    $apiPrefix = config('nadota.api.prefix', 'nadota-api');
                    return "/{$apiPrefix}/{$resourceKey}/resource/field/{$this->morphFieldName}/morph-options/{morphType}";
                }
            } catch (\Exception $e) {
                // Resource not available
            }
        }

        // Build endpoint from a resource key and field name
        if ($this->resourceKey && $this->field) {
            $apiPrefix = config('nadota.api.prefix', 'nadota-api');
            return "/{$apiPrefix}/{$this->resourceKey}/resource/field/{$this->field}/options";
        }

        // Try to get from the request resource
        try {
            $resource = $request->getResource();
            if ($resource) {
                $resourceKey = $resource::getKey();
                $apiPrefix = config('nadota.api.prefix', 'nadota-api');
                return "/{$apiPrefix}/{$resourceKey}/resource/field/{$this->field}/options";
            }
        } catch (\Exception $e) {
            // Resource not available
        }

        return null;
    }

    /**
     * Get default value
     */
    public function default(): string
    {
        if ($this->defaultValue !== null) {
            if (is_array($this->defaultValue)) {
                return json_encode($this->defaultValue);
            }
            return (string) $this->defaultValue;
        }
        return parent::default();
    }

    /**
     * Get additional props for the filter
     */
    public function props(): array
    {
        $props = [
            'valueField' => $this->valueField,
            'labelField' => $this->labelField,
            'multiple' => $this->multiple,
            'searchable' => $this->searchable,
            'applyToQuery' => $this->applyToQuery || $this->relation !== null,
        ];

        // Add endpoint if available (will be set in toArray with request)
        if ($this->endpoint) {
            $props['endpoint'] = $this->endpoint;
            // Check if endpoint has placeholder for morph type
            if (str_contains($this->endpoint, '{morphType}') || str_contains($this->endpoint, '/morph-options/')) {
                $props['isMorphEndpoint'] = true;
                $props['endpointTemplate'] = $this->endpoint;
            }
        }

        // If this is a morph filter, mark it accordingly
        // The actual endpoint will be built in toArray() but we need the flags here
        if ($this->isMorphFilter) {
            $props['isMorphEndpoint'] = true;
        }

        if ($this->dependsOn !== null) {
            $props['dependsOn'] = $this->dependsOn;
        }

        if ($this->softDependsOn !== null) {
            $props['softDependsOn'] = $this->softDependsOn;
        }

        if ($this->filtersToSend !== null) {
            $props['filtersToSend'] = $this->filtersToSend;
        }

        if ($this->relation !== null) {
            $props['relation'] = $this->relation;
        }

        return array_merge(parent::props(), $props);
    }

    /**
     * Convert filter to array for JSON response
     */
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        // Add endpoint if available
        $endpoint = $this->getEndpointUrl($request);
        if ($endpoint) {
            $data['endpoint'] = $endpoint;
            // Also add to props for frontend
            $data['props']['endpoint'] = $endpoint;

            // If this is a morph endpoint, add the template
            if (str_contains($endpoint, '{morphType}') || str_contains($endpoint, '/morph-options/')) {
                $data['props']['isMorphEndpoint'] = true;
                $data['props']['endpointTemplate'] = $endpoint;
            }
        }

        // Add static options if provided (not a closure)
        if (is_array($this->options)) {
            $data['options'] = collect($this->options)->map(function ($value, $label) {
                return is_array($value)
                    ? collect($value)->put('label', $label)->all()
                    : ['label' => $label ?? $value, 'value' => $value];
            })->values()->all();
        }

        return $data;
    }
}

