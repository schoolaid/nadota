<?php

namespace SchoolAid\Nadota\Http\Filters;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class DefaultFilter  extends Filter
{
    public function apply(NadotaRequest $request, $query, $value)
    {
        return $query->where($this->field, 'like', '%' . $value . '%');
    }
}
