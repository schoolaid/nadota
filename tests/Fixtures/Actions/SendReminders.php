<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Actions;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Actions\Action;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/** Plural twin of SendReminder; both currently resolve to the key "send-reminders" (AUD-023). */
class SendReminders extends Action
{
    public function handle(Collection $models, NadotaRequest $request): mixed
    {
        return Action::message('plural');
    }
}
