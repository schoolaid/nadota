<?php

namespace SchoolAid\Nadota\Http\Services;

use SchoolAid\Nadota\Contracts\ResourceShowInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use Illuminate\Http\JsonResponse;

class ResourceShowService implements ResourceShowInterface
{
    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();
        $attributes = $resource->getSelectColumns($request);
        $eagerLoadRelations = $resource->getEagerLoadRelations($request);

        $model = $resource->getQuery($request)
            ->with([...$resource->getWithOnShow(), ...$eagerLoadRelations])
            ->select(...$attributes)
            ->findOrFail($id);

        $request->authorized('view', $model);
        $action = $request->get('action', 'show');

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
                'actions' => $resource->actions($request),
                'tools' => $resource->tools($request),
                'deletedAt' => $model->deleted_at ?? null,
            ],
        ]);
    }
}