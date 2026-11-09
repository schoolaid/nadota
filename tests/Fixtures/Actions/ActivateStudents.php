<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Actions;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Actions\Action;
use SchoolAid\Nadota\Http\Actions\ActionResponse;
use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/** Key: "activate-students". Marks the selected students active; takes an optional reason field. */
class ActivateStudents extends Action
{
    protected ?string $name = 'Activate Students';

    public function fields(NadotaRequest $request): array
    {
        return [
            Textarea::make('Reason', 'reason'),
        ];
    }

    public function handle(Collection $models, NadotaRequest $request): mixed
    {
        $models->each(fn ($model) => $model->update(['is_active' => true]));

        return ActionResponse::message("Activated {$models->count()} students");
    }
}
