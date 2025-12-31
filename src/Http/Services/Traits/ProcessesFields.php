<?php

namespace SchoolAid\Nadota\Http\Services\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Trait for processing fields in store/update operations.
 *
 * Provides common functionality for filtering fields, building
 * validation rules, and collecting attributes.
 */
trait ProcessesFields
{
    /**
     * Filter fields for store operation.
     * Uses flattenFields to extract fields from sections.
     *
     * @param ResourceInterface $resource
     * @param NadotaRequest $request
     * @return Collection
     */
    protected function filterFieldsForStore(ResourceInterface $resource, NadotaRequest $request): Collection
    {
        return $resource->flattenFields($request)
            ->filter(function ($field) {
                return $field->isShowOnCreation()
                    && !$field->isReadonly()
                    && !$field->isComputed();
            });
    }

    /**
     * Filter fields for update operation.
     * Uses flattenFields to extract fields from sections.
     *
     * @param ResourceInterface $resource
     * @param NadotaRequest $request
     * @return Collection
     */
    protected function filterFieldsForUpdate(ResourceInterface $resource, NadotaRequest $request): Collection
    {
        return $resource->flattenFields($request)
            ->filter(function ($field) {
                return $field->isShowOnUpdate()
                    && !$field->isReadonly()
                    && !$field->isComputed();
            });
    }

    /**
     * Build validation rules from fields.
     *
     * @param Collection $fields
     * @param Model|null $model For update operations, to replace :id placeholder
     * @return array
     */
    protected function buildValidationRules(Collection $fields, ?Model $model = null): array
    {
        $rules = [];

        foreach ($fields as $field) {
            if ($field instanceof MorphTo) {
                $this->addMorphToRules($field, $rules, $model);
            } else {
                $fieldRules = $field->getRules();
                $rules[$field->getAttribute()] = $model
                    ? $this->replaceIdPlaceholder($fieldRules, $model)
                    : $fieldRules;

                // Support for nested validation rules (e.g., array fields with items)
                if (method_exists($field, 'getNestedRules')) {
                    $nestedRules = $field->getNestedRules();
                    foreach ($nestedRules as $key => $nestedRule) {
                        $rules[$key] = $model
                            ? $this->replaceIdPlaceholder($nestedRule, $model)
                            : $nestedRule;
                    }
                }
            }
        }

        return $rules;
    }

    /**
     * Add validation rules for MorphTo field.
     *
     * MorphTo fields need validation for both type and id attributes.
     *
     * @param MorphTo $field
     * @param array $rules
     * @param Model|null $model
     * @return void
     */
    protected function addMorphToRules(MorphTo $field, array &$rules, ?Model $model = null): void
    {
        $typeAttribute = $field->getMorphTypeAttribute();
        $idAttribute = $field->getMorphIdAttribute();
        $fieldRules = $field->getRules();

        if (empty($fieldRules)) {
            return;
        }

        // If rules are provided as array with specific keys
        if (isset($fieldRules[$typeAttribute])) {
            $rules[$typeAttribute] = $model
                ? $this->replaceIdPlaceholder($fieldRules[$typeAttribute], $model)
                : $fieldRules[$typeAttribute];
        }

        if (isset($fieldRules[$idAttribute])) {
            $rules[$idAttribute] = $model
                ? $this->replaceIdPlaceholder($fieldRules[$idAttribute], $model)
                : $fieldRules[$idAttribute];
        }

        // If rules are provided as single string/array, apply to id field
        if (!isset($fieldRules[$typeAttribute]) && !isset($fieldRules[$idAttribute])) {
            $rules[$idAttribute] = $model
                ? $this->replaceIdPlaceholder($fieldRules, $model)
                : $fieldRules;
        }
    }

    /**
     * Collect all attributes from fields for validation.
     *
     * @param Collection $fields
     * @return array
     */
    protected function collectFieldAttributes(Collection $fields): array
    {
        $attributes = [];

        foreach ($fields as $field) {
            if ($field instanceof MorphTo) {
                $attributes[] = $field->getMorphTypeAttribute();
                $attributes[] = $field->getMorphIdAttribute();
            } else {
                $attributes[] = $field->getAttribute();
            }
        }

        return $attributes;
    }

    /**
     * Replace :id placeholder in validation rules.
     *
     * @param mixed $rules
     * @param Model $model
     * @return mixed
     */
    protected function replaceIdPlaceholder(mixed $rules, Model $model): mixed
    {
        $modelId = $model->getKey();

        if (is_array($rules)) {
            foreach ($rules as &$rule) {
                if (is_string($rule)) {
                    $rule = str_replace(':id', $modelId, $rule);
                }
            }
            return $rules;
        }

        if (is_string($rules)) {
            return str_replace(':id', $modelId, $rules);
        }

        return $rules;
    }

    /**
     * Get fields that support afterSave.
     *
     * @param Collection $fields
     * @return Collection
     */
    protected function getAfterSaveFields(Collection $fields): Collection
    {
        return $fields->filter(function ($field) {
            return method_exists($field, 'supportsAfterSave')
                && $field->supportsAfterSave();
        });
    }
}
