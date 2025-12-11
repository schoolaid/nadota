<?php

namespace SchoolAid\Nadota\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\RelationIndexService;

class RelationController extends Controller
{
    public function __construct(
        private RelationIndexService $relationService
    ) {}

    /**
     * Get paginated relation data for a field.
     */
    public function index(NadotaRequest $request, string $resourceKey, string $id, string $field): JsonResponse
    {
        $request->prepareResource();

        return $this->relationService->handle($request, $id, $field);
    }
}
