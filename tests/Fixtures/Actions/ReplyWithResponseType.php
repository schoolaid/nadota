<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Actions;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Actions\Action;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Key: "reply-with-response-types". Standalone action that answers with the
 * ActionResponse named in the request's "reply" input (message, danger,
 * redirect, download, openInNewTab, data), so every response type can be
 * checked over HTTP with one fixture. The message carries the selected count.
 */
class ReplyWithResponseType extends Action
{
    protected bool $standalone = true;

    public function handle(Collection $models, NadotaRequest $request): mixed
    {
        return match ($request->input('reply')) {
            'danger' => Action::danger('Nothing to do'),
            'redirect' => Action::redirect('/resources/student/1'),
            'download' => Action::download('/storage/report-cards.zip', 'report-cards.zip'),
            'openInNewTab' => Action::openInNewTab('https://example.test/transcript'),
            'data' => Action::message('Done')->withData(['processed' => $models->count()]),
            default => Action::message("Selected {$models->count()}"),
        };
    }
}
