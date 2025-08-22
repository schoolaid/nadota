<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Http\JsonResponse;
use SchoolAid\Nadota\Contracts\ResourceDestroyInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ResourceDestroyService implements ResourceDestroyInterface
{
    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        $model = $resource->getQuery($request)->findOrFail($id);

        $request->authorized('delete', $model);

        $model->delete();

        return response()->json([
            'message' => 'Resource deleted successfully',
        ], 200);
    }
}
