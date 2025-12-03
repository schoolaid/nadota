<?php

namespace SchoolAid\Nadota\Http\Filters;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class BooleanFilter extends Filter
{
    protected mixed $trueValue = 1;
    protected mixed $falseValue = 0;

    public function __construct(string $name = null, string $field = null, string $type = null, string $component = null, $id = null)
    {
        parent::__construct($name, $field, $type ?? 'boolean', $component ?? 'FilterBoolean', $id);
    }

    public function trueValue(mixed $value): static
    {
        $this->trueValue = $value;
        return $this;
    }

    public function falseValue(mixed $value): static
    {
        $this->falseValue = $value;
        return $this;
    }

    public function apply(NadotaRequest $request, $query, $value)
    {
        // Handle string 'true'/'false' or boolean true/false
        if ($value === 'true' || $value === true || $value === '1' || $value === 1) {
            return $query->where($this->field, $this->trueValue);
        }
        
        if ($value === 'false' || $value === false || $value === '0' || $value === 0) {
            return $query->where($this->field, $this->falseValue);
        }

        return $query;
    }

    public function resources(NadotaRequest $request): array
    {
        return [
            'true' => 'Sí',
            'false' => 'No'
        ];
    }

    public function props(): array
    {
        return array_merge(parent::props(), [
            'trueValue' => $this->trueValue,
            'falseValue' => $this->falseValue,
        ]);
    }
}

