<?php

namespace SchoolAid\Nadota\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\ResourceManager;

class GlobalOptionsController extends Controller
{
    /**
     * Return all registered resources with their filter configuration.
     * This endpoint is public (no auth required).
     */
    public function index(Request $request): JsonResponse
    {
        $resources = ResourceManager::getResources();

        $nadotaRequest = app(NadotaRequest::class);

        $result = [];

        foreach ($resources as $key => $resource) {
            $resourceInstance = new $resource['class'];

            if (!$resourceInstance->isAvailableInGlobalOptions()) {
                continue;
            }

            // Get field-based filters (key + label only)
            $flatFields = $resourceInstance->flattenFields($nadotaRequest);
            $fieldFilters = $flatFields
                ->filter(fn($field) => $field->isFilterable())
                ->flatMap(fn($field) => $field->filters())
                ->map(fn($filter) => [
                    'key' => $filter->key(),
                    'label' => $filter->name(),
                ])
                ->values()
                ->toArray();

            // Get resource-level filters (key + label only)
            $resourceFilters = collect($resourceInstance->filters($nadotaRequest))
                ->map(fn($filter) => [
                    'key' => $filter->key(),
                    'label' => $filter->name(),
                ])
                ->values()
                ->toArray();

            $result[] = [
                'key' => $key,
                'filters' => array_merge($fieldFilters, $resourceFilters),
            ];
        }

        return response()->json($result);
    }
}
