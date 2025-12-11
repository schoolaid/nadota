<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use Illuminate\Support\Collection;

trait ManagesRelationLoading
{
    /**
     * Get relations with constraints for eager loading (with).
     *
     * @param mixed $request
     * @param Collection|null $fields Optional pre-filtered fields collection
     * @return array
     */
    public function getEagerLoadRelations($request, ?Collection $fields = null): array
    {
        $fields = $fields ?? collect($this->fields($request))
            ->filter(fn($field) => $field->isAppliedInShowQuery());

        // Always filter out paginated fields - they should be loaded via pagination endpoint
        $fields = $fields->filter(fn($field) => !$this->isFieldPaginated($field));

        return $fields
            ->filter(fn($field) => $field->isRelationship())
            ->mapWithKeys(function ($field) use ($request) {
                return $this->buildRelationConstraint($field, $request);
            })
            ->toArray();
    }

    /**
     * Check if a field is configured for pagination.
     * Paginated fields should not be eager loaded as they will be fetched via the pagination endpoint.
     *
     * @param mixed $field
     * @return bool
     */
    protected function isFieldPaginated($field): bool
    {
        return method_exists($field, 'isPaginated') && $field->isPaginated();
    }

    /**
     * Build constraint for a single relation field.
     * Each field defines its columns via getRelatedColumns().
     * For pivot relations (BelongsToMany, MorphToMany, MorphedByMany), also applies withPivot().
     */
    protected function buildRelationConstraint($field, $request): array
    {
        $relationName = $field->getRelation();
        $columns = $field->getRelatedColumns($request);
        $pivotColumns = $this->getPivotColumnsFromField($field);

        if ($columns !== null) {
            return [$relationName => function ($query) use ($columns, $pivotColumns) {
                // Qualify columns with table name to avoid ambiguity with pivot table columns
                $qualifiedColumns = $this->qualifyColumnsWithTable($query, $columns);
                $query->select($qualifiedColumns);

                // Apply pivot columns if the relation supports it
                if (!empty($pivotColumns)) {
                    $query->withPivot($pivotColumns);
                }
            }];
        }

        // null means select all columns, but still apply pivot if needed
        return [$relationName => function ($query) use ($pivotColumns) {
            if (!empty($pivotColumns)) {
                $query->withPivot($pivotColumns);
            }
        }];
    }

    /**
     * Qualify column names with the related table name.
     * This prevents ambiguity when pivot tables have columns with the same name.
     *
     * @param mixed $query The relation query
     * @param array $columns Column names to qualify
     * @return array Qualified column names
     */
    protected function qualifyColumnsWithTable($query, array $columns): array
    {
        $table = $query->getRelated()->getTable();

        return array_map(function ($column) use ($table) {
            // Skip if already qualified (contains a dot)
            if (str_contains($column, '.')) {
                return $column;
            }

            return "{$table}.{$column}";
        }, $columns);
    }

    /**
     * Get pivot columns from a field if it supports them.
     * Only BelongsToMany, MorphToMany, and MorphedByMany support pivot columns.
     *
     * @param mixed $field
     * @return array
     */
    protected function getPivotColumnsFromField($field): array
    {
        if (method_exists($field, 'hasPivotColumns') && $field->hasPivotColumns()) {
            return $field->getPivotColumns();
        }

        return [];
    }
}
