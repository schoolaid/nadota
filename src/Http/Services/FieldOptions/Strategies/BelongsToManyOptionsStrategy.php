<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions\Strategies;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Contracts\ResourceInterface;
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

    /**
     * Format results as options array.
     * Overrides parent to include pivot defaults when configured.
     *
     * @param Collection $results
     * @param Field $field
     * @param string $keyAttribute
     * @param ResourceInterface|null $resourceInstance
     * @return array
     */
    protected function formatResults(
        Collection $results,
        Field $field,
        string $keyAttribute,
        ?ResourceInterface $resourceInstance = null
    ): array {
        /** @var BelongsToMany $field */
        $hasPivotDefaults = $field->hasPivotDefaults();

        return $results->map(function ($item) use ($field, $keyAttribute, $resourceInstance, $hasPivotDefaults) {
            $option = [
                'value' => $item->{$keyAttribute},
                'label' => $this->resolveLabel($field, $item, $resourceInstance),
            ];

            // Include pivot defaults if configured
            if ($hasPivotDefaults) {
                $defaults = $field->resolvePivotDefaults($item);
                if ($defaults !== null) {
                    $option['pivotDefaults'] = $defaults;
                }
            }

            return $option;
        })->toArray();
    }
}
