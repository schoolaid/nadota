<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions\Strategies;

use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\FieldOptions\Contracts\FieldOptionsStrategy;

class BelongsToOptionsStrategy implements FieldOptionsStrategy
{
    /**
     * Check if this strategy can handle the given field.
     *
     * @param Field $field
     * @return bool
     */
    public function canHandle(Field $field): bool
    {
        return $field instanceof BelongsTo;
    }

    /**
     * Fetch options for the BelongsTo field.
     *
     * @param NadotaRequest $request
     * @param ResourceInterface $resource
     * @param Field $field
     * @param array $params
     * @return array
     */
    public function fetchOptions(
        NadotaRequest $request,
        ResourceInterface $resource,
        Field $field,
        array $params = []
    ): array {
        // Get search and limit from params or request
        $search = $params['search'] ?? $request->get('search', '');
        $limit = $params['limit'] ?? $request->get('limit', 10);

        // Get the field resource and model
        $fieldResource = $field->getResource();

        if (!$fieldResource) {
            return [];
        }

        $keyAttribute = $fieldResource::$attributeKey ?? 'id';
        $fieldResourceInstance = new $fieldResource;
        $fieldModel = $field->getModel();
        if (!$fieldModel) {
            return [];
        }

        $model = new $fieldModel;
        $query = $model::query();

        // Apply search if provided
        if (!empty($search)) {
            $query->where(function($q) use ($search, $fieldResourceInstance) {
                // Search in searchable attributes
                $searchableAttributes = $fieldResourceInstance->getSearchableAttributes();
                foreach ($searchableAttributes as $attribute) {
                    $q->orWhere($attribute, 'like', '%' . $search . '%');
                }

                // Search in searchable relations
                $searchableRelations = $fieldResourceInstance->getSearchableRelations();

                foreach ($searchableRelations as $relationPath) {
                    $parts = explode('.', $relationPath);

                    if (count($parts) === 2) {
                        $relation = $parts[0];
                        $attribute = $parts[1];

                        $q->orWhereHas($relation, function($relationQuery) use ($attribute, $search) {
                            $relationQuery->where($attribute, 'like', '%' . $search . '%');
                        });
                    } elseif (count($parts) > 2) {
                        $nestedPath = implode('.', array_slice($parts, 0, -1));
                        $attribute = end($parts);

                        $q->orWhereHas($nestedPath, function($relationQuery) use ($attribute, $search) {
                            $relationQuery->where($attribute, 'like', '%' . $search . '%');
                        });
                    }
                }
            });
        }

        // Apply limit
        $query->limit($limit);

        // Get columns to select
        $selectColumns = [];
        if (method_exists($fieldResourceInstance, 'getSelectColumns')) {
            $selectColumns = $fieldResourceInstance->getSelectColumns($request);
        }

        // Always include the key attribute
        if (!in_array($keyAttribute, $selectColumns)) {
            $selectColumns[] = $keyAttribute;
        }

        // Get results
        $results = $query->select($selectColumns)->get();

        // Format as an option array
        return $results->map(function ($item) use ($keyAttribute, $field) {
            return [
                'value' => $item->{$keyAttribute},
                'label' => $field->resolveDisplay($item)
            ];
        })->toArray();
    }

    /**
     * Get the priority of this strategy.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 100; // Default priority for BelongsTo
    }
}