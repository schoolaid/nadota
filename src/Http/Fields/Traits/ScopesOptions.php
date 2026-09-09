<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use Closure;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Http\Fields\DataTransferObjects\OptionScopeDTO;

/**
 * Constrains a field's options query with the value of another field
 * in the same form.
 *
 * The observed field does not need to be persisted: a Lookup field, or any
 * field marked ->virtual(), works as the source of the value.
 */
trait ScopesOptions
{
    /**
     * Declared option scopes, keyed by the observed field name.
     *
     * @var array<string, OptionScopeDTO>
     */
    protected array $optionScopes = [];

    /**
     * Constrain this field's options by the value of another field.
     *
     * @param string $field The observed field's key in the form.
     * @param string|Closure|null $target A column name on the related model, or
     *        fn(Builder $query, mixed $value): Builder. Defaults to the observed
     *        field name in snake_case with an `_id` suffix.
     * @param bool $optional When false (the default), an observed field with no
     *        value makes the options query return nothing.
     * @return static
     */
    public function scopedBy(string $field, string|Closure|null $target = null, bool $optional = false): static
    {
        $this->optionScopes[$field] = new OptionScopeDTO(
            field: $field,
            column: $target instanceof Closure ? null : ($target ?? Str::snake($field) . '_id'),
            callback: $target instanceof Closure ? $target : null,
            optional: $optional,
        );

        return $this;
    }

    /**
     * @return array<string, OptionScopeDTO>
     */
    public function getOptionScopes(): array
    {
        return $this->optionScopes;
    }

    public function hasOptionScopes(): bool
    {
        return $this->optionScopes !== [];
    }
}
