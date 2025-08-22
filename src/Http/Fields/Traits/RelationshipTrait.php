<?php

namespace Said\Nadota\Http\Fields\Traits;

trait RelationshipTrait
{
    protected string $relation;
    protected string $relationAttribute = 'name';
    protected string $relationAttributeKey = 'id';
    protected string $foreignKey = 'id';
    protected bool $applyInIndexQuery = false;
    protected bool $applyInShowQuery = true;

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function foreignKey(string $foreignKey): static
    {
        $this->foreignKey = $foreignKey;
        return $this;
    }

    public function relationAttribute(string $relationAttribute): static
    {
        $this->relationAttribute = $relationAttribute;
        return $this;
    }

    public function relationAttributeKey(string $relationAttributeKey): static
    {
        $this->relationAttributeKey = $relationAttributeKey;
        return $this;
    }

    public function getRelation(): string
    {
        return $this->relation;
    }

    public function getAttributeForDisplay(): string
    {
        return $this->relationAttribute;
    }

    public function applyInIndexQuery(): static
    {
        $this->applyInIndexQuery = true;
        return $this;
    }

    public function applyInShowQuery(): static
    {
        $this->applyInShowQuery = true;
        return $this;
    }

    public function isAppliedInIndexQuery(): bool
    {
        return $this->applyInIndexQuery;
    }

    public function isAppliedInShowQuery(): bool
    {
        return $this->applyInShowQuery;
    }
}
