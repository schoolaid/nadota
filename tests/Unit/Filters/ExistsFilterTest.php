<?php

use SchoolAid\Nadota\Http\Filters\ExistsFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

beforeEach(function () {
    $this->request = Mockery::mock(NadotaRequest::class);
});

afterEach(function () {
    Mockery::close();
});

it('can be instantiated', function () {
    $filter = new ExistsFilter('Has Comments', 'comments_exists', 'comments');

    expect($filter)->toBeInstanceOf(ExistsFilter::class)
        ->and($filter->name())->toBe('Has Comments')
        ->and($filter->key())->toBe('comments_exists')
        ->and($filter->getRelation())->toBe('comments');
});

it('can be instantiated with constraint', function () {
    $constraint = fn($q) => $q->where('active', true);
    $filter = new ExistsFilter('Has Active Comments', 'active_comments_exists', 'comments', $constraint);

    expect($filter)->toBeInstanceOf(ExistsFilter::class)
        ->and($filter->getRelation())->toBe('comments')
        ->and($filter->getConstraint())->toBe($constraint);
});

it('has correct component for frontend', function () {
    $filter = new ExistsFilter('Has Comments', 'comments_exists', 'comments');

    expect($filter->component())->toBe('FilterBoolean');
});

it('is an "exists" filter keyed by its attribute, without range keys', function () {
    $filter = new ExistsFilter('Has Notes', 'notes_exists', 'notes');

    expect($filter->type)->toBe('exists')
        ->and($filter->id())->toBe('has-notes')
        ->and($filter->isRange())->toBeFalse()
        ->and($filter->getFilterKeys())->toBe(['value' => 'notes_exists'])
        ->and($filter->props())->toBe([]);
});

it('serialises as a FilterBoolean descriptor without options', function () {
    // FilterBoolean filters built from BooleanFilter ship Sí/No options; this one ships none (FLT-022).
    expect((new ExistsFilter('Has Notes', 'notes_exists', 'notes'))->toArray($this->request))->toBe([
        'key' => 'notes_exists',
        'label' => 'Has Notes',
        'component' => 'FilterBoolean',
        'type' => 'exists',
        'options' => [],
        'value' => '',
        'props' => [],
        'isRange' => false,
        'filterKeys' => ['value' => 'notes_exists'],
    ]);
});

// --- apply() against a real relation ------------------------------------------------------

function seedExistsFilterStudents(): void
{
    FixtureSchema::create();
    $ana = Student::create(['name' => 'Ana']);
    $bob = Student::create(['name' => 'Bob']);
    Student::create(['name' => 'Cid']);
    $ana->notes()->create(['body' => 'pinned note', 'pinned' => true]);
    $bob->notes()->create(['body' => 'plain note', 'pinned' => false]);
}

function existsFilterNames(ExistsFilter $filter, mixed $value): array
{
    return $filter->apply(createNadotaRequest(), Student::query(), $value)
        ->orderBy('name')->pluck('name')->all();
}

it('keeps only rows that have the relation for truthy values', function ($value) {
    seedExistsFilterStudents();

    expect(existsFilterNames(new ExistsFilter('Has Notes', 'notes_exists', 'notes'), $value))->toBe(['Ana', 'Bob']);
})->with(['bool' => true, 'string true' => 'true', 'string 1' => '1', 'int' => 1, 'on' => 'on', 'yes' => 'yes']);

it('keeps only rows without the relation for falsy values', function ($value) {
    seedExistsFilterStudents();

    expect(existsFilterNames(new ExistsFilter('Has Notes', 'notes_exists', 'notes'), $value))->toBe(['Cid']);
})->with(['bool' => false, 'string false' => 'false', 'string 0' => '0', 'int' => 0, 'off' => 'off', 'no' => 'no']);

it('applies the constraint to both the "has" and the "has not" branch', function () {
    seedExistsFilterStudents();
    $filter = new ExistsFilter('Has Pinned Notes', 'pinned_notes_exists', 'notes', fn ($q) => $q->where('pinned', true));

    expect(existsFilterNames($filter, 'true'))->toBe(['Ana'])
        ->and(existsFilterNames($filter, 'false'))->toBe(['Bob', 'Cid']);
});

it('leaves the query untouched for values that are neither true nor false', function ($value) {
    seedExistsFilterStudents();

    expect(existsFilterNames(new ExistsFilter('Has Notes', 'notes_exists', 'notes'), $value))->toBe(['Ana', 'Bob', 'Cid']);
})->with(['garbage' => 'banana', 'empty string' => '', 'array' => [['1']]])
    ->skip('ISSUE FLT-007: filter_var() without FILTER_NULL_ON_FAILURE maps any unknown value to false, i.e. whereDoesntHave');
