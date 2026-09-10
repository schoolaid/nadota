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
 *
 * Requires DependsOnTrait on the same class: declaring a scope registers the
 * observed field as a dependency so the frontend re-fetches options when it
 * changes, and clears this field's value so a stale selection is not submitted.
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
        $scope = new OptionScopeDTO(
            field: $field,
            column: $target instanceof Closure ? null : ($target ?? Str::snake($field) . '_id'),
            callback: $target instanceof Closure ? $target : null,
            optional: $optional,
        );

        $this->optionScopes[$field] = $scope;

        $this->dependsOn($field);
        $this->clearOnDependencyChange();
        $this->getDependencyDTO()->addOptionScope($scope);

        return $this;
    }

    /**
     * @return array<string, OptionScopeDTO>
     */
    public function getOptionScopes(): array
    {
        return $this->optionScopes;
    }

    /**
     * Not to be confused with RelationshipTrait::hasOptionsScope() (singular "Scope"):
     * this one reports whether any scopedBy() declarations exist, while
     * hasOptionsScope() reports whether a single closure was set via
     * optionsScope(callable).
     */
    public function hasOptionScopes(): bool
    {
        return $this->optionScopes !== [];
    }
}
