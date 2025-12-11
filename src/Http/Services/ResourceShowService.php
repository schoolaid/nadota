<?php

namespace SchoolAid\Nadota\Http\Services;

use SchoolAid\Nadota\Contracts\ResourceShowInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceShowService implements ResourceShowInterface
{
    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        // Build the query with all necessary eager loading and column selection
        $attributes = $resource->getSelectColumns($request);
        $eagerLoadRelations = $resource->getEagerLoadRelations($request);

        $model = $resource->getQuery($request)
            ->with([...$resource->getWithOnShow(), ...$eagerLoadRelations])
            ->select(...$attributes)
            ->findOrFail($id);

        $request->authorized('view', $model);
        $action = $request->get('action', 'show');

        // Check if a custom show response resource is defined
        $customResourceClass = $resource->getShowResponseResource();

        if ($customResourceClass && is_subclass_of($customResourceClass, JsonResource::class)) {
            return (new $customResourceClass($model))->response();
        }

        return $this->buildDefaultResponse($request, $resource, $model, $action);
    }

    /**
     * Build the default Nadota response format.
     */
    protected function buildDefaultResponse(
        NadotaRequest $request,
        $resource,
        $model,
        string $action
    ): JsonResponse {
        $fields = $resource->fieldsForShow($request, $action)
            ->map(function ($field) use ($request, $model, $resource) {
                return $field->toArray($request, $model, $resource);
            });

        return response()->json([
            'data' => [
                'id' => $model->getKey(),
                'attributes' => $fields,
                'permissions' => $resource->getPermissionsForResource($request, $model),
                'title' => $resource->title(),
                'tools' => $resource->tools($request),
                'deletedAt' => $model->deleted_at ?? null,
            ],
        ]);
    }
}