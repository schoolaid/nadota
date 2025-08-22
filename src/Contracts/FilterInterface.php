<?php

namespace Said\Nadota\Contracts;

use Said\Nadota\Http\Requests\NadotaRequest;

interface FilterInterface
{
   public function apply(NadotaRequest $request, $query, $value);
   public function resources(NadotaRequest $request);
}
