<?php

use Said\Nadota\Http\Fields\Checkbox;

it('can be instantiated', function () {
    $field = Checkbox::make('Accept Terms', 'accept_terms');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('accept_terms')
        ->and($field->getName())->toBe('Accept Terms');
});

it('resolves boolean value from model', function () {
    $model = createTestModel(['is_active' => true]);
    $field = Checkbox::make('Active', 'is_active');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeTrue();
});

it('resolves false boolean value from model', function () {
    $model = createTestModel(['is_active' => false]);
    $field = Checkbox::make('Active', 'is_active');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeFalse();
});

it('can set checked value', function () {
    $field = Checkbox::make('Accept Terms', 'accept_terms')
        ->trueValue('yes');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('trueValue', 'yes');
});

it('can set unchecked value', function () {
    $field = Checkbox::make('Accept Terms', 'accept_terms')
        ->falseValue('no');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('falseValue', 'no');
});

it('can be made sortable', function () {
    $field = Checkbox::make('Active', 'is_active')
        ->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = Checkbox::make('Active', 'is_active')
        ->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('serializes to array correctly', function () {
    $model = createTestModel(['is_active' => true]);
    $field = Checkbox::make('Active', 'is_active')
        ->trueValue('yes')
        ->falseValue('no')
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('name', 'Active')
        ->toHaveKey('attribute', 'is_active')
        ->toHaveKey('filterable', true)
        ->toHaveKey('value', true)
        ->toHaveKey('props')
        ->and($array['props']['trueValue'])->toBe('yes')
        ->and($array['props']['falseValue'])->toBe('no');
});

it('handles null values as false', function () {
    $model = createTestModel(['is_active' => null]);
    $field = Checkbox::make('Active', 'is_active');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeFalse();
});

it('recognizes custom true values', function () {
    $model = createTestModel(['accept_terms' => 'yes']);
    $field = Checkbox::make('Accept Terms', 'accept_terms')
        ->trueValue('yes')
        ->falseValue('no');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeTrue();
});

it('recognizes custom false values', function () {
    $model = createTestModel(['status' => 'no']);
    $field = Checkbox::make('Accept Terms', 'status')
        ->trueValue('yes')
        ->falseValue('no');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeFalse();
});