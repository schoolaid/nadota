<?php

namespace Said\Nadota\Http\Fields\Traits;

trait SearchableTrait
{
    protected bool $searchable = false;
    protected bool $searchableGlobally = false;
    protected ?int $searchWeight = null;

    public function searchable(): static
    {
        $this->searchable = true;
        return $this;
    }

    public function notSearchable(): static
    {
        $this->searchable = false;
        return $this;
    }

    public function searchableGlobally(): static
    {
        $this->searchableGlobally = true;
        $this->searchable = true;
        return $this;
    }

    public function searchWeight(int $weight): static
    {
        $this->searchWeight = $weight;
        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isSearchableGlobally(): bool
    {
        return $this->searchableGlobally;
    }

    public function getSearchWeight(): ?int
    {
        return $this->searchWeight;
    }
}
