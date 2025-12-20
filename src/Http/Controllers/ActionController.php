<?php

namespace SchoolAid\Nadota\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ActionExecutionService;

class ActionController extends Controller
{
    protected ActionExecutionService $actionService;

    public function __construct(NadotaRequest $request, ActionExecutionService $actionService)
    {
        $this->actionService = $actionService;

        if (!App::runningInConsole()) {
            $request->validateResource();
        }
    }

    /**
     * Get all available actions for a resource.
     */
    public function index(NadotaRequest $request): JsonResponse
    {
        $request->authorized('viewAny');

        $resource = $request->getResource();
        $context = $request->input('context', 'index'); // 'index' or 'detail'

        $actions = collect($resource->actions($request))
            ->filter(function ($action) use ($context) {
                if ($context === 'detail') {
                    return $action->showOnDetail();
                }
                return $action->showOnIndex();
            })
            ->map(fn($action) => $action->toArray($request))
            ->values()
            ->toArray();

        return response()->json([
            'actions' => $actions,
        ]);
    }

    /**
     * Get the fields for a specific action.
     */
    public function fields(NadotaRequest $request, string $resourceKey, string $actionKey): JsonResponse
    {
        $request->authorized('viewAny');

        $action = $this->actionService->findAction($request, $actionKey);

        if (!$action) {
            return response()->json([
                'message' => 'Action not found.',
            ], 404);
        }

        $fields = collect($action->fields($request))
            ->map(fn($field) => $field->toArray($request))
            ->values()
            ->toArray();

        return response()->json([
            'fields' => $fields,
        ]);
    }

    /**
     * Execute an action on selected resources.
     */
    public function execute(NadotaRequest $request, string $resourceKey,  string $actionKey): JsonResponse
    {
        $request->authorized('viewAny');

        $action = $this->actionService->findAction($request, $actionKey);

        if (!$action) {
            return response()->json([
                'message' => 'Action not found.',
            ], 404);
        }

        // Get selected model IDs
        $modelIds = $request->input('resources', []);

        // Validate that models are provided unless the action is standalone
        if (empty($modelIds) && !$action->isStandalone()) {
            return response()->json([
                'message' => 'No resources selected.',
            ], 422);
        }

        try {
            $result = $this->actionService->execute($request, $action, $modelIds);

            return response()->json($result->toArray());
        } catch (\Exception $e) {
            return response()->json([
                'type' => 'danger',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
