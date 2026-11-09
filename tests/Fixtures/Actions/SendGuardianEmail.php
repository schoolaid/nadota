<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Actions;

use Illuminate\Support\Collection;
use RuntimeException;
use SchoolAid\Nadota\Http\Actions\Action;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/** Key: "send-guardian-emails". Always fails, like an action whose mail server is down. */
class SendGuardianEmail extends Action
{
    public const FAILURE = 'SMTP connection refused by mail.internal:2525';

    public function handle(Collection $models, NadotaRequest $request): mixed
    {
        throw new RuntimeException(self::FAILURE);
    }
}
