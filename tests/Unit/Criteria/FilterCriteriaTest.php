<?php

use SchoolAid\Nadota\Http\Criteria\FilterCriteria;
use SchoolAid\Nadota\Http\Filters\Filter;
use SchoolAid\Nadota\Http\Filters\NumberFilter;
use SchoolAid\Nadota\Http\Filters\SelectFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/** A filter that only records the values it is applied with. */
function filterCriteriaSpy(string $name, string $field): Filter
{
    return new class($name, $field) extends Filter {
        public array $applied = [];

        public function apply(NadotaRequest $request, $query, $value)
        {
            $this->applied[] = $value;

            return $query;
        }
    };
}

it('applies each filter whose key matches a submitted value', function () {
    $status = filterCriteriaSpy('Status', 'status');
    $age = filterCriteriaSpy('Age', 'age');

    (new FilterCriteria(['status' => 'draft', 'age' => '10']))->apply(createNadotaRequest(), Student::query(), [$status, $age]);

    expect($status->applied)->toBe(['draft'])
        ->and($age->applied)->toBe(['10']);
});

it('matches on key() (the field), not on the label or id', function () {
    $filter = filterCriteriaSpy('Status Filter', 'status');

    (new FilterCriteria(['Status Filter' => 'x', 'status-filter' => 'y', 'statusfilter' => 'z']))
        ->apply(createNadotaRequest(), Student::query(), [$filter]);

    expect($filter->applied)->toBe([]);
});

it('ignores submitted keys that no filter declares', function () {
    $status = filterCriteriaSpy('Status', 'status');

    $query = Student::query();
    $result = (new FilterCriteria(['unknown' => 'x', 'name' => 'Ana']))->apply(createNadotaRequest(), $query, [$status]);

    expect($status->applied)->toBe([])
        ->and($result)->toBe($query);
});

it('leaves filters without a submitted value alone', function () {
    $status = filterCriteriaSpy('Status', 'status');

    (new FilterCriteria([]))->apply(createNadotaRequest(), Student::query(), [$status]);

    expect($status->applied)->toBe([]);
});

it('skips null values but forwards every other "empty" value to the filter', function () {
    $filter = filterCriteriaSpy('Status', 'status');

    foreach ([null, '', '0', 0, false, []] as $value) {
        (new FilterCriteria(['status' => $value]))->apply(createNadotaRequest(), Student::query(), [$filter]);
    }

    expect($filter->applied)->toBe(['', '0', 0, false, []]);
});

it('applies only the first filter when two filters share a key', function () {
    $fieldFilter = filterCriteriaSpy('Age', 'age');
    $resourceFilter = filterCriteriaSpy('Age Range', 'age');

    (new FilterCriteria(['age' => '10']))->apply(createNadotaRequest(), Student::query(), [$fieldFilter, $resourceFilter]);

    expect($fieldFilter->applied)->toBe(['10'])
        ->and($resourceFilter->applied)->toBe([]);
});

it('accepts filters as any iterable collection', function () {
    $status = filterCriteriaSpy('Status', 'status');

    (new FilterCriteria(['status' => 'draft']))->apply(createNadotaRequest(), Student::query(), collect([$status]));

    expect($status->applied)->toBe(['draft']);
});

it('AND-combines several filters on a real query', function () {
    FixtureSchema::create();
    Student::create(['name' => 'Ana', 'status' => 'draft', 'age' => 10]);
    Student::create(['name' => 'Bob', 'status' => 'draft', 'age' => 12]);
    Student::create(['name' => 'Cid', 'status' => 'enrolled', 'age' => 10]);

    $query = (new FilterCriteria(['status' => 'draft', 'age' => '10']))->apply(
        createNadotaRequest(),
        Student::query(),
        [new SelectFilter('Status', 'status'), new NumberFilter('Age', 'age')]
    );

    expect($query->pluck('name')->all())->toBe(['Ana']);
});

it('returns the builder each filter hands back', function () {
    $replacement = Student::query()->where('name', 'replaced');
    $filter = new class('Status', 'status', $replacement) extends Filter {
        public function __construct($name, $field, private $replacement)
        {
            parent::__construct($name, $field);
        }

        public function apply(NadotaRequest $request, $query, $value)
        {
            return $this->replacement;
        }
    };

    $result = (new FilterCriteria(['status' => 'x']))->apply(createNadotaRequest(), Student::query(), [$filter]);

    expect($result)->toBe($replacement);
});
