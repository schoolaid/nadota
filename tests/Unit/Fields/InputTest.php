<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = Input::make('Test Field', 'test_attribute');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('test_attribute')
        ->and($field->getName())->toBe('Test Field')
        ->and($field->getLabel())->toBe('Test Field');
});

it('sets correct default type and component', function () {
    $field = Input::make('Test Field', 'test_attribute');
    
    expect($field->type)->toBe('text')
        ->and($field->getComponent())->toBe('FieldText');
});

it('can set label', function () {
    $field = Input::make('Test Field', 'test_attribute')
        ->label('Custom Label');
    
    expect($field->getLabel())->toBe('Custom Label');
});

it('can set placeholder', function () {
    $field = Input::make('Test Field', 'test_attribute')
        ->placeholder('Enter your text here');
    
    expect($field->getPlaceholder())->toBe('Enter your text here');
});

it('can be made sortable', function () {
    $field = Input::make('Test Field', 'test_attribute')
        ->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made searchable', function () {
    $field = Input::make('Test Field', 'test_attribute')
        ->searchable();
    
    expect($field->isSearchable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = Input::make('Test Field', 'test_attribute')
        ->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('can be made required', function () {
    $field = Input::make('Test Field', 'test_attribute')
        ->required();
    
    expect($field->isRequired())->toBeTrue();
});

it('can set validation rules', function () {
    $field = Input::make('Test Field', 'test_attribute')
        ->rules(['min:3', 'max:255']);
    
    expect($field->getRules())
        ->toContain('min:3')
        ->toContain('max:255');
});

it('resolves value from model', function () {
    $model = createTestModel(['name' => 'John Doe']);
    $field = Input::make('Name', 'name');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('John Doe');
});

it('can hide from index', function () {
    $field = Input::make('Test Field', 'test_attribute')
        ->hideFromIndex();
    $request = createNadotaRequest();
    
    expect($field->isShowOnIndex($request, null))->toBeFalse();
});

it('can hide from detail', function () {
    $field = Input::make('Test Field', 'test_attribute')
        ->hideFromDetail();
    $request = createNadotaRequest();
    
    expect($field->isShowOnDetail($request, null))->toBeFalse();
});

it('can hide from creation', function () {
    $field = Input::make('Test Field', 'test_attribute')
        ->hideFromCreation();
    $request = createNadotaRequest();
    
    expect($field->isShowOnCreation($request, null))->toBeFalse();
});

it('can hide from update', function () {
    $field = Input::make('Test Field', 'test_attribute')
        ->hideFromUpdate();
    $request = createNadotaRequest();
    
    expect($field->isShowOnUpdate($request, null))->toBeFalse();
});

it('serializes to array correctly', function () {
    $model = createTestModel(['name' => 'John Doe']);
    $field = Input::make('Name', 'name')
        ->sortable()
        ->searchable()
        ->required()
        ->rules(['min:2']);
        
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Name')
        ->toHaveKey('attribute', 'name')
        ->toHaveKey('sortable', true)
        ->toHaveKey('searchable', true)
        ->toHaveKey('required', true)
        ->toHaveKey('value', 'John Doe')
        ->toHaveKey('rules')
        ->and($array['rules'])->toContain('min:2');
});

it('can set default value', function () {
    $model = createTestModel();
    $field = Input::make('Name', 'name')
        ->default('Default Name');
        
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('Default Name');
});