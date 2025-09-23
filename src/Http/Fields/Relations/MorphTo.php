<?php

namespace SchoolAid\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;
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
     * Create a new MorphTo field.
     *
     * @param string $name Display name for the field
     * @param string $relation Morph relation method name on the model
     */
    public function __construct(string $name, string $relation)
    {
        // Infer the morph attributes from the relation name
        $this->morphTypeAttribute = Str::snake($relation) . '_type';
        $this->morphIdAttribute = Str::snake($relation) . '_id';

        parent::__construct($name, $this->morphIdAttribute, FieldType::MORPH_TO->value, config('nadota.fields.morphTo.component', 'field-morph-to'));
        $this->relation($relation);
        $this->isRelationship = true;
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

        if ($relatedModel === null) {
            return null;
        }

        // Find the alias for this morph type
        $alias = $this->getMorphAlias($morphType);

        // Use the resolveDisplay method for the label
        $label = $this->resolveDisplay($relatedModel);

        // If no label was resolved, try to auto-detect using displayAttribute first
        if ($label === null && $this->displayAttribute) {
            $label = $relatedModel->{$this->displayAttribute} ?? null;
        }

        // If still no label, try common attributes
        if ($label === null) {
            $commonAttributes = ['name', 'title', 'label', 'display_name', 'full_name', 'description'];
            foreach ($commonAttributes as $attr) {
                if (isset($relatedModel->{$attr})) {
                    $label = $relatedModel->{$attr};
                    break;
                }
            }
            // Fallback to primary key
            if ($label === null) {
                $label = $relatedModel->getKey();
            }
        }

        // Get resource key if available
        $resourceKey = null;
        if (isset($this->morphResources[$alias])) {
            $resourceClass = $this->morphResources[$alias];
            $resourceKey = $resourceClass::getKey();
        }

        return [
            'type' => $alias,
            'typeLabel' => $this->getMorphTypeLabel($alias),
            'key' => $relatedModel->getKey(),
            'label' => $label,
            'resource' => $resourceKey,
            'optionsUrl' => $this->getMorphOptionsUrl($resource, $alias)
        ];
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
        $apiPrefix = config('nadota.api.prefix', 'nadota-api');

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
        $apiPrefix = config('nadota.api.prefix', 'nadota-api');

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
            $apiPrefix = config('nadota.api.prefix', 'nadota-api');
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