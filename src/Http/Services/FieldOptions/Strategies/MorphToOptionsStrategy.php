<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions\Strategies;

use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\FieldOptions\Contracts\FieldOptionsStrategy;

class MorphToOptionsStrategy implements FieldOptionsStrategy
{
    /**
     * Check if this strategy can handle the given field.
     *
     * @param Field $field
     * @return bool
     */
    public function canHandle(Field $field): bool
    {
        return $field instanceof MorphTo;
    }

    /**
     * Fetch options for the MorphTo field.
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
        // Get the morph type from params
        $morphType = $params['morphType'] ?? null;

        if (!$morphType) {
            return [];
        }

        // Get search and limit from params or request
        $search = $params['search'] ?? $request->get('search', '');
        $limit = $params['limit'] ?? $request->get('limit', 10);

        // Get the morph models from the field
        $morphModels = $this->getMorphModels($field);

        if (!isset($morphModels[$morphType])) {
            return [];
        }

        // Get the model class for this morph type
        $modelClass = $morphModels[$morphType];
        $model = new $modelClass;

        // Get the resource class if available
        $morphResources = $this->getMorphResources($field);
        $resourceClass = $morphResources[$morphType] ?? null;

        // Build the query
        $query = $model::query();

        // If we have a resource, use its searchable attributes
        if ($resourceClass && !empty($search)) {
            $resourceInstance = new $resourceClass;

            $query->where(function($q) use ($search, $resourceInstance) {
                // Search in searchable attributes
                $searchableAttributes = $resourceInstance->getSearchableAttributes();
                foreach ($searchableAttributes as $attribute) {
                    $q->orWhere($attribute, 'like', '%' . $search . '%');
                }

                // Search in searchable relations
                $searchableRelations = $resourceInstance->getSearchableRelations();
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
        } elseif (!empty($search)) {
            // Fallback to searching common attributes
            $query->where(function($q) use ($search) {
                $q->orWhere('name', 'like', '%' . $search . '%')
                  ->orWhere('title', 'like', '%' . $search . '%')
                  ->orWhere('label', 'like', '%' . $search . '%');
            });
        }

        // Apply limit
        $query->limit($limit);

        // Get results
        $results = $query->get();

        // Format as an option array
        return $results->map(function ($item) use ($field) {
            // Use the field's resolveDisplay method
            $label = $field->resolveDisplay($item);

            // If no label, try common attributes
            if (!$label) {
                $commonAttributes = ['name', 'title', 'label', 'display_name', 'full_name', 'description'];
                foreach ($commonAttributes as $attr) {
                    if (isset($item->{$attr})) {
                        $label = $item->{$attr};
                        break;
                    }
                }
                // Fallback to primary key
                if (!$label) {
                    $label = $item->getKey();
                }
            }

            return [
                'value' => $item->getKey(),
                'label' => $label
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
        return 90; // Slightly lower priority than BelongsTo
    }

    /**
     * Get morph models from the field using reflection.
     *
     * @param MorphTo $field
     * @return array
     */
    protected function getMorphModels(MorphTo $field): array
    {
        // Use reflection to access protected property
        $reflection = new \ReflectionClass($field);
        $property = $reflection->getProperty('morphModels');
        $property->setAccessible(true);

        return $property->getValue($field);
    }

    /**
     * Get morph resources from the field using reflection.
     *
     * @param MorphTo $field
     * @return array
     */
    protected function getMorphResources(MorphTo $field): array
    {
        // Use reflection to access protected property
        $reflection = new \ReflectionClass($field);
        $property = $reflection->getProperty('morphResources');
        $property->setAccessible(true);

        return $property->getValue($field);
    }
}