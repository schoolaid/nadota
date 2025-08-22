<?php

namespace Said\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Said\Nadota\Contracts\ResourceInterface;
use Said\Nadota\Http\Fields\Enums\FieldType;

class BelongsToMany extends RelationField
{
    protected string $relationAttribute = 'name';
    protected bool $searchable = false;
    protected array $pivotFields = [];

    public function __construct(?string $name, ?string $relation)
    {
        parent::__construct($name, $relation);
        $this->type(FieldType::BELONGS_TO_MANY);
        $this->component(config('nadota.fields.belongsToMany.component', 'field-belongs-to-many'));
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
     * Make the relationship field searchable.
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;
        return $this;
    }

    /**
     * Define pivot table fields to include.
     */
    public function withPivot(array $fields): static
    {
        $this->pivotFields = $fields;
        return $this;
    }

    public function relationType(): string
    {
        return 'belongsToMany';
    }

    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $related = $model->{$this->getRelation()};

        if (!$related || $related->isEmpty()) {
            return [];
        }

        return $related->map(function ($item) {
            $data = [
                'key' => $item->getKey(),
                'label' => $item->{$this->getAttributeForDisplay()},
            ];

            // Include pivot data if pivot exists and fields are specified
            if ($item->pivot && !empty($this->pivotFields)) {
                $data['pivot'] = $this->resolvePivotData($item->pivot);
            }

            return $data;
        })->toArray();
    }

    protected function resolvePivotData($pivot): array
    {
        $data = [];
        
        foreach ($this->pivotFields as $field) {
            if (is_string($field)) {
                // Simple field name
                $data[$field] = $pivot->{$field} ?? null;
            } elseif (is_array($field) && isset($field['name'])) {
                // Field with configuration
                $fieldName = $field['name'];
                $data[$fieldName] = $pivot->{$fieldName} ?? null;
            }
        }

        return $data;
    }

    public function getOptions(): array
    {
        if (empty($this->options) && $this->relatedModelClass) {
            $query = $this->relatedModelClass::query();
            
            // Add search functionality if enabled
            if ($this->searchable && request()->has('search')) {
                $search = request()->get('search');
                $query->where($this->getAttributeForDisplay(), 'like', "%{$search}%");
            }

            $this->options = $query
                ->pluck($this->getAttributeForDisplay(), $this->getForeignKey())
                ->toArray();
        }

        return collect($this->options)
            ->map(fn($name, $id) => [
                'value' => $id, 
                'label' => $name,
                'searchable' => $this->searchable
            ])
            ->values()
            ->toArray();
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'searchable' => $this->searchable,
            'pivotFields' => $this->pivotFields,
            'options' => $this->getOptions(),
        ]);
    }

    public function applySorting(Builder $query, $sortDirection, $modelInstance): Builder
    {
        // BelongsToMany relationships require complex sorting through pivot tables
        // This would need specific implementation based on requirements
        return $query;
    }
}