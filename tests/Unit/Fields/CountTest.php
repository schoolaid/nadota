<?php

use SchoolAid\Nadota\Http\Fields\Count;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('counts a relation through the snake_cased {relation}_count attribute', function () {
    $field = Count::make('Courses', 'courses');

    expect($field)->toBeField()
        ->and($field->getAttribute())->toBe('courses_count')
        ->and($field->key())->toBe('courses_count')
        ->and($field->getCountRelation())->toBe('courses')
        ->and(Count::make('Latest Notes', 'latestNotes')->getAttribute())->toBe('latest_notes_count');
});

it('reports a number type rendered by the FieldCount component', function () {
    $field = Count::make('Courses', 'courses');

    expect($field->getType())->toBe(FieldType::NUMBER->value)
        ->and($field->getComponent())->toBe('FieldCount');
});

it('is computed, readonly and shown only on index and detail', function () {
    $field = Count::make('Courses', 'courses');
    $request = createNadotaRequest();

    expect($field->isComputed())->toBeTrue()
        ->and($field->isReadonly())->toBeTrue()
        ->and($field->isShowOnIndex($request))->toBeTrue()
        ->and($field->isShowOnDetail($request))->toBeTrue()
        ->and($field->isShowOnCreation($request))->toBeFalse()
        ->and($field->isShowOnUpdate($request))->toBeFalse()
        ->and($field->isAppliedInIndexQuery())->toBeTrue()
        ->and($field->isAppliedInShowQuery())->toBeTrue()
        ->and($field->requiresWithCount())->toBeTrue();
});

it('keeps the constraint callback for the withCount query', function () {
    $constraint = fn ($query) => $query->where('credits', '>', 3);

    $field = Count::make('Heavy Courses', 'courses')->constraint($constraint);

    expect($field->getCountConstraint())->toBe($constraint)
        ->and(Count::make('Courses', 'courses')->getCountConstraint())->toBeNull();
});

it('resolves the loaded count and zero when the count was not loaded', function () {
    $model = new TestModel();
    $model->courses_count = 4;

    expect(Count::make('Courses', 'courses')->resolve(createNadotaRequest(), $model, null))->toBe(4)
        ->and(Count::make('Courses', 'courses')->resolve(createNadotaRequest(), new TestModel(), null))->toBe(0);
});

it('resolves the count Eloquent loads with withCount()', function () {
    FixtureSchema::create();
    $student = Student::create(['name' => 'Ana']);
    $student->courses()->attach([
        Course::create(['name' => 'Math'])->id,
        Course::create(['name' => 'Art'])->id,
    ]);

    $loaded = Student::query()->withCount('courses')->find($student->id);

    expect(Count::make('Courses', 'courses')->resolve(createNadotaRequest(), $loaded, null))->toBe(2);
});

it('adds no column to the select clause', function () {
    expect(Count::make('Courses', 'courses')->getColumnsForSelect(Student::class))->toBe([]);
});

it('never fills the model', function () {
    $model = new TestModel();

    Count::make('Courses', 'courses')->fill(createNadotaRequest(['courses_count' => 99]), $model);

    expect($model->getAttributes())->toBe([]);
});

it('exposes the counted relation in props', function () {
    $array = Count::make('Courses', 'courses')->toArray(createNadotaRequest());

    expect($array['props'])->toBe(['relation' => 'courses'])
        ->and($array['readonly'])->toBeTrue()
        ->and($array['showOnCreation'])->toBeFalse();
});

it('exports the count value', function () {
    $model = new TestModel();
    $model->courses_count = 7;

    expect(Count::make('Courses', 'courses')->resolveForExport(createNadotaRequest(), $model, null))->toBe(7);
});
