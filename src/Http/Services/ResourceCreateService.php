<?php

namespace SchoolAid\Nadota\Http\Services;

use SchoolAid\Nadota\Contracts\ResourceCreateInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
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
                'key' => $resource::getKey(),
                'attributes' => $fields->map(fn($field) => $field->toArray(request: $request, resource: $resource)),
                'title' => $resource->title(),
            ],
        ]);
    }
}
