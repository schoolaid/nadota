<?php

use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\Time;

/** @return array<int, string> */
function timeErrors(Time $field, mixed $value): array
{
    // Like the persist service: normalizeInput() runs before validation
    return Validator::make(['t' => $field->normalizeInput($value)], ['t' => $field->getRulesFor(false)])->errors()->get('t');
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
        ->and(Time::make('Start', 't')->required()->getRules())->toBe(['required', 'date_format:H:i:s']);
});

it('keeps a date_format the field declares', function () {
    expect(Time::make('Start', 't')->rules(['date_format:H:i'])->getRules())->toBe(['nullable', 'date_format:H:i']);
});

/*
 * With `date_format:H:i,H:i:s` Laravel's after/before took only the first format (H:i) to read the
 * other field: '08:00:00' (what the form sends back untouched) could not be compared and an app rule
 * like `after:start_time` failed every edit. Time now normalizes to H:i:s before validating and
 * checks that single format.
 */
function normalizedTimeErrors(array $data, array $rules): array
{
    $field = Time::make('Start', 't');
    $data = array_map(fn ($v) => $field->normalizeInput($v), $data);

    return Validator::make($data, $rules)->errors()->all();
}

it('normalizes hours and minutes to H:i:s', function (string $in, string $out) {
    expect(Time::make('Start', 't')->normalizeInput($in))->toBe($out);
})->with([['08:00', '08:00:00'], ['8:00', '08:00:00'], ['08:00:00', '08:00:00'], ['8:05:09', '08:05:09']]);

it('lets an app compare two times with after, with or without seconds', function (string $start, string $end) {
    $field = Time::make('End', 'end')->required()->rules(['after:start']);

    expect(normalizedTimeErrors(['start' => $start, 'end' => $end], ['end' => $field->getRulesFor(false)]))->toBe([]);
})->with([['08:00:00', '09:00'], ['08:00', '09:00:00'], ['8:00', '9:30']]);

it('keeps a value it cannot read and a declared date_format untouched', function () {
    expect(Time::make('Start', 't')->normalizeInput('25:99x'))->toBe('25:99x')
        ->and(Time::make('Start', 't')->rules(['date_format:H:i'])->normalizeInput('08:00'))->toBe('08:00');
});
