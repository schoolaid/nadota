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

        // Format as options
        return $this->formatResults($results, $field, $keyAttribute);
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

        // Build base query
        $query = $fieldModel::query();

        // Apply resource's optionsQuery customization
        $query = $this->applyResourceOptionsQuery($query, $fieldResourceInstance, $request, $originalParams);

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

        // Apply limit
        $query->limit($limit);

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
     * @return array
     */
    protected function formatResults(Collection $results, Field $field, string $keyAttribute): array
    {
        return $this->formatAsOptions($results, $keyAttribute, function ($item) use ($field) {
            return $this->resolveLabel($field, $item);
        });
    }

    /**
     * Resolve the display label for an item.
     *
     * @param Field $field
     * @param mixed $item
     * @return mixed
     */
    protected function resolveLabel(Field $field, mixed $item): mixed
    {
        // Try field's resolveDisplay first
        if (method_exists($field, 'resolveDisplay')) {
            $label = $field->resolveDisplay($item);
            if ($label !== null && $label !== '') {
                return $label;
            }
        }

        // Fallback to default label resolution
        return $this->resolveDefaultLabel($item);
    }
}
