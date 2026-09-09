<?php

use SchoolAid\Nadota\Http\Fields\Lookup;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\RelatedModelResource;

it('can be instantiated', function () {
    $field = Lookup::make('Grade', 'grade');

    expect($field)->toBeInstanceOf(Lookup::class)
        ->and($field->getName())->toBe('Grade')
        ->and($field->getAttribute())->toBe('grade')
        ->and($field->getType())->toBe('lookup');
});

it('is virtual without being told', function () {
    $field = Lookup::make('Grade', 'grade');

    expect($field->isVirtual())->toBeTrue()
        ->and($field->shouldSkipFill())->toBeTrue();
});

it('contributes no columns to the select clause', function () {
    $field = Lookup::make('Grade', 'grade');

    expect($field->getColumnsForSelect(TestModel::class))->toBe([]);
});

it('never fills the model', function () {
    $field = Lookup::make('Grade', 'grade');
    $model = new TestModel();

    $field->fill(createNadotaRequest(['grade' => 7]), $model);

    expect($model->getAttributes())->toBe([]);
});

it('resolves to null', function () {
    $model = new TestModel(['name' => 'Ada']);
    $model->grade = 7;

    $field = Lookup::make('Grade', 'grade');

    expect($field->resolve(createNadotaRequest(), $model, createTestResource()))->toBeNull();
});

it('is shown on forms only', function () {
    $field = Lookup::make('Grade', 'grade');
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeFalse()
        ->and($field->isShowOnCreation($request, null))->toBeTrue()
        ->and($field->isShowOnUpdate($request, null))->toBeTrue();
});

it('is not a relationship field', function () {
    $field = Lookup::make('Grade', 'grade')->resource(RelatedModelResource::class);

    expect($field->isRelationship())->toBeFalse();
});

it('exposes an options url once a resource is set', function () {
    $field = Lookup::make('Grade', 'grade')->resource(RelatedModelResource::class);

    $payload = $field->toArray(createNadotaRequest(), null, createTestResource());

    expect($payload['optionsUrl'])->toContain('/field/grade/options')
        ->and($payload['type'])->toBe('lookup');
});

it('can be the source of another field option scope', function () {
    $lookup = Lookup::make('Grade', 'grade');
    $student = Lookup::make('Student', 'student')->scopedBy('grade', 'grade_id');

    expect($lookup->hasOptionScopes())->toBeFalse()
        ->and($student->getOptionScopes()['grade']->column)->toBe('grade_id');
});
