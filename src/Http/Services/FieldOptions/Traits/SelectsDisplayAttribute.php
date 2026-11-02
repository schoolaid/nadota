<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions\Traits;

use Illuminate\Database\Eloquent\Builder;
use SchoolAid\Nadota\Http\Fields\Field;

trait SelectsDisplayAttribute
{
    /**
     * Add the field's display attribute to the select columns.
     *
     * The related resource's select columns only cover its own fields, so a
     * displayAttribute without a matching field would never be loaded and every
     * label would fall back to the primary key. Accessors are not columns, so
     * they are left out.
     *
     * @param array $selectColumns
     * @param Field $field
     * @param Builder $query
     * @return array
     */
    protected function withDisplayAttribute(array $selectColumns, Field $field, Builder $query): array
    {
        if ($selectColumns === ['*'] || !method_exists($field, 'getAttributeForDisplay')) {
            return $selectColumns;
        }

        $attribute = $field->getAttributeForDisplay();

        if (!$attribute || in_array($attribute, $selectColumns, true)) {
            return $selectColumns;
        }

        $model = $query->getModel();

        if ($model->hasGetMutator($attribute) || $model->hasAttributeMutator($attribute)) {
            return $selectColumns;
        }

        $selectColumns[] = $attribute;

        return $selectColumns;
    }
}
