<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Http\StudentSummaryJsonResource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Route key: "custom-response-student".
 *
 * Replaces the default show and edit envelopes with a plain Laravel
 * JsonResource ($showResponseResource / $editResponseResource).
 */
class CustomResponseStudentResource extends Resource
{
    public string $model = Student::class;

    protected ?string $showResponseResource = StudentSummaryJsonResource::class;
    protected ?string $editResponseResource = StudentSummaryJsonResource::class;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->required(),
            Input::make('Email', 'email'),
        ];
    }
}
