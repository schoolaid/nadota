<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

trait ManagesRelationLoading
{
    /**
     * Get relation constraints for eager loading.
     */
    public function getRelationAttributesForSelect($request): array
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
     */
    protected function buildRelationConstraint($field, $request): array
    {
        $relationName = $field->getRelation();

        // Handle fields with custom selection logic (like HasMany)
        if (method_exists($field, 'getFieldsForSelect')) {
            return $this->handleCustomFieldSelection($field, $relationName, $request);
        }

        // Handle regular relations with resource
        if ($field->getResource()) {
            return $this->handleResourceBasedSelection($field, $relationName, $request);
        }

        // Default: load relation without constraints
        return [$relationName];
    }

    /**
     * Handle fields with custom getFieldsForSelect method.
     */
    protected function handleCustomFieldSelection($field, string $relationName, $request): array
    {
        $fields = $field->getFieldsForSelect($request);

        if ($fields !== null) {
            return [$relationName => fn($query) => $query->select($fields)];
        }

        // null means select all columns
        return [$relationName];
    }

    /**
     * Handle relations with associated resources.
     */
    protected function handleResourceBasedSelection($field, string $relationName, $request): array
    {
        $resourceClass = $field->getResource();
        $resource = new $resourceClass;
        $fields = $resource->getAttributesForSelect($request);

        return [$relationName => fn($query) => $query->select($fields)];
    }
}