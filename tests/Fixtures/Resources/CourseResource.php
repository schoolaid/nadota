<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;

/** Route key: "course". Covers BelongsToMany from the inverse side. */
class CourseResource extends Resource
{
    public string $model = Course::class;

    protected array $searchableAttributes = ['name', 'code'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->required()->sortable(),
            Input::make('Code', 'code')->sortable(),
            Number::make('Credits', 'credits')->sortable()->filterable(),
            BelongsToMany::make('Students', 'students', StudentResource::class),
        ];
    }
}
