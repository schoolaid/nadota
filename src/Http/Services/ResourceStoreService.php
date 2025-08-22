<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Contracts\ResourceStoreInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ResourceStoreService implements ResourceStoreInterface
{
    public function handle(NadotaRequest $request): JsonResponse
    {
        $request->authorized('create');
        $resource = $request->getResource();
        $model = new $resource->model();
        $fields = collect($resource->fields($request))
            ->filter(function ($field) {
                return $field->isShowOnCreation();
            });
        $rules = $fields->mapWithKeys(function ($field) {
            return [$field->getAttribute() => $field->getRules()];
        })->toArray();
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        $validator->validated();
        $onlyAttributes = $fields->map(function ($field) {
            return $field->getAttribute();
        })->toArray();
        $validatedData = $validator->safe()->only($onlyAttributes);
        $fields->each(function ($field) use (&$validatedData, $request, $model, $resource) {
            $attribute = $field->getAttribute();

            if (!array_key_exists($attribute, $validatedData) && $field->hasDefault()) {
                $validatedData[$attribute] = $field->resolveDefault($request, $model, $resource);
            }

            $value = $validatedData[$attribute] ?? null;


            if($field->getType() === 'belongsTo') {
                $foreignKey = $model->{$attribute}()->getForeignKeyName();
                $model->{$foreignKey} = $value;
            }

            if($field->getType() !== 'belongsTo') {
                $model->{$attribute} = $value;
            }

        });
        $model->save();
        return response()->json([
            'message' => 'Resource created successfully',
            'data' => $model,
        ], 201);
    }
}
