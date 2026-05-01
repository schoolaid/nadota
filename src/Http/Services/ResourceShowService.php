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
        $action = $request->get('action', 'show');

        // First pass: all fields for this action (no model yet) — used only to build the query
        $allFields = $resource->fieldsForShow($request, $action);

        // Build optimized query with proper eager loading and column selection
        $columns = $resource->getSelectColumns($request, $allFields);
        $eagerLoadRelations = $resource->getEagerLoadRelations($request, $allFields);
        $withCountRelations = $resource->getWithCountRelations($request, $allFields);
        $withExistsRelations = $resource->getWithExistsRelations($request, $allFields);

        // Include soft delete column if model uses soft deletes
        $columns = $this->includeDeletedAtColumn($resource, $columns);

        $query = $resource->getQuery($request)
            ->with([...$resource->getWithOnShow(), ...$eagerLoadRelations])
            ->select(...$columns);

        // Apply withCount for Count fields
        if (!empty($withCountRelations)) {
            $query->withCount($withCountRelations);
        }

        // Apply withExists for Exists fields
        if (!empty($withExistsRelations)) {
            $query->withExists($withExistsRelations);
        }

        $model = $query->findOrFail($id);

        // Authorize based on action
        $permission = $action === 'update' ? 'update' : 'view';
        $request->authorized($permission, $model);

        // Check if a custom response resource is defined
        $customResourceClass = $action === 'update'
            ? $resource->getEditResponseResource()
            : $resource->getShowResponseResource();

        if ($customResourceClass && is_subclass_of($customResourceClass, JsonResource::class)) {
            return (new $customResourceClass($model))->response();
        }

        // Second pass: filter fields by visibility with the loaded model
        $fields = $resource->fieldsForShow($request, $action, $model);

        return $this->buildDefaultResponse($request, $resource, $model, $action, $fields);
    }

    /**
     * Build the default Nadota response format.
     */
    protected function buildDefaultResponse(
        NadotaRequest $request,
        $resource,
        $model,
        string $action,
        $fields
    ): JsonResponse {
        // Transform fields with model values
        $attributes = $fields->map(function ($field) use ($request, $model, $resource) {
            return $field->toArray($request, $model, $resource);
        });

        $response = [
            'key' => $resource::getKey(),
            'attributes' => $attributes,
            'permissions' => $resource->getPermissionsForResource($request, $model),
            'title' => $resource->title(),
            'deletedAt' => $model->deleted_at ?? null,
            'detailCardWidth' => $resource->getDetailCardWidth(),
        ];

        // Only include 'id' if the resource allows it
        if ($resource->shouldIncludeId()) {
            $response = ['id' => $model->getKey()] + $response;
        }

        // Include additional data for show action
        if ($action === 'show') {
            $response['tools'] = $resource->tools($request);
            $response['actionEventsUrl'] = $resource->buildActionEventsUrl($model);
        }

        return response()->json([
            'data' => $response,
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
