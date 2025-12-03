<?php

use SchoolAid\Nadota\Http\Fields\Toggle;

it('can be instantiated', function () {
    $field = Toggle::make('Is Active', 'is_active');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('is_active')
        ->and($field->getName())->toBe('Is Active');
});

it('resolves boolean value from model', function () {
    $model = createTestModel(['is_active' => true]);
    $field = Toggle::make('Active', 'is_active');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeTrue();
});

it('resolves false boolean value from model', function () {
    $model = createTestModel(['is_active' => false]);
    $field = Toggle::make('Active', 'is_active');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeFalse();
});

it('can set true label', function () {
    $field = Toggle::make('Published', 'is_published')
        ->trueLabel('Published');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('trueLabel', 'Published');
});

it('can set false label', function () {
    $field = Toggle::make('Published', 'is_published')
        ->falseLabel('Draft');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('falseLabel', 'Draft');
});

it('can set both labels', function () {
    $field = Toggle::make('Status', 'status')
        ->trueLabel('Active')
        ->falseLabel('Inactive');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['trueLabel'])->toBe('Active')
        ->and($array['props']['falseLabel'])->toBe('Inactive');
});

it('can set custom true and false values', function () {
    $field = Toggle::make('Agreement', 'agreement')
        ->trueValue('accepted')
        ->falseValue('declined');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['trueValue'])->toBe('accepted')
        ->and($array['props']['falseValue'])->toBe('declined');
});

it('can be made sortable', function () {
    $field = Toggle::make('Active', 'is_active')
        ->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = Toggle::make('Active', 'is_active')
        ->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('serializes to array correctly', function () {
    $model = createTestModel(['is_published' => true]);
    $field = Toggle::make('Published', 'is_published')
        ->trueLabel('Live')
        ->falseLabel('Draft')
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Published')
        ->toHaveKey('attribute', 'is_published')
        ->toHaveKey('filterable', true)
        ->toHaveKey('value', true)
        ->toHaveKey('props')
        ->and($array['props']['trueLabel'])->toBe('Live')
        ->and($array['props']['falseLabel'])->toBe('Draft');
});

it('handles null values as false', function () {
    $model = createTestModel(['is_active' => null]);
    $field = Toggle::make('Active', 'is_active');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeFalse();
});

it('recognizes custom true values', function () {
    $model = createTestModel(['status' => 'active']);
    $field = Toggle::make('Status', 'status')
        ->trueValue('active')
        ->falseValue('inactive');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeTrue();
});

it('recognizes custom false values', function () {
    $model = createTestModel(['status' => 'inactive']);
    $field = Toggle::make('Status', 'status')
        ->trueValue('active')
        ->falseValue('inactive');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeFalse();
});