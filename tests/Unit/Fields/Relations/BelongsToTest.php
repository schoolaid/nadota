<?php

use Said\Nadota\Http\Fields\Relations\BelongsTo;
use Said\Nadota\Tests\Models\TestModel;
use Said\Nadota\Tests\Models\RelatedModel;

it('can be instantiated', function () {
    $field = BelongsTo::make('Test Model', 'testModel');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('testModel')
        ->and($field->fieldData->name)->toBe('Test Model');
});

it('returns belongsTo relation type', function () {
    $field = BelongsTo::make('Test Model', 'testModel');
    
    expect($field->relationType())->toBe('belongsTo');
});

it('can set related model', function () {
    $field = BelongsTo::make('Test Model', 'testModel')
        ->relatedModel(TestModel::class);
    
    expect($field->relatedModelClass)->toBe(TestModel::class);
});

it('resolves relationship value from model', function () {
    $testModel = TestModel::factory()->create(['name' => 'Parent Model']);
    $relatedModel = RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Child Model'
    ]);
    
    $field = BelongsTo::make('Test Model', 'testModel')
        ->relatedModel(TestModel::class)
        ->relationAttribute('name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $relatedModel, null);
    
    expect($value)->toBeArray()
        ->toHaveKey('key', $testModel->id)
        ->toHaveKey('label', 'Parent Model');
});

it('returns null when no relationship exists', function () {
    $relatedModel = RelatedModel::factory()->make(['test_model_id' => null]);
    
    $field = BelongsTo::make('Test Model', 'testModel')
        ->relatedModel(TestModel::class);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $relatedModel, null);
    
    expect($value)->toBeNull();
});

it('generates options for select', function () {
    TestModel::factory()->count(3)->create();
    
    $field = BelongsTo::make('Test Model', 'testModel')
        ->relatedModel(TestModel::class)
        ->relationAttribute('name');
    
    $options = $field->getOptions();
    
    expect($options)->toHaveCount(3)
        ->and($options[0])->toHaveKey('value')
        ->and($options[0])->toHaveKey('label');
});

it('can be made sortable', function () {
    $field = BelongsTo::make('Test Model', 'testModel')
        ->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = BelongsTo::make('Test Model', 'testModel')
        ->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('applies sorting with join', function () {
    $relatedModel = new RelatedModel();
    $field = BelongsTo::make('Test Model', 'testModel')
        ->relatedModel(TestModel::class)
        ->relationAttribute('name');
    
    $query = $relatedModel->newQuery();
    $sortedQuery = $field->applySorting($query, 'asc', $relatedModel);
    
    expect($sortedQuery)->toBeInstanceOf(\Illuminate\Database\Eloquent\Builder::class);
});

it('adds validation rules for related model existence', function () {
    $field = BelongsTo::make('Test Model', 'testModel')
        ->relatedModel(TestModel::class);
    
    $rules = $field->getRules();
    
    expect($rules)->toContain('exists:test_models,id');
});

it('serializes to array correctly', function () {
    $testModel = TestModel::factory()->create(['name' => 'Parent Model']);
    $relatedModel = RelatedModel::factory()->create([
        'test_model_id' => $testModel->id
    ]);
    
    $field = BelongsTo::make('Test Model', 'testModel')
        ->relatedModel(TestModel::class)
        ->relationAttribute('name')
        ->sortable()
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $relatedModel, null);
    
    expect($array)
        ->toHaveKey('name', 'Test Model')
        ->toHaveKey('attribute', 'testModel')
        ->toHaveKey('relationType', 'belongsTo')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->toHaveKey('value')
        ->toHaveKey('options');
});

it('can set display attribute', function () {
    $field = BelongsTo::make('Test Model', 'testModel')
        ->relationAttribute('email');
    
    expect($field->getAttributeForDisplay())->toBe('email');
});

it('uses id as default foreign key', function () {
    $field = BelongsTo::make('Test Model', 'testModel');
    
    expect($field->getForeignKey())->toBe('id');
});