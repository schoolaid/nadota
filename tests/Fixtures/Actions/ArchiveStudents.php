<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Actions;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Actions\Action;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Key: "archive-students". Detail-only action with a required "reason" field
 * (min 5 characters). Sets status = 'archived' and returns nothing, so the
 * service answers with its default success message.
 */
class ArchiveStudents extends Action
{
    protected bool $showOnIndex = false;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Reason', 'reason')->required()->rules(['string', 'min:5']),
        ];
    }

    public function handle(Collection $models, NadotaRequest $request): mixed
    {
        $models->each(fn ($model) => $model->update(['status' => 'archived']));

        return null;
    }
}
