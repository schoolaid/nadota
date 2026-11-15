<?php

use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\Time;

/** @return array<int, string> */
function timeErrors(Time $field, mixed $value): array
{
    return Validator::make(['t' => $value], ['t' => $field->getRulesFor(false)])->errors()->get('t');
}

// Without a rule, '25:99' or 'abc' reached the TIME column and failed there (500).
it('rejects what is not a time', function (string $value) {
    expect(timeErrors(Time::make('Start', 't')->required(), $value))->not->toBe([]);
})->with(['25:99', 'abc', '8 am', '08:00:00:00']);

it('accepts hours with or without seconds', function (string $value) {
    expect(timeErrors(Time::make('Start', 't')->required(), $value))->toBe([]);
})->with(['08:00', '23:59', '08:00:00']);

it('reports only required when empty, and lets an optional time be empty', function () {
    expect(timeErrors(Time::make('Start', 't')->required(), null))->toHaveCount(1)
        ->and(timeErrors(Time::make('Start', 't'), null))->toBe([])
        ->and(Time::make('Start', 't')->required()->getRules())->toBe(['required', 'date_format:H:i,H:i:s']);
});

it('keeps a date_format the field declares', function () {
    expect(Time::make('Start', 't')->rules(['date_format:H:i'])->getRules())->toBe(['nullable', 'date_format:H:i']);
});
