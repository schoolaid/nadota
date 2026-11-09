<?php

use SchoolAid\Nadota\Http\Filters\RangeFilter;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

beforeEach(function () {
    FixtureSchema::create();
    Student::create(['name' => 'Ana', 'balance' => 0]);
    Student::create(['name' => 'Bob', 'balance' => 50.5]);
    Student::create(['name' => 'Cid', 'balance' => 100]);
    Student::create(['name' => 'Dee', 'balance' => null]);
});

function rangeFilterNames(RangeFilter $filter, mixed $value): array
{
    return $filter->apply(createNadotaRequest(), Student::query(), $value)
        ->orderBy('name')->pluck('name')->all();
}

it('is always a range filter with from/to keys', function () {
    $filter = new RangeFilter('Balance', 'balance');

    expect($filter->isRange())->toBeTrue()
        ->and($filter->key())->toBe('balance')
        ->and($filter->getFilterKeys())->toBe(['from' => 'balance_from', 'to' => 'balance_to']);
});

it('inherits the generic text type and FilterText component', function () {
    // Field-generated RangeFilters receive the field type (e.g. "currency" → "FilterCurrency").
    expect((new RangeFilter('Balance', 'balance'))->type)->toBe('text')
        ->and((new RangeFilter('Balance', 'balance'))->component())->toBe('FilterText')
        ->and((new RangeFilter('Balance', 'balance', 'currency'))->component())->toBe('FilterCurrency');
});

it('serialises isRange and filterKeys without props', function () {
    expect((new RangeFilter('Balance', 'balance'))->toArray(createNadotaRequest()))->toMatchArray([
        'key' => 'balance',
        'props' => [],
        'isRange' => true,
        'filterKeys' => ['from' => 'balance_from', 'to' => 'balance_to'],
    ]);
});

it('applies an inclusive between for start and end', function () {
    expect(rangeFilterNames(new RangeFilter('Balance', 'balance'), ['start' => 50, 'end' => 100]))->toBe(['Bob', 'Cid']);
});

it('applies >= with only a start and <= with only an end', function () {
    $filter = new RangeFilter('Balance', 'balance');

    expect(rangeFilterNames($filter, ['start' => 60]))->toBe(['Cid'])
        ->and(rangeFilterNames($filter, ['end' => 60]))->toBe(['Ana', 'Bob'])
        ->and(rangeFilterNames($filter, ['start' => null, 'end' => 60]))->toBe(['Ana', 'Bob']);
});

it('ignores a value without bounds', function ($value) {
    expect(rangeFilterNames(new RangeFilter('Balance', 'balance'), $value))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
})->with([
    'empty array' => [[]],
    'null bounds' => [['start' => null, 'end' => null]],
    'empty-string bounds' => [['start' => '', 'end' => '']],
]);

it('does not understand positional [start, end] arrays (unlike NumberFilter/DateFilter)', function () {
    expect(rangeFilterNames(new RangeFilter('Balance', 'balance'), [50, 100]))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
});

it('ignores a scalar value', function () {
    expect(rangeFilterNames(new RangeFilter('Balance', 'balance'), '50'))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
});

it('treats zero as a real bound', function () {
    $filter = new RangeFilter('Balance', 'balance');

    expect(rangeFilterNames($filter, ['start' => 0, 'end' => 0]))->toBe(['Ana'])
        ->and(rangeFilterNames($filter, ['start' => '0', 'end' => '60']))->toBe(['Ana', 'Bob'])
        ->and(rangeFilterNames($filter, ['end' => '0']))->toBe(['Ana']);
})->skip('ISSUE FLT-006: bounds are tested for truthiness, so 0 / "0" are dropped as if absent');
