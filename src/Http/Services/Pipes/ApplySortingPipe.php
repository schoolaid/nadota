<?php

namespace SchoolAid\Nadota\Http\Services\Pipes;

use Closure;
use SchoolAid\Nadota\Http\DataTransferObjects\IndexRequestDTO;
use SchoolAid\Nadota\Http\Fields\Field;

class ApplySortingPipe
{
    public function handle(IndexRequestDTO $data, Closure $next)
    {
        $sortField = $data->request->input('sortField') ?? null;
        $sortDirection = $data->request->input('sortDirection') ?? 'desc';
        $fields = $data->getFields();

        if (!$sortField) {
            $this->applyDefaultSort($data, $fields);
            return $next($data);
        }

        /** @var Field $field */
        $field = $fields->first(fn($field) => $field->key() === $sortField);

        if (!$field) {
            $this->applyDefaultSort($data, $fields);
            return $next($data);
        }

        if ($field->isRelationship()) {
            $data->query = $field->applySorting($data->query, $sortDirection, $data->modelInstance);
        } else {
            $attribute = $field->getAttribute();
            $data->query->orderBy($attribute, $sortDirection);
        }

        return $next($data);
    }

    protected function applyDefaultSort(IndexRequestDTO $data, $fields): void
    {
        $defaultSort = $data->resource->getDefaultSort();

        if (empty($defaultSort)) {
            return;
        }

        $sortField = $defaultSort['field'] ?? null;
        $sortDirection = $defaultSort['direction'] ?? 'desc';

        if (!$sortField) {
            return;
        }

        /** @var Field $field */
        $field = $fields->first(fn($field) => $field->key() === $sortField);

        if ($field) {
            if ($field->isRelationship()) {
                $data->query = $field->applySorting($data->query, $sortDirection, $data->modelInstance);
            } else {
                $data->query->orderBy($field->getAttribute(), $sortDirection);
            }
        }
    }
}
