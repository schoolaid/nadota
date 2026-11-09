<?php

use SchoolAid\Nadota\Http\Filters\BooleanFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

beforeEach(function () {
    $this->request = Mockery::mock(NadotaRequest::class);
});

afterEach(function () {
    Mockery::close();
});

it('falls back to the Sí/No labels when the app sets no config', function () {
    $filter = new BooleanFilter('Liquidated', 'is_liquidated');

    expect($filter->resources($this->request))->toBe([
        'Sí' => true,
        'No' => false,
    ]);
});

it('uses the labels configured by the app, so it can hand i18n keys to the frontend', function () {
    config()->set('nadota.filters.boolean.true_label', 'nadota.yes');
    config()->set('nadota.filters.boolean.false_label', 'nadota.no');

    $filter = new BooleanFilter('Liquidated', 'is_liquidated');

    expect($filter->resources($this->request))->toBe([
        'nadota.yes' => true,
        'nadota.no' => false,
    ]);
});

it('serializes the configured labels as the filter options', function () {
    config()->set('nadota.filters.boolean.true_label', 'nadota.yes');
    config()->set('nadota.filters.boolean.false_label', 'nadota.no');

    $filter = new BooleanFilter('creditCardHistoryResource.fields.isLiquidated', 'is_liquidated');

    expect($filter->toArray($this->request)['options'])->toBe([
        ['label' => 'nadota.yes', 'value' => true],
        ['label' => 'nadota.no', 'value' => false],
    ]);
});

// --- constructor defaults & serialisation -------------------------------------------------

it('is a boolean filter rendered by FilterBoolean, keyed by its field', function () {
    $filter = new BooleanFilter('Active', 'is_active');

    expect($filter->type)->toBe('boolean')
        ->and($filter->component())->toBe('FilterBoolean')
        ->and($filter->key())->toBe('is_active')
        ->and($filter->isRange())->toBeFalse()
        ->and($filter->getFilterKeys())->toBe(['value' => 'is_active']);
});

it('exposes its true/false column values as props (1/0 by default)', function () {
    expect((new BooleanFilter('Active', 'is_active'))->props())->toBe(['trueValue' => 1, 'falseValue' => 0])
        ->and((new BooleanFilter('Active', 'flag'))->trueValue('Y')->falseValue('N')->props())
        ->toBe(['trueValue' => 'Y', 'falseValue' => 'N']);
});

it('serialises boolean options as real booleans', function () {
    $array = (new BooleanFilter('Active', 'is_active'))->toArray($this->request);

    expect($array['options'])->toBe([
        ['label' => 'Sí', 'value' => true],
        ['label' => 'No', 'value' => false],
    ])->and($array['component'])->toBe('FilterBoolean')
        ->and($array['type'])->toBe('boolean');
});

// --- apply() against a real table ---------------------------------------------------------

function booleanFilterStudentNames($query): array
{
    return $query->orderBy('name')->pluck('name')->all();
}

function seedBooleanFilterStudents(): void
{
    FixtureSchema::create();
    Student::create(['name' => 'Ana', 'is_active' => true, 'status' => 'Y']);
    Student::create(['name' => 'Bob', 'is_active' => false, 'status' => 'N']);
    Student::create(['name' => 'Cid', 'is_active' => true, 'status' => 'N']);
}

it('matches the true value for every truthy spelling', function ($value) {
    seedBooleanFilterStudents();
    $filter = new BooleanFilter('Active', 'is_active');

    $query = $filter->apply(createNadotaRequest(), Student::query(), $value);

    expect(booleanFilterStudentNames($query))->toBe(['Ana', 'Cid']);
})->with([
    'string true' => 'true',
    'bool true' => true,
    'string 1' => '1',
    'int 1' => 1,
]);

it('matches the false value for every falsy spelling', function ($value) {
    seedBooleanFilterStudents();
    $filter = new BooleanFilter('Active', 'is_active');

    $query = $filter->apply(createNadotaRequest(), Student::query(), $value);

    expect(booleanFilterStudentNames($query))->toBe(['Bob']);
})->with([
    'string false' => 'false',
    'bool false' => false,
    'string 0' => '0',
    'int 0' => 0,
]);

it('leaves the query untouched for values it does not recognise', function ($value) {
    seedBooleanFilterStudents();
    $filter = new BooleanFilter('Active', 'is_active');
    $query = Student::query();

    $result = $filter->apply(createNadotaRequest(), $query, $value);

    expect($result->getQuery()->wheres)->toBe([])
        ->and(booleanFilterStudentNames($result))->toBe(['Ana', 'Bob', 'Cid']);
})->with([
    'empty string' => '',
    'null' => null,
    'yes' => 'yes',
    'upper TRUE' => 'TRUE',
    'array' => [['1']],
    '2' => 2,
]);

it('compares against custom true/false column values', function () {
    seedBooleanFilterStudents();
    $filter = (new BooleanFilter('Flag', 'status'))->trueValue('Y')->falseValue('N');

    expect(booleanFilterStudentNames($filter->apply(createNadotaRequest(), Student::query(), 'true')))->toBe(['Ana'])
        ->and(booleanFilterStudentNames($filter->apply(createNadotaRequest(), Student::query(), 'false')))->toBe(['Bob', 'Cid']);
});
