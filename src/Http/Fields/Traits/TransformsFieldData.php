<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

trait TransformsFieldData
{
    /**
     * Transform model data for the index / list view.
     */
    public function transformForIndex($item, NadotaRequest $request, $fields): array
    {
        return [
            'id' => $item[$this::$attributeKey],
            'attributes' => $this->transformFieldsToArray($fields, $request, $item),
            'deletedAt' => $item->deleted_at ?? null,
            'permissions' => $this->getPermissionsForResource($request, $item),
        ];
    }

    /**
     * Transform a collection of fields to array format.
     */
    protected function transformFieldsToArray($fields, NadotaRequest $request, $model): array
    {
        return $fields->map(function ($field) use ($model, $request) {
            return $field->toArray($request, $model, $this);
        })->toArray();
    }
}