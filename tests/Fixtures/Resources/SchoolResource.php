<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Count;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasManyThrough;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;

/** Route key: "school". Covers HasMany, HasManyThrough and Count. */
class SchoolResource extends Resource
{
    public string $model = School::class;

    protected array $searchableAttributes = ['name', 'city'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->required()->sortable(),
            Input::make('City', 'city')->sortable()->filterable(),
            Count::make('Grades', 'grades'),
            HasMany::make('Grades', 'grades', GradeResource::class),
            HasManyThrough::make('Students', 'students', StudentResource::class),
        ];
    }
}
