<?php

namespace SchoolAid\Nadota\Http\Criteria;

use SchoolAid\Nadota\Http\Filters\Filter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class FilterCriteria
{
    protected array $filterValues;

    public function __construct(array $filters)
    {
        $this->filterValues = $filters;
    }

    public function apply(NadotaRequest $request, $query, $filters)
    {
        foreach ($this->filterValues as $filterName => $value) {
            $filter = collect($filters)->first(function ($filter) use ($filterName) {
                return $filter->key() === $filterName;
            });

            if ($filter && $value !== null) {
                $query = $filter->apply($request, $query, $value);
            }
        }

        return $query;
    }
}
