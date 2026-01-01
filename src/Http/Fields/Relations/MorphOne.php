<?php

namespace SchoolAid\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Resources\RelationResource;

class MorphOne extends Field
{
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
     * Create a new MorphOne field.
     *
     * @param string $name Display name for the field
     * @param string $relation Relation method name on the model
     * @param string|null $resource The resource class for the related model
     */
    public function __construct(string $name, string $relation, ?string $resource = null)
    {
        parent::__construct($name, '', FieldType::MORPH_ONE->value, static::safeConfig('nadota.fields.morphOne.component', 'field-morph-one'));
        $this->relation($relation);
        $this->isRelationship = true;

        // MorphOne should not show on index by default
        $this->showOnIndex = false;
        $this->showOnDetail = true;
        $this->showOnCreation = false;
        $this->showOnUpdate = false;

        // Don't apply in index query, apply in show query
        $this->applyInIndexQuery = false;
        $this->applyInShowQuery = true;

        if ($resource) {
            $this->resource($resource);
        }
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

        $relatedModel = $model->{$relationName};

        if ($relatedModel === null) {
            if ($this->hasDefault()) {
                return $this->resolveDefault($request, $model, $resource);
            }
            return null;
        }

        // If we have a resource, format with fields
        if ($this->getResource()) {
            $resourceClass = $this->getResource();

            // Verify it's a valid Nadota Resource
            if (!is_subclass_of($resourceClass, ResourceInterface::class)) {
                return $this->formatBasic($relatedModel);
            }

            $relatedResource = new $resourceClass;
            return $this->formatWithResource($relatedModel, $relatedResource, $request);
        }

        // Otherwise, return basic formatting
        return $this->formatBasic($relatedModel);
    }

    /**
     * Format related model using resource fields.
     */
    protected function formatWithResource(Model $item, ResourceInterface $resource, Request $request): array
    {
        // Use custom fields if provided, otherwise use resource's index fields
        $fields = $this->customFields !== null
            ? collect($this->customFields)
            : collect($resource->fieldsForIndex($request));

        $relationResource = RelationResource::make($fields, $resource, $this->exceptFieldKeys)
            ->withLabelResolver(fn($model, $res) => $this->resolveLabel($model, $res))
            ->withoutPermissions();

        // Only include fields if explicitly enabled
        if (!$this->withFields) {
            $relationResource->withoutFields();
        }

        return $relationResource->formatItem($item, $request, [
            'morphType' => get_class($item),
        ]);
    }

    /**
     * Format related model without resource (basic formatting).
     * Uses same structure as index/show for consistency.
     */
    protected function formatBasic(Model $item): array
    {
        return [
            'id' => $item->getKey(),
            'label' => $this->resolveLabel($item, null),
            'resource' => null,
            'morphType' => get_class($item),
            'deletedAt' => $item->deleted_at ?? null,
        ];
    }

    /**
     * Resolve display label for the related model.
     */
    protected function resolveLabel(Model $item, ?ResourceInterface $resource): mixed
    {
        // Priority 1: Display callback set on the field
        if ($this->hasDisplayCallback()) {
            return $this->resolveDisplay($item);
        }

        // Priority 2: Display attribute set on the field
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
     * MorphOne fields don't apply sorting to the parent query.
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
     * Get columns to select for the related model in eager loading.
     * Includes the foreign key and morph type to maintain the relationship.
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

        $morphColumns = $this->resolveMorphColumns($request);

        foreach ($morphColumns as $col) {
            if ($col && !in_array($col, $columns)) {
                $columns[] = $col;
            }
        }

        return $columns;
    }

    /**
     * Resolve the morph columns from the Eloquent relationship.
     *
     * @param Request $request
     * @return array [foreignKey, morphType]
     */
    protected function resolveMorphColumns(Request $request): array
    {
        try {
            if (!method_exists($request, 'getResource')) {
                return [];
            }

            $parentResource = $request->getResource();
            $morphKeys = $this->resolveMorphKeysFromModel($parentResource->model);

            return array_filter([$morphKeys['id'], $morphKeys['type']]);
        } catch (\Throwable $e) {
            // Silently fail
        }

        return [];
    }

    /**
     * Resolve the morph type and id column names from the parent model class.
     *
     * @param string $parentModelClass
     * @return array{type: string|null, id: string|null}
     */
    protected function resolveMorphKeysFromModel(string $parentModelClass): array
    {
        try {
            $parentModel = new $parentModelClass;
            $relationName = $this->getRelation();

            if (method_exists($parentModel, $relationName)) {
                $relation = $parentModel->{$relationName}();

                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\MorphOne) {
                    return [
                        'type' => $relation->getMorphType(),
                        'id' => $relation->getForeignKeyName(),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Silently fail
        }

        return ['type' => null, 'id' => null];
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
            'relationType' => 'morphOne',
            'resource' => $this->getResource() ? $this->getResource()::getKey() : null,
            'isPolymorphic' => true,
        ]);

        // Add URLs and createContext if we have a model and resource
        if ($model && $resource) {
            $resourceKey = $resource::getKey();
            $modelId = $model->getKey();
            $apiPrefix = static::safeConfig('nadota.api.prefix', 'nadota-api');
            $frontendPrefix = static::safeConfig('nadota.frontend.prefix', 'resources');

            $relatedResourceClass = $this->getResource();
            if ($relatedResourceClass) {
                $relatedResourceKey = $relatedResourceClass::getKey();
                $morphKeys = $this->resolveMorphKeysFromModel($resource->model);
                $morphType = $morphKeys['type'] ?? null;
                $morphId = $morphKeys['id'] ?? null;
                $morphClass = get_class($model);

                $props['urls'] = [
                    'create' => "/{$apiPrefix}/{$relatedResourceKey}/resource/create",
                    'show' => "/{$apiPrefix}/{$relatedResourceKey}/resource",
                ];

                $prefill = [];
                $lock = [];

                if ($morphType && $morphId) {
                    $prefill[$morphType] = $morphClass;
                    $prefill[$morphId] = $modelId;
                    $lock = [$morphType, $morphId];
                }

                $props['createContext'] = [
                    'parentResource' => $resourceKey,
                    'parentId' => $modelId,
                    'relatedResource' => $relatedResourceKey,
                    'morphType' => $morphType,
                    'morphId' => $morphId,
                    'morphClass' => $morphClass,
                    'prefill' => $prefill,
                    'lock' => $lock,
                    'returnUrl' => "/{$frontendPrefix}/{$resourceKey}/{$modelId}",
                    'createUrl' => "/{$apiPrefix}/{$relatedResourceKey}/resource/create",
                    'storeUrl' => "/{$apiPrefix}/{$relatedResourceKey}/resource",
                    'isPolymorphic' => true,
                ];
            }
        }

        return $props;
    }

    /**
     * Fill method for MorphOne (not used in create/update).
     *
     * @param Request $request
     * @param Model $model
     * @return void
     */
    public function fill(Request $request, Model $model): void
    {
        // MorphOne relations are not filled directly in create/update
        // They are managed separately through their own CRUD operations
    }

    /**
     * Get the relation type.
     *
     * @return string
     */
    public function relationType(): string
    {
        return 'morphOne';
    }
}
