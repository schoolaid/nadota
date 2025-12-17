<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use SchoolAid\Nadota\Contracts\ResourceEditInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ResourceEditService implements ResourceEditInterface
{
    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        // Get fields for update form (before loading model to build query correctly)
        $fields = $resource->fieldsForForm($request, true); // true indicates update mode

        // Build optimized query with proper eager loading and column selection
        $columns = $resource->getSelectColumns($request, $fields);
        $eagerLoadRelations = $resource->getEagerLoadRelations($request, $fields);

        // Include soft delete column if model uses soft deletes
        $columns = $this->includeDeletedAtColumn($resource, $columns);

        $model = $resource->getQuery($request)
            ->with($eagerLoadRelations)
            ->select(...$columns)
            ->findOrFail($id);

        $request->authorized('update', $model);

        // Check if a custom edit response resource is defined
        $customResourceClass = $resource->getEditResponseResource();

        if ($customResourceClass && is_subclass_of($customResourceClass, JsonResource::class)) {
            return (new $customResourceClass($model))->response();
        }

        return $this->buildDefaultResponse($request, $resource, $model, $fields);
    }

    /**
     * Build the default Nadota response format.
     */
    protected function buildDefaultResponse(
        NadotaRequest $request,
        $resource,
        $model,
        $fields
    ): JsonResponse {
        // Transform fields with model values
        $attributes = $fields->map(function ($field) use ($request, $model, $resource) {
            return $field->toArray($request, $model, $resource);
        });

        return response()->json([
            'data' => [
                'id' => $model->getKey(),
                'key' => $resource::getKey(),
                'attributes' => $attributes,
                'permissions' => $resource->getPermissionsForResource($request, $model),
                'title' => $resource->title(),
                'deletedAt' => $model->deleted_at ?? null,
            ],
        ]);
    }

    /**
     * Include deleted_at column if model uses soft deletes.
     */
    protected function includeDeletedAtColumn($resource, array $columns): array
    {
        $modelClass = $resource->model;
        $model = new $modelClass;

        if (method_exists($model, 'getDeletedAtColumn')) {
            $deletedAt = $model->getDeletedAtColumn();
            if (!in_array($deletedAt, $columns) && !in_array('*', $columns)) {
                $columns[] = $deletedAt;
            }
        }

        return $columns;
    }
}
