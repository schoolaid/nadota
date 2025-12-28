<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasManyThrough;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphToMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphedByMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Resources\RelationResource;

class RelationIndexService
{
    /**
     * Multi-record relation types that support pagination.
     */
    protected array $multiRelationTypes = [
        HasMany::class,
        BelongsToMany::class,
        MorphMany::class,
        MorphToMany::class,
        MorphedByMany::class,
        HasManyThrough::class,
    ];

    /**
     * Handle paginated relation request.
     */
    public function handle(NadotaRequest $request, string $modelId, string $fieldKey): JsonResponse
    {
        $resource = $request->getResource();

        // Find the parent model
        $parentModel = $resource->model::findOrFail($modelId);

        // Find the relation field
        $field = $this->findRelationField($resource, $request, $fieldKey);

        if (!$field) {
            return response()->json(['error' => 'Field not found', 'field' => $fieldKey], 404);
        }

        if (!$this->isMultiRelation($field)) {
            return response()->json(['error' => 'Field is not a multi-record relation'], 422);
        }

        $relationName = $field->getRelation();

        if (!method_exists($parentModel, $relationName)) {
            return response()->json(['error' => 'Relation method not found on model'], 404);
        }

        // Build and execute query
        $query = $parentModel->{$relationName}();

        // Resolve related resource if available
        $relatedResource = null;
        if ($field->getResource()) {
            $relatedResource = new ($field->getResource());
        }

        // Apply query modifications
        $query = $this->buildQuery($request, $query, $field, $relatedResource);
        $query = $this->applySearch($request, $query, $field, $relatedResource);
        $query = $this->applySorting($request, $query, $field);

        // Paginate and format response
        return $this->paginateAndFormat($request, $query, $field, $relatedResource, $resource);
    }

    /**
     * Find a relation field by key.
     */
    protected function findRelationField(ResourceInterface $resource, NadotaRequest $request, string $fieldKey): ?Field
    {
        $fields = $resource->flattenFields($request);

        // Try to find by key
        $field = $fields->first(fn($f) => $f->key() === $fieldKey);

        if ($field) {
            return $field;
        }

        // Try to find by relation name
        return $fields->first(fn($f) => $f->isRelationship() && $f->getRelation() === $fieldKey);
    }

    /**
     * Check if field is a multi-record relation.
     */
    protected function isMultiRelation(Field $field): bool
    {
        foreach ($this->multiRelationTypes as $type) {
            if ($field instanceof $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build the base query with column selection and pivot data.
     */
    protected function buildQuery(NadotaRequest $request, $query, Field $field, ?ResourceInterface $resource)
    {
        // Apply column selection from related resource
        if ($resource) {
            $columns = $resource->getSelectColumns($request);
            if (!empty($columns)) {
                // Ensure primary key is included
                $relatedModel = $query->getRelated();
                $primaryKey = $relatedModel->getKeyName();
                if (!in_array($primaryKey, $columns)) {
                    array_unshift($columns, $primaryKey);
                }

                // Qualify columns with table name to avoid ambiguity with pivot table columns
                $qualifiedColumns = $this->qualifyColumnsWithTable($query, $columns);
                $query->select($qualifiedColumns);
            }
        }

        // Apply pivot columns for many-to-many relations
        if (method_exists($field, 'hasPivotColumns') && $field->hasPivotColumns()) {
            $query->withPivot($field->getPivotColumns());
        }

        return $query;
    }

    /**
     * Qualify column names with the related table name.
     * This prevents ambiguity when pivot tables have columns with the same name.
     *
     * @param mixed $query The relation query
     * @param array $columns Column names to qualify
     * @return array Qualified column names
     */
    protected function qualifyColumnsWithTable($query, array $columns): array
    {
        $table = $query->getRelated()->getTable();

        return array_map(function ($column) use ($table) {
            // Skip if already qualified (contains a dot)
            if (str_contains($column, '.')) {
                return $column;
            }

            return "{$table}.{$column}";
        }, $columns);
    }

    /**
     * Apply search filters to the query.
     */
    protected function applySearch(NadotaRequest $request, $query, Field $field, ?ResourceInterface $resource)
    {
        $search = $request->get('search');

        if (!$search) {
            return $query;
        }

        $searchableAttributes = [];

        // Get searchable attributes from related resource using the getter method
        if ($resource && method_exists($resource, 'getSearchableAttributes')) {
            $searchableAttributes = $resource->getSearchableAttributes();
        }

        if (empty($searchableAttributes)) {
            return $query;
        }

        // Get the related table name for qualifying columns
        $table = $query->getRelated()->getTable();

        $query->where(function ($q) use ($searchableAttributes, $search, $table) {
            foreach ($searchableAttributes as $attr) {
                // Qualify column with table name to avoid ambiguity
                $qualifiedAttr = str_contains($attr, '.') ? $attr : "{$table}.{$attr}";
                $q->orWhere($qualifiedAttr, 'like', "%{$search}%");
            }
        });

        return $query;
    }

    /**
     * Apply sorting to the query.
     */
    protected function applySorting(NadotaRequest $request, $query, Field $field)
    {
        // Get sort parameters from request or field defaults
        $sortField = $request->get('sort_field');
        $sortDirection = $request->get('sort_direction', 'desc');

        // Use field's default ordering if no request sort
        if (!$sortField && method_exists($field, 'getOrderBy')) {
            $sortField = $field->getOrderBy();
        }

        if (!$sortDirection && method_exists($field, 'getOrderDirection')) {
            $sortDirection = $field->getOrderDirection();
        }

        if ($sortField) {
            $query->orderBy($sortField, $sortDirection);
        }

        return $query;
    }

    /**
     * Paginate results and format the response.
     */
    protected function paginateAndFormat(
        NadotaRequest $request,
        $query,
        Field $field,
        ?ResourceInterface $relatedResource,
        ResourceInterface $parentResource
    ): JsonResponse {
        $perPage = (int) $request->get('per_page', 15);
        $paginated = $query->paginate($perPage);

        // Format items
        $items = $this->formatItems($paginated->items(), $field, $relatedResource, $request);

        // Build response
        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
                'resource' => $relatedResource ? $relatedResource::getKey() : null,
                'relation_type' => method_exists($field, 'relationType') ? $field->relationType() : null,
                'has_pivot' => method_exists($field, 'hasPivotColumns') ? $field->hasPivotColumns() : false,
            ],
            'links' => [
                'first' => $paginated->url(1),
                'last' => $paginated->url($paginated->lastPage()),
                'prev' => $paginated->previousPageUrl(),
                'next' => $paginated->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Format items using RelationResource or basic formatting.
     */
    protected function formatItems(array $items, Field $field, ?ResourceInterface $resource, NadotaRequest $request): array
    {
        if (!$resource) {
            return $this->formatBasicItems($items, $field);
        }

        // Use custom fields if defined on the field, otherwise use resource's index fields
        $customFields = method_exists($field, 'getCustomFields') ? $field->getCustomFields() : null;
        $fields = $customFields !== null
            ? collect($customFields)
            : collect($resource->fieldsForIndex($request));

        // Get except field keys
        $exceptFieldKeys = method_exists($field, 'getExceptFieldKeys') ? $field->getExceptFieldKeys() : null;

        $relationResource = RelationResource::make($fields, $resource, $exceptFieldKeys)
            ->withLabelResolver(fn($item, $res) => $this->resolveLabel($item, $field, $res));

        // Check if fields should be included in the response
        $shouldIncludeFields = method_exists($field, 'shouldIncludeFields') ? $field->shouldIncludeFields() : false;
        if (!$shouldIncludeFields) {
            $relationResource->withoutFields();
        }

        // Add pivot columns if applicable
        if (method_exists($field, 'hasPivotColumns') && $field->hasPivotColumns()) {
            $relationResource->withPivotColumns($field->getPivotColumns());
        }

        return collect($items)->map(fn($item) => $relationResource->formatItem($item, $request))->toArray();
    }

    /**
     * Format items without a resource (basic formatting).
     */
    protected function formatBasicItems(array $items, Field $field): array
    {
        return collect($items)->map(function ($item) use ($field) {
            $data = [
                'id' => $item->getKey(),
                'label' => $this->resolveLabel($item, $field, null),
                'deletedAt' => $item->deleted_at ?? null,
            ];

            // Include pivot data if available
            if (method_exists($field, 'hasPivotColumns') && $field->hasPivotColumns() && isset($item->pivot)) {
                $pivotData = [];
                foreach ($field->getPivotColumns() as $column) {
                    $pivotData[$column] = $item->pivot->{$column} ?? null;
                }
                $data['pivot'] = $pivotData;
            }

            return $data;
        })->toArray();
    }

    /**
     * Resolve display label for an item.
     */
    protected function resolveLabel(Model $item, Field $field, ?ResourceInterface $resource): mixed
    {
        // Priority 1: Field's display callback
        if (method_exists($field, 'hasDisplayCallback') && $field->hasDisplayCallback()) {
            return $field->resolveDisplay($item);
        }

        // Priority 2: Field's display attribute
        if (method_exists($field, 'getAttributeForDisplay')) {
            $displayAttr = $field->getAttributeForDisplay();
            if ($displayAttr && isset($item->{$displayAttr})) {
                return $item->{$displayAttr};
            }
        }

        // Priority 3: Resource's displayLabel method
        if ($resource && method_exists($resource, 'displayLabel')) {
            return $resource->displayLabel($item);
        }

        // Priority 4: Try common attributes
        $commonAttributes = ['name', 'title', 'label', 'display_name'];
        foreach ($commonAttributes as $attr) {
            if (isset($item->{$attr})) {
                return $item->{$attr};
            }
        }

        // Fallback: primary key
        return $item->getKey();
    }
}
