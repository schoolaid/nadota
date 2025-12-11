<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Database\Eloquent\Builder;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\FieldOptions\OptionsConfig;

class ResourceOptionsService
{
    /**
     * Get options for a resource.
     *
     * @param NadotaRequest $request
     * @param ResourceInterface $resource
     * @param array $params
     * @return array
     */
    public function getOptions(
        NadotaRequest $request,
        ResourceInterface $resource,
        array $params = []
    ): array {
        $search = $params['search'] ?? $request->get('search', '');
        $limit = $params['limit'] ?? $request->get('limit', OptionsConfig::DEFAULT_LIMIT);
        $exclude = $params['exclude'] ?? $request->get('exclude', []);
        $orderBy = $params['orderBy'] ?? $request->get('orderBy');
        $orderDirection = $params['orderDirection'] ?? $request->get('orderDirection', OptionsConfig::DEFAULT_ORDER_DIRECTION);

        // Normalize exclude
        if (is_string($exclude)) {
            $exclude = array_filter(explode(',', $exclude));
        }

        // Build params array for custom methods
        $searchParams = [
            'search' => $search,
            'limit' => $limit,
            'exclude' => $exclude,
            'orderBy' => $orderBy,
            'orderDirection' => $orderDirection,
        ];

        // Get key attribute
        $keyAttribute = $resource::$attributeKey ?? 'id';

        // Check if resource has custom search implementation (e.g., Meilisearch)
        $customResults = $resource->optionsSearch($request, $searchParams);

        if ($customResults !== null) {
            // Use custom search results
            $results = collect($customResults);
        } else {
            // Use default database search
            $results = $this->getDefaultResults($request, $resource, $searchParams, $keyAttribute);
        }

        // Format as options
        $options = $results->map(function ($item) use ($resource, $keyAttribute) {
            return [
                'value' => $item->{$keyAttribute},
                'label' => $resource->displayLabel($item),
            ];
        })->toArray();

        return [
            'success' => true,
            'options' => $options,
            'meta' => [
                'total' => count($options),
                'search' => $search,
                'limit' => $limit,
            ],
        ];
    }

    /**
     * Apply search to the query.
     *
     * @param Builder $query
     * @param string $search
     * @param ResourceInterface $resource
     * @return void
     */
    protected function applySearch(Builder $query, string $search, ResourceInterface $resource): void
    {
        $query->where(function ($q) use ($search, $resource) {
            // Search in searchable attributes
            $searchableAttributes = $resource->getSearchableAttributes();
            foreach ($searchableAttributes as $attribute) {
                $q->orWhere($attribute, 'like', '%' . $search . '%');
            }

            // Search in searchable relations
            $searchableRelations = $resource->getSearchableRelations();
            foreach ($searchableRelations as $relationPath) {
                $this->applyRelationSearch($q, $search, $relationPath);
            }

            // Fallback if no searchable attributes configured
            if (empty($searchableAttributes) && empty($searchableRelations)) {
                $this->applyFallbackSearch($q, $search);
            }
        });
    }

    /**
     * Apply search on a relation path.
     *
     * @param Builder $query
     * @param string $search
     * @param string $relationPath
     * @return void
     */
    protected function applyRelationSearch(Builder $query, string $search, string $relationPath): void
    {
        $parts = explode('.', $relationPath);

        if (count($parts) === 2) {
            $relation = $parts[0];
            $attribute = $parts[1];

            $query->orWhereHas($relation, function ($relationQuery) use ($attribute, $search) {
                $relationQuery->where($attribute, 'like', '%' . $search . '%');
            });
        } elseif (count($parts) > 2) {
            $nestedPath = implode('.', array_slice($parts, 0, -1));
            $attribute = end($parts);

            $query->orWhereHas($nestedPath, function ($relationQuery) use ($attribute, $search) {
                $relationQuery->where($attribute, 'like', '%' . $search . '%');
            });
        }
    }

    /**
     * Apply fallback search on common attributes.
     *
     * @param Builder $query
     * @param string $search
     * @return void
     */
    protected function applyFallbackSearch(Builder $query, string $search): void
    {
        foreach (OptionsConfig::FALLBACK_SEARCH_ATTRIBUTES as $attribute) {
            $query->orWhere($attribute, 'like', '%' . $search . '%');
        }
    }

    /**
     * Get results using default database query.
     *
     * @param NadotaRequest $request
     * @param ResourceInterface $resource
     * @param array $params
     * @param string $keyAttribute
     * @return \Illuminate\Support\Collection
     */
    protected function getDefaultResults(
        NadotaRequest $request,
        ResourceInterface $resource,
        array $params,
        string $keyAttribute
    ): \Illuminate\Support\Collection {
        $search = $params['search'];
        $limit = $params['limit'];
        $exclude = $params['exclude'];
        $orderBy = $params['orderBy'];
        $orderDirection = $params['orderDirection'];

        // Build base query
        $query = $resource->getQuery($request);

        // Apply resource's optionsQuery customization
        $query = $resource->optionsQuery($query, $request, $params);

        // Apply search using resource's searchable configuration
        if (!empty($search)) {
            $this->applySearch($query, $search, $resource);
        }

        // Apply exclude
        if (!empty($exclude)) {
            $query->whereNotIn($keyAttribute, $exclude);
        }

        // Apply ordering
        if ($orderBy) {
            $query->orderBy($orderBy, $orderDirection);
        }

        // Apply limit
        $query->limit($limit);

        // Get select columns for performance
        $selectColumns = $this->getSelectColumns($resource, $request, $keyAttribute);

        return $query->select($selectColumns)->get();
    }

    /**
     * Get columns to select for performance.
     *
     * @param ResourceInterface $resource
     * @param NadotaRequest $request
     * @param string $keyAttribute
     * @return array
     */
    protected function getSelectColumns(ResourceInterface $resource, NadotaRequest $request, string $keyAttribute): array
    {
        $selectColumns = [];

        if (method_exists($resource, 'getSelectColumns')) {
            $selectColumns = $resource->getSelectColumns($request);
        }

        // Always include the key attribute
        if (!in_array($keyAttribute, $selectColumns)) {
            $selectColumns[] = $keyAttribute;
        }

        // If empty, return all columns (let Laravel handle it)
        if (empty($selectColumns) || $selectColumns === [$keyAttribute]) {
            return ['*'];
        }

        return $selectColumns;
    }
}
