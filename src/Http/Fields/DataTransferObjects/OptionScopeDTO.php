<?php

namespace SchoolAid\Nadota\Http\Fields\DataTransferObjects;

use Closure;

/**
 * A single option-scope declaration: how one field's value constrains
 * another field's options query.
 */
class OptionScopeDTO
{
    public function __construct(
        public string $field,
        public ?string $column = null,
        public ?Closure $callback = null,
        public bool $optional = false,
    ) {
    }

    /**
     * Whether this scope is applied through a closure instead of a column.
     */
    public function usesCallback(): bool
    {
        return $this->callback !== null;
    }

    /**
     * The frontend-facing shape. The column is deliberately omitted:
     * it is server-side configuration and must never reach the client.
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'optional' => $this->optional,
        ];
    }
}
