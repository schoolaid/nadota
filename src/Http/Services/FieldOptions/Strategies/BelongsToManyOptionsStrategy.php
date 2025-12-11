<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions\Strategies;

use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;

class BelongsToManyOptionsStrategy extends AbstractOptionsStrategy
{
    /**
     * Check if this strategy can handle the given field.
     *
     * @param Field $field
     * @return bool
     */
    public function canHandle(Field $field): bool
    {
        return $field instanceof BelongsToMany;
    }

    /**
     * Get the priority of this strategy.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 80; // Below BelongsTo but above Default
    }
}
