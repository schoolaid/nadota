<?php

namespace SchoolAid\Nadota\Http\Filters;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Filter for MorphTo type field that converts alias to full model class
 */
class MorphTypeFilter extends SelectFilter
{
    /**
     * Morph types mapping [alias => model class or config]
     */
    protected array $morphTypes = [];

    /**
     * Set the morph types mapping
     */
    public function morphTypes(array $morphTypes): static
    {
        $this->morphTypes = $morphTypes;
        return $this;
    }

    /**
     * Apply the filter, converting alias to model class
     */
    public function apply(NadotaRequest $request, $query, $value)
    {
        // Convert alias to model class
        $modelClass = $this->getModelClassFromAlias($value);

        if (!$modelClass) {
            // If we can't resolve, don't filter
            return $query;
        }

        // Apply the filter with the full model class
        if (is_array($modelClass)) {
            return $query->whereIn($this->field, $modelClass);
        }

        return $query->where($this->field, $modelClass);
    }

    /**
     * Convert alias (or array of aliases) to model class(es)
     */
    protected function getModelClassFromAlias($value): mixed
    {
        if (is_array($value)) {
            return array_map(fn($alias) => $this->resolveModelClass($alias), $value);
        }

        return $this->resolveModelClass($value);
    }

    /**
     * Resolve a single alias to its model class
     */
    protected function resolveModelClass(string $alias): ?string
    {
        if (!isset($this->morphTypes[$alias])) {
            return null;
        }

        $config = $this->morphTypes[$alias];

        // If config is an array, extract the model key
        if (is_array($config)) {
            return $config['model'] ?? null;
        }

        // Otherwise, it's the model class directly
        return $config;
    }
}
