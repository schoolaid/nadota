<?php

use SchoolAid\Nadota\Http\Services\Handlers\RelationHandler;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * RelationHandler has no caller in src/ (the persist services fill relation
 * fields through Field::fill()/afterSave()). These tests pin down what it does
 * today; see REL-018.
 */
beforeEach(function () {
    FixtureSchema::create();
});

it('assigns validated scalar attributes onto the model', function () {
    $student = new Student();
    $data = ['name' => 'Ana', 'age' => 12];

    (new RelationHandler())->handleRelations($student, $data);

    expect($student->getDirty())->toBe(['name' => 'Ana', 'age' => 12]);
});

it('handles a validated key that matches a relation method without crashing', function () {
    $grade = Grade::create(['name' => 'G']);
    $student = Student::create(['name' => 'Ana']);
    $data = ['grade' => $grade->id];

    (new RelationHandler())->handleRelations($student, $data);

    expect($student->grade_id)->toBe($grade->id);
})->skip('ISSUE REL-018: getField() returns the Eloquent relation from a ?Field return type (TypeError) and Field has no getRelationType()');
