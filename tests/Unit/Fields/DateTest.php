<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\Date;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Tests\Models\TestModel;

function dateFieldModel(mixed $value): TestModel
{
    $model = new TestModel();
    $model->published_at = $value; // cast to datetime on TestModel

    return $model;
}

it('is a date field rendered by the FieldDate component', function () {
    $field = Date::make('Birth Date', 'birth_date');

    expect($field)->toBeField()
        ->toHaveFieldAttribute('birth_date')
        ->and($field->getType())->toBe(FieldType::DATE->value)
        ->and($field->getComponent())->toBe('FieldDate');
});

it('exposes the display format and the min/max dates as Y-m-d', function () {
    $props = Date::make('Birth Date', 'birth_date')
        ->format('d/m/Y')
        ->min(Carbon::parse('2000-01-31 13:45:00'))
        ->max(new DateTimeImmutable('2030-12-01'))
        ->toArray(createNadotaRequest())['props'];

    expect($props)->toBe(['format' => 'd/m/Y', 'min' => '2000-01-31', 'max' => '2030-12-01']);
});

it('defaults to the Y-m-d format without limits', function () {
    expect(Date::make('Birth Date', 'birth_date')->toArray(createNadotaRequest())['props'])
        ->toBe(['format' => 'Y-m-d', 'min' => null, 'max' => null]);
});

it('formats a date cast attribute with the display format', function () {
    $model = dateFieldModel('2014-03-05 18:30:00');

    expect(Date::make('Date', 'published_at')->resolve(createNadotaRequest(), $model, null))->toBe('2014-03-05')
        ->and(Date::make('Date', 'published_at')->format('d/m/Y')->resolve(createNadotaRequest(), $model, null))->toBe('05/03/2014');
});

it('returns a plain string value untouched', function () {
    $model = new TestModel();
    $model->name = '2014-03-05';

    expect(Date::make('Date', 'name')->format('d/m/Y')->resolve(createNadotaRequest(), $model, null))->toBe('2014-03-05');
});

it('resolves null for an empty date', function () {
    expect(Date::make('Date', 'published_at')->resolve(createNadotaRequest(), dateFieldModel(null), null))->toBeNull();
});

it('stores empty strings as null so a date can be cleared', function (mixed $input) {
    $field = Date::make('Date', 'published_at');

    expect($field->resolveForStore(createNadotaRequest(), new TestModel(), null, $input))->toBeNull()
        ->and($field->resolveForUpdate(createNadotaRequest(), new TestModel(), null, $input))->toBeNull();
})->with(['empty string' => [''], 'null' => [null]]);

it('stores a submitted date as is', function () {
    $field = Date::make('Date', 'published_at');

    expect($field->resolveForStore(createNadotaRequest(), new TestModel(), null, '2024-02-29'))->toBe('2024-02-29')
        ->and($field->resolveForUpdate(createNadotaRequest(), new TestModel(), null, '2024-02-29'))->toBe('2024-02-29');
});

it('keeps the submitted date even when a default exists', function () {
    $field = Date::make('Date', 'published_at')->default('2020-01-01');

    expect($field->resolveForStore(createNadotaRequest(), new TestModel(), null, '2021-05-05'))->toBe('2021-05-05');
});

it('shows the default when the model has no date', function () {
    $field = Date::make('Date', 'published_at')->default('2020-01-01');

    expect($field->resolve(createNadotaRequest(), dateFieldModel(null), null))->toBe('2020-01-01');
})->skip('ISSUE FLD-014: Date::resolve() overrides Field::resolve() and ignores default() and displayUsing()');

it('uses the displayUsing callback', function () {
    $field = Date::make('Date', 'published_at')->displayUsing(fn ($model) => 'custom');

    expect($field->resolve(createNadotaRequest(), dateFieldModel('2014-03-05'), null))->toBe('custom');
})->skip('ISSUE FLD-014: Date::resolve() overrides Field::resolve() and ignores default() and displayUsing()');

it('validates the submitted value as a date within min and max', function () {
    $field = Date::make('Date', 'published_at')
        ->min(Carbon::parse('2020-01-01'))
        ->max(Carbon::parse('2020-12-31'));

    $rules = ['published_at' => $field->getRules()];

    expect(Validator::make(['published_at' => 'not a date'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['published_at' => '2019-12-31'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['published_at' => '2020-06-15'], $rules)->passes())->toBeTrue();
})->skip('ISSUE FLD-013: Date adds no date/after_or_equal/before_or_equal rule, min()/max() are only frontend props');
