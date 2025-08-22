<?php

namespace Said\Nadota\Http\Services;

use Said\Nadota\Contracts\ResourceShowInterface;
use Said\Nadota\Http\Requests\NadotaRequest;
use Illuminate\Http\JsonResponse;

class ResourceShowService implements ResourceShowInterface
{
    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();
        $model = $resource->getQuery($request)->findOrFail($id);
        $request->authorized('view', $model);

        $fields = collect($resource->fields($request))
            ->filter(function ($field) {
                return $field->isShowOnDetail();
            })
            ->map(function ($field) use ($request, $model, $resource) {
                return $field->toArray($request, $model, $resource);
            });
        return response()->json([
            'data' => [
                'id' => $model->getKey(),
                'attributes' => $fields,
                'permissions' => $resource->getPermissionsForResource($request, $model),
                'title' => $resource->title(),
                'actions' => $resource->actions($request),
                'tools' => $resource->tools($request),
                'deletedAt' => $model->deleted_at,
            ],
        ]);
    }
}
