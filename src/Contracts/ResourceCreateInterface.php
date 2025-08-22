<?php

namespace SchoolAid\Nadota\Contracts;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use Illuminate\Http\JsonResponse;

interface ResourceCreateInterface
{
    public function handle(NadotaRequest $request): JsonResponse;
}
