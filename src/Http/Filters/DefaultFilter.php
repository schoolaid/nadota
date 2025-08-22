<?php

namespace Said\Nadota\Http\Filters;

use Said\Nadota\Http\Requests\NadotaRequest;

class DefaultFilter  extends Filter
{
    public function apply(NadotaRequest $request, $query, $value)
    {
        return $query->where($this->field, 'like', '%' . $value . '%');
    }
}
