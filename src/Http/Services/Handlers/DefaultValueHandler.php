<?php

namespace SchoolAid\Nadota\Http\Services\Handlers;

use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Resource;
use Illuminate\Support\Collection;

class DefaultValueHandler
{
    public function applyDefaults(Collection $fields, array $validatedData, $request, Model $model, Resource $resource): array
    {
        foreach ($fields as $field) {
            $attribute = $field->getAttribute();

            if (!array_key_exists($attribute, $validatedData) || is_null($validatedData[$attribute])) {
                $validatedData[$attribute] = $field->hasDefault()
                    ? $field->resolveDefault($request, $model, $resource)
                    : $validatedData[$attribute];
            }
        }

        return $validatedData;
    }
}

