<?php

namespace SchoolAid\Nadota\Tests\Resources;

use Illuminate\Database\Eloquent\Builder;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\Tag;

/**
 * Tags as options of a BelongsToMany: `optionsQuery()` hides the ones named `hidden…`, like a
 * resource that only offers some records (e.g. a role list without the internal accounts).
 */
class TagResource extends Resource
{
    public string $model = Tag::class;

    protected array $searchableAttributes = ['name'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name'),
        ];
    }

    public function optionsQuery(Builder $query, NadotaRequest $request, array $params = []): Builder
    {
        return $query->where($query->qualifyColumn('name'), 'not like', 'hidden%');
    }
}
