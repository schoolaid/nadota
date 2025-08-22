<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Http\JsonResponse;
use SchoolAid\Nadota\Contracts\ResourceCreateInterface;
use SchoolAid\Nadota\Contracts\ResourceEditInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ResourceEditService implements ResourceEditInterface
{
    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();
        $model = $resource->getQuery($request)->findOrFail($id);
        $request->authorized('update', $model);

        $fields = $resource->fieldsForForm($request, $model);

        return response()->json([
            'data' => [
                'id' => $model->getKey(),
                'fields' => $fields,
            ],
        ], 200);
    }
}
