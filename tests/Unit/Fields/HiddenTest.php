<?php

use Said\Nadota\Http\Fields\Hidden;
use Said\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = Hidden::make('Secret Key', 'secret_key');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('secret_key')
        ->and($field->fieldData->name)->toBe('Secret Key');
});

it('has correct type and component', function () {
    $field = Hidden::make('Secret Key', 'secret_key');
    
    expect($field->fieldData->type->value)->toBe('hidden')
        ->and($field->fieldData->component)->toBe('field-hidden');
});

it('is hidden from index by default', function () {
    $field = Hidden::make('Secret Key', 'secret_key');
    $request = createNadotaRequest();
    
    expect($field->isShowOnIndex($request, null))->toBeFalse();
});

it('is hidden from detail by default', function () {
    $field = Hidden::make('Secret Key', 'secret_key');
    $request = createNadotaRequest();
    
    expect($field->isShowOnDetail($request, null))->toBeFalse();
});

it('is still shown on forms by default', function () {
    $field = Hidden::make('Secret Key', 'secret_key');
    $request = createNadotaRequest();
    
    expect($field->isShowOnCreation($request, null))->toBeTrue()
        ->and($field->isShowOnUpdate($request, null))->toBeTrue();
});

it('resolves value from model', function () {
    $model = TestModel::factory()->make(['name' => 'secret-value']);
    $field = Hidden::make('Secret Key', 'name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('secret-value');
});

it('can be made visible on index if needed', function () {
    $field = Hidden::make('Secret Key', 'secret_key')->showOnIndex();
    $request = createNadotaRequest();
    
    expect($field->isShowOnIndex($request, null))->toBeTrue();
});

it('serializes to array correctly', function () {
    $model = TestModel::factory()->make(['name' => 'hidden-value']);
    $field = Hidden::make('Secret Key', 'name');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('name', 'Secret Key')
        ->toHaveKey('attribute', 'name')
        ->toHaveKey('type', 'hidden')
        ->toHaveKey('component', 'field-hidden')
        ->toHaveKey('value', 'hidden-value')
        ->toHaveKey('showOnIndex', false)
        ->toHaveKey('showOnDetail', false);
});