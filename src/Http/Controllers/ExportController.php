<?php

namespace SchoolAid\Nadota\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use SchoolAid\Nadota\Contracts\ResourceExportInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function __construct(NadotaRequest $request)
    {
        if (!App::runningInConsole()) {
            $request->validateResource();
        }
    }

    /**
     * Export resource data.
     */
    public function export(NadotaRequest $request, ResourceExportInterface $exportService): Response
    {
        return $exportService->handle($request);
    }

    /**
     * Get export configuration for a resource.
     */
    public function config(NadotaRequest $request): JsonResponse
    {
        $request->authorized('viewAny');

        $resource = $request->getResource();

        return response()->json([
            'data' => $resource->getExportConfig($request),
        ]);
    }
}
