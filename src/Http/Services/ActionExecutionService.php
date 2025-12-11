<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SchoolAid\Nadota\Contracts\ActionInterface;
use SchoolAid\Nadota\Http\Actions\Action;
use SchoolAid\Nadota\Http\Actions\ActionResponse;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ActionExecutionService
{
    protected ActionEventService $actionEventService;

    public function __construct(ActionEventService $actionEventService)
    {
        $this->actionEventService = $actionEventService;
    }

    /**
     * Find an action by its key within a resource.
     */
    public function findAction(NadotaRequest $request, string $actionKey): ?ActionInterface
    {
        $resource = $request->getResource();
        $actions = $resource->actions($request);

        foreach ($actions as $action) {
            if ($action::getKey() === $actionKey) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Execute an action on the given model IDs.
     */
    public function execute(
        NadotaRequest $request,
        ActionInterface $action,
        array $modelIds
    ): ActionResponse {
        $resource = $request->getResource();
        $modelClass = $resource->model;

        // Get the models
        $models = $this->getModels($modelClass, $modelIds, $resource->usesSoftDeletes());

        // Authorize the action for each model
        $authorizedModels = $this->filterAuthorizedModels($request, $action, $models);

        if ($authorizedModels->isEmpty() && !$action->isStandalone()) {
            return ActionResponse::danger('You are not authorized to run this action on the selected resources.');
        }

        // Execute the action
        $result = $action->handle($authorizedModels, $request);

        // Log the action execution
        $this->logActionExecution($request, $action, $authorizedModels, $resource);

        // Ensure we return an ActionResponse
        if ($result instanceof ActionResponse) {
            return $result;
        }

        // If the action returns nothing, return a default success message
        return ActionResponse::message('Action executed successfully.');
    }

    /**
     * Get models by their IDs.
     */
    protected function getModels(string $modelClass, array $modelIds, bool $withTrashed = false): Collection
    {
        if (empty($modelIds)) {
            return collect();
        }

        $query = $modelClass::query()->whereIn('id', $modelIds);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->get();
    }

    /**
     * Filter models based on authorization.
     */
    protected function filterAuthorizedModels(
        NadotaRequest $request,
        ActionInterface $action,
        Collection $models
    ): Collection {
        return $models->filter(function (Model $model) use ($request, $action) {
            return $action->authorizedToRun($request, $model);
        });
    }

    /**
     * Log the action execution.
     */
    protected function logActionExecution(
        NadotaRequest $request,
        ActionInterface $action,
        Collection $models,
        $resource
    ): void {
        foreach ($models as $model) {
            $this->actionEventService->logAction(
                action: 'action:' . $action::getKey(),
                model: $model,
                resource: $resource,
                request: $request,
                fields: $request->only(array_keys($request->all())),
                metadata: [
                    'action_name' => $action->name(),
                    'action_key' => $action::getKey(),
                ]
            );
        }
    }

    /**
     * Execute an action in batches for large datasets.
     */
    public function executeBatched(
        NadotaRequest $request,
        ActionInterface $action,
        array $modelIds,
        int $batchSize = 100
    ): ActionResponse {
        $resource = $request->getResource();
        $modelClass = $resource->model;

        $chunks = array_chunk($modelIds, $batchSize);
        $processedCount = 0;
        $lastResult = null;

        foreach ($chunks as $chunkIds) {
            $models = $this->getModels($modelClass, $chunkIds, $resource->usesSoftDeletes());
            $authorizedModels = $this->filterAuthorizedModels($request, $action, $models);

            if ($authorizedModels->isNotEmpty()) {
                $lastResult = $action->handle($authorizedModels, $request);
                $processedCount += $authorizedModels->count();

                // Log the action execution for this batch
                $this->logActionExecution($request, $action, $authorizedModels, $resource);
            }
        }

        if ($lastResult instanceof ActionResponse) {
            return $lastResult;
        }

        return ActionResponse::message("Action executed on {$processedCount} resources.");
    }
}
