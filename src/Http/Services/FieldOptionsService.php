<?php

namespace SchoolAid\Nadota\Http\Services;

use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\FieldOptions\Contracts\FieldOptionsStrategy;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\BelongsToOptionsStrategy;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\MorphToOptionsStrategy;
use SchoolAid\Nadota\ResourceManager;

class FieldOptionsService
{
    /**
     * @var array<FieldOptionsStrategy>
     */
    protected array $strategies = [];

    public function __construct(
        protected ResourceManager $resourceManager
    ) {
        $this->registerDefaultStrategies();
    }

    /**
     * Register default strategies.
     */
    protected function registerDefaultStrategies(): void
    {
        $this->registerStrategy(new BelongsToOptionsStrategy());
        $this->registerStrategy(new MorphToOptionsStrategy());
    }

    /**
     * Register a field options strategy.
     *
     * @param FieldOptionsStrategy $strategy
     * @return void
     */
    public function registerStrategy(FieldOptionsStrategy $strategy): void
    {
        $this->strategies[] = $strategy;

        // Sort strategies by priority (higher priority first)
        usort($this->strategies, function ($a, $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    /**
     * Get options for a field.
     *
     * @param NadotaRequest $request
     * @param string $resourceName
     * @param string $fieldName
     * @param array $additionalParams
     * @return array
     */
    public function getFieldOptions(
        NadotaRequest $request,
        string $resourceName,
        string $fieldName,
        array $additionalParams = []
    ): array {
        // Get the resource class
        $resourceClass = $this->resourceManager::getResourceByKey($resourceName);

        if (!$resourceClass) {
            return [
                'success' => false,
                'message' => 'Resource not found',
                'options' => []
            ];
        }

        // Create a resource instance
        $resource = new $resourceClass();

        // Find the field
        $field = $this->findField($request, $resource, $fieldName);

        if (!$field) {
            return [
                'success' => false,
                'message' => 'Field not found',
                'options' => []
            ];
        }

        // Find the appropriate strategy
        $strategy = $this->findStrategy($field);

        if (!$strategy) {
            // Fallback to legacy implementation for backward compatibility
            $searchQuery = $request->get('search', '');
            $limit = $request->get('limit', 10);
            $options = $this->fetchOptions($resource, $field, $searchQuery, $limit, $request);
        } else {
            // Use strategy to fetch options
            $params = array_merge([
                'search' => $request->get('search', ''),
                'limit' => $request->get('limit', 10)
            ], $additionalParams);

            $options = $strategy->fetchOptions($request, $resource, $field, $params);
        }

        return [
            'success' => true,
            'options' => $options,
            'meta' => [
                'total' => count($options),
                'search' => $request->get('search', ''),
                'fieldType' => method_exists($field, 'getType') ? $field->getType() : null
            ]
        ];
    }

    /**
     * Find the appropriate strategy for a field.
     *
     * @param Field $field
     * @return FieldOptionsStrategy|null
     */
    protected function findStrategy(Field $field): ?FieldOptionsStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->canHandle($field)) {
                return $strategy;
            }
        }

        return null;
    }

    /**
     * Find a field by name in the resource.
     *
     * @param NadotaRequest $request
     * @param ResourceInterface $resource
     * @param string $fieldName
     * @return Field|null
     */
    protected function findField(NadotaRequest $request, ResourceInterface $resource, string $fieldName): ?Field
    {
        // Cache the fields collection to avoid multiple calls
        static $fieldsCache = [];
        $cacheKey = get_class($resource) . '_' . $fieldName;

        if (isset($fieldsCache[$cacheKey])) {
            return $fieldsCache[$cacheKey];
        }

        $fields = collect($resource->fields($request));

        // Try to find by key first (most common case)
        $field = $fields->first(function ($field) use ($fieldName) {
            return $field->key() === $fieldName;
        });

        // If not found by key, try by attribute name
        if (!$field) {
            $field = $fields->first(function ($field) use ($fieldName) {
                return $field->getAttribute() === $fieldName;
            });
        }

        // If not found and it's a relationship field, try by relation name
        if (!$field) {
            $field = $fields->first(function ($field) use ($fieldName) {
                if (method_exists($field, 'getRelation')) {
                    return $field->getRelation() === $fieldName;
                }
                return false;
            });
        }

        // Cache the result
        if ($field) {
            $fieldsCache[$cacheKey] = $field;
        }

        return $field;
    }

    /**
     * Fetch options for a relation field with optional search.
     */
    protected function fetchOptions(ResourceInterface $resource, Field $field, string $search, int $limit, $request): array
    {
        $fieldResource = $field->getResource();
        $keyAttribute = $fieldResource::$attributeKey;

        $fieldResourceInstance = new $fieldResource;

        $fieldModel = $field->getModel();
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

        // Get results
        $results = $query->select([...$fieldResourceInstance->getAttributesForSelect($request), $keyAttribute])->get();

        // Format as an option array
        return $results->map(function ($item) use ($keyAttribute, $field) {
            return [
                'value' => $item->{$keyAttribute},
                'label' => $field->resolveDisplay($item)
            ];
        })->toArray();
    }
}