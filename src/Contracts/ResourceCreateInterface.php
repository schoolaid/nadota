<?php

namespace Said\Nadota\Contracts;

use Said\Nadota\Http\Requests\NadotaRequest;
use Illuminate\Http\JsonResponse;

interface ResourceCreateInterface
{
    public function handle(NadotaRequest $request): JsonResponse;
}
