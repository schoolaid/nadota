<?php

namespace SchoolAid\Nadota\Contracts;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use Illuminate\Http\JsonResponse;

interface ResourceStoreInterface
{
    public function handle(NadotaRequest $request);
}
