<?php

namespace SchoolAid\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Traits\ManagesAttachments;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Resources\RelationResource;

class HasMany extends Field
{
    use ManagesAttachments;
    /**
     * Maximum number of related items to show (when not paginated).
     */
    protected ?int $limit = 10;

    /**
     * Whether to show as paginated list (future feature).
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
     * Whether to allow creating new related items from this field.
     */
    protected bool $canCreate = false;

    /**
     * Create a new HasMany field.
     *
     * @param string $name Display name for the field
     * @param string $relation Relation method name on the model
     * @param string|null $resource The resource class for the related model
     */
    public function __construct(string $name, string $relation, ?string $resource = null)
    {
        parent::__construct($name, '', FieldType::HAS_MANY->value, static::safeConfig('nadota.fields.hasMany.component', 'field-has-many'));
        $this->relation($relation);
        $this->isRelationship = true;

        // Set key to relation name for URL generation (attribute stays empty to avoid column selection)
        $this->fieldData->key = $relation;

        // HasMany should not show on index by default
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
     * Enable pagination for this field (future feature).
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
     * Enable or disable creation of new related items.
     *
     * @param bool $canCreate
     * @return static
     */
    public function canCreate(bool $canCreate = true): static
    {
        $this->canCreate = $canCreate;
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
        // If paginated, return empty structure - data will be loaded via pagination endpoint
        if ($this->paginated) {
            return $this->getEmptyPaginatedResponse();
        }

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

            $relatedResource = new $resourceClass;

            return $this->formatWithResource($relatedItems, $relatedResource, $request);
        }

        // Otherwise, return raw data with basic formatting
        return $this->formatBasic($relatedItems);
    }

    /**
     * Resolve the field value for export.
     * Flattens the relation to a comma-separated string.
     *
     * @param Request $request
     * @param Model $model
     * @param ResourceInterface|null $resource
     * @return mixed
     */
    public function resolveForExport(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $relationName = $this->getRelation();

        if (!method_exists($model, $relationName)) {
            return '';
        }

        // Get related items (query fresh to avoid pagination issues)
        $query = $model->{$relationName}();

        // Apply limit if set
        if ($this->exportLimit !== null) {
            $query->limit($this->exportLimit);
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            return '';
        }

        // Determine which attribute to use
        $attribute = $this->exportAttribute ?? $this->displayAttribute ?? $this->guessDisplayAttribute($items->first());

        // Extract values and join
        return $items
            ->map(fn($item) => $item->{$attribute} ?? $item->getKey())
            ->filter()
            ->implode($this->exportSeparator);
    }

    /**
     * Guess the best display attribute for export.
     *
     * @param Model $item
     * @return string
     */
    protected function guessDisplayAttribute(Model $item): string
    {
        $commonAttributes = ['name', 'title', 'label', 'display_name', 'full_name', 'email'];

        foreach ($commonAttributes as $attr) {
            if (isset($item->{$attr})) {
                return $attr;
            }
        }

        return $item->getKeyName();
    }

    /**
     * Get empty response structure for paginated fields.
     *
     * @return array
     */
    protected function getEmptyPaginatedResponse(): array
    {
        return [
            'data' => [],
            'meta' => [
                'total' => 0,
                'hasMore' => false,
                'paginated' => true,
            ]
        ];
    }

    /**
     * Format related items using resource fields.
     *
     * @param Collection $items
     * @param ResourceInterface $resource
     * @param Request $request
     * @return array
     */
    protected function formatWithResource(Collection $items, ResourceInterface $resource, NadotaRequest $request): array
    {
        // Use custom fields if provided, otherwise use resource's index fields
        $fields = $this->customFields !== null
            ? collect($this->customFields)
            : collect($resource->fieldsForIndex($request));

        $relationResource = RelationResource::make($fields, $resource, $this->exceptFieldKeys)
            ->withLabelResolver(fn($item, $res) => $this->resolveLabel($item, $res));

        // Only include fields if explicitly enabled
        if (!$this->withFields) {
            $relationResource->withoutFields();
        }

        // Propagate withoutId setting
        if (!$this->shouldIncludeId()) {
            $relationResource->withoutId();
        }

        return $relationResource->formatCollection($items, $request);
    }

    /**
     * Resolve display label for the related model.
     */
    protected function resolveLabel(Model $item, ?ResourceInterface $resource): mixed
    {
        // Priority 1: Display callback set on field
        if ($this->hasDisplayCallback()) {
            return $this->resolveDisplay($item);
        }

        // Priority 2: Display attribute set on field
        if ($this->displayAttribute) {
            return $item->{$this->displayAttribute} ?? $item->getKey();
        }

        // Priority 3: Resource's displayLabel method
        if ($resource && method_exists($resource, 'displayLabel')) {
            return $resource->displayLabel($item);
        }

        // Fallback: primary key
        return $item->getKey();
    }

    /**
     * Format related items without resource.
     * Uses same structure as index/show for consistency.
     *
     * @param Collection $items
     * @return array
     */
    protected function formatBasic(Collection $items): array
    {
        return [
            'data' => $items->map(function ($item) {
                // Try to find a display attribute
                $label = null;
                if ($this->displayAttribute) {
                    $label = $item->{$this->displayAttribute};
                } else {
                    // Try common attributes
                    $commonAttributes = ['name', 'title', 'label', 'display_name'];
                    foreach ($commonAttributes as $attr) {
                        if (isset($item->{$attr})) {
                            $label = $item->{$attr};
                            break;
                        }
                    }
                }

                $data = [
                    'label' => $label ?? "Item #{$item->getKey()}",
                    'attributes' => $item->toArray(),
                    'deletedAt' => $item->deleted_at ?? null,
                ];

                if ($this->shouldIncludeId()) {
                    $data = ['id' => $item->getKey()] + $data;
                }

                return $data;
            })->toArray(),
            'meta' => [
                'total' => $items->count(),
                'hasMore' => $this->limit !== null && $items->count() >= $this->limit
            ]
        ];
    }

    /**
     * HasMany fields don't apply sorting to the parent query.
     *
     * @param Builder $query
     * @param mixed $sortDirection
     * @param mixed $modelInstance
     * @return Builder
     */
    public function applySorting(Builder $query, $sortDirection, $modelInstance): Builder
    {
        // HasMany doesn't affect parent query sorting
        return $query;
    }

    /**
     * Get columns to select for the related model in eager loading.
     * Includes the foreign key to maintain the relationship.
     *
     * @param Request $request
     * @return array|null
     */
    public function getRelatedColumns(Request $request): ?array
    {
        $columns = parent::getRelatedColumns($request);

        // If parent returned null (select all), no need to add FK
        if ($columns === null) {
            return null;
        }

        // Get the FK from the Eloquent relation
        $foreignKey = $this->resolveForeignKey($request);

        if ($foreignKey && !in_array($foreignKey, $columns)) {
            $columns[] = $foreignKey;
        }

        return $columns;
    }

    /**
     * Resolve the foreign key name from the Eloquent relationship.
     *
     * @param Request $request
     * @return string|null
     */
    protected function resolveForeignKey(Request $request): ?string
    {
        try {
            // Get parent model class from the request's resource
            if (!method_exists($request, 'getResource')) {
                return null;
            }

            $parentResource = $request->getResource();
            return $this->resolveForeignKeyFromModel($parentResource->model);
        } catch (\Throwable $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Resolve the foreign key name from the parent model class.
     *
     * @param string $parentModelClass
     * @return string|null
     */
    protected function resolveForeignKeyFromModel(string $parentModelClass): ?string
    {
        try {
            $parentModel = new $parentModelClass;
            $relationName = $this->getRelation();

            if (method_exists($parentModel, $relationName)) {
                $relation = $parentModel->{$relationName}();

                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
                    return $relation->getForeignKeyName();
                }
            }
        } catch (\Throwable $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Get props for frontend component.
     *
     * @param Request $request
     * @param Model|null $model
     * @param ResourceInterface|null $resource
     * @return array
     */
    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        $props = parent::getProps($request, $model, $resource);

        $props = array_merge($props, [
            'limit' => $this->limit,
            'paginated' => $this->paginated,
            'orderBy' => $this->orderBy,
            'orderDirection' => $this->orderDirection,
            'relationType' => 'hasMany',
            'resource' => $this->getResource() ? $this->getResource()::getKey() : null,
            'canCreate' => $this->canCreate,
        ]);

        // Add attachment configuration
        if ($this->attachable) {
            $props['attachment'] = $this->getAttachmentConfig();
        }

        // Add URLs when we have resource context
        if ($resource) {
            $resourceKey = $resource::getKey();
            $fieldKey = $this->key();
            $apiPrefix = static::safeConfig('nadota.api.prefix', 'nadota-api');
            $frontendPrefix = static::safeConfig('nadota.frontend.prefix', 'resources');

            // Options URL is always available (no model ID needed)
            $props['urls'] = [
                'options' => "/{$apiPrefix}/{$resourceKey}/resource/field/{$fieldKey}/options",
            ];

            // Attach/detach URLs require an existing model
            if ($model) {
                $modelId = $model->getKey();

                // Update options URL with resource ID context
                $props['urls']['options'] = "/{$apiPrefix}/{$resourceKey}/resource/field/{$fieldKey}/options?resourceId={$modelId}";
                $props['urls']['attachable'] = "/{$apiPrefix}/{$resourceKey}/resource/{$modelId}/attachable/{$fieldKey}";
                $props['urls']['attach'] = "/{$apiPrefix}/{$resourceKey}/resource/{$modelId}/attach/{$fieldKey}";
                $props['urls']['detach'] = "/{$apiPrefix}/{$resourceKey}/resource/{$modelId}/detach/{$fieldKey}";

                // Add pagination URL if paginated
                if ($this->paginated) {
                    $props['paginationUrl'] = "/{$apiPrefix}/{$resourceKey}/resource/{$modelId}/relation/{$fieldKey}";
                }

                // Add createContext for frontend to handle relation creation
                $relatedResourceClass = $this->getResource();
                if ($relatedResourceClass) {
                    $relatedResourceKey = $relatedResourceClass::getKey();
                    $foreignKey = $this->resolveForeignKeyFromModel($resource->model);

                    $props['createContext'] = [
                        'parentResource' => $resourceKey,
                        'parentId' => $modelId,
                        'relatedResource' => $relatedResourceKey,
                        'foreignKey' => $foreignKey,
                        'prefill' => $foreignKey ? [$foreignKey => $modelId] : [],
                        'lock' => $foreignKey ? [$foreignKey] : [],
                        'returnUrl' => "/{$frontendPrefix}/{$resourceKey}/{$modelId}",
                        'createUrl' => "/{$apiPrefix}/{$relatedResourceKey}/resource/create",
                        'storeUrl' => "/{$apiPrefix}/{$relatedResourceKey}/resource",
                    ];

                    // Add export URL with filter for parent model
                    if ($foreignKey && $this->isExportable()) {
                        $props['urls']['export'] = "/{$apiPrefix}/{$relatedResourceKey}/resource/export?filters[{$foreignKey}]={$modelId}";
                    }
                }
            }
        }

        return $props;
    }

    /**
     * Build the pagination URL for this relation.
     *
     * @param Model $model
     * @param ResourceInterface $resource
     * @return string
     */
    protected function buildPaginationUrl(Model $model, ResourceInterface $resource): string
    {
        $apiPrefix = static::safeConfig('nadota.api.prefix', 'nadota-api');
        $resourceKey = $resource::getKey();
        $modelId = $model->getKey();
        $fieldKey = $this->key();

        return "/{$apiPrefix}/{$resourceKey}/resource/{$modelId}/relation/{$fieldKey}";
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
     * HasMany does not have pivot columns.
     *
     * @return bool
     */
    public function hasPivotColumns(): bool
    {
        return false;
    }

    /**
     * HasMany does not have pivot columns.
     *
     * @return array
     */
    public function getPivotColumns(): array
    {
        return [];
    }

    /**
     * Fill method for HasMany (not used in create/update).
     *
     * @param Request $request
     * @param Model $model
     * @return void
     */
    public function fill(Request $request, Model $model): void
    {
        // HasMany relations are not filled directly in create/update
        // They are managed separately through their own CRUD operations
    }
}