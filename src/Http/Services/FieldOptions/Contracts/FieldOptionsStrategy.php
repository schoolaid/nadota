<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions\Contracts;

use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface FieldOptionsStrategy
{
    /**
     * Check if this strategy can handle the given field.
     *
     * @param Field $field
     * @return bool
     */
    public function canHandle(Field $field): bool;

    /**
     * Fetch options for the field.
     *
     * @param NadotaRequest $request
     * @param ResourceInterface $resource
     * @param Field $field
     * @param array $params Additional parameters
     * @return array
     */
    public function fetchOptions(
        NadotaRequest $request,
        ResourceInterface $resource,
        Field $field,
        array $params = []
    ): array;

    /**
     * Get the priority of this strategy (higher = checked first).
     *
     * @return int
     */
    public function getPriority(): int;
}