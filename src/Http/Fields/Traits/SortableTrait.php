<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use Illuminate\Database\Eloquent\Builder;

trait SortableTrait
{
    protected bool $sortable = false;

    public function sortable(): static
    {
        $this->sortable = true;
        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function applySorting(Builder $query, $sortDirection, $modelInstance): Builder
    {
        return $query->orderBy($this->getAttribute(), $sortDirection);
    }
}
