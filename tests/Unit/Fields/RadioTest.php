<?php

use Said\Nadota\Http\Fields\Radio;
use Said\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = Radio::make('Status', 'status');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('status')
        ->and($field->fieldData->name)->toBe('Status');
});

it('has correct type and component', function () {
    $field = Radio::make('Status', 'status');
    
    expect($field->fieldData->type->value)->toBe('radio')
        ->and($field->fieldData->component)->toBe('field-radio');
});

it('can set options', function () {
    $field = Radio::make('Status', 'status')
        ->options(['active' => 'Active', 'inactive' => 'Inactive']);
    
    expect($field->options)->toBe(['active' => 'Active', 'inactive' => 'Inactive']);
});

it('can be made inline', function () {
    $field = Radio::make('Status', 'status')->inline();
    
    expect($field->inline)->toBeTrue();
});

it('can be made not inline', function () {
    $field = Radio::make('Status', 'status')->inline(false);
    
    expect($field->inline)->toBeFalse();
});

it('formats options correctly', function () {
    $field = Radio::make('Status', 'status')
        ->options(['active' => 'Active', 'inactive' => 'Inactive']);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['options'])->toBeArray()
        ->and($array['props']['options'][0])->toHaveKey('value', 'active')
        ->and($array['props']['options'][0])->toHaveKey('label', 'Active')
        ->and($array['props']['options'][1])->toHaveKey('value', 'inactive')
        ->and($array['props']['options'][1])->toHaveKey('label', 'Inactive');
});

it('resolves value from model', function () {
    $model = TestModel::factory()->make(['name' => 'active']);
    $field = Radio::make('Status', 'name')
        ->options(['active' => 'Active', 'inactive' => 'Inactive']);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('active');
});

it('can be made sortable', function () {
    $field = Radio::make('Status', 'status')->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = Radio::make('Status', 'status')->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('serializes to array correctly', function () {
    $model = TestModel::factory()->make(['name' => 'active']);
    $field = Radio::make('Status', 'name')
        ->options(['active' => 'Active', 'inactive' => 'Inactive'])
        ->inline()
        ->sortable()
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('name', 'Status')
        ->toHaveKey('attribute', 'name')
        ->toHaveKey('type', 'radio')
        ->toHaveKey('component', 'field-radio')
        ->toHaveKey('value', 'active')
        ->toHaveKey('props')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true);
        
    expect($array['props'])
        ->toHaveKey('options')
        ->toHaveKey('inline', true);
});