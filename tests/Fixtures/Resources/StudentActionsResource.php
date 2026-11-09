<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Actions\ActivateStudents;
use SchoolAid\Nadota\Tests\Fixtures\Actions\ArchiveStudents;
use SchoolAid\Nadota\Tests\Fixtures\Actions\ReplyWithResponseType;
use SchoolAid\Nadota\Tests\Fixtures\Actions\SendGuardianEmail;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Route key: "student-actions".
 *
 * Students with a richer action set for the action HTTP tests:
 * - activate-students, restricted with canRun() to students who accepted the terms;
 * - archive-students, detail only, with a required "reason" field;
 * - send-guardian-emails, which always throws;
 * - reply-with-response-types, standalone, answering with any ActionResponse type.
 */
class StudentActionsResource extends Resource
{
    public string $model = Student::class;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name'),
        ];
    }

    public function actions(NadotaRequest $request): array
    {
        return [
            ActivateStudents::make()->canRun(fn (NadotaRequest $request, $model) => (bool) $model->accept_terms),
            new ArchiveStudents(),
            new SendGuardianEmail(),
            new ReplyWithResponseType(),
        ];
    }
}
