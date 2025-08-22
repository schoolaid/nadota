<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Contracts\ResourceUpdateInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ResourceUpdateService implements ResourceUpdateInterface
{
    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();
        $query = $resource->getQuery($request);
        $model = $resource->queryIndex($request, $query)->findOrFail($id);
        $request->authorized('update', $model);

        $fields = collect($resource->fields($request))
            ->filter(function ($field) {
                return $field->isShowOnUpdate();
            });

        $rules = $fields->mapWithKeys(function ($field) use ($model) {
            $fieldRules = $field->getRules();
            foreach ($fieldRules as &$rule) {
                $rule = str_replace(':id', $model->id, $rule);
            }
            return [
                $field->getAttribute() => $fieldRules
            ];
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

            if($field->getType() !== 'belongsTo') {
                $value = $validatedData[$attribute] ?? null;
                $model->{$attribute} = $value;
            }

        });

        $model->save();

        $model->refresh();
        $fields->each(function ($field) use (&$validatedData, $request, $model, $resource) {
            $attribute = $field->getAttribute();

            if (!array_key_exists($attribute, $validatedData) && $field->hasDefault()) {
                $validatedData[$attribute] = $field->resolveDefault($request, $model, $resource);
            }

            if($field->getType() === 'belongsTo') {
                $relation = $model->{$attribute}
                    ->where(
                        $field->getForeignKey(),
                        $validatedData[$attribute])
                    ->first();

                $model->{$attribute}()->associate($relation);
            }
        });

        $model->save();

        return response()->json([
            'message' => 'Resource updated successfully',
            'data' => $model,
        ]);
    }
}
