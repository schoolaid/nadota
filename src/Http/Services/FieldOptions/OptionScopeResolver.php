<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions;

use Illuminate\Database\Eloquent\Builder;
use SchoolAid\Nadota\Http\Fields\Field;

/**
 * Applies a field's declared option scopes to its options query.
 *
 * Only scopes declared on the server are applied, and the column always comes
 * from that declaration: the request supplies the value and nothing else.
 */
class OptionScopeResolver
{
    /**
     * @param mixed $values Raw scope values from the request (scope[...]). Request input is
     *                      not guaranteed to be an array (e.g. a plain `?scope=abc` query
     *                      string arrives as a string), so this is intentionally untyped and
     *                      coerced below rather than declared as array.
     * @return Builder|null Null when a strict scope has no value, meaning the query is
     *                      unsatisfiable and must not be executed as it stands. When null is
     *                      returned, the passed-in $query builder has still been mutated in
     *                      place by any scopes applied before the missing one was reached; it
     *                      is not restored to its pre-call state.
     */
    public function apply(Builder $query, Field $field, mixed $values): ?Builder
    {
        if (! $field->hasOptionScopes()) {
            return $query;
        }

        // Request input is not guaranteed to be an array (e.g. `?scope=abc`). Treat
        // anything else as "no scope values supplied" instead of letting it reach
        // whereIn()/isset() below, which would throw a TypeError for a non-array.
        if (! is_array($values)) {
            $values = [];
        }

        foreach ($field->getOptionScopes() as $key => $scope) {
            $value = $values[$key] ?? null;

            if ($this->isMissing($value)) {
                if ($scope->optional) {
                    continue;
                }

                return null;
            }

            if ($scope->usesCallback()) {
                $query = call_user_func($scope->callback, $query, $value) ?? $query;
                continue;
            }

            if (is_array($value)) {
                // Only scalar members are safe to pass to whereIn(); a nested array
                // (e.g. scope[grade][a][b]=1) would otherwise reach the query builder
                // and fail at the driver. Drop non-scalar members rather than the
                // whole value, so a partially-valid list still narrows the query.
                $scalarValues = array_filter($value, 'is_scalar');

                if ($scalarValues === []) {
                    if ($scope->optional) {
                        continue;
                    }

                    return null;
                }

                $query->whereIn($scope->column, $scalarValues);
                continue;
            }

            $query->where($scope->column, '=', $value);
        }

        return $query;
    }

    /**
     * A scope value is missing when it is null, an empty string, an empty array,
     * or the literal string "null" that query strings produce.
     */
    protected function isMissing(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || $value === 'null';
    }
}
