<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\FieldOptions\Contracts\FieldOptionsStrategy;
use SchoolAid\Nadota\Http\Services\FieldOptions\OptionsConfig;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\BelongsToManyOptionsStrategy;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\BelongsToOptionsStrategy;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\DefaultOptionsStrategy;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\MorphToManyOptionsStrategy;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\MorphToOptionsStrategy;
use SchoolAid\Nadota\ResourceManager;

class FieldOptionsService
{
    /**
     * @var array<FieldOptionsStrategy>
     */
    protected array $strategies = [];

    /**
     * Flag to track if strategies have been sorted.
     */
    protected bool $strategiesSorted = false;

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
        // Register in priority order (will be sorted by priority anyway)
        $this->registerStrategy(new BelongsToOptionsStrategy());
        $this->registerStrategy(new MorphToOptionsStrategy());
        $this->registerStrategy(new BelongsToManyOptionsStrategy());
        $this->registerStrategy(new MorphToManyOptionsStrategy());
        $this->registerStrategy(new DefaultOptionsStrategy()); // Fallback
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
        // Mark as unsorted - sorting will happen once when needed
        $this->strategiesSorted = false;
    }

    /**
     * Ensure strategies are sorted by priority (higher priority first).
     * Only sorts once, subsequent calls are no-ops.
     */
    protected function ensureStrategiesSorted(): void
    {
        if (!$this->strategiesSorted) {
            usort($this->strategies, function ($a, $b) {
                return $b->getPriority() <=> $a->getPriority();
            });
            $this->strategiesSorted = true;
        }
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
            return [
                'success' => false,
                'message' => 'No strategy found for field type',
                'options' => []
            ];
        }

        // Build exclude a list (manual + auto from related records)
        $exclude = $request->get('exclude', []);
        $resourceId = $request->get('resourceId');

        // Auto-exclude already attached records if resourceId is provided
        if ($resourceId) {
            $attachedIds = $this->getAttachedIds($resource, $field, $resourceId);
            $exclude = $this->mergeExcludeIds($exclude, $attachedIds);
        }

        // Determine limit: field's optionsLimit takes priority over request/default
        $limit = $request->get('limit', OptionsConfig::DEFAULT_LIMIT);
        if (method_exists($field, 'hasOptionsLimit') && $field->hasOptionsLimit()) {
            $limit = $field->getOptionsLimit();
        }

        // Use strategy to fetch options
        $params = array_merge([
            'search' => $request->get('search', ''),
            'limit' => $limit,
            'exclude' => $exclude,
            'orderBy' => $request->get('orderBy'),
            'orderDirection' => $request->get('orderDirection', OptionsConfig::DEFAULT_ORDER_DIRECTION),
            'filters' => $request->get('filters', []),
        ], $additionalParams);

        $options = $strategy->fetchOptions($request, $resource, $field, $params);

        return [
            'success' => true,
            'options' => $options,
            'meta' => [
                'total' => count($options),
                'search' => $params['search'],
                'limit' => $params['limit'],
                'fieldType' => method_exists($field, 'getType') ? $field->getType() : null
            ]
        ];
    }

    /**
     * Get paginated options for a field.
     *
     * @param NadotaRequest $request
     * @param string $resourceName
     * @param string $fieldName
     * @param array $additionalParams
     * @return array
     */
    public function getPaginatedOptions(
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
                'data' => [],
                'meta' => []
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
                'data' => [],
                'meta' => []
            ];
        }

        // Get field resource
        $fieldResource = $field->getResource();

        if (!$fieldResource) {
            return [
                'success' => false,
                'message' => 'Field has no associated resource',
                'data' => [],
                'meta' => []
            ];
        }

        $fieldResourceInstance = new $fieldResource();

        // Get model
        $fieldModel = method_exists($field, 'getModel') ? $field->getModel() : null;
        if (!$fieldModel) {
            $fieldModel = $fieldResourceInstance->model ?? null;
        }

        if (!$fieldModel) {
            return [
                'success' => false,
                'message' => 'Could not determine model for field',
                'data' => [],
                'meta' => []
            ];
        }

        // Get parameters
        $search = $request->get('search', '');
        $perPage = min((int) $request->get('per_page', OptionsConfig::DEFAULT_PER_PAGE), OptionsConfig::MAX_PER_PAGE);
        $page = $request->get('page', 1);
        $exclude = $request->get('exclude', []);
        $resourceId = $request->get('resourceId');
        $orderBy = $request->get('orderBy');
        $orderDirection = $request->get('orderDirection', OptionsConfig::DEFAULT_ORDER_DIRECTION);
        $filters = $request->get('filters', []);

        // Auto-exclude already attached records if resourceId is provided
        if ($resourceId) {
            $attachedIds = $this->getAttachedIds($resource, $field, $resourceId);
            $exclude = $this->mergeExcludeIds($exclude, $attachedIds);
        } else {
            // Normalize exclude
            if (is_string($exclude)) {
                $exclude = array_filter(explode(',', $exclude));
            }
        }

        // Get key attribute
        $keyAttribute = $fieldResource::$attributeKey ?? 'id';

        // Build query
        $query = $fieldModel::query();

        // Apply resource's optionsQuery customization
        if (method_exists($fieldResourceInstance, 'optionsQuery')) {
            $query = $fieldResourceInstance->optionsQuery($query, $request, [
                'search' => $search,
                'exclude' => $exclude,
                'orderBy' => $orderBy,
                'orderDirection' => $orderDirection,
                'filters' => $filters,
            ]);
        }

        // Apply custom filters
        if (!empty($filters)) {
            $this->applyFilters($query, $filters, $fieldResourceInstance);
        }

        // Apply search
        if (!empty($search)) {
            $this->applySearch($query, $search, $fieldResourceInstance);
        }

        // Apply exclude
        if (!empty($exclude)) {
            $query->whereNotIn($keyAttribute, $exclude);
        }

        // Apply ordering
        if ($orderBy) {
            $query->orderBy($orderBy, $orderDirection);
        }

        // Get select columns
        $selectColumns = $this->getSelectColumns($fieldResourceInstance, $request, $keyAttribute);

        // Paginate
        $paginator = $query->select($selectColumns)->paginate($perPage, ['*'], 'page', $page);

        // Format options
        $options = collect($paginator->items())->map(function ($item) use ($field, $keyAttribute, $fieldResourceInstance) {
            $label = null;

            // Try field's resolveDisplay first
            if (method_exists($field, 'resolveDisplay')) {
                $label = $field->resolveDisplay($item);
            }

            // Fallback to resource displayLabel
            if (!$label && method_exists($fieldResourceInstance, 'displayLabel')) {
                $label = $fieldResourceInstance->displayLabel($item);
            }

            // Ultimate fallback
            if (!$label) {
                $label = $this->resolveDefaultLabel($item);
            }

            return [
                'value' => $item->{$keyAttribute},
                'label' => $label,
            ];
        })->toArray();

        return [
            'success' => true,
            'data' => $options,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'search' => $search,
                'fieldType' => method_exists($field, 'getType') ? $field->getType() : null
            ]
        ];
    }

    /**
     * Apply search to query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @param ResourceInterface $resourceInstance
     * @return void
     */
    protected function applySearch($query, string $search, ResourceInterface $resourceInstance): void
    {
        $query->where(function ($q) use ($search, $resourceInstance) {
            // Search in searchable attributes
            if (method_exists($resourceInstance, 'getSearchableAttributes')) {
                $searchableAttributes = $resourceInstance->getSearchableAttributes();
                foreach ($searchableAttributes as $attribute) {
                    $q->orWhere($attribute, 'like', '%' . $search . '%');
                }
            }

            // Search in searchable relations
            if (method_exists($resourceInstance, 'getSearchableRelations')) {
                $searchableRelations = $resourceInstance->getSearchableRelations();
                foreach ($searchableRelations as $relationPath) {
                    $parts = explode('.', $relationPath);
                    if (count($parts) >= 2) {
                        $relation = implode('.', array_slice($parts, 0, -1));
                        $attribute = end($parts);
                        $q->orWhereHas($relation, function ($relationQuery) use ($attribute, $search) {
                            $relationQuery->where($attribute, 'like', '%' . $search . '%');
                        });
                    }
                }
            }

            // Fallback if no searchable attributes configured
            if (
                (!method_exists($resourceInstance, 'getSearchableAttributes') || empty($resourceInstance->getSearchableAttributes())) &&
                (!method_exists($resourceInstance, 'getSearchableRelations') || empty($resourceInstance->getSearchableRelations()))
            ) {
                foreach (OptionsConfig::FALLBACK_SEARCH_ATTRIBUTES as $attribute) {
                    $q->orWhere($attribute, 'like', '%' . $search . '%');
                }
            }

            // Allow resource to add custom search logic
            if (method_exists($resourceInstance, 'applySearch')) {
                $resourceInstance->applySearch($q, $search);
            }
        });
    }

    /**
     * Apply custom filters to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @param ResourceInterface $resourceInstance
     * @return void
     */
    protected function applyFilters($query, array $filters, ResourceInterface $resourceInstance): void
    {
        // Get allowed filters from resource (if defined)
        $allowedFilters = method_exists($resourceInstance, 'getAllowedOptionsFilters')
            ? $resourceInstance->getAllowedOptionsFilters()
            : null;

        foreach ($filters as $field => $value) {
            // Skip if allowedFilters is defined and field is not in it
            if ($allowedFilters !== null && !in_array($field, $allowedFilters)) {
                continue;
            }

            // Handle null values
            if ($value === null || $value === 'null') {
                $query->whereNull($field);
                continue;
            }

            // Handle array values (whereIn)
            if (is_array($value)) {
                $query->whereIn($field, $value);
                continue;
            }

            // Handle standard equality
            $query->where($field, $value);
        }
    }

    /**
     * Get select columns for query.
     *
     * @param ResourceInterface $resourceInstance
     * @param NadotaRequest $request
     * @param string $keyAttribute
     * @return array
     */
    protected function getSelectColumns(ResourceInterface $resourceInstance, NadotaRequest $request, string $keyAttribute): array
    {
        $selectColumns = [];

        if (method_exists($resourceInstance, 'getSelectColumns')) {
            $selectColumns = $resourceInstance->getSelectColumns($request);
        }

        // Always include the key attribute
        if (!empty($selectColumns) && !in_array($keyAttribute, $selectColumns)) {
            $selectColumns[] = $keyAttribute;
        }

        // If empty, select all
        if (empty($selectColumns)) {
            return ['*'];
        }

        return $selectColumns;
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
     * Find the appropriate strategy for a field.
     *
     * @param Field $field
     * @return FieldOptionsStrategy|null
     */
    protected function findStrategy(Field $field): ?FieldOptionsStrategy
    {
        // Ensure strategies are sorted before searching
        $this->ensureStrategiesSorted();
        
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
        $fields = $resource->flattenFields($request);

        // Try to find by key first (the most common case)
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

        return $field;
    }

    /**
     * Get IDs of records already attached to a resource via a relation field.
     *
     * @param ResourceInterface $resource
     * @param Field $field
     * @param mixed $resourceId
     * @return array
     */
    protected function getAttachedIds(ResourceInterface $resource, Field $field, mixed $resourceId): array
    {
        // Only works for relationship fields
        if (!method_exists($field, 'getRelation')) {
            return [];
        }

        $relationName = $field->getRelation();

        if (!$relationName) {
            return [];
        }

        try {
            // Get the parent model
            $modelClass = $resource->model;
            $model = $modelClass::find($resourceId);

            if (!$model) {
                return [];
            }

            // Check if the relation exists
            if (!method_exists($model, $relationName)) {
                return [];
            }

            // Get the relation
            $relation = $model->{$relationName}();

            // Get the related model's key name
            $relatedKeyName = $relation->getRelated()->getKeyName();

            // Pluck the IDs
            return $model->{$relationName}()->pluck($relatedKeyName)->toArray();
        } catch (\Throwable $e) {
            // Silently fail - don't break the options request
            return [];
        }
    }

    /**
     * Merge exclude IDs from request with auto-excluded IDs.
     *
     * @param mixed $exclude
     * @param array $attachedIds
     * @return array
     */
    protected function mergeExcludeIds(mixed $exclude, array $attachedIds): array
    {
        // Normalize exclude from request
        if (is_string($exclude)) {
            $exclude = array_filter(explode(',', $exclude));
        }

        if (!is_array($exclude)) {
            $exclude = [];
        }

        // Merge and deduplicate
        return array_values(array_unique(array_merge($exclude, $attachedIds)));
    }
}
