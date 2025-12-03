<?php

namespace SchoolAid\Nadota\Http\Filters;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class SelectFilter extends Filter
{
    protected array $options = [];

    public function __construct(string $name = null, string $field = null, string $type = null, string $component = null, $id = null)
    {
        parent::__construct($name, $field, $type ?? 'select', $component ?? 'FilterSelect', $id);
    }

    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function apply(NadotaRequest $request, $query, $value)
    {
        if (is_array($value)) {
            return $query->whereIn($this->field, $value);
        }
        
        return $query->where($this->field, $value);
    }

    public function resources(NadotaRequest $request): array
    {
        return $this->options;
    }
}

