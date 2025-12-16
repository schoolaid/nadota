<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions\Traits;

use Illuminate\Database\Eloquent\Builder;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Services\FieldOptions\OptionsConfig;

trait SearchesOptions
{
    /**
     * Apply search query to the builder.
     *
     * @param Builder $query
     * @param string $search
     * @param ResourceInterface|null $resourceInstance
     * @return Builder
     */
    protected function applySearch(Builder $query, string $search, ?ResourceInterface $resourceInstance = null): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search, $resourceInstance) {
            if ($resourceInstance) {
                $this->applyResourceSearch($q, $search, $resourceInstance);
            } else {
                $this->applyFallbackSearch($q, $search);
            }
        });
    }

    /**
     * Apply search using resource's searchable configuration.
     *
     * @param Builder $query
     * @param string $search
     * @param ResourceInterface $resourceInstance
     * @return void
     */
    protected function applyResourceSearch(Builder $query, string $search, ResourceInterface $resourceInstance): void
    {
        // Search in searchable attributes
        $searchableAttributes = $resourceInstance->getSearchableAttributes();
        foreach ($searchableAttributes as $attribute) {
            $query->orWhere($attribute, 'like', '%' . $search . '%');
        }

        // Search in searchable relations
        $searchableRelations = $resourceInstance->getSearchableRelations();
        foreach ($searchableRelations as $relationPath) {
            $this->applyRelationSearch($query, $search, $relationPath);
        }
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
     * Apply exclude filter to query.
     *
     * @param Builder $query
     * @param array $excludeIds
     * @param string $keyAttribute
     * @return Builder
     */
    protected function applyExclude(Builder $query, array $excludeIds, string $keyAttribute = 'id'): Builder
    {
        if (!empty($excludeIds)) {
            $query->whereNotIn($keyAttribute, $excludeIds);
        }

        return $query;
    }

    /**
     * Apply ordering to query.
     *
     * @param Builder $query
     * @param string|null $orderBy
     * @param string $direction
     * @return Builder
     */
    protected function applyOrdering(Builder $query, ?string $orderBy = null, string $direction = 'asc'): Builder
    {
        if ($orderBy) {
            $query->orderBy($orderBy, $direction);
        }

        return $query;
    }

    /**
     * Format results as options array.
     *
     * @param \Illuminate\Support\Collection $results
     * @param string $keyAttribute
     * @param callable|null $labelResolver
     * @return array
     */
    protected function formatAsOptions($results, string $keyAttribute, ?callable $labelResolver = null): array
    {
        return $results->map(function ($item) use ($keyAttribute, $labelResolver) {
            $label = $labelResolver ? $labelResolver($item) : $this->resolveDefaultLabel($item);

            return [
                'value' => $item->{$keyAttribute},
                'label' => $label,
            ];
        })->toArray();
    }

    /**
     * Resolve default label from common attributes.
     *
     * @param mixed $item
     * @return mixed
     */
    protected function resolveDefaultLabel($item): mixed
    {
        foreach (OptionsConfig::FALLBACK_LABEL_ATTRIBUTES as $attr) {
            if (isset($item->{$attr}) && $item->{$attr} !== null) {
                return $item->{$attr};
            }
        }

        return $item->getKey();
    }

    /**
     * Get common parameters from request/params.
     *
     * @param \SchoolAid\Nadota\Http\Requests\NadotaRequest $request
     * @param array $params
     * @return array
     */
    protected function getCommonParams($request, array $params = []): array
    {
        $limit = $params['limit'] ?? $request->get('limit', OptionsConfig::DEFAULT_LIMIT);
        $limit = min((int) $limit, OptionsConfig::MAX_LIMIT);

        return [
            'search' => $params['search'] ?? $request->get('search', ''),
            'limit' => $limit,
            'exclude' => $params['exclude'] ?? $request->get('exclude', []),
            'orderBy' => $params['orderBy'] ?? $request->get('orderBy'),
            'orderDirection' => $params['orderDirection'] ?? $request->get('orderDirection', OptionsConfig::DEFAULT_ORDER_DIRECTION),
        ];
    }

    /**
     * Apply resource's optionsQuery customization to the query.
     *
     * @param Builder $query
     * @param ResourceInterface|null $resourceInstance
     * @param \SchoolAid\Nadota\Http\Requests\NadotaRequest $request
     * @param array $params
     * @return Builder
     */
    protected function applyResourceOptionsQuery(
        Builder $query,
        ?ResourceInterface $resourceInstance,
        $request,
        array $params = []
    ): Builder {
        if ($resourceInstance && method_exists($resourceInstance, 'optionsQuery')) {
            return $resourceInstance->optionsQuery($query, $request, $params);
        }

        return $query;
    }
}
