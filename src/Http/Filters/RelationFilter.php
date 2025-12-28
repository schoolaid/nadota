<?php

namespace SchoolAid\Nadota\Http\Filters;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Filter for HasMany, BelongsToMany, HasOne, MorphMany, MorphOne relations.
 * Uses whereHas to filter by related models.
 */
class RelationFilter extends DynamicSelectFilter
{
    protected string $relationType = 'hasMany';
    protected ?string $relatedResource = null;
    protected ?string $pivotTable = null;

    public function __construct(
        ?string $name = null,
        ?string $field = null,
        ?string $type = null,
        ?string $component = null,
        $id = null
    ) {
        parent::__construct($name, $field, $type ?? 'relation', $component ?? 'FilterDynamicSelect', $id);
    }

    /**
     * Set the relation type (hasMany, belongsToMany, hasOne, morphMany, morphOne)
     */
    public function relationType(string $relationType): static
    {
        $this->relationType = $relationType;
        return $this;
    }

    /**
     * Set the related resource class for building options endpoint
     */
    public function relatedResource(string $relatedResource): static
    {
        $this->relatedResource = $relatedResource;
        return $this;
    }

    /**
     * Apply the filter to the query using whereHas
     */
    public function apply(NadotaRequest $request, $query, $value)
    {
        if (empty($value)) {
            return $query;
        }

        $relationName = $this->relation ?? $this->field;

        if (!$relationName) {
            return $query;
        }

        return $query->whereHas($relationName, function ($q) use ($value) {
            $relatedKeyName = $q->getModel()->getKeyName();

            if ($this->multiple && is_array($value)) {
                $q->whereIn($relatedKeyName, $value);
            } else {
                $q->where($relatedKeyName, $value);
            }
        });
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

        // Build endpoint from the current resource and field
        try {
            $resource = $request->getResource();
            if ($resource) {
                $resourceKey = $resource::getKey();
                $fieldKey = $this->field;
                $apiPrefix = config('nadota.api.prefix', 'nadota-api');
                return "/{$apiPrefix}/{$resourceKey}/resource/field/{$fieldKey}/options";
            }
        } catch (\Exception $e) {
            // Resource not available
        }

        return null;
    }

    /**
     * Get additional props for the filter
     */
    public function props(): array
    {
        $props = parent::props();
        $props['relationType'] = $this->relationType;

        return $props;
    }
}
