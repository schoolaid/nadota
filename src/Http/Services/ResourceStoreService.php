<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Contracts\ResourceStoreInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\File;
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
                return $field->isShowOnCreation()
                    && !$field->isReadonly()
                    && !$field->isComputed();
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
                    // If rules are provided as a single string, apply to id field
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
                // Fields that handle their own filling (Files, MorphTo, etc.)
                if ($field instanceof File || $field instanceof MorphTo) {
                    // Use the fill method for special fields
                    $field->fill($request, $model);
                }
                // Default handling for simple fields
                else {
                    $attribute = $field->getAttribute();
                    if (isset($validatedData[$attribute])) {
                        $model->{$attribute} = $field->resolveForStore($request, $model, $resource, $validatedData[$attribute]);
                    }
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

    /**
     * Replace :id placeholder in validation rules.
     * This is useful for unique rules that need to exclude a specific record.
     *
     * @param mixed $rules
     * @param mixed $id The ID to replace :id with
     * @return mixed
     */
    private function replaceIdPlaceholder($rules, $id)
    {
        if (is_array($rules)) {
            foreach ($rules as &$rule) {
                if (is_string($rule)) {
                    $rule = str_replace(':id', $id, $rule);
                }
            }
            return $rules;
        } elseif (is_string($rules)) {
            return str_replace(':id', $id, $rules);
        }

        return $rules;
    }
}
