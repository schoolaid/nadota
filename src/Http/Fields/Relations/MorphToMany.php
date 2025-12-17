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

class MorphToMany extends Field
{
    use ManagesAttachments;
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
     * Pivot columns to include in the response.
     */
    protected array $pivotColumns = [];

    /**
     * Pivot fields for forms (Field instances).
     */
    protected array $pivotFields = [];

    /**
     * Whether to include pivot data in the response.
     */
    protected bool $withPivot = false;

    /**
     * Whether to include timestamps from pivot table.
     */
    protected bool $withTimestamps = false;

    /**
     * Create a new MorphToMany field.
     *
     * @param string $name Display name for the field
     * @param string $relation Relation method name on the model
     * @param string|null $resource The resource class for the related model
     */
    public function __construct(string $name, string $relation, ?string $resource = null)
    {
        parent::__construct($name, '', FieldType::BELONGS_TO_MANY->value, static::safeConfig('nadota.fields.morphToMany.component', 'field-morph-to-many'));
        $this->relation($relation);
        $this->isRelationship = true;

        // Set key to relation name for URL generation (attribute stays empty to avoid column selection)
        $this->fieldData->key = $relation;

        // MorphToMany should not show on index by default
        $this->showOnIndex = false;
        $this->showOnDetail = true;
        $this->showOnCreation = true;
        $this->showOnUpdate = true;

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
     * Set pivot columns to include in the response.
     *
     * @param array $columns Column names from the pivot table
     * @return static
     */
    public function withPivot(array $columns = []): static
    {
        $this->withPivot = true;
        $this->pivotColumns = $columns;
        return $this;
    }

    /**
     * Set pivot fields for forms.
     *
     * @param array $fields Array of Field instances for pivot data
     * @return static
     */
    public function pivotFields(array $fields): static
    {
        $this->pivotFields = $fields;

        // Extract column names from fields for withPivot
        foreach ($fields as $field) {
            $attribute = $field->getAttribute();
            if ($attribute && !in_array($attribute, $this->pivotColumns)) {
                $this->pivotColumns[] = $attribute;
            }
        }

        $this->withPivot = true;
        return $this;
    }

    /**
     * Include timestamps from pivot table.
     *
     * @param bool $withTimestamps
     * @return static
     */
    public function withTimestamps(bool $withTimestamps = true): static
    {
        $this->withTimestamps = $withTimestamps;
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
                'pivotColumns' => $this->withPivot ? $this->pivotColumns : [],
                'isPolymorphic' => true,
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
    protected function formatWithResource(Collection $items, ResourceInterface $resource, Request $request): array
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

        // Format with pivot data if enabled
        if ($this->withPivot) {
            $relationResource->withPivotColumns($this->pivotColumns);
        }

        return $relationResource->formatCollection($items, $request, [
            'hasMore' => $this->limit !== null && $items->count() >= $this->limit,
            'pivotColumns' => $this->withPivot ? $this->pivotColumns : [],
            'isPolymorphic' => true,
        ]);
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
                $data = [
                    'id' => $item->getKey(),
                    'label' => $this->resolveLabelBasic($item),
                    'deletedAt' => $item->deleted_at ?? null,
                ];

                // Include pivot data if enabled
                if ($this->withPivot && $item->pivot) {
                    $data['pivot'] = $this->extractPivotData($item);
                }

                return $data;
            })->toArray(),
            'meta' => [
                'total' => $items->count(),
                'hasMore' => $this->limit !== null && $items->count() >= $this->limit,
                'pivotColumns' => $this->withPivot ? $this->pivotColumns : [],
                'isPolymorphic' => true,
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

        // Try common attributes
        $commonAttributes = ['name', 'title', 'label', 'display_name'];
        foreach ($commonAttributes as $attr) {
            if (isset($item->{$attr})) {
                return $item->{$attr};
            }
        }

        return $item->getKey();
    }

    /**
     * Extract pivot data from an item.
     */
    protected function extractPivotData(Model $item): array
    {
        $pivotData = [];

        foreach ($this->pivotColumns as $column) {
            $pivotData[$column] = $item->pivot->{$column} ?? null;
        }

        // Include timestamps if enabled
        if ($this->withTimestamps && $item->pivot) {
            if (isset($item->pivot->created_at)) {
                $pivotData['created_at'] = $item->pivot->created_at;
            }
            if (isset($item->pivot->updated_at)) {
                $pivotData['updated_at'] = $item->pivot->updated_at;
            }
        }

        return $pivotData;
    }

    /**
     * MorphToMany fields don't apply sorting to the parent query.
     *
     * @param Builder $query
     * @param mixed $sortDirection
     * @param mixed $modelInstance
     * @return Builder
     */
    public function applySorting(Builder $query, $sortDirection, $modelInstance): Builder
    {
        return $query;
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
            'relationType' => 'morphToMany',
            'resource' => $this->getResource() ? $this->getResource()::getKey() : null,
            'pivotColumns' => $this->pivotColumns,
            'withPivot' => $this->withPivot,
            'withTimestamps' => $this->withTimestamps,
            'isPolymorphic' => true,
        ]);

        // Add URLs - options URL is always needed (for create and edit forms)
        if ($resource) {
            $resourceKey = $resource::getKey();
            $fieldKey = $this->key();
            $apiPrefix = static::safeConfig('nadota.api.prefix', 'nadota-api');

            // Options URL is always available (no model ID needed)
            $props['urls'] = [
                'options' => "/{$apiPrefix}/{$resourceKey}/resource/field/{$fieldKey}/options",
            ];

            // Attach/detach/sync URLs require an existing model
            if ($model) {
                $modelId = $model->getKey();
                $props['urls']['options'] = "/{$apiPrefix}/{$resourceKey}/resource/field/{$fieldKey}/options?resourceId={$modelId}";
                $props['urls']['attach'] = "/{$apiPrefix}/{$resourceKey}/resource/{$modelId}/attach/{$fieldKey}";
                $props['urls']['detach'] = "/{$apiPrefix}/{$resourceKey}/resource/{$modelId}/detach/{$fieldKey}";
                $props['urls']['sync'] = "/{$apiPrefix}/{$resourceKey}/resource/{$modelId}/sync/{$fieldKey}";

                // Add pagination URL if paginated
                if ($this->paginated) {
                    $props['paginationUrl'] = "/{$apiPrefix}/{$resourceKey}/resource/{$modelId}/relation/{$fieldKey}";
                }
            }
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
     * Convert field to array representation.
     * Adds pivotFields at the same level as other field properties for consistency.
     *
     * @param NadotaRequest $request
     * @param Model|null $model
     * @param ResourceInterface|null $resource
     * @return array
     */
    public function toArray(NadotaRequest $request, ?Model $model = null, ?ResourceInterface $resource = null): array
    {
        $data = parent::toArray($request, $model, $resource);

        // Add pivot fields at the same level as other fields (not inside props)
        if (!empty($this->pivotFields)) {
            $data['pivotFields'] = collect($this->pivotFields)
                ->map(fn($field) => $field->toArray($request, $model, $resource))
                ->values()
                ->toArray();
        } else {
            $data['pivotFields'] = [];
        }

        return $data;
    }

    /**
     * Fill method for MorphToMany.
     * MorphToMany needs the model to be saved first,
     * so actual syncing is done in afterSave().
     *
     * @param Request $request
     * @param Model $model
     * @return void
     */
    public function fill(Request $request, Model $model): void
    {
        // MorphToMany needs the model to be saved first
        // Actual syncing is handled in afterSave()
    }

    /**
     * Determine if this field supports afterSave callback.
     *
     * @return bool
     */
    public function supportsAfterSave(): bool
    {
        return true;
    }

    /**
     * Sync the relationship after the model is saved.
     *
     * @param Request $request
     * @param Model $model
     * @return void
     */
    public function afterSave(Request $request, Model $model): void
    {
        $relationName = $this->getRelation();
        $key = $this->key();

        if (!$request->has($key)) {
            return;
        }

        $values = $request->input($key, []);

        if (!is_array($values)) {
            $values = [];
        }

        // Check if values include pivot data
        if ($this->hasPivotData($values)) {
            $syncData = $this->prepareSyncWithPivot($values);
            $model->{$relationName}()->sync($syncData);
        } else {
            $model->{$relationName}()->sync($values);
        }
    }

    /**
     * Check if the values array includes pivot data.
     */
    protected function hasPivotData(array $values): bool
    {
        if (empty($values)) {
            return false;
        }

        $firstValue = reset($values);
        return is_array($firstValue) && isset($firstValue['id']);
    }

    /**
     * Prepare sync data with pivot attributes.
     */
    protected function prepareSyncWithPivot(array $values): array
    {
        $syncData = [];

        foreach ($values as $item) {
            if (is_array($item) && isset($item['id'])) {
                $id = $item['id'];
                unset($item['id']);
                $syncData[$id] = $item;
            } elseif (is_numeric($item) || is_string($item)) {
                $syncData[$item] = [];
            }
        }

        return $syncData;
    }

    /**
     * Get the relation type.
     *
     * @return string
     */
    public function relationType(): string
    {
        return 'morphToMany';
    }

    /**
     * Get the pivot columns configured for this field.
     * Used by ManagesRelationLoading to apply withPivot() in eager loading.
     *
     * @return array
     */
    public function getPivotColumns(): array
    {
        $columns = $this->pivotColumns;

        // Include timestamps if enabled
        if ($this->withTimestamps) {
            if (!in_array('created_at', $columns)) {
                $columns[] = 'created_at';
            }
            if (!in_array('updated_at', $columns)) {
                $columns[] = 'updated_at';
            }
        }

        return $columns;
    }

    /**
     * Check if this field has pivot columns configured.
     *
     * @return bool
     */
    public function hasPivotColumns(): bool
    {
        return $this->withPivot || $this->withTimestamps || !empty($this->pivotColumns);
    }
}
