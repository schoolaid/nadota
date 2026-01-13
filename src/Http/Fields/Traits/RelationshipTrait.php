<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Resource;

trait RelationshipTrait
{
    protected ?string $relation = null;
    protected ?string $displayAttribute = null;
    protected $displayCallback = null;
    protected bool $isRelationship = false;
    protected bool $applyInIndexQuery = true;
    protected bool $applyInShowQuery = true;
    protected ?string $relatedModel = null;
    protected ?string $relatedResource = null;

    /**
     * Limit for options endpoint. Null means no limit.
     */
    protected ?int $optionsLimit = null;

    /**
     * Whether optionsLimit has been explicitly set.
     */
    protected bool $optionsLimitSet = false;

    public function getAttributeForDisplay(): ?string
    {
        return $this->displayAttribute;
    }

    public function displayAttribute(string $attribute): static
    {
        $this->displayAttribute = $attribute;
        return $this;
    }

    /**
     * Set a custom callback to determine the display value.
     *
     * @param callable $callback
     * @return static
     */
    public function displayUsing(callable $callback): static
    {
        $this->displayCallback = $callback;
        return $this;
    }

    /**
     * Check if a display callback is set.
     *
     * @return bool
     */
    public function hasDisplayCallback(): bool
    {
        return $this->displayCallback !== null;
    }

    /**
     * Resolve the display value using callback or attribute.
     *
     * @param mixed $relatedModel
     * @return mixed
     */
    public function resolveDisplay(mixed $relatedModel): mixed
    {
        if ($this->hasDisplayCallback()) {
            return call_user_func($this->displayCallback, $relatedModel);
        }

        if ($this->displayAttribute) {
            return $relatedModel->{$this->displayAttribute};
        }

        return null;
    }

    public function model(string $relatedModel): static
    {
        $this->relatedModel = $relatedModel;
        $this->isRelationship = true;
        return $this;
    }

    public function resource(string $relatedResource): static
    {
        $this->relatedResource = $relatedResource;
        return $this;
    }

    public function relation(string $relation): static
    {
        $this->relation = $relation;
        return $this;
    }

    public function isRelationship(): bool
    {
        return $this->isRelationship;
    }

    public function getModel(): ?string
    {
        return $this->relatedModel;
    }

    public function getResource(): ?string
    {
        return $this->relatedResource ?? null;
    }

    public function getRelation(): ?string
    {
        return $this->relation;
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

    protected function getOptionsUrl(?ResourceInterface $resource = null): ?string
    {
        if (!$resource) {
            return null;
        }

        $resourceKey = $resource::getKey();
        $fieldName = $this->key();
        $apiPrefix = config('nadota.api.prefix', 'nadota-api');

        return "/{$apiPrefix}/{$resourceKey}/resource/field/{$fieldName}/options";
    }

    /**
     * Set the limit for the options endpoint.
     * Use null to return all options without limit.
     *
     * @param int|null $limit
     * @return static
     */
    public function optionsLimit(?int $limit): static
    {
        $this->optionsLimit = $limit;
        $this->optionsLimitSet = true;
        return $this;
    }

    /**
     * Get the options limit.
     *
     * @return int|null
     */
    public function getOptionsLimit(): ?int
    {
        return $this->optionsLimit;
    }

    /**
     * Check if options limit has been explicitly set.
     *
     * @return bool
     */
    public function hasOptionsLimit(): bool
    {
        return $this->optionsLimitSet;
    }
}
