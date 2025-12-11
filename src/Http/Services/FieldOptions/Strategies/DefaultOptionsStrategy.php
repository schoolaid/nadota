<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions\Strategies;

use SchoolAid\Nadota\Http\Fields\Field;

class DefaultOptionsStrategy extends AbstractOptionsStrategy
{
    /**
     * Check if this strategy can handle the given field.
     * This is the fallback strategy, so it handles any field with a resource.
     *
     * @param Field $field
     * @return bool
     */
    public function canHandle(Field $field): bool
    {
        // Handle any field that has a resource configured
        return $field->getResource() !== null;
    }

    /**
     * Get the priority of this strategy.
     * Lowest priority as this is the fallback.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 0;
    }
}
