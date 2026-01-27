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

        // Use array_flip for O(1) lookups instead of in_array
        $columnSet = [];
        
        foreach ($fields as $field) {
            $fieldColumns = $field->getColumnsForSelect($this->model);
            foreach ($fieldColumns as $column) {
                if ($column) { // Filter out null/empty values
                    $columnSet[$column] = true;
                }
            }
        }
        
        // Always include the primary key
        $columnSet[$this::$attributeKey] = true;
        
        return array_keys($columnSet);
    }
}
