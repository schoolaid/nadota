<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions\Strategies;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\FieldOptions\Contracts\FieldOptionsStrategy;
use SchoolAid\Nadota\Http\Services\FieldOptions\Traits\SearchesOptions;

/**
 * Strategy for MorphTo fields.
 * This strategy requires special handling because it needs a morphType parameter
 * to determine which model to query.
 */
class MorphToOptionsStrategy implements FieldOptionsStrategy
{
    use SearchesOptions;

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
     * @param Field|MorphTo $field
     * @param array $params
     * @return array
     */
    public function fetchOptions(
        NadotaRequest $request,
        ResourceInterface $resource,
        Field $field,
        array $params = []
    ): array {
        // Get the morph type from params (required for MorphTo)
        $morphType = $params['morphType'] ?? null;

        if (!$morphType) {
            return [];
        }

        // Get common parameters
        $commonParams = $this->getCommonParams($request, $params);
        $search = $commonParams['search'];
        $limit = $commonParams['limit'];
        $exclude = $commonParams['exclude'];
        $orderBy = $commonParams['orderBy'];
        $orderDirection = $commonParams['orderDirection'];

        // Get the morph models from the field
        $morphModels = $field->getMorphModels();

        if (!isset($morphModels[$morphType])) {
            return [];
        }

        // Get the model class for this morph type
        $modelClass = $morphModels[$morphType];
        $model = new $modelClass;

        // Get the resource class if available
        $morphResources = $field->getMorphResources();
        $resourceClass = $morphResources[$morphType] ?? null;
        $resourceInstance = $resourceClass ? new $resourceClass : null;

        // Get key attribute
        $keyAttribute = $resourceInstance
            ? ($resourceClass::$attributeKey ?? $model->getKeyName())
            : $model->getKeyName();

        // Build the query
        $query = $modelClass::query();

        // Apply resource's optionsQuery customization
        $query = $this->applyResourceOptionsQuery($query, $resourceInstance, $request, $params);

        // Apply search
        if (!empty($search)) {
            $this->applySearch($query, $search, $resourceInstance);
        }

        // Apply exclude
        $exclude = $this->normalizeExclude($exclude);
        if (!empty($exclude)) {
            $this->applyExclude($query, $exclude, $keyAttribute);
        }

        // Apply ordering
        $this->applyOrdering($query, $orderBy, $orderDirection);

        // Apply limit
        $query->limit($limit);

        // Get select columns
        $selectColumns = $this->buildSelectColumns($resourceInstance, $request, $keyAttribute);

        // Get results
        $results = $query->select($selectColumns)->get();

        // Format as options
        return $this->formatAsOptions($results, $keyAttribute, function ($item) use ($field) {
            return $this->resolveLabel($field, $item);
        });
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
     * Build select columns for the query.
     *
     * @param ResourceInterface|null $resourceInstance
     * @param NadotaRequest $request
     * @param string $keyAttribute
     * @return array
     */
    protected function buildSelectColumns(
        ?ResourceInterface $resourceInstance,
        NadotaRequest $request,
        string $keyAttribute
    ): array {
        $selectColumns = [];

        if ($resourceInstance && method_exists($resourceInstance, 'getSelectColumns')) {
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

    /**
     * Get the priority of this strategy.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 90; // Below BelongsTo but above most others
    }
}
