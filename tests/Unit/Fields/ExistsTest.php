<?php

use SchoolAid\Nadota\Http\Fields\Exists;
use SchoolAid\Nadota\Http\Filters\ExistsFilter;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = Exists::make('Has Comments', 'comments');

    expect($field)->toBeInstanceOf(Exists::class)
        ->and($field->getName())->toBe('Has Comments')
        ->and($field->getAttribute())->toBe('comments_exists')
        ->and($field->getExistsRelation())->toBe('comments');
});

it('creates attribute from relation name with _exists suffix', function () {
    $field = Exists::make('Has Profile', 'profile');

    expect($field->getAttribute())->toBe('profile_exists');
});

it('is computed and readonly by default', function () {
    $field = Exists::make('Has Comments', 'comments');

    expect($field->isComputed())->toBeTrue()
        ->and($field->isReadonly())->toBeTrue();
});

it('is shown on index and detail but not on forms', function () {
    $field = Exists::make('Has Comments', 'comments');

    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeTrue()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeFalse()
        ->and($field->isShowOnUpdate($request, null))->toBeFalse();
});

it('can set constraint callback', function () {
    $constraint = fn($q) => $q->where('active', true);
    $field = Exists::make('Has Active Comments', 'comments')
        ->constraint($constraint);

    expect($field->getExistsConstraint())->toBe($constraint);
});

it('resolves value from model', function () {
    $model = new TestModel();
    $model->comments_exists = true;

    $field = Exists::make('Has Comments', 'comments');
    $request = createNadotaRequest();
    $resource = createTestResource();

    $value = $field->resolve($request, $model, $resource);

    expect($value)->toBeTrue();
});

it('resolves false when attribute is missing', function () {
    $model = new TestModel();

    $field = Exists::make('Has Comments', 'comments');
    $request = createNadotaRequest();
    $resource = createTestResource();

    $value = $field->resolve($request, $model, $resource);

    expect($value)->toBeFalse();
});

it('returns empty array for getColumnsForSelect', function () {
    $field = Exists::make('Has Comments', 'comments');

    $columns = $field->getColumnsForSelect(TestModel::class);

    expect($columns)->toBeArray()
        ->and($columns)->toBeEmpty();
});

it('does not fill model data', function () {
    $model = new TestModel();
    $field = Exists::make('Has Comments', 'comments');
    $request = createNadotaRequest(['comments_exists' => true]);

    $field->fill($request, $model);

    // Should not set any attributes
    expect(isset($model->comments_exists))->toBeFalse();
});

it('returns ExistsFilter when filterable', function () {
    $field = Exists::make('Has Comments', 'comments')
        ->filterable();

    $filters = $field->filters();

    expect($filters)->toBeArray()
        ->and($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(ExistsFilter::class)
        ->and($filters[0]->name())->toBe('Has Comments')
        ->and($filters[0]->key())->toBe('comments_exists')
        ->and($filters[0]->getRelation())->toBe('comments');
});

it('returns ExistsFilter with constraint when filterable', function () {
    $constraint = fn($q) => $q->where('active', true);
    $field = Exists::make('Has Active Comments', 'comments')
        ->filterable()
        ->constraint($constraint);

    $filters = $field->filters();

    expect($filters)->toBeArray()
        ->and($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(ExistsFilter::class)
        ->and($filters[0]->getConstraint())->toBe($constraint);
});

it('returns empty array when not filterable', function () {
    $field = Exists::make('Has Comments', 'comments');

    $filters = $field->filters();

    expect($filters)->toBeArray()
        ->and($filters)->toBeEmpty();
});
