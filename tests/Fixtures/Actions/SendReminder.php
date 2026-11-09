<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Actions;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Actions\Action;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Singular class name, no $name, one required field. Used by the action audit
 * tests (AUD-021, AUD-023): its key should be "send-reminder" and its
 * "subject" rules should be enforced before handle() runs.
 *
 * Records the subject each run received in static::$received (reset it in tests).
 */
class SendReminder extends Action
{
    /** @var array<int, mixed> */
    public static array $received = [];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Subject', 'subject')->rules(['required', 'string', 'max:5']),
        ];
    }

    public function handle(Collection $models, NadotaRequest $request): mixed
    {
        static::$received[] = $request->input('subject');

        return null;
    }
}
