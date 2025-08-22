<?php

namespace Said\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Said\Nadota\Contracts\ResourceInterface;
use Said\Nadota\Http\Fields\Enums\FieldType;

class HasMany extends RelationField
{
    protected string $relationAttribute = 'name';
    protected int $limit = 5;
    protected bool $collapsible = false;
    protected array $nestedFields = [];

    public function __construct(?string $name, ?string $relation)
    {
        parent::__construct($name, $relation);
        $this->type(FieldType::HAS_MANY);
        $this->component(config('nadota.fields.hasMany.component', 'field-has-many'));
        $this->applyInIndexQuery = true;
    }

    /**
     * Set the attribute to display for the relationship.
     */
    public function relationAttribute(string $attribute): static
    {
        $this->relationAttribute = $attribute;
        return $this;
    }

    /**
     * Set the maximum number of related records to display.
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Make the relationship field collapsible.
     */
    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;
        return $this;
    }

    /**
     * Define nested fields for managing related records.
     */
    public function fields(callable $callback): static
    {
        $this->nestedFields = call_user_func($callback);
        return $this;
    }

    public function relationType(): string
    {
        return 'hasMany';
    }

    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $related = $model->{$this->getRelation()};

        if (!$related || $related->isEmpty()) {
            return [];
        }

        return $related->take($this->limit)->map(function ($item) {
            return [
                'key' => $item->getKey(),
                'label' => $item->{$this->getAttributeForDisplay()},
                'data' => $this->resolveNestedFields($item)
            ];
        })->toArray();
    }

    protected function resolveNestedFields(Model $model): array
    {
        if (empty($this->nestedFields)) {
            return [];
        }

        $data = [];
        foreach ($this->nestedFields as $field) {
            $data[$field->getAttribute()] = $field->resolve(request(), $model, null);
        }

        return $data;
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'limit' => $this->limit,
            'collapsible' => $this->collapsible,
            'nestedFields' => $this->getNestedFieldsData($request, $model, $resource),
        ]);
    }

    protected function getNestedFieldsData(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        if (empty($this->nestedFields)) {
            return [];
        }

        return collect($this->nestedFields)->map(function ($field) use ($request, $model, $resource) {
            return $field->toArray($request, $model, $resource);
        })->toArray();
    }

    public function applySorting(Builder $query, $sortDirection, $modelInstance): Builder
    {
        // HasMany relationships typically don't support direct sorting on the parent model
        // This would need to be implemented based on specific requirements
        return $query;
    }
}