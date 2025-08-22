<?php

namespace Said\Nadota\Contracts;

use Said\Nadota\Http\Requests\NadotaRequest;
use Illuminate\Http\JsonResponse;

interface ResourceStoreInterface
{
    public function handle(NadotaRequest $request);
}
