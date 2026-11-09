<?php

use SchoolAid\Nadota\Http\Filters\DefaultFilter;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

beforeEach(function () {
    FixtureSchema::create();
    Student::create(['name' => 'Ana Lopez', 'email' => 'ana@school.test']);
    Student::create(['name' => 'Bob Ray', 'email' => 'bob@school.test']);
    Student::create(['name' => 'Ann_Marie', 'email' => null]);
    Student::create(['name' => '100% Cid', 'email' => 'cid@other.test']);
});

function defaultFilterNames(DefaultFilter $filter, mixed $value): array
{
    return $filter->apply(createNadotaRequest(), Student::query(), $value)
        ->orderBy('name')->pluck('name')->all();
}

it('is a FilterText "text" filter keyed by its field', function () {
    $filter = new DefaultFilter('Name', 'name');

    expect($filter->type)->toBe('text')
        ->and($filter->component())->toBe('FilterText')
        ->and($filter->key())->toBe('name')
        ->and($filter->isRange())->toBeFalse()
        ->and($filter->getFilterKeys())->toBe(['value' => 'name']);
});

it('derives the component from the field type it is given', function () {
    expect((new DefaultFilter('Email', 'email', 'email'))->component())->toBe('FilterEmail')
        ->and((new DefaultFilter('Bio', 'bio', 'textarea'))->component())->toBe('FilterTextarea');
});

it('serialises without options or props', function () {
    expect((new DefaultFilter('Name', 'name'))->toArray(createNadotaRequest()))->toBe([
        'key' => 'name',
        'label' => 'Name',
        'component' => 'FilterText',
        'type' => 'text',
        'options' => [],
        'value' => '',
        'props' => [],
        'isRange' => false,
        'filterKeys' => ['value' => 'name'],
    ]);
});

it('matches a substring anywhere in the column', function () {
    $filter = new DefaultFilter('Email', 'email');

    expect(defaultFilterNames($filter, 'school'))->toBe(['Ana Lopez', 'Bob Ray'])
        ->and(defaultFilterNames($filter, 'bob@'))->toBe(['Bob Ray']);
});

it('matches case-insensitively on SQLite', function () {
    expect(defaultFilterNames(new DefaultFilter('Name', 'name'), 'LOPEZ'))->toBe(['Ana Lopez']);
});

it('returns nothing when no row contains the value', function () {
    expect(defaultFilterNames(new DefaultFilter('Name', 'name'), 'zzz'))->toBe([]);
});

it('treats an empty string as "any non-null value"', function () {
    // FilterCriteria forwards '' (only null is skipped); over HTTP the
    // ConvertEmptyStringsToNull middleware turns it into null first.
    expect(defaultFilterNames(new DefaultFilter('Email', 'email'), ''))->toBe(['100% Cid', 'Ana Lopez', 'Bob Ray']);
});

it('matches LIKE wildcards in the value literally', function () {
    $filter = new DefaultFilter('Name', 'name');

    expect(defaultFilterNames($filter, '_'))->toBe(['Ann_Marie'])
        ->and(defaultFilterNames($filter, '%'))->toBe(['100% Cid']);
})->skip('ISSUE FLT-019: the value is interpolated into LIKE without escaping, so "_" and "%" act as wildcards');

it('ignores an array value instead of failing', function () {
    expect(defaultFilterNames(new DefaultFilter('Name', 'name'), ['Ana']))->toBe(['100% Cid', 'Ana Lopez', 'Ann_Marie', 'Bob Ray']);
})->skip('ISSUE FLT-009: "%" . $value . "%" on an array raises "Array to string conversion"');
