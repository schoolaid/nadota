<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Actions;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Actions\ActionResponse;
use SchoolAid\Nadota\Http\Actions\DestructiveAction;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/** Key: "purge-inactive-students". Standalone destructive action, index only. */
class PurgeInactiveStudents extends DestructiveAction
{
    protected ?string $name = 'Purge Inactive Students';
    protected bool $standalone = true;
    protected bool $showOnDetail = false;

    public function handle(Collection $models, NadotaRequest $request): mixed
    {
        $count = Student::query()->where('is_active', false)->delete();

        return ActionResponse::danger("Purged {$count} students");
    }
}
