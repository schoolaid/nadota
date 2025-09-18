<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

trait ManagesFieldSelection
{
    /**
     * Get attributes for SELECT clause in queries.
     */
    public function getAttributesForSelect($request): array
    {
        $fields = collect($this->fields($request));

        // Get regular field attributes
        $regularAttributes = $this->getRegularFieldAttributes($fields);

        // Get morph type attributes
        $morphAttributes = $this->getMorphTypeAttributes($fields);

        // Always include the primary key
        return array_unique([
            ...$regularAttributes,
            ...$morphAttributes,
            $this::$attributeKey
        ]);
    }

    /**
     * Get attributes from regular (non-relation) fields.
     */
    protected function getRegularFieldAttributes($fields): array
    {
        return $fields
            ->filter(fn($field) =>
                $field->isAppliedInShowQuery() &&
                $field->getType() != FieldType::HAS_MANY->value
            )
            ->map(fn($field) => $field->getAttribute())
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get morph type attributes for polymorphic relations.
     */
    protected function getMorphTypeAttributes($fields): array
    {
        return $fields
            ->filter(fn($field) => $field->getType() == FieldType::MORPH_TO->value)
            ->map(fn($field) => $field->getMorphTypeAttribute())
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }
}