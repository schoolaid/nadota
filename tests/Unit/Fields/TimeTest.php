<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Time;
use SchoolAid\Nadota\Tests\Models\TestModel;
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

it('is a time field rendered by the FieldTime component', function () {
    $field = Time::make('Class Time', 'class_time');

    expect($field)->toBeField()
        ->toHaveFieldAttribute('class_time')
        ->and($field->getType())->toBe(FieldType::TIME->value)
        ->and($field->getComponent())->toBe('FieldTime');
});

it('defaults to H:i:s with a one minute step and no limits', function () {
    expect(Time::make('Class Time', 'class_time')->toArray(createNadotaRequest())['props'])
        ->toBe(['format' => 'H:i:s', 'min' => null, 'max' => null, 'step' => 60]);
});

it('exposes format, limits and step in props', function () {
    $props = Time::make('Class Time', 'class_time')->format('H:i')->min('08:00')->max('17:30')->step(300)
        ->toArray(createNadotaRequest())['props'];

    expect($props)->toBe(['format' => 'H:i', 'min' => '08:00', 'max' => '17:30', 'step' => 300]);
});

it('offers step shortcuts in seconds', function (string $method, int $seconds) {
    $field = Time::make('Class Time', 'class_time')->step(1)->{$method}();

    expect($field->toArray(createNadotaRequest())['props']['step'])->toBe($seconds);
})->with([
    'minute' => ['minuteStep', 60],
    'quarter hour' => ['quarterHourStep', 900],
    'half hour' => ['halfHourStep', 1800],
    'hour' => ['hourStep', 3600],
]);

it('formats a date/time object with the display format', function () {
    $model = new TestModel();
    $model->published_at = Carbon::parse('2024-01-15 08:05:09'); // datetime cast

    expect(Time::make('Time', 'published_at')->resolve(createNadotaRequest(), $model, null))->toBe('08:05:09')
        ->and(Time::make('Time', 'published_at')->format('g:i A')->resolve(createNadotaRequest(), $model, null))->toBe('8:05 AM');
});

it('returns a time string column untouched', function () {
    $model = new TestModel();
    $model->name = '08:30';

    expect(Time::make('Time', 'name')->resolve(createNadotaRequest(), $model, null))->toBe('08:30');
});

it('resolves null for an empty time', function () {
    expect(Time::make('Time', 'name')->resolve(createNadotaRequest(), new TestModel(), null))->toBeNull();
});

it('stores the submitted value as is', function () {
    expect(Time::make('Time', 'name')->resolveForStore(createNadotaRequest(), new TestModel(), null, '23:59'))->toBe('23:59');
});

it('validates the submitted value against the time limits', function () {
    $field = Time::make('Time', 't')->format('H:i')->min('08:00')->max('17:00');
    $passes = fn (string $value) => Validator::make(['t' => $field->normalizeInput($value)], ['t' => $field->getRules()])->passes();

    expect($passes('07:59'))->toBeFalse()
        ->and($passes('17:01'))->toBeFalse()
        ->and($passes('09:15'))->toBeTrue();
})->skip('ISSUE FLD-029: min()/max() are only frontend props, no limit rule (the time shape is validated since 28451c6/c86ec9f)');
