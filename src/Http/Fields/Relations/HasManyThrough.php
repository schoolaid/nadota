<?php

namespace SchoolAid\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Resources\RelationResource;

class HasManyThrough extends Field
{
    /**
     * Maximum number of related items to show (when not paginated).
     */
    protected ?int $limit = 10;

    /**
     * Whether to show as paginated list.
     */
    protected bool $paginated = false;

    /**
     * Order by field for the relation.
     */
    protected ?string $orderBy = null;

    /**
     * Order direction for the relation.
     */
    protected string $orderDirection = 'desc';

    /**
     * Custom fields to display for the relation.
     */
    protected ?array $customFields = null;

    /**
     * Whether to include fields in the response.
     * Default false for lighter responses. Use ->withFields() to enable.
     */
    protected bool $withFields = false;

    /**
     * Create a new HasManyThrough field.
     *
     * @param string $name Display name for the field
     * @param string $relation Relation method name on the model
     * @param string|null $resource The resource class for the related model
     */
    public function __construct(string $name, string $relation, ?string $resource = null)
    {
        parent::__construct($name, '', FieldType::HAS_MANY->value, static::safeConfig('nadota.fields.hasManyThrough.component', 'field-has-many-through'));
        $this->relation($relation);
        $this->isRelationship = true;

        // Set key to relation name for URL generation (attribute stays empty to avoid column selection)
        $this->fieldData->key = $relation;

        // HasManyThrough should not show on index by default
        $this->showOnIndex = false;
        $this->showOnDetail = true;
        $this->showOnCreation = false;
        $this->showOnUpdate = false;

        // Don't apply in index query
        $this->applyInIndexQuery = false;
        $this->applyInShowQuery = true;

        if ($resource) {
            $this->resource($resource);
        }
    }

    /**
     * Set the limit for related items.
     *
     * @param int|null $limit
     * @return static
     */
    public function limit(?int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set the order for related items.
     *
     * @param string $field
     * @param string $direction
     * @return static
     */
    public function orderBy(string $field, string $direction = 'desc'): static
    {
        $this->orderBy = $field;
        $this->orderDirection = $direction;
        return $this;
    }

    /**
     * Enable pagination for this field.
     *
     * @param bool $paginated
     * @return static
     */
    public function paginated(bool $paginated = true): static
    {
        $this->paginated = $paginated;
        return $this;
    }

    /**
     * Set custom fields to display for the relation.
     *
     * @param array $fields Array of Field instances
     * @return static
     */
    public function fields(array $fields): static
    {
        $this->customFields = $fields;
        return $this;
    }

    /**
     * Enable including fields in the response.
     *
     * @param bool $value
     * @return static
     */
    public function withFields(bool $value = true): static
    {
        $this->withFields = $value;
        return $this;
    }

    /**
     * Resolve the field value for display.
     *
     * @param Request $request
     * @param Model $model
     * @param ResourceInterface|null $resource
     * @return mixed
     */
    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $relationName = $this->getRelation();

        if (!method_exists($model, $relationName)) {
            return [];
        }

        // Use already loaded relation to avoid N+1 queries
        $relatedItems = $model->{$relationName};

        // Ensure we have a collection
        if (!$relatedItems instanceof Collection) {
            return [];
        }

        // If we have a resource, format each item with its fields
        if ($this->getResource()) {
            $resourceClass = $this->getResource();

            // Verify it's a valid Nadota Resource
            if (!is_subclass_of($resourceClass, ResourceInterface::class)) {
                return $this->formatBasic($relatedItems);
            }

            $relatedResource = new $resourceClass;

            return $this->formatWithResource($relatedItems, $relatedResource, $request);
        }

        // Otherwise, return raw data with basic formatting
        return $this->formatBasic($relatedItems);
    }

    /**
     * Format related items using resource fields.
     */
    protected function formatWithResource(Collection $items, ResourceInterface $resource, Request $request): array
    {
        $fields = $this->customFields !== null
            ? collect($this->customFields)
            : collect($resource->fieldsForIndex($request));

        $relationResource = RelationResource::make($fields, $resource, $this->exceptFieldKeys)
            ->withLabelResolver(fn($item, $res) => $this->resolveLabel($item, $res));

        // Only include fields if explicitly enabled
        if (!$this->withFields) {
            $relationResource->withoutFields();
        }

        return $relationResource->formatCollection($items, $request, [
            'hasMore' => $this->limit !== null && $items->count() >= $this->limit,
        ]);
    }

    /**
     * Resolve display label for the related model.
     */
    protected function resolveLabel(Model $item, ?ResourceInterface $resource): mixed
    {
        if ($this->hasDisplayCallback()) {
            return $this->resolveDisplay($item);
        }

        if ($this->displayAttribute) {
            return $item->{$this->displayAttribute} ?? $item->getKey();
        }

        if ($resource && method_exists($resource, 'displayLabel')) {
            return $resource->displayLabel($item);
        }

        return $item->getKey();
    }

    /**
     * Format related items without resource.
     * Uses same structure as index/show for consistency.
     */
    protected function formatBasic(Collection $items): array
    {
        return [
            'data' => $items->map(function ($item) {
                return [
                    'id' => $item->getKey(),
                    'label' => $this->resolveLabelBasic($item),
                    'deletedAt' => $item->deleted_at ?? null,
                ];
            })->toArray(),
            'meta' => [
                'total' => $items->count(),
                'hasMore' => $this->limit !== null && $items->count() >= $this->limit,
            ]
        ];
    }

    /**
     * Resolve label for basic formatting.
     */
    protected function resolveLabelBasic(Model $item): mixed
    {
        if ($this->displayAttribute) {
            return $item->{$this->displayAttribute} ?? $item->getKey();
        }

        $commonAttributes = ['name', 'title', 'label', 'display_name'];
        foreach ($commonAttributes as $attr) {
            if (isset($item->{$attr})) {
                return $item->{$attr};
            }
        }

        return $item->getKey();
    }

    /**
     * HasManyThrough fields don't apply sorting to the parent query.
     */
    public function applySorting(Builder $query, $sortDirection, $modelInstance): Builder
    {
        return $query;
    }

    /**
     * Get columns to select for the related model in eager loading.
     * HasManyThrough is complex - the FK is on the intermediate table.
     *
     * @param Request $request
     * @return array|null
     */
    public function getRelatedColumns(Request $request): ?array
    {
        $columns = parent::getRelatedColumns($request);

        if ($columns === null) {
            return null;
        }

        // For HasManyThrough, try to include the laravel_through_key
        // which Laravel automatically adds during eager loading
        $throughKey = $this->resolveThroughKey($request);

        if ($throughKey && !in_array($throughKey, $columns)) {
            $columns[] = $throughKey;
        }

        return $columns;
    }

    /**
     * Resolve the through key from the Eloquent relationship.
     *
     * @param Request $request
     * @return string|null
     */
    protected function resolveThroughKey(Request $request): ?string
    {
        try {
            if (!method_exists($request, 'getResource')) {
                return null;
            }

            $parentResource = $request->getResource();
            $parentModelClass = $parentResource->model;
            $parentModel = new $parentModelClass;
            $relationName = $this->getRelation();

            if (method_exists($parentModel, $relationName)) {
                $relation = $parentModel->{$relationName}();

                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasManyThrough) {
                    // Return the second local key (the key on the final table)
                    return $relation->getSecondLocalKeyName();
                }
            }
        } catch (\Throwable $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Get props for frontend component.
     */
    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        $props = parent::getProps($request, $model, $resource);

        $props = array_merge($props, [
            'limit' => $this->limit,
            'paginated' => $this->paginated,
            'orderBy' => $this->orderBy,
            'orderDirection' => $this->orderDirection,
            'relationType' => 'hasManyThrough',
            'resource' => $this->getResource() ? $this->getResource()::getKey() : null,
        ]);

        // Add pagination URL if paginated and we have model context
        if ($this->paginated && $model && $resource) {
            $apiPrefix = static::safeConfig('nadota.api.prefix', 'nadota-api');
            $resourceKey = $resource::getKey();
            $modelId = $model->getKey();
            $fieldKey = $this->key();

            $props['paginationUrl'] = "/{$apiPrefix}/{$resourceKey}/resource/{$modelId}/relation/{$fieldKey}";
        }

        return $props;
    }

    /**
     * Check if this field is configured for pagination.
     *
     * @return bool
     */
    public function isPaginated(): bool
    {
        return $this->paginated;
    }

    /**
     * Get the configured limit.
     *
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Get the configured order by field.
     *
     * @return string|null
     */
    public function getOrderBy(): ?string
    {
        return $this->orderBy;
    }

    /**
     * Get the configured order direction.
     *
     * @return string
     */
    public function getOrderDirection(): string
    {
        return $this->orderDirection;
    }

    /**
     * Get the custom fields configured for this relation.
     *
     * @return array|null
     */
    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    /**
     * Check if fields should be included in the response.
     *
     * @return bool
     */
    public function shouldIncludeFields(): bool
    {
        return $this->withFields;
    }

    /**
     * Get the field keys to exclude from the response.
     *
     * @return array|null
     */
    public function getExceptFieldKeys(): ?array
    {
        return $this->exceptFieldKeys ?? null;
    }

    /**
     * HasManyThrough does not have pivot columns.
     *
     * @return bool
     */
    public function hasPivotColumns(): bool
    {
        return false;
    }

    /**
     * HasManyThrough does not have pivot columns.
     *
     * @return array
     */
    public function getPivotColumns(): array
    {
        return [];
    }

    /**
     * Fill method for HasManyThrough (read-only).
     */
    public function fill(Request $request, Model $model): void
    {
        // HasManyThrough relations are read-only
    }

    /**
     * Get the relation type.
     */
    public function relationType(): string
    {
        return 'hasManyThrough';
    }
}
