<?php

namespace SchoolAid\Nadota\Http\Services\Pipes;

use Closure;
use SchoolAid\Nadota\Http\DataTransferObjects\IndexRequestDTO;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\RelationField;

class ApplySortingPipe
{
    public function handle(IndexRequestDTO $data, Closure $next)
    {

        $sortField = $data->request->input('sortField') ?? 'created_at';
        $sortDirection = $data->request->input('sortDirection') ?? 'desc';
        $fields = $data->getFields();

        /** @var Field $field */
        $field = $fields->first(fn($field) => $field->key() === $sortField);

        if (!$field) {
            $data->query->orderBy('created_at', 'desc');
            return $next($data);
        }

        if ($field instanceof RelationField) {
            $data->query = $field->applySorting($data->query, $sortDirection, $data->modelInstance);
        }else{
            $attribute = $field->getAttribute();
            $data->query->orderBy($attribute, $sortDirection);
        }

        return $next($data);
    }
}
