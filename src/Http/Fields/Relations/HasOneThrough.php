<?php

namespace SchoolAid\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Resources\RelationResource;

class HasOneThrough extends Field
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
     * Create a new HasOneThrough field.
     *
     * @param string $name Display name for the field
     * @param string $relation Relation method name on the model
     * @param string|null $resource The resource class for the related model
     */
    public function __construct(string $name, string $relation, ?string $resource = null)
    {
        parent::__construct($name, '', FieldType::HAS_ONE->value, static::safeConfig('nadota.fields.hasOneThrough.component', 'field-has-one-through'));
        $this->relation($relation);
        $this->isRelationship = true;

        // HasOneThrough should not show on index by default
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
        $fields = $this->customFields !== null
            ? collect($this->customFields)
            : collect($resource->fieldsForIndex($request));

        $relationResource = RelationResource::make($fields, $resource, $this->exceptFieldKeys)
            ->withLabelResolver(fn($model, $res) => $this->resolveLabel($model, $res))
            ->withoutPermissions();

        if (!$this->withFields) {
            $relationResource->withoutFields();
        }

        return $relationResource->formatItem($item, $request);
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
            'deletedAt' => $item->deleted_at ?? null,
        ];
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
     * HasOneThrough fields don't apply sorting to the parent query.
     */
    public function applySorting(Builder $query, $sortDirection, $modelInstance): Builder
    {
        return $query;
    }

    /**
     * Get columns to select for the related model in eager loading.
     * HasOneThrough is complex - the FK is on the intermediate table.
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

                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasOneThrough) {
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
            'relationType' => 'hasOneThrough',
            'resource' => $this->getResource() ? $this->getResource()::getKey() : null,
        ]);

        return $props;
    }

    /**
     * Fill method for HasOneThrough (read-only).
     */
    public function fill(Request $request, Model $model): void
    {
        // HasOneThrough relations are read-only
    }

    /**
     * Get the relation type.
     */
    public function relationType(): string
    {
        return 'hasOneThrough';
    }
}
