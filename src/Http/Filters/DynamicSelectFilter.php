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

        // If a relation is specified, use whereHas
        if ($this->relation) {
            return $query->whereHas($this->relation, function ($q) use ($value) {
                if ($this->multiple && is_array($value)) {
                    return $q->whereIn($this->field, $value);
                }
                return $q->where($this->field, $value);
            });
        }

        // Direct query on the main table
        if ($this->multiple && is_array($value)) {
            return $query->whereIn($this->field, $value);
        }

        return $query->when($value, function ($query, $value) {
            return $query->where($this->field, $value);
        });
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

        // Build endpoint from a resource key and field name
        if ($this->resourceKey && $this->field) {
            $apiPrefix = config('nadota.api.prefix', 'nadota-api');
            return "/{$apiPrefix}/{$this->resourceKey}/resource/field/{$this->field}";
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

