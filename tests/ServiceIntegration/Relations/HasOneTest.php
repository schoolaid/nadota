<?php

use SchoolAid\Nadota\Http\Fields\Relations\HasOne;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Models\Profile;

it('can be instantiated', function () {
    $field = HasOne::make('Profile', 'profile');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('profile')
        ->and($field->fieldData->name)->toBe('Profile');
});

it('returns hasOne relation type', function () {
    $field = HasOne::make('Profile', 'profile');
    
    expect($field->relationType())->toBe('hasOne');
});

it('can set related model', function () {
    $field = HasOne::make('Profile', 'profile')
        ->relatedModel(Profile::class);
    
    expect($field->relatedModelClass)->toBe(Profile::class);
});

it('resolves relationship value from model', function () {
    $testModel = TestModel::factory()->create();
    $profile = Profile::factory()->create([
        'test_model_id' => $testModel->id,
        'bio' => 'User bio content'
    ]);
    
    $field = HasOne::make('Profile', 'profile')
        ->relatedModel(Profile::class)
        ->relationAttribute('bio');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);
    
    expect($value)->toBeArray()
        ->toHaveKey('key', $profile->id)
        ->toHaveKey('label', 'User bio content');
});

it('returns null when no relationship exists', function () {
    $testModel = TestModel::factory()->create();
    
    $field = HasOne::make('Profile', 'profile')
        ->relatedModel(Profile::class);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);
    
    expect($value)->toBeNull();
});

it('generates options for select', function () {
    Profile::factory()->count(3)->create();
    
    $field = HasOne::make('Profile', 'profile')
        ->relatedModel(Profile::class)
        ->relationAttribute('bio');
    
    $options = $field->getOptions();
    
    expect($options)->toHaveCount(3)
        ->and($options[0])->toHaveKey('value')
        ->and($options[0])->toHaveKey('label');
});

it('can be made sortable', function () {
    $field = HasOne::make('Profile', 'profile')
        ->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = HasOne::make('Profile', 'profile')
        ->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('applies sorting with join', function () {
    $testModel = new TestModel();
    $field = HasOne::make('Profile', 'profile')
        ->relatedModel(Profile::class)
        ->relationAttribute('bio');
    
    $query = $testModel->newQuery();
    $sortedQuery = $field->applySorting($query, 'asc', $testModel);
    
    expect($sortedQuery)->toBeInstanceOf(\Illuminate\Database\Eloquent\Builder::class);
});

it('adds validation rules for related model existence', function () {
    $field = HasOne::make('Profile', 'profile')
        ->relatedModel(Profile::class);
    
    $rules = $field->getRules();
    
    expect($rules)->toContain('exists:profiles,id');
});

it('serializes to array correctly', function () {
    $testModel = TestModel::factory()->create();
    $profile = Profile::factory()->create([
        'test_model_id' => $testModel->id,
        'bio' => 'User bio'
    ]);
    
    $field = HasOne::make('Profile', 'profile')
        ->relatedModel(Profile::class)
        ->relationAttribute('bio')
        ->sortable()
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $testModel, null);
    
    expect($array)
        ->toHaveKey('name', 'Profile')
        ->toHaveKey('attribute', 'profile')
        ->toHaveKey('relationType', 'hasOne')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->toHaveKey('value')
        ->toHaveKey('options');
});

it('can set display attribute', function () {
    $field = HasOne::make('Profile', 'profile')
        ->relationAttribute('bio');
    
    expect($field->getAttributeForDisplay())->toBe('bio');
});

it('uses id as default foreign key', function () {
    $field = HasOne::make('Profile', 'profile');
    
    expect($field->getForeignKey())->toBe('id');
});

it('is typically shown on index', function () {
    $field = HasOne::make('Profile', 'profile');
    $request = createNadotaRequest();
    
    expect($field->isShowOnIndex($request, null))->toBeTrue();
});