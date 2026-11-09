<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Route key: "locked-student".
 *
 * Disables delete, restore and force delete for every record through the
 * resource flags ($canDelete / $canRestore / $canForceDelete), independently
 * of any policy.
 */
class LockedStudentResource extends Resource
{
    public string $model = Student::class;

    protected bool $canDelete = false;
    protected bool $canRestore = false;
    protected bool $canForceDelete = false;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->required(),
        ];
    }
}
