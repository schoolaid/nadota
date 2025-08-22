<?php

namespace SchoolAid\Nadota\Contracts;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface FilterInterface
{
   public function apply(NadotaRequest $request, $query, $value);
   public function resources(NadotaRequest $request);
}
