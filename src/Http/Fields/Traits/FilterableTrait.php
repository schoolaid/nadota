<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use SchoolAid\Nadota\Http\Filters\DefaultFilter;
use SchoolAid\Nadota\Http\Filters\RangeFilter;

trait FilterableTrait
{
    protected bool $filterable = false;
    protected string $filterableType = 'text';

    public function filterable(): static
    {
        $this->filterable = true;
        $this->filterableType = 'default';
        return $this;
    }

    public function filterableRange(): static
    {
        $this->filterable = true;
        $this->filterableType = 'range';
        return $this;
    }

    public function isFilterable(): bool
    {
        return $this->filterable;
    }

    public function filters(): array
    {
        $filters = [];
        if ($this->filterable) {
            if ($this->filterableType === 'range') {
                $component = 'FilterRange' . ucfirst($this->fieldData->type);
                $filters[] = new RangeFilter($this->fieldData->name, $this->fieldData->attribute, $this->fieldData->type, $component);
            } elseif ($this->filterableType === 'default') {
                $filters[] = new DefaultFilter($this->fieldData->name, $this->fieldData->attribute, $this->fieldData->type);
            }
        }
        return $filters;
    }
}
