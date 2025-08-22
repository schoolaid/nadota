<?php

namespace Said\Nadota\Http\Fields\Relations;

use Illuminate\Database\Eloquent\Builder;

class HasOne extends RelationField
{
    protected string $relationAttribute = 'name';

    public function __construct(?string $name, ?string $relation)
    {
        parent::__construct($name, $relation);
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

    public function relationType(): string
    {
        return 'hasOne';
    }

    public function applySorting(Builder $query, $sortDirection, $modelInstance): Builder
    {
        $relation = $modelInstance->{$this->getRelation()}();
        $relatedTable = $relation->getRelated()->getTable();
        $modelTable = $modelInstance->getTable();
        $displayField = $this->getAttributeForDisplay();

        $foreignKey = $relation->getForeignKeyName();
        $localKey = $relation->getLocalKeyName();
        $query->join($relatedTable, "$modelTable.$localKey", '=', "$relatedTable.$foreignKey");


        return $query
            ->orderBy("$relatedTable.$displayField", $sortDirection)
            ->select("$modelTable.*");
    }
}
