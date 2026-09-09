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
     * @param array<string, mixed> $values Raw scope values from the request (scope[...]).
     * @return Builder|null Null when a strict scope has no value, meaning the query is
     *                      unsatisfiable and must not be executed as it stands.
     */
    public function apply(Builder $query, Field $field, array $values): ?Builder
    {
        if (! $field->hasOptionScopes()) {
            return $query;
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

            is_array($value)
                ? $query->whereIn($scope->column, $value)
                : $query->where($scope->column, '=', $value);
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
