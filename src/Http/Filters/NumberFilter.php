<?php

namespace SchoolAid\Nadota\Http\Filters;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class NumberFilter extends Filter
{
    protected bool $isRange = false;

    public function __construct(string $name = null, string $field = null, string $type = null, string $component = null, $id = null, bool $isRange = false)
    {
        $this->isRange = $isRange;
        $componentName = $isRange ? 'FilterNumberRange' : 'FilterNumber';
        parent::__construct($name, $field, $type ?? 'number', $component ?? $componentName, $id);
    }

    public function range(bool $isRange = true): static
    {
        $this->isRange = $isRange;
        $this->component = $isRange ? 'FilterNumberRange' : 'FilterNumber';
        return $this;
    }

    public function apply(NadotaRequest $request, $query, $value)
    {
        if ($this->isRange && is_array($value)) {
            $start = $value['start'] ?? $value[0] ?? null;
            $end = $value['end'] ?? $value[1] ?? null;

            if ($start !== null && $end !== null) {
                return $query->whereBetween($this->field, [$start, $end]);
            } elseif ($start !== null) {
                return $query->where($this->field, '>=', $start);
            } elseif ($end !== null) {
                return $query->where($this->field, '<=', $end);
            }
        } else {
            // Single number filter - exact match
            if ($value !== null && $value !== '') {
                return $query->where($this->field, $value);
            }
        }

        return $query;
    }

    public function props(): array
    {
        return array_merge(parent::props(), [
            'isRange' => $this->isRange,
        ]);
    }

    public function isRange(): bool
    {
        return $this->isRange;
    }

    public function getFilterKeys(): array
    {
        if ($this->isRange) {
            return [
                'from' => "{$this->field}_from",
                'to' => "{$this->field}_to",
            ];
        }

        return parent::getFilterKeys();
    }
}

