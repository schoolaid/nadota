<?php

use Illuminate\Database\Eloquent\Builder;
use SchoolAid\Nadota\Http\Fields\Input;

it('has no option scopes by default', function () {
    $field = Input::make('Student', 'student_id');

    expect($field->hasOptionScopes())->toBeFalse()
        ->and($field->getOptionScopes())->toBe([]);
});

it('infers the column from the observed field name', function () {
    $field = Input::make('Student', 'student_id')->scopedBy('grade');

    $scope = $field->getOptionScopes()['grade'];

    expect($scope->field)->toBe('grade')
        ->and($scope->column)->toBe('grade_id')
        ->and($scope->callback)->toBeNull()
        ->and($scope->optional)->toBeFalse()
        ->and($scope->usesCallback())->toBeFalse();
});

it('snake cases a camel cased field name when inferring the column', function () {
    $field = Input::make('Student', 'student_id')->scopedBy('schoolYear');

    expect($field->getOptionScopes()['schoolYear']->column)->toBe('school_year_id');
});

it('accepts an explicit column name', function () {
    $field = Input::make('Student', 'student_id')->scopedBy('grade', 'current_grade_id');

    expect($field->getOptionScopes()['grade']->column)->toBe('current_grade_id');
});

it('accepts a closure and leaves the column null', function () {
    $callback = fn (Builder $query, $value) => $query->where('grade_id', $value);

    $field = Input::make('Student', 'student_id')->scopedBy('grade', $callback);

    $scope = $field->getOptionScopes()['grade'];

    expect($scope->column)->toBeNull()
        ->and($scope->callback)->toBe($callback)
        ->and($scope->usesCallback())->toBeTrue();
});

it('marks a scope as optional when asked', function () {
    $field = Input::make('Student', 'student_id')
        ->scopedBy('campus', 'campus_id', optional: true);

    expect($field->getOptionScopes()['campus']->optional)->toBeTrue();
});

it('accumulates scopes on different fields', function () {
    $field = Input::make('Student', 'student_id')
        ->scopedBy('grade', 'grade_id')
        ->scopedBy('campus', 'campus_id');

    expect(array_keys($field->getOptionScopes()))->toBe(['grade', 'campus'])
        ->and($field->hasOptionScopes())->toBeTrue();
});

it('replaces a previous declaration on the same field', function () {
    $field = Input::make('Student', 'student_id')
        ->scopedBy('grade', 'grade_id')
        ->scopedBy('grade', 'other_grade_id');

    expect($field->getOptionScopes())->toHaveCount(1)
        ->and($field->getOptionScopes()['grade']->column)->toBe('other_grade_id');
});

it('is chainable', function () {
    $field = Input::make('Student', 'student_id');

    expect($field->scopedBy('grade'))->toBe($field);
});
