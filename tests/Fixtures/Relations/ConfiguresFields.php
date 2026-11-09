<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Lets a relation test declare a resource's fields (and optionally scope its
 * query) inline, so each test shows the exact relation configuration it
 * exercises while the resource still has a real class name and route key.
 *
 * Static state is per using class (trait statics are not shared). Tests that
 * call using()/scopeQueryUsing() must call reset() in afterEach().
 */
trait ConfiguresFields
{
    protected static ?Closure $fieldsUsing = null;

    protected static ?Closure $queryUsing = null;

    /**
     * @param Closure(NadotaRequest): array $fields
     */
    public static function using(Closure $fields): void
    {
        static::$fieldsUsing = $fields;
    }

    /**
     * @param Closure(Builder): mixed $scope
     */
    public static function scopeQueryUsing(Closure $scope): void
    {
        static::$queryUsing = $scope;
    }

    public static function reset(): void
    {
        static::$fieldsUsing = null;
        static::$queryUsing = null;
    }

    public function fields(NadotaRequest $request): array
    {
        return static::$fieldsUsing ? (static::$fieldsUsing)($request) : [];
    }

    public function getQuery(NadotaRequest $request, ?Model $modelInstance = null): Builder
    {
        $query = parent::getQuery($request, $modelInstance);

        if (static::$queryUsing) {
            (static::$queryUsing)($query);
        }

        return $query;
    }
}
