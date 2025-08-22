<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

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
}
