<?php

use SchoolAid\Nadota\Http\Fields\Date;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;

/**
 * Flags and keys only. Which Filter class each field type produces
 * (FilterableTrait::filters()) is covered by the filter tests.
 */

it('is not filterable by default', function () {
    $field = Input::make('Name', 'name');

    expect($field->isFilterable())->toBeFalse()
        ->and($field->isFilterableRange())->toBeFalse()
        ->and($field->filters())->toBe([]);
});

it('becomes filterable as a single value', function () {
    $field = Number::make('Age', 'age')->filterable();

    expect($field->isFilterable())->toBeTrue()
        ->and($field->isFilterableRange())->toBeFalse()
        ->and($field->getFilterKeys())->toBe(['value' => 'age']);
});

it('becomes filterable as a range, which implies filterable', function () {
    $field = Date::make('Birth Date', 'birth_date')->filterableRange();

    expect($field->isFilterable())->toBeTrue()
        ->and($field->isFilterableRange())->toBeTrue()
        ->and($field->getFilterKeys())->toBe(['from' => 'birth_date_from', 'to' => 'birth_date_to']);
});

it('keeps the range when filterable() is chained after filterableRange()', function () {
    $field = Number::make('Age', 'age')->filterableRange()->filterable();

    expect($field->isFilterableRange())->toBeTrue();
});

// Whether getFilterKeys() should follow key() instead of the attribute is FLT-028
// (tested with the filters).

it('serialises the flags and keys for the frontend', function () {
    $array = Number::make('Age', 'age')->filterableRange()->toArray(createNadotaRequest());

    expect($array['filterable'])->toBeTrue()
        ->and($array['filterableRange'])->toBeTrue()
        ->and($array['filterKeys'])->toBe(['from' => 'age_from', 'to' => 'age_to']);
});

it('serialises the single-value keys even when not filterable', function () {
    // The frontend gets filterKeys for every field; only "filterable" tells them apart.
    $array = Input::make('Name', 'name')->toArray(createNadotaRequest());

    expect($array['filterable'])->toBeFalse()
        ->and($array['filterKeys'])->toBe(['value' => 'name']);
});
