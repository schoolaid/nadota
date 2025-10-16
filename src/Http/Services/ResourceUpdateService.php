<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Contracts\ResourceUpdateInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Traits\TracksActionEvents;

class ResourceUpdateService implements ResourceUpdateInterface
{
    use TracksActionEvents;
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
                        $rules[$typeAttribute] = $this->replaceIdPlaceholder($fieldRules[$typeAttribute], $model);
                    }
                    if (isset($fieldRules[$idAttribute])) {
                        $rules[$idAttribute] = $this->replaceIdPlaceholder($fieldRules[$idAttribute], $model);
                    }
                    // If rules are provided as single string, apply to id field
                    if (!isset($fieldRules[$typeAttribute]) && !isset($fieldRules[$idAttribute])) {
                        $rules[$idAttribute] = $this->replaceIdPlaceholder($fieldRules, $model);
                    }
                }
            } else {
                $fieldRules = $field->getRules();
                $rules[$field->getAttribute()] = $this->replaceIdPlaceholder($fieldRules, $model);
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

        // Store original data before changes
        $originalData = $model->getAttributes();

        try {
            DB::beginTransaction();

            // First pass: handle non-relation fields
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
                        $model->{$attribute} = $field->resolveForUpdate($request, $model, $resource, $validatedData[$attribute]);
                    }
                }
            });

            $model->save();

            // Track the update action
            $this->trackUpdate($model, $request, $validatedData, $originalData);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update resource',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Resource updated successfully',
            'data' => $model,
        ]);
    }

    /**
     * Replace :id placeholder in validation rules.
     *
     * @param mixed $rules
     * @param mixed $model
     * @return mixed
     */
    private function replaceIdPlaceholder($rules, $model)
    {
        if (is_array($rules)) {
            foreach ($rules as &$rule) {
                if (is_string($rule)) {
                    $rule = str_replace(':id', $model->id, $rule);
                }
            }
            return $rules;
        } elseif (is_string($rules)) {
            return str_replace(':id', $model->id, $rules);
        }

        return $rules;
    }
}
