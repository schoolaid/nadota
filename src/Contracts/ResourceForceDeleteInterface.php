<?php

namespace SchoolAid\Nadota\Contracts;

use Illuminate\Http\JsonResponse;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface ResourceForceDeleteInterface
{
    /**
     * Force delete a resource permanently.
     *
     * @param NadotaRequest $request
     * @param $id
     * @return JsonResponse
     */
    public function handle(NadotaRequest $request, $id): JsonResponse;
}