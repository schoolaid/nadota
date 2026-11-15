<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasOneThrough;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;

/** Route key: "grade". Covers BelongsTo, HasMany, HasOneThrough, MorphMany (as a second morph parent). */
class GradeResource extends Resource
{
    public string $model = Grade::class;

    protected array $searchableAttributes = ['name'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->required()->sortable(),
            Number::make('Level', 'level')->sortable()->filterable(),
            BelongsTo::make('School', 'school', SchoolResource::class)->filterable(),
            HasMany::make('Students', 'students', StudentResource::class)->attachable(),
            HasOneThrough::make('First Student Profile', 'firstStudentProfile', StudentProfileResource::class),
            MorphMany::make('Notes', 'notes', NoteResource::class)->attachable(),
        ];
    }
}
