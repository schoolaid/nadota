<?php

namespace SchoolAid\Nadota\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceOptionsService;

class ResourceOptionsController extends Controller
{
    public function __construct(
        protected ResourceOptionsService $resourceOptionsService,
        NadotaRequest $request
    ) {
        if (!App::runningInConsole()) {
            $request->validateResource();
        }
    }

    /**
     * Get options for a resource.
     *
     * Authorizes using viewAny first, falls back to viewOptions if viewAny is denied.
     *
     * @param NadotaRequest $request
     * @return JsonResponse
     */
    public function index(NadotaRequest $request): JsonResponse
    {
        $resource = $request->getResource();

        // Check viewAny first, fallback to viewOptions
        if (!$resource->authorizedTo($request, 'viewAny') && !$resource->authorizedTo($request, 'viewOptions')) {
            abort(403);
        }

        $result = $this->resourceOptionsService->getOptions($request, $resource);

        return response()->json($result);
    }
}
