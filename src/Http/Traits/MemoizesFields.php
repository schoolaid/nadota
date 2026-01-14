<?php

namespace SchoolAid\Nadota\Http\Traits;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Trait to memoize expensive field operations within a request lifecycle.
 * This prevents repeated calls to fields() and flattenFields() during a single request.
 */
trait MemoizesFields
{
    /**
     * Cache for memoized field collections.
     * Key format: "{hash}_{method}"
     */
    protected array $fieldMemoizationCache = [];

    /**
     * Get fields with memoization.
     * Caches the result of fields() call for the current request.
     */
    protected function getMemoizedFields(NadotaRequest $request): array
    {
        $cacheKey = $this->getFieldsCacheKey($request, 'fields');

        if (!isset($this->fieldMemoizationCache[$cacheKey])) {
            $this->fieldMemoizationCache[$cacheKey] = $this->fields($request);
        }

        return $this->fieldMemoizationCache[$cacheKey];
    }

    /**
     * Get flattened fields with memoization.
     * Caches the result to avoid repeated flattening operations.
     */
    protected function getMemoizedFlattenedFields(NadotaRequest $request): Collection
    {
        $cacheKey = $this->getFieldsCacheKey($request, 'flattened');

        if (!isset($this->fieldMemoizationCache[$cacheKey])) {
            $this->fieldMemoizationCache[$cacheKey] = $this->flattenFieldsWithoutMemoization($request);
        }

        return $this->fieldMemoizationCache[$cacheKey];
    }

    /**
     * Original flatten logic extracted to avoid circular dependency.
     */
    protected function flattenFieldsWithoutMemoization(NadotaRequest $request): Collection
    {
        $fields = $this->getMemoizedFields($request);
        
        return collect($fields)
            ->flatMap(function ($item) {
                if ($item instanceof \SchoolAid\Nadota\Http\Fields\Section) {
                    return $item->getFields();
                }
                return [$item];
            })
            ->values();
    }

    /**
     * Generate a cache key based on request context.
     * Uses request path and user to ensure proper cache scoping.
     */
    protected function getFieldsCacheKey(NadotaRequest $request, string $type): string
    {
        // Include user ID if available for user-specific field configurations
        $userId = $request->user()?->id ?? 'guest';
        
        // Include resource key to avoid collisions between resources
        $resourceKey = static::class;
        
        return md5("{$resourceKey}_{$userId}_{$type}");
    }

    /**
     * Clear field memoization cache.
     * Useful for testing or when fields need to be recalculated.
     */
    public function clearFieldMemoizationCache(): void
    {
        $this->fieldMemoizationCache = [];
    }
}
