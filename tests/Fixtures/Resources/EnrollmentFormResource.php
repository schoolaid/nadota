<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Lookup;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\StudentProfile;

/**
 * Route key: "enrollment-form".
 *
 * A form whose student picker is narrowed by a grade lookup: the Lookup
 * "grade" is virtual, and the BelongsTo "student" declares an optional
 * scopedBy('grade', 'grade_id'), so its options honour scope[grade] when sent
 * and list every student otherwise. The picker targets ScopedStudentResource,
 * whose getQuery() hides archived students.
 *
 * Export is disabled on this resource.
 */
class EnrollmentFormResource extends Resource
{
    public string $model = StudentProfile::class;

    protected bool $exportEnabled = false;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Nickname', 'nickname'),
            Lookup::make('Grade', 'grade')->resource(GradeResource::class),
            BelongsTo::make('Student', 'student', ScopedStudentResource::class)
                ->scopedBy('grade', 'grade_id', true),
        ];
    }
}
