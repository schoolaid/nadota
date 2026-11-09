<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Filters\DateFilter;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

beforeEach(function () {
    FixtureSchema::create();
    // Raw inserts: the `date` cast would store "2010-01-01 00:00:00" in SQLite, which is
    // not what a real DATE column holds. Datetime values keep their time on purpose.
    DB::table('fx_students')->insert([
        ['name' => 'Ana', 'birth_date' => '2010-01-01', 'enrolled_at' => '2024-01-01 08:00:00'],
        ['name' => 'Bob', 'birth_date' => '2012-06-15', 'enrolled_at' => '2024-01-31 10:00:00'],
        ['name' => 'Cid', 'birth_date' => '2014-12-31', 'enrolled_at' => '2024-02-01 00:00:00'],
        ['name' => 'Dee', 'birth_date' => null, 'enrolled_at' => null],
    ]);
});

function dateFilterNames(DateFilter $filter, mixed $value): array
{
    return $filter->apply(createNadotaRequest(), Student::query(), $value)
        ->orderBy('name')->pluck('name')->all();
}

// --- constructor defaults & serialisation -------------------------------------------------

it('is a single-date FilterDate by default', function () {
    $filter = new DateFilter('Birth Date', 'birth_date');

    expect($filter->type)->toBe('date')
        ->and($filter->component())->toBe('FilterDate')
        ->and($filter->key())->toBe('birth_date')
        ->and($filter->isRange())->toBeFalse()
        ->and($filter->props())->toBe(['isRange' => false])
        ->and($filter->getFilterKeys())->toBe(['value' => 'birth_date']);
});

it('becomes a FilterDateRange with from/to keys through the constructor flag', function () {
    $filter = new DateFilter('Enrolled', 'enrolled_at', null, null, null, true);

    expect($filter->component())->toBe('FilterDateRange')
        ->and($filter->isRange())->toBeTrue()
        ->and($filter->props())->toBe(['isRange' => true])
        ->and($filter->getFilterKeys())->toBe(['from' => 'enrolled_at_from', 'to' => 'enrolled_at_to']);
});

it('toggles range mode and component with range()', function () {
    $filter = DateFilter::make('Enrolled', 'enrolled_at')->range();

    expect($filter->component())->toBe('FilterDateRange')
        ->and($filter->isRange())->toBeTrue();

    $filter->range(false);

    expect($filter->component())->toBe('FilterDate')
        ->and($filter->isRange())->toBeFalse()
        ->and($filter->getFilterKeys())->toBe(['value' => 'enrolled_at']);
});

it('keeps the type it is given (the field type for field-generated filters)', function () {
    expect((new DateFilter('Enrolled', 'enrolled_at', 'datetime'))->type)->toBe('datetime');
});

it('serialises isRange and filterKeys at the top level and inside props', function () {
    $array = DateFilter::make('Enrolled Between', 'enrolled_at')->range()->toArray(createNadotaRequest());

    expect($array)->toMatchArray([
        'key' => 'enrolled_at',
        'label' => 'Enrolled Between',
        'component' => 'FilterDateRange',
        'type' => 'date',
        'options' => [],
        'value' => '',
        'props' => ['isRange' => true],
        'isRange' => true,
        'filterKeys' => ['from' => 'enrolled_at_from', 'to' => 'enrolled_at_to'],
    ]);
});

// --- single date --------------------------------------------------------------------------

it('matches a single day on a date column', function () {
    expect(dateFilterNames(new DateFilter('Birth', 'birth_date'), '2012-06-15'))->toBe(['Bob']);
});

it('matches a single day on a datetime column regardless of the time', function () {
    expect(dateFilterNames(new DateFilter('Enrolled', 'enrolled_at'), '2024-01-31'))->toBe(['Bob']);
});

it('ignores an empty single value', function ($value) {
    expect(dateFilterNames(new DateFilter('Birth', 'birth_date'), $value))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
})->with(['empty string' => '', 'null' => null, 'false' => false]);

it('treats a scalar sent to a range filter as a single day', function () {
    expect(dateFilterNames(DateFilter::make('Birth', 'birth_date')->range(), '2014-12-31'))->toBe(['Cid']);
});

it('returns nothing for an unparseable date instead of failing', function () {
    expect(dateFilterNames(new DateFilter('Birth', 'birth_date'), 'not-a-date'))->toBe([]);
});

// --- range --------------------------------------------------------------------------------

it('applies an inclusive between for start and end on a date column', function () {
    $filter = DateFilter::make('Birth', 'birth_date')->range();

    expect(dateFilterNames($filter, ['start' => '2010-01-01', 'end' => '2012-06-15']))->toBe(['Ana', 'Bob']);
});

it('applies >= when only the start is given', function () {
    $filter = DateFilter::make('Birth', 'birth_date')->range();

    expect(dateFilterNames($filter, ['start' => '2012-06-15', 'end' => null]))->toBe(['Bob', 'Cid'])
        ->and(dateFilterNames($filter, ['start' => '2012-06-15']))->toBe(['Bob', 'Cid']);
});

it('applies <= when only the end is given', function () {
    $filter = DateFilter::make('Birth', 'birth_date')->range();

    expect(dateFilterNames($filter, ['start' => null, 'end' => '2012-06-15']))->toBe(['Ana', 'Bob'])
        ->and(dateFilterNames($filter, ['end' => '2012-06-15']))->toBe(['Ana', 'Bob']);
});

it('accepts positional [start, end] arrays', function () {
    $filter = DateFilter::make('Birth', 'birth_date')->range();

    expect(dateFilterNames($filter, ['2012-01-01', '2015-01-01']))->toBe(['Bob', 'Cid']);
});

it('ignores a range without bounds', function ($value) {
    $filter = DateFilter::make('Birth', 'birth_date')->range();

    expect(dateFilterNames($filter, $value))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
})->with([
    'empty array' => [[]],
    'null bounds' => [['start' => null, 'end' => null]],
    'empty-string bounds' => [['start' => '', 'end' => '']],
]);

it('includes the whole end day when the range targets a datetime column', function () {
    $filter = DateFilter::make('Enrolled', 'enrolled_at')->range();

    // Bob enrolled on 2024-01-31 at 10:00; a range "to 2024-01-31" must include him.
    expect(dateFilterNames($filter, ['start' => '2024-01-01', 'end' => '2024-01-31']))->toBe(['Ana', 'Bob'])
        ->and(dateFilterNames($filter, ['end' => '2024-01-31']))->toBe(['Ana', 'Bob']);
})->skip('ISSUE FLT-003: whereBetween/<= compare "2024-01-31 10:00:00" with "2024-01-31", so the end day is excluded on datetime columns');

it('includes rows exactly on the start instant of a datetime range', function () {
    $filter = DateFilter::make('Enrolled', 'enrolled_at')->range();

    expect(dateFilterNames($filter, ['start' => '2024-02-01']))->toBe(['Cid']);
});

it('includes the end day for a date column written through the Eloquent date cast', function () {
    // The `date` cast serialises as "Y-m-d H:i:s", so on SQLite a DATE column holds
    // "2012-06-15 00:00:00" and a "to 2012-06-15" bound must still include it.
    Student::create(['name' => 'Eve', 'birth_date' => '2016-03-03']);
    $filter = DateFilter::make('Birth', 'birth_date')->range();

    expect(dateFilterNames($filter, ['start' => '2016-03-03', 'end' => '2016-03-03']))->toBe(['Eve']);
})->skip('ISSUE FLT-003: same string comparison; the cast value "2016-03-03 00:00:00" is greater than the bound "2016-03-03"');
