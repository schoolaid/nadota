<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

trait ManagesRelationLoading
{
    /**
     * Get relations with constraints for eager loading (with).
     */
    public function getEagerLoadRelations($request): array
    {
        return collect($this->fields($request))
            ->filter(fn($field) =>
                $field->isAppliedInShowQuery() &&
                $field->isRelationship()
            )
            ->mapWithKeys(function ($field) use ($request) {
                return $this->buildRelationConstraint($field, $request);
            })
            ->toArray();
    }

    /**
     * Build constraint for a single relation field.
     * Each field defines its columns via getRelatedColumns().
     */
    protected function buildRelationConstraint($field, $request): array
    {
        $relationName = $field->getRelation();
        $columns = $field->getRelatedColumns($request);

        if ($columns !== null) {
            return [$relationName => fn($query) => $query->select($columns)];
        }

        // null means select all columns
        return [$relationName => fn($query) => $query];
    }
}
