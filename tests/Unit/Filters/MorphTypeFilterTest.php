<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use SchoolAid\Nadota\Http\Filters\MorphTypeFilter;
use SchoolAid\Nadota\Http\Filters\SelectFilter;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

beforeEach(function () {
    FixtureSchema::create();
    $student = Student::create(['name' => 'Ana']);
    $grade = Grade::create(['name' => 'G1']);
    $student->notes()->create(['body' => 'student note']);
    $grade->notes()->create(['body' => 'grade note']);
    Note::create(['body' => 'orphan note']);
});

afterEach(function () {
    Relation::morphMap([], false);
});

function morphTypeFilterBodies(MorphTypeFilter $filter, mixed $value): array
{
    return $filter->apply(createNadotaRequest(), Note::query(), $value)
        ->orderBy('body')->pluck('body')->all();
}

function morphTypeFilterUnderTest(): MorphTypeFilter
{
    return MorphTypeFilter::make('Notable Type', 'notable_type')
        ->options(['Student' => 'student', 'Grade' => 'grade'])
        ->morphTypes([
            'student' => Student::class,                                   // string form
            'grade' => ['model' => Grade::class, 'label' => 'Grade'],      // config form
        ]);
}

it('is a SelectFilter keyed by the morph type column', function () {
    $filter = morphTypeFilterUnderTest();

    expect($filter)->toBeInstanceOf(SelectFilter::class)
        ->and($filter->type)->toBe('select')
        ->and($filter->component())->toBe('FilterSelect')
        ->and($filter->key())->toBe('notable_type');
});

it('serialises its alias options, not the model classes', function () {
    expect(morphTypeFilterUnderTest()->toArray(createNadotaRequest())['options'])->toBe([
        ['label' => 'Student', 'value' => 'student'],
        ['label' => 'Grade', 'value' => 'grade'],
    ]);
});

it('resolves an alias mapped to a model class string', function () {
    expect(morphTypeFilterBodies(morphTypeFilterUnderTest(), 'student'))->toBe(['student note']);
});

it('resolves an alias mapped to a config array', function () {
    expect(morphTypeFilterBodies(morphTypeFilterUnderTest(), 'grade'))->toBe(['grade note']);
});

it('resolves an array of aliases with whereIn', function () {
    expect(morphTypeFilterBodies(morphTypeFilterUnderTest(), ['student', 'grade']))->toBe(['grade note', 'student note']);
});

it('drops unknown aliases from an array instead of failing', function () {
    expect(morphTypeFilterBodies(morphTypeFilterUnderTest(), ['student', 'teacher']))->toBe(['student note']);
});

it('leaves the query untouched for an unknown alias', function ($value) {
    expect(morphTypeFilterBodies(morphTypeFilterUnderTest(), $value))->toBe(['grade note', 'orphan note', 'student note']);
})->with(['unknown' => 'teacher', 'empty string' => '', 'model class itself' => Student::class]);

it('ignores a config array without a model key', function () {
    $filter = MorphTypeFilter::make('Type', 'notable_type')->morphTypes(['student' => ['label' => 'Student']]);

    expect(morphTypeFilterBodies($filter, 'student'))->toBe(['grade note', 'orphan note', 'student note']);
});

it('matches the stored morph alias when the application uses a morph map', function () {
    Relation::morphMap(['student' => Student::class, 'grade' => Grade::class]);
    $mapped = Student::create(['name' => 'Bob']);
    $mapped->notes()->create(['body' => 'mapped note']); // stored with notable_type = "student"

    expect(morphTypeFilterBodies(morphTypeFilterUnderTest(), 'student'))->toContain('mapped note');
})->skip('ISSUE FLT-014: the alias is converted to the FQCN, but with a morph map the column stores the alias');
