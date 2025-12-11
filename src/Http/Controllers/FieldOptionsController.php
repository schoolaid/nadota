<?php

namespace SchoolAid\Nadota\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\FieldOptionsService;

class FieldOptionsController extends Controller
{
    public function __construct(
        protected FieldOptionsService $fieldOptionsService,
        NadotaRequest $request
    ) {
        if (!App::runningInConsole()) {
            $request->validateResource();
        }
    }

    /**
     * Get options for a specific field.
     *
     * @param NadotaRequest $request
     * @param string $resourceKey
     * @param string $fieldName
     * @return JsonResponse
     */
    public function index(NadotaRequest $request, string $resourceKey, string $fieldName): JsonResponse
    {
        $options = $this->fieldOptionsService->getFieldOptions(
            $request,
            $resourceKey,
            $fieldName
        );

        return response()->json($options);
    }

    /**
     * Get paginated options for a specific field.
     *
     * @param NadotaRequest $request
     * @param string $resourceKey
     * @param string $fieldName
     * @return JsonResponse
     */
    public function paginated(NadotaRequest $request, string $resourceKey, string $fieldName): JsonResponse
    {
        $options = $this->fieldOptionsService->getPaginatedOptions(
            $request,
            $resourceKey,
            $fieldName
        );

        return response()->json($options);
    }

    /**
     * Get options for a specific morph type of morph field.
     *
     * @param NadotaRequest $request
     * @param string $resourceKey
     * @param string $fieldName
     * @param string $morphType
     * @return JsonResponse
     */
    public function morphOptions(
        NadotaRequest $request,
        string $resourceKey,
        string $fieldName,
        string $morphType
    ): JsonResponse {
        $options = $this->fieldOptionsService->getFieldOptions(
            $request,
            $resourceKey,
            $fieldName,
            ['morphType' => $morphType]
        );

        return response()->json($options);
    }
}