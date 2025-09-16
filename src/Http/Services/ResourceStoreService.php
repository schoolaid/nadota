<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Contracts\ResourceStoreInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Traits\TracksActionEvents;

class ResourceStoreService implements ResourceStoreInterface
{
    use TracksActionEvents;
    public function handle(NadotaRequest $request): JsonResponse
    {
        $request->authorized('create');
        $resource = $request->getResource();
        $model = new $resource->model;

        $fields = collect($resource->fields($request))
            ->filter(function ($field) {
                return $field->isShowOnCreation();
            });

        // Build validation rules including MorphTo fields
        $rules = [];
        foreach ($fields as $field) {
            if ($field instanceof MorphTo) {
                // MorphTo needs validation for both type and id attributes
                $typeAttribute = $field->getMorphTypeAttribute();
                $idAttribute = $field->getMorphIdAttribute();

                // Add validation rules for morph fields if they exist
                $fieldRules = $field->getRules();
                if (!empty($fieldRules)) {
                    // If rules are provided as array, split them
                    if (isset($fieldRules[$typeAttribute])) {
                        $rules[$typeAttribute] = $fieldRules[$typeAttribute];
                    }
                    if (isset($fieldRules[$idAttribute])) {
                        $rules[$idAttribute] = $fieldRules[$idAttribute];
                    }
                    // If rules are provided as single string, apply to id field
                    if (!isset($fieldRules[$typeAttribute]) && !isset($fieldRules[$idAttribute])) {
                        $rules[$idAttribute] = $fieldRules;
                    }
                }
            } else {
                $rules[$field->getAttribute()] = $field->getRules();
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        $validator->validated();

        // Collect all attributes including morph attributes
        $onlyAttributes = [];
        foreach ($fields as $field) {
            if ($field instanceof MorphTo) {
                $onlyAttributes[] = $field->getMorphTypeAttribute();
                $onlyAttributes[] = $field->getMorphIdAttribute();
            } else {
                $onlyAttributes[] = $field->getAttribute();
            }
        }

        $validatedData = $validator->safe()->only($onlyAttributes);

        try {
            DB::beginTransaction();

            // Process each field
            $fields->each(function ($field) use (&$validatedData, $request, $model, $resource) {
                if ($field instanceof MorphTo) {
                    // Use the fill method for MorphTo fields
                    $field->fill($request, $model);
                } else {
                    $attribute = $field->getAttribute();
                    $model->{$attribute} = $field->resolveForStore($request, $model, $resource, $validatedData[$attribute] ?? null);
                }
            });
            $model->save();

            // Track the create action
            $this->trackCreate($model, $request, $validatedData);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create resource',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Resource created successfully',
            'data' => $model,
        ], 201);
    }
}
