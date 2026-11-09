<?php

use SchoolAid\Nadota\Http\Filters\NumberFilter;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

beforeEach(function () {
    FixtureSchema::create();
    Student::create(['name' => 'Ana', 'age' => 0]);
    Student::create(['name' => 'Bob', 'age' => 10]);
    Student::create(['name' => 'Cid', 'age' => 12]);
    Student::create(['name' => 'Dee', 'age' => 15]);
    Student::create(['name' => 'Eve', 'age' => null]);
});

function numberFilterNames(NumberFilter $filter, mixed $value): array
{
    return $filter->apply(createNadotaRequest(), Student::query(), $value)
        ->orderBy('name')->pluck('name')->all();
}

// --- constructor defaults & serialisation -------------------------------------------------

it('is an exact-match FilterNumber by default', function () {
    $filter = new NumberFilter('Age', 'age');

    expect($filter->type)->toBe('number')
        ->and($filter->component())->toBe('FilterNumber')
        ->and($filter->key())->toBe('age')
        ->and($filter->isRange())->toBeFalse()
        ->and($filter->props())->toBe(['isRange' => false])
        ->and($filter->getFilterKeys())->toBe(['value' => 'age']);
});

it('becomes a FilterNumberRange with from/to keys through the constructor flag', function () {
    $filter = new NumberFilter('Age', 'age', null, null, null, true);

    expect($filter->component())->toBe('FilterNumberRange')
        ->and($filter->isRange())->toBeTrue()
        ->and($filter->getFilterKeys())->toBe(['from' => 'age_from', 'to' => 'age_to']);
});

it('toggles range mode and component with range()', function () {
    $filter = NumberFilter::make('Age Range', 'age')->range();

    expect($filter->component())->toBe('FilterNumberRange')
        ->and($filter->props())->toBe(['isRange' => true]);

    expect($filter->range(false)->component())->toBe('FilterNumber')
        ->and($filter->isRange())->toBeFalse();
});

it('keeps an explicit component given to the constructor', function () {
    expect((new NumberFilter('Age', 'age', null, 'MySlider', null, true))->component())->toBe('MySlider');
});

it('serialises the range descriptor', function () {
    expect(NumberFilter::make('Age Range', 'age')->range()->toArray(createNadotaRequest()))->toBe([
        'key' => 'age',
        'label' => 'Age Range',
        'component' => 'FilterNumberRange',
        'type' => 'number',
        'options' => [],
        'value' => '',
        'props' => ['isRange' => true],
        'isRange' => true,
        'filterKeys' => ['from' => 'age_from', 'to' => 'age_to'],
    ]);
});

// --- exact --------------------------------------------------------------------------------

it('matches an exact number given as int or numeric string', function ($value) {
    expect(numberFilterNames(new NumberFilter('Age', 'age'), $value))->toBe(['Cid']);
})->with(['int' => 12, 'string' => '12']);

it('matches zero exactly', function ($value) {
    expect(numberFilterNames(new NumberFilter('Age', 'age'), $value))->toBe(['Ana']);
})->with(['int' => 0, 'string' => '0']);

it('ignores null and empty-string values', function ($value) {
    expect(numberFilterNames(new NumberFilter('Age', 'age'), $value))->toBe(['Ana', 'Bob', 'Cid', 'Dee', 'Eve']);
})->with(['null' => null, 'empty string' => '']);

it('returns nothing for a non-numeric value instead of failing', function () {
    expect(numberFilterNames(new NumberFilter('Age', 'age'), 'abc'))->toBe([]);
});

it('does not silently reduce an array to its first element in exact mode', function () {
    // [10, 12] is either "any of" (whereIn) or invalid; it must not become "age = 10".
    expect(numberFilterNames(new NumberFilter('Age', 'age'), [10, 12]))->not->toBe(['Bob']);
})->skip('ISSUE FLT-020: exact mode passes the array to where(), and the query builder keeps only its first element');

// --- range --------------------------------------------------------------------------------

it('applies an inclusive between when both bounds are given', function () {
    $filter = NumberFilter::make('Age', 'age')->range();

    expect(numberFilterNames($filter, ['start' => 10, 'end' => 12]))->toBe(['Bob', 'Cid'])
        ->and(numberFilterNames($filter, ['start' => '10', 'end' => '12']))->toBe(['Bob', 'Cid']);
});

it('applies >= with only a start and <= with only an end', function () {
    $filter = NumberFilter::make('Age', 'age')->range();

    expect(numberFilterNames($filter, ['start' => 12, 'end' => null]))->toBe(['Cid', 'Dee'])
        ->and(numberFilterNames($filter, ['start' => 12]))->toBe(['Cid', 'Dee'])
        ->and(numberFilterNames($filter, ['start' => null, 'end' => 10]))->toBe(['Ana', 'Bob'])
        ->and(numberFilterNames($filter, ['end' => 10]))->toBe(['Ana', 'Bob']);
});

it('treats zero as a real bound', function () {
    $filter = NumberFilter::make('Age', 'age')->range();

    expect(numberFilterNames($filter, ['start' => 0, 'end' => 0]))->toBe(['Ana'])
        ->and(numberFilterNames($filter, ['start' => '0', 'end' => '10']))->toBe(['Ana', 'Bob'])
        ->and(numberFilterNames($filter, ['end' => 0]))->toBe(['Ana']);
});

it('accepts positional [start, end] arrays', function () {
    expect(numberFilterNames(NumberFilter::make('Age', 'age')->range(), [11, 20]))->toBe(['Cid', 'Dee']);
});

it('ignores a range without bounds', function ($value) {
    expect(numberFilterNames(NumberFilter::make('Age', 'age')->range(), $value))->toBe(['Ana', 'Bob', 'Cid', 'Dee', 'Eve']);
})->with([
    'empty array' => [[]],
    'null bounds' => [['start' => null, 'end' => null]],
]);

it('treats empty-string bounds as missing, like null', function () {
    // Over HTTP '' already arrives as null; a direct caller sending '' must not get
    // "age >= ''" (which matches nothing on SQLite).
    expect(numberFilterNames(NumberFilter::make('Age', 'age')->range(), ['start' => '', 'end' => '10']))->toBe(['Ana', 'Bob']);
})->skip('ISSUE FLT-006: range bounds are tested with !== null, so "" is used as a bound');

it('treats a scalar sent to a range filter as an exact match', function () {
    expect(numberFilterNames(NumberFilter::make('Age', 'age')->range(), '15'))->toBe(['Dee']);
});
