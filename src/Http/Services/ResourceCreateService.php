<?php

namespace Said\Nadota\Http\Services;

use Said\Nadota\Contracts\ResourceCreateInterface;
use Said\Nadota\Http\Requests\NadotaRequest;
use Illuminate\Http\JsonResponse;

class ResourceCreateService implements ResourceCreateInterface
{
    public function handle(NadotaRequest $request): JsonResponse
    {
        $request->authorized('create');

        $resource = $request->getResource();

        $fields = $resource->fieldsForForm($request);

        return response()->json([
            'data' => [
                'key' => $resource->getKey(),
                'attributes' => $fields,
                'title' => $resource->title(),
            ],
        ]);
    }
}
