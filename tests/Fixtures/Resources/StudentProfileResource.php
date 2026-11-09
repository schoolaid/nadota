<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\StudentProfile;

/** Route key: "student-profile". */
class StudentProfileResource extends Resource
{
    public string $model = StudentProfile::class;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Nickname', 'nickname'),
            Textarea::make('About', 'about'),
            BelongsTo::make('Student', 'student', StudentResource::class),
        ];
    }
}
