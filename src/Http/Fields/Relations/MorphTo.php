<?php

namespace SchoolAid\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Resources\RelationResource;
use SchoolAid\Nadota\ResourceManager;

class MorphTo extends Field
{
    /**
     * Available morph types as models.
     * Format: ['alias' => Model::class]
     * Example: ['post' => Post::class, 'video' => Video::class]
     *
     * @var array
     */
    protected array $morphModels = [];

    /**
     * Available morph types as resources.
     * Format: ['alias' => ResourceClass::class]
     * Example: ['post' => PostResource::class, 'video' => VideoResource::class]
     *
     * @var array
     */
    protected array $morphResources = [];

    /**
     * The morph type field attribute (usually ends with _type).
     *
     * @var string|null
     */
    protected ?string $morphTypeAttribute = null;

    /**
     * The morph id field attribute (usually ends with _id).
     *
     * @var string|null
     */
    protected ?string $morphIdAttribute = null;

    /**
     * Custom fields to select from the related model.
     */
    protected ?array $customFields = null;

    /**
     * Whether to include fields in the response.
     * Default false for lighter responses. Use ->withFields() to enable.
     */
    protected bool $withFields = false;

    /**
     * Create a new MorphTo field.
     *
     * @param string $name Display name for the field
     * @param string $relation Morph relation method name on the model
     * @param array|null $resources Array of morph resources ['alias' => ResourceClass::class]
     */
    public function __construct(string $name, string $relation, ?array $resources = null)
    {
        // Infer the morph attributes from the relation name
        $this->morphTypeAttribute = Str::snake($relation) . '_type';
        $this->morphIdAttribute = Str::snake($relation) . '_id';

        parent::__construct($name, $this->morphIdAttribute, FieldType::MORPH_TO->value, static::safeConfig('nadota.fields.morphTo.component', 'field-morph-to'));
        $this->relation($relation);
        $this->isRelationship = true;

        if ($resources) {
            $this->resources($resources);
        }
    }

    /**
     * Set the available morph models.
     *
     * @param array $models
     * @return static
     */
    public function models(array $models): static
    {
        $this->morphModels = $models;
        return $this;
    }

    /**
     * Set the available morph resources.
     *
     * @param array $resources
     * @return static
     */
    public function resources(array $resources): static
    {
        $this->morphResources = $resources;

        // Auto-detect models from resources if not set
        if (empty($this->morphModels)) {
            foreach ($resources as $alias => $resourceClass) {
                if (class_exists($resourceClass) && property_exists($resourceClass, 'model')) {
                    $resource = new $resourceClass();
                    $this->morphModels[$alias] = $resource->model;
                }
            }
        }

        return $this;
    }

    /**
     * Add a single morph type.
     *
     * @param string $alias
     * @param string $modelClass
     * @param string|null $resourceClass
     * @return static
     */
    public function addMorphType(string $alias, string $modelClass, ?string $resourceClass = null): static
    {
        $this->morphModels[$alias] = $modelClass;

        if ($resourceClass) {
            $this->morphResources[$alias] = $resourceClass;
        }

        return $this;
    }

    /**
     * Get the morph type attribute name.
     *
     * @return string|null
     */
    public function getMorphTypeAttribute(): ?string
    {
        return $this->morphTypeAttribute;
    }

    /**
     * Get the morph ID attribute name.
     *
     * @return string|null
     */
    public function getMorphIdAttribute(): ?string
    {
        return $this->morphIdAttribute;
    }

    /**
     * Get the available morph models.
     *
     * @return array
     */
    public function getMorphModels(): array
    {
        return $this->morphModels;
    }

    /**
     * Get the available morph resources.
     *
     * @return array
     */
    public function getMorphResources(): array
    {
        return $this->morphResources;
    }

    /**
     * Get the columns this field needs for SELECT queries.
     * Returns both the type and id columns for polymorphic relations.
     * Resolves actual column names from Eloquent relationship.
     *
     * @param string $modelClass The parent model class
     * @return array
     */
    public function getColumnsForSelect(string $modelClass): array
    {
        return $this->resolveMorphColumnsFromModel($modelClass);
    }

    /**
     * Resolve the morph columns from the Eloquent relationship.
     *
     * @param string $modelClass The parent model class
     * @return array [typeColumn, idColumn]
     */
    public function resolveMorphColumnsFromModel(string $modelClass): array
    {
        try {
            $model = new $modelClass;
            $relationName = $this->getRelation();

            if (method_exists($model, $relationName)) {
                $relation = $model->{$relationName}();

                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\MorphTo) {
                    return [
                        $relation->getMorphType(),
                        $relation->getForeignKeyName(),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Fall back to inferred attributes
        }

        return array_filter([
            $this->morphTypeAttribute,
            $this->morphIdAttribute,
        ]);
    }

    /**
     * Set custom fields to select from the related model.
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
            return null;
        }

        // Get the morph type and ID
        $morphType = $model->{$this->morphTypeAttribute};
        $morphId = $model->{$this->morphIdAttribute};

        if (!$morphType || !$morphId) {
            if ($this->hasDefault()) {
                return $this->resolveDefault($request, $model, $resource);
            }
            return null;
        }

        // Get the related model instance
        $relatedModel = $model->{$relationName};

        // Ensure we have a Model instance, not a scalar value
        if (!$relatedModel instanceof Model) {
            if ($this->hasDefault()) {
                return $this->resolveDefault($request, $model, $resource);
            }
            return null;
        }

        // Find the alias for this morph type
        $alias = $this->getMorphAlias($morphType);

        // Get the resource for this morph type if available
        $morphResource = null;
        if (isset($this->morphResources[$alias])) {
            $morphResourceClass = $this->morphResources[$alias];
            // Verify it's a valid Nadota Resource
            if (is_subclass_of($morphResourceClass, ResourceInterface::class)) {
                $morphResource = new $morphResourceClass;
            }
        }

        // If we have a resource for this morph type, format with fields
        if ($morphResource) {
            return $this->formatWithResource($relatedModel, $morphResource, $request, $alias, $resource);
        }

        // Otherwise, return basic formatting
        return $this->formatBasic($relatedModel, $alias, $resource);
    }

    /**
     * Format related model using resource fields.
     */
    protected function formatWithResource(
        Model $item,
        ResourceInterface $morphResource,
        Request $request,
        string $alias,
        ?ResourceInterface $parentResource
    ): array {
        // Use custom fields if provided, otherwise use resource's index fields
        $fields = $this->customFields !== null
            ? collect($this->customFields)
            : collect($morphResource->fieldsForIndex($request));

        $extra = [
            'type' => $alias,
            'typeLabel' => $this->getMorphTypeLabel($alias),
            'optionsUrl' => $this->getMorphOptionsUrl($parentResource, $alias),
        ];

        $relationResource = RelationResource::make($fields, $morphResource, $this->exceptFieldKeys)
            ->withLabelResolver(fn($model, $res) => $this->resolveLabel($model, $res))
            ->withoutPermissions();

        // Only include fields if explicitly enabled
        if (!$this->withFields) {
            $relationResource->withoutFields();
        }

        return $relationResource->formatItem($item, $request, $extra);
    }

    /**
     * Format related model without resource (basic formatting).
     * Uses same structure as index/show for consistency.
     */
    protected function formatBasic(Model $item, string $alias, ?ResourceInterface $parentResource): array
    {
        return [
            'id' => $item->getKey(),
            'label' => $this->resolveLabel($item, null),
            'resource' => null,
            'type' => $alias,
            'typeLabel' => $this->getMorphTypeLabel($alias),
            'optionsUrl' => $this->getMorphOptionsUrl($parentResource, $alias),
            'deletedAt' => $item->deleted_at ?? null,
        ];
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
     * Get the alias for a morph type.
     *
     * @param string $morphType
     * @return string|null
     */
    protected function getMorphAlias(string $morphType): ?string
    {
        // First check if it's already an alias
        if (isset($this->morphModels[$morphType])) {
            return $morphType;
        }

        // Search for the model class in the array
        $alias = array_search($morphType, $this->morphModels);

        if ($alias !== false) {
            return $alias;
        }

        // Try to match by class basename
        foreach ($this->morphModels as $alias => $modelClass) {
            if (class_basename($modelClass) === class_basename($morphType)) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * Get a human-readable label for a morph type.
     *
     * @param string $alias
     * @return string
     */
    protected function getMorphTypeLabel(string $alias): string
    {
        if (isset($this->morphResources[$alias])) {
            $resourceClass = $this->morphResources[$alias];
            if (method_exists($resourceClass, 'label')) {
                return $resourceClass::label();
            }
        }

        return Str::title(str_replace(['_', '-'], ' ', $alias));
    }

    /**
     * Get the options URL for a specific morph type.
     *
     * @param ResourceInterface|null $resource
     * @param string $alias
     * @return string|null
     */
    protected function getMorphOptionsUrl(?ResourceInterface $resource, string $alias): ?string
    {
        if (!$resource || !isset($this->morphResources[$alias])) {
            return null;
        }

        $resourceKey = $resource::getKey();
        $fieldName = $this->key();
        $apiPrefix = static::safeConfig('nadota.api.prefix', 'nadota-api');

        return "/{$apiPrefix}/{$resourceKey}/resource/field/{$fieldName}/morph-options/{$alias}";
    }

    /**
     * Get the available morph types for frontend.
     *
     * @param ResourceInterface|null $parentResource The parent resource that contains this field
     * @return array
     */
    public function getMorphTypes(?ResourceInterface $parentResource = null): array
    {
        $types = [];

        // Get base configuration for URLs
        $parentResourceKey = $parentResource ? $parentResource::getKey() : null;
        $fieldName = $this->key();
        $apiPrefix = static::safeConfig('nadota.api.prefix', 'nadota-api');

        foreach ($this->morphModels as $alias => $modelClass) {
            // Get resource key if available
            $resourceKey = null;
            if (isset($this->morphResources[$alias])) {
                $resourceClass = $this->morphResources[$alias];
                $resourceKey = $resourceClass::getKey();
            }

            // Build the options URL for this specific morph type
            $optionsUrl = null;
            if ($parentResourceKey) {
                $optionsUrl = "/{$apiPrefix}/{$parentResourceKey}/resource/field/{$fieldName}/morph-options/{$alias}";
            }

            $types[] = [
                'value' => $alias,
                'label' => $this->getMorphTypeLabel($alias),
                'resource' => $resourceKey,  // Resource key or null
                'optionsUrl' => $optionsUrl  // Direct URL for this morph type's options
            ];
        }

        return $types;
    }

    /**
     * Apply sorting for morph fields.
     *
     * @param Builder $query
     * @param mixed $sortDirection
     * @param mixed $modelInstance
     * @return Builder
     */
    public function applySorting(Builder $query, $sortDirection, $modelInstance): Builder
    {
        // Sorting morph fields is complex and depends on the specific types
        // For now, we'll sort by the type and ID columns
        $modelTable = $modelInstance->getTable();

        return $query
            ->orderBy("{$modelTable}.{$this->morphTypeAttribute}", $sortDirection)
            ->orderBy("{$modelTable}.{$this->morphIdAttribute}", $sortDirection);
    }

    /**
     * Fill the model with the field value.
     *
     * @param Request $request
     * @param Model $model
     * @return void
     */
    public function fill(Request $request, Model $model): void
    {
        // Get the type value from the request (this should be the resource key, e.g., 'students')
        $typeValue = $request->get($this->morphTypeAttribute);
        $idValue = $request->get($this->morphIdAttribute);

        if (!$typeValue || !$idValue) {
            // Clear the morph relationship if values are not provided
            $model->{$this->morphTypeAttribute} = null;
            $model->{$this->morphIdAttribute} = null;
            return;
        }

        // First, check if it's a direct alias match
        if (isset($this->morphModels[$typeValue])) {
            $model->{$this->morphTypeAttribute} = $this->morphModels[$typeValue];
            $model->{$this->morphIdAttribute} = $idValue;
            return;
        }

        // If not found, try to find by resource key
        foreach ($this->morphResources as $alias => $resourceClass) {
            if ($resourceClass::getKey() === $typeValue) {
                // Found the matching resource, use its associated model
                $model->{$this->morphTypeAttribute} = $this->morphModels[$alias];
                $model->{$this->morphIdAttribute} = $idValue;
                return;
            }
        }

        // If still not found, try ResourceManager as fallback
        $resourceClass = ResourceManager::getResourceByKey($typeValue);
        if ($resourceClass) {
            $resource = new $resourceClass();
            $model->{$this->morphTypeAttribute} = $resource->model;
            $model->{$this->morphIdAttribute} = $idValue;
            return;
        }

        // If nothing matches, log error but don't break
        \Log::warning("MorphTo field could not resolve type: {$typeValue}");
        $model->{$this->morphTypeAttribute} = null;
        $model->{$this->morphIdAttribute} = null;
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
        // Get the base options URL
        $baseOptionsUrl = null;
        if ($resource) {
            $resourceKey = $resource::getKey();
            $fieldName = $this->key();
            $apiPrefix = static::safeConfig('nadota.api.prefix', 'nadota-api');
            $baseOptionsUrl = "/{$apiPrefix}/{$resourceKey}/resource/field/{$fieldName}/morph-options";
        }

        return array_merge(parent::getProps($request, $model, $resource), [
            'morphTypes' => $this->getMorphTypes($resource),
            'morphTypeAttribute' => $this->morphTypeAttribute,
            'morphIdAttribute' => $this->morphIdAttribute,
            'isPolymorphic' => true,
            'baseOptionsUrl' => $baseOptionsUrl  // Base URL for morph options
        ]);
    }
}