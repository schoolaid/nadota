<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

trait BuildsSelectColumns
{
    /**
     * Get columns for SELECT clause in queries.
     * Each field defines its own columns via getColumnsForSelect().
     */
    public function getSelectColumns($request): array
    {
        $columns = collect($this->fields($request))
            ->filter(fn($field) => $field->isAppliedInShowQuery())
            ->flatMap(fn($field) => $field->getColumnsForSelect($this->model))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Always include the primary key
        return array_unique([...$columns, $this::$attributeKey]);
    }
}
