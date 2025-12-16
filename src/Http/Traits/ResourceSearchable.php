<?php

namespace SchoolAid\Nadota\Http\Traits;

/**
 * Trait ResourceSearchable
 *
 * Provides search configuration for resources. Used by:
 * - Index queries (global search)
 * - Options endpoints (relation field options search)
 *
 * @example
 * ```php
 * class StudentResource extends Resource
 * {
 *     // Direct attributes to search
 *     protected array $searchableAttributes = ['name', 'email', 'student_id'];
 *
 *     // Related model attributes to search (format: 'relation.attribute')
 *     protected array $searchableRelations = [
 *         'family.name',           // Search in family name
 *         'grade.title',           // Search in grade title
 *         'enrollments.year',      // Search in enrollment years
 *     ];
 * }
 * ```
 *
 * If no searchable attributes are configured, the system falls back to:
 * ['name', 'title', 'label', 'display_name', 'full_name', 'description']
 */
trait ResourceSearchable
{
    /**
     * The key used for global search in query parameters.
     * This helps avoid conflicts with filters that might use 'search' as a key.
     *
     * @var string
     */
    protected string $searchKey = 'globalSearch';

    /**
     * The attributes that should be searchable on the resource.
     *
     * These are direct columns on the model's table that will be searched
     * using LIKE queries when the user types in a search box.
     *
     * @var array
     *
     * @example
     * ```php
     * protected array $searchableAttributes = ['name', 'email', 'phone'];
     * ```
     */
    protected array $searchableAttributes = [];

    /**
     * The relations that should be searchable on the resource.
     *
     * Format: ['relation.attribute', 'relation.nested.attribute']
     * Uses whereHas() to search within related models.
     *
     * @var array
     *
     * @example
     * ```php
     * protected array $searchableRelations = [
     *     'user.name',           // BelongsTo relation
     *     'category.title',      // BelongsTo relation
     *     'tags.name',           // BelongsToMany relation
     *     'comments.body',       // HasMany relation
     *     'author.profile.bio',  // Nested relation (HasOne through BelongsTo)
     * ];
     * ```
     */
    protected array $searchableRelations = [];

    /**
     * Get the search key for the resource.
     *
     * @return string
     */
    public function getSearchKey(): string
    {
        return $this->searchKey;
    }

    /**
     * Check if global search is enabled for this resource.
     *
     * @return bool
     */
    public function isSearchable(): bool
    {
        return !empty($this->searchableAttributes) || !empty($this->searchableRelations);
    }

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