<?php

namespace SchoolAid\Nadota\Http\Traits;

trait ResourceSearchable
{
    /**
     * The attributes that should be searchable on the resource.
     *
     * @var array
     */
    protected array $searchableAttributes = [];

    /**
     * The relations that should be searchable on the resource.
     * Format: ['relation.attribute', 'relation.nested.attribute']
     * Example: ['user.name', 'category.title', 'tags.name']
     *
     * @var array
     */
    protected array $searchableRelations = [];

    /**
     * Get the searchable attributes for the resource.
     *
     * @return array
     */
    public function getSearchableAttributes(): array
    {
        return $this->searchableAttributes;
    }

    /**
     * Set the searchable attributes for the resource.
     *
     * @param array $attributes
     * @return $this
     */
    public function setSearchableAttributes(array $attributes): static
    {
        $this->searchableAttributes = $attributes;
        return $this;
    }

    /**
     * Get the searchable relations for the resource.
     *
     * @return array
     */
    public function getSearchableRelations(): array
    {
        return $this->searchableRelations;
    }

    /**
     * Set the searchable relations for the resource.
     *
     * @param array $relations
     * @return $this
     */
    public function setSearchableRelations(array $relations): static
    {
        $this->searchableRelations = $relations;
        return $this;
    }

    /**
     * Check if a specific attribute is searchable.
     *
     * @param string $attribute
     * @return bool
     */
    public function isAttributeSearchable(string $attribute): bool
    {
        return in_array($attribute, $this->searchableAttributes);
    }

    /**
     * Check if a specific relation is searchable.
     *
     * @param string $relation
     * @return bool
     */
    public function isRelationSearchable(string $relation): bool
    {
        return in_array($relation, $this->searchableRelations);
    }

    /**
     * Add a searchable attribute to the resource.
     *
     * @param string $attribute
     * @return $this
     */
    public function addSearchableAttribute(string $attribute): static
    {
        if (!in_array($attribute, $this->searchableAttributes)) {
            $this->searchableAttributes[] = $attribute;
        }
        return $this;
    }

    /**
     * Add a searchable relation to the resource.
     *
     * @param string $relation
     * @return $this
     */
    public function addSearchableRelation(string $relation): static
    {
        if (!in_array($relation, $this->searchableRelations)) {
            $this->searchableRelations[] = $relation;
        }
        return $this;
    }

    /**
     * Remove a searchable attribute from the resource.
     *
     * @param string $attribute
     * @return $this
     */
    public function removeSearchableAttribute(string $attribute): static
    {
        $this->searchableAttributes = array_values(array_diff($this->searchableAttributes, [$attribute]));
        return $this;
    }

    /**
     * Remove a searchable relation from the resource.
     *
     * @param string $relation
     * @return $this
     */
    public function removeSearchableRelation(string $relation): static
    {
        $this->searchableRelations = array_values(array_diff($this->searchableRelations, [$relation]));
        return $this;
    }

    /**
     * Get all searchable items (attributes and relations) as a flat array.
     *
     * @return array
     */
    public function getAllSearchableItems(): array
    {
        return array_merge($this->searchableAttributes, $this->searchableRelations);
    }

    /**
     * Clear all searchable configurations.
     *
     * @return $this
     */
    public function clearSearchable(): static
    {
        $this->searchableAttributes = [];
        $this->searchableRelations = [];
        return $this;
    }
}