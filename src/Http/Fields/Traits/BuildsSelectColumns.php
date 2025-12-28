<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use Illuminate\Support\Collection;

trait BuildsSelectColumns
{
    /**
     * Get columns for SELECT clause in queries.
     * Each field defines its own columns via getColumnsForSelect().
     *
     * @param mixed $request
     * @param Collection|null $fields Optional pre-filtered fields collection
     * @return array
     */
    public function getSelectColumns($request, ?Collection $fields = null): array
    {
        $fields = $fields ?? $this->flattenFields($request)
            ->filter(fn($field) => $field->isAppliedInShowQuery());

        $columns = $fields
            ->flatMap(fn($field) => $field->getColumnsForSelect($this->model))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Always include the primary key
        return array_unique([...$columns, $this::$attributeKey]);
    }
}
