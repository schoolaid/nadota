<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions\Strategies;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\FieldOptions\Contracts\FieldOptionsStrategy;
use SchoolAid\Nadota\Http\Services\FieldOptions\OptionsConfig;
use SchoolAid\Nadota\Http\Services\FieldOptions\Traits\SearchesOptions;

/**
 * Abstract base class for options strategies.
 * Provides common functionality to reduce code duplication.
 */
abstract class AbstractOptionsStrategy implements FieldOptionsStrategy
{
    use SearchesOptions;

    /**
     * Fetch options for a field.
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
        // Get common parameters
        $commonParams = $this->getCommonParams($request, $params);

        // Resolve field resource and model
        $fieldResource = $this->resolveFieldResource($field);

        if (!$fieldResource) {
            return [];
        }

        $fieldResourceInstance = new $fieldResource;
        $fieldModel = $this->resolveModel($field, $fieldResourceInstance);

        if (!$fieldModel) {
            return [];
        }

        // Get key attribute
        $keyAttribute = $fieldResource::$attributeKey ?? 'id';

        // Build and execute query
        $results = $this->buildAndExecuteQuery(
            $request,
            $field,
            $fieldModel,
            $fieldResourceInstance,
            $keyAttribute,
            $commonParams,
            $params
        );

        // Format as options (pass resource instance for displayLabel resolution)
        return $this->formatResults($results, $field, $keyAttribute, $fieldResourceInstance);
    }

    /**
     * Resolve the field resource class.
     *
     * @param Field $field
     * @return string|null
     */
    protected function resolveFieldResource(Field $field): ?string
    {
        return $field->getResource();
    }

    /**
     * Resolve the model class for the field.
     *
     * @param Field $field
     * @param ResourceInterface $fieldResourceInstance
     * @return string|null
     */
    protected function resolveModel(Field $field, ResourceInterface $fieldResourceInstance): ?string
    {
        // Try to get model from field first
        if (method_exists($field, 'getModel')) {
            $model = $field->getModel();
            if ($model) {
                return $model;
            }
        }

        // Fallback to resource model
        return $fieldResourceInstance->model ?? null;
    }

    /**
     * Build and execute the query for options.
     *
     * @param NadotaRequest $request
     * @param Field $field
     * @param string $fieldModel
     * @param ResourceInterface $fieldResourceInstance
     * @param string $keyAttribute
     * @param array $commonParams
     * @param array $originalParams
     * @return Collection
     */
    protected function buildAndExecuteQuery(
        NadotaRequest $request,
        Field $field,
        string $fieldModel,
        ResourceInterface $fieldResourceInstance,
        string $keyAttribute,
        array $commonParams,
        array $originalParams
    ): Collection {
        $search = $commonParams['search'];
        $limit = $commonParams['limit'];
        $exclude = $commonParams['exclude'];
        $orderBy = $commonParams['orderBy'];
        $orderDirection = $commonParams['orderDirection'];
        $filters = $commonParams['filters'] ?? [];

        // Build base query
        $query = $fieldModel::query();

        // Apply resource's optionsQuery customization
        $query = $this->applyResourceOptionsQuery($query, $fieldResourceInstance, $request, $originalParams);

        // Apply custom filters
        if (!empty($filters)) {
            $this->applyFilters($query, $filters, $fieldResourceInstance);
        }

        // Apply search
        if (!empty($search)) {
            $this->applySearch($query, $search, $fieldResourceInstance);
        }

        // Apply exclude
        $exclude = $this->normalizeExclude($exclude);
        if (!empty($exclude)) {
            $this->applyExclude($query, $exclude, $keyAttribute);
        }

        // Apply ordering (with field-level fallback if available)
        $this->applyFieldOrdering($query, $field, $orderBy, $orderDirection);

        // Apply limit (null means no limit)
        if ($limit !== null) {
            $query->limit($limit);
        }

        // Get select columns
        $selectColumns = $this->buildSelectColumns($fieldResourceInstance, $request, $keyAttribute);

        return $query->select($selectColumns)->get();
    }

    /**
     * Normalize exclude parameter to array.
     *
     * @param mixed $exclude
     * @return array
     */
    protected function normalizeExclude(mixed $exclude): array
    {
        if (is_string($exclude)) {
            return array_filter(explode(',', $exclude));
        }

        return is_array($exclude) ? $exclude : [];
    }

    /**
     * Apply ordering with field-level fallback.
     *
     * @param Builder $query
     * @param Field $field
     * @param string|null $orderBy
     * @param string $orderDirection
     * @return void
     */
    protected function applyFieldOrdering(
        Builder $query,
        Field $field,
        ?string $orderBy,
        string $orderDirection
    ): void {
        // Use provided orderBy or fallback to field config
        $effectiveOrderBy = $orderBy;
        $effectiveDirection = $orderDirection;

        if (!$effectiveOrderBy && method_exists($field, 'getOrderBy')) {
            $effectiveOrderBy = $field->getOrderBy();
        }

        if (method_exists($field, 'getOrderDirection')) {
            $fieldDirection = $field->getOrderDirection();
            if ($fieldDirection && !$orderBy) {
                $effectiveDirection = $fieldDirection;
            }
        }

        $this->applyOrdering($query, $effectiveOrderBy, $effectiveDirection);
    }

    /**
     * Build select columns for the query.
     *
     * @param ResourceInterface $resourceInstance
     * @param NadotaRequest $request
     * @param string $keyAttribute
     * @return array
     */
    protected function buildSelectColumns(
        ResourceInterface $resourceInstance,
        NadotaRequest $request,
        string $keyAttribute
    ): array {
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
     * Format results as options array.
     *
     * @param Collection $results
     * @param Field $field
     * @param string $keyAttribute
     * @param ResourceInterface|null $resourceInstance
     * @return array
     */
    protected function formatResults(
        Collection $results,
        Field $field,
        string $keyAttribute,
        ?ResourceInterface $resourceInstance = null
    ): array {
        return $this->formatAsOptions($results, $keyAttribute, function ($item) use ($field, $resourceInstance) {
            return $this->resolveLabel($field, $item, $resourceInstance);
        });
    }

    /**
     * Resolve the display label for an item.
     *
     * Priority:
     * 1. Field's resolveDisplay (callback or displayAttribute)
     * 2. Resource's displayLabel method
     * 3. Fallback to common attributes (name, title, etc.)
     *
     * @param Field $field
     * @param mixed $item
     * @param ResourceInterface|null $resourceInstance
     * @return mixed
     */
    protected function resolveLabel(Field $field, mixed $item, ?ResourceInterface $resourceInstance = null): mixed
    {
        // Priority 1: Field's resolveDisplay (callback or displayAttribute)
        if (method_exists($field, 'resolveDisplay')) {
            $label = $field->resolveDisplay($item);
            if ($label !== null && $label !== '') {
                return $label;
            }
        }

        // Priority 2: Resource's displayLabel method
        if ($resourceInstance && method_exists($resourceInstance, 'displayLabel')) {
            $label = $resourceInstance->displayLabel($item);
            if ($label !== null && $label !== '') {
                return $label;
            }
        }

        // Priority 3: Fallback to common attributes
        return $this->resolveDefaultLabel($item);
    }
}
