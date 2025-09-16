<?php

namespace SchoolAid\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;

class HasMany extends Field
{
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
     * Create a new HasMany field.
     *
     * @param string $name Display name for the field
     * @param string $relation Relation method name on the model
     */
    public function __construct(string $name, string $relation)
    {
        parent::__construct($name, '', FieldType::HAS_MANY->value, config('nadota.fields.hasMany.component', 'field-has-many'));
        $this->relation($relation);
        $this->isRelationship = true;

        // HasMany should not show on index by default
        $this->showOnIndex = false;
        $this->showOnDetail = true;
        $this->showOnCreation = false;
        $this->showOnUpdate = false;

        // Don't apply in index query
        $this->applyInIndexQuery = false;
        $this->applyInShowQuery = true;
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

        // Build the query for the relation
        $query = $model->{$relationName}();

        // Apply ordering if specified
        if ($this->orderBy) {
            $query->orderBy($this->orderBy, $this->orderDirection);
        } else {
            // Default ordering by created_at or id
            $relatedModel = $query->getRelated();
            if (in_array('created_at', $relatedModel->getFillable()) ||
                $relatedModel->timestamps) {
                $query->orderBy('created_at', 'desc');
            } else {
                $query->orderBy($relatedModel->getKeyName(), 'desc');
            }
        }

        // Apply limit if specified
        if ($this->limit !== null && !$this->paginated) {
            $query->limit($this->limit);
        }

        // Get the related items
        $relatedItems = $query->get();

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
     * Format related items using resource fields.
     *
     * @param Collection $items
     * @param ResourceInterface $resource
     * @param Request $request
     * @return array
     */
    protected function formatWithResource(Collection $items, ResourceInterface $resource, Request $request): array
    {
        $fields = collect($resource->fieldsForIndex($request));

        return [
            'data' => $items->map(function ($item) use ($fields, $request, $resource) {
                return [
                    'key' => $item->getKey(),
                    'fields' => $fields->map(function ($field) use ($item, $request, $resource) {
                        return $field->toArray($request, $item, $resource);
                    })->toArray(),
                    'permissions' => $resource->getPermissionsForResource($request, $item)
                ];
            })->toArray(),
            'meta' => [
                'total' => $items->count(),
                'hasMore' => $this->limit !== null && $items->count() >= $this->limit,
                'resource' => $resource::getKey(),
                'fields' => $fields->map(fn($field) => [
                    'key' => $field->key(),
                    'name' => $field->getName(),
                    'sortable' => $field->isSortable(),
                ])->toArray()
            ]
        ];
    }

    /**
     * Format related items without resource.
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

                return [
                    'key' => $item->getKey(),
                    'label' => $label ?? "Item #{$item->getKey()}",
                    'attributes' => $item->toArray()
                ];
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

        return array_merge($props, [
            'limit' => $this->limit,
            'paginated' => $this->paginated,
            'orderBy' => $this->orderBy,
            'orderDirection' => $this->orderDirection,
            'relationType' => 'hasMany',
            'resource' => $this->getResource() ? $this->getResource()::getKey() : null,
        ]);
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