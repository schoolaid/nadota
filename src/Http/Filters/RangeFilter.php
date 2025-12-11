<?php

namespace SchoolAid\Nadota\Http\Filters;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class RangeFilter extends Filter
{
    public function apply(NadotaRequest $request, $query, $value)
    {
        $start = $value['start'] ?? null;
        $end = $value['end'] ?? null;

        if ($start && $end) {
            $query->whereBetween($this->field, [$start, $end]);
        }
        elseif ($start) {
            $query->where($this->field, '>=', $start);
        }
        elseif ($end) {
            $query->where($this->field, '<=', $end);
        }

        return $query;
    }

    public function isRange(): bool
    {
        return true;
    }

    public function getFilterKeys(): array
    {
        return [
            'from' => "{$this->field}_from",
            'to' => "{$this->field}_to",
        ];
    }
}
