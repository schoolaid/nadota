<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions\Strategies;

use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\MorphToMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphedByMany;

class MorphToManyOptionsStrategy extends AbstractOptionsStrategy
{
    /**
     * Check if this strategy can handle the given field.
     *
     * @param Field $field
     * @return bool
     */
    public function canHandle(Field $field): bool
    {
        return $field instanceof MorphToMany || $field instanceof MorphedByMany;
    }

    /**
     * Get the priority of this strategy.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 75; // Below BelongsToMany but above Default
    }
}
