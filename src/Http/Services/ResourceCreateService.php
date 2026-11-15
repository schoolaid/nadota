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

        // The create form comes from this endpoint without `action`: serialize the fields in the
        // store context, so creationRules() reach `required` and `rules` (Field::toArray()).
        if (!$request->has('action')) {
            $request->merge(['action' => 'create']);
        }

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
