<?php

use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = Number::make('Age', 'age');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('age')
        ->and($field->fieldData->label)->toBe('Age');
});

it('has correct type and component', function () {
    $field = Number::make('Age', 'age');
    
    expect($field->fieldData->type->value)->toBe('number')
        ->and($field->fieldData->component)->toBe('field-number');
});

it('can set minimum value', function () {
    $field = Number::make('Age', 'age')->min(18);
    
    expect($field->min)->toBe(18.0);
});

it('can set maximum value', function () {
    $field = Number::make('Age', 'age')->max(120);
    
    expect($field->max)->toBe(120.0);
});

it('can set step value', function () {
    $field = Number::make('Price', 'price')->step(0.01);
    
    expect($field->step)->toBe(0.01);
});

it('can set all constraints', function () {
    $field = Number::make('Rating', 'rating')
        ->min(1)
        ->max(10)
        ->step(0.5);
    
    expect($field->min)->toBe(1.0)
        ->and($field->max)->toBe(10.0)
        ->and($field->step)->toBe(0.5);
});

it('adds numeric validation rule by default', function () {
    $field = Number::make('Age', 'age');
    
    $rules = $field->getRules();
    expect($rules)->toContain('numeric');
});

it('adds min validation rule when min is set', function () {
    $field = Number::make('Age', 'age')->min(18);
    
    $rules = $field->getRules();
    expect($rules)->toContain('min:18');
});

it('adds max validation rule when max is set', function () {
    $field = Number::make('Age', 'age')->max(120);
    
    $rules = $field->getRules();
    expect($rules)->toContain('max:120');
});

it('adds all validation rules when constraints are set', function () {
    $field = Number::make('Age', 'age')
        ->min(18)
        ->max(120)
        ->required();
    
    $rules = $field->getRules();
    expect($rules)->toContain('numeric')
        ->and($rules)->toContain('min:18')
        ->and($rules)->toContain('max:120')
        ->and($rules)->toContain('required');
});

it('resolves numeric value from model', function () {
    $model = TestModel::factory()->make(['age' => 25]);
    $field = Number::make('Age', 'age');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe(25);
});

it('can be made sortable', function () {
    $field = Number::make('Age', 'age')->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = Number::make('Age', 'age')->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('serializes to array correctly', function () {
    $model = TestModel::factory()->make(['age' => 30]);
    $field = Number::make('Age', 'age')
        ->min(18)
        ->max(120)
        ->step(1)
        ->sortable()
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Age')
        ->toHaveKey('attribute', 'age')
        ->toHaveKey('type', 'number')
        ->toHaveKey('component', 'field-number')
        ->toHaveKey('value', 30)
        ->toHaveKey('props')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true);
        
    expect($array['props'])
        ->toHaveKey('min', 18.0)
        ->toHaveKey('max', 120.0)
        ->toHaveKey('step', 1.0);
});