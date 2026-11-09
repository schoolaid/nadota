<?php

use SchoolAid\Nadota\Http\Filters\SelectFilter;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

beforeEach(function () {
    FixtureSchema::create();
    Student::create(['name' => 'Ana', 'status' => 'draft']);
    Student::create(['name' => 'Bob', 'status' => 'enrolled']);
    Student::create(['name' => 'Cid', 'status' => 'graduated']);
    Student::create(['name' => 'Dee', 'status' => null]);
});

function selectFilterNames(SelectFilter $filter, mixed $value): array
{
    return $filter->apply(createNadotaRequest(), Student::query(), $value)
        ->orderBy('name')->pluck('name')->all();
}

// --- constructor defaults & serialisation -------------------------------------------------

it('is a FilterSelect "select" filter without options or translation by default', function () {
    $filter = new SelectFilter('Status', 'status');

    expect($filter->type)->toBe('select')
        ->and($filter->component())->toBe('FilterSelect')
        ->and($filter->key())->toBe('status')
        ->and($filter->isRange())->toBeFalse()
        ->and($filter->resources(createNadotaRequest()))->toBe([])
        ->and($filter->props())->toBe(['translateLabels' => false])
        ->and($filter->getFilterKeys())->toBe(['value' => 'status']);
});

it('serialises [label => value] options as {label, value}', function () {
    $filter = SelectFilter::make('Status', 'status')->options(['Draft' => 'draft', 'Enrolled' => 'enrolled']);

    expect($filter->toArray(createNadotaRequest())['options'])->toBe([
        ['label' => 'Draft', 'value' => 'draft'],
        ['label' => 'Enrolled', 'value' => 'enrolled'],
    ]);
});

it('passes already-formatted {value, label} options through untouched', function () {
    $filter = SelectFilter::make('Status', 'status')->options([
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'enrolled', 'label' => 'Enrolled'],
    ]);

    expect($filter->toArray(createNadotaRequest())['options'])->toBe([
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'enrolled', 'label' => 'Enrolled'],
    ]);
});

it('inverts the options when given the [value => label] map a Select field uses', function () {
    // Documents the convention clash: the same STATUSES constant feeds Select::options()
    // (value => label) and SelectFilter::options() (label => value).
    $filter = SelectFilter::make('Status', 'status')->options(['draft' => 'Draft']);

    expect($filter->toArray(createNadotaRequest())['options'])->toBe([
        ['label' => 'draft', 'value' => 'Draft'],
    ]);
});

it('tells the frontend to translate labels with withTranslation()/translateLabels()', function () {
    expect(SelectFilter::make('Status', 'status')->withTranslation()->props())->toBe(['translateLabels' => true])
        ->and(SelectFilter::make('Status', 'status')->translateLabels(false)->props())->toBe(['translateLabels' => false]);
});

// --- apply() ------------------------------------------------------------------------------

it('matches a single value exactly', function () {
    expect(selectFilterNames(new SelectFilter('Status', 'status'), 'enrolled'))->toBe(['Bob']);
});

it('matches any of several values with an array', function () {
    expect(selectFilterNames(new SelectFilter('Status', 'status'), ['draft', 'graduated']))->toBe(['Ana', 'Cid']);
});

it('does not restrict the value to the declared options', function () {
    $filter = SelectFilter::make('Status', 'status')->options(['Draft' => 'draft']);

    expect(selectFilterNames($filter, 'graduated'))->toBe(['Cid']);
});

it('is case sensitive and does not partially match', function () {
    $filter = new SelectFilter('Status', 'status');

    expect(selectFilterNames($filter, 'Draft'))->toBe([])
        ->and(selectFilterNames($filter, 'dra'))->toBe([]);
});

it('matches the empty string literally (only null is skipped upstream)', function () {
    expect(selectFilterNames(new SelectFilter('Status', 'status'), ''))->toBe([]);
});

it('ignores an empty array instead of matching nothing', function () {
    expect(selectFilterNames(new SelectFilter('Status', 'status'), []))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
})->skip('ISSUE FLT-016: whereIn(field, []) compiles to "0 = 1", so an empty multi-select hides every row');
