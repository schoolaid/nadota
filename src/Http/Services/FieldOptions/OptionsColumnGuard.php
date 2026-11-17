<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Which columns a client may filter or sort options on.
 *
 * `filters[...]` and `orderBy` arrive from the request. Without a limit they are an oracle on
 * columns the API never shows: `filters[password][operator]=like&filters[password][value]=a%`
 * tells whether a hash starts with "a", and sorting by it leaks its order.
 *
 * - Filters: the target resource's getAllowedOptionsFilters() when it declares one; otherwise every
 *   real column of the table except the ones the model hides ($hidden).
 * - Sorting: every real column of the table except the hidden ones.
 */
final class OptionsColumnGuard
{
    /**
     * Allowed filter columns, keyed by column, with their declared config (e.g. ['operator' => 'like']).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function filterable(Builder $query, ?object $resource = null): array
    {
        if ($resource && method_exists($resource, 'getAllowedOptionsFilters')) {
            $declared = $resource->getAllowedOptionsFilters();

            if ($declared !== null) {
                return self::normalize($declared);
            }
        }

        return array_fill_keys(self::exposedColumns($query), []);
    }

    public static function isSortable(Builder $query, mixed $column): bool
    {
        return is_string($column) && in_array($column, self::exposedColumns($query), true);
    }

    /**
     * The table's columns minus the attributes the model hides from serialization.
     *
     * @return array<int, string>
     */
    public static function exposedColumns(Builder $query): array
    {
        $model = $query->getModel();
        $columns = Schema::connection($model->getConnectionName())->getColumnListing($model->getTable());

        return array_values(array_diff($columns, $model->getHidden()));
    }

    /**
     * ['field', 'other' => ['operator' => 'like']] → ['field' => [], 'other' => ['operator' => 'like']]
     *
     * @return array<string, array<string, mixed>>
     */
    private static function normalize(array $declared): array
    {
        $normalized = [];

        foreach ($declared as $key => $value) {
            if (is_int($key)) {
                $normalized[(string) $value] = [];
            } else {
                $normalized[$key] = is_array($value) ? $value : [];
            }
        }

        return $normalized;
    }
}
