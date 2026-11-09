<?php

use SchoolAid\Nadota\Http\Fields\Hidden;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = Hidden::make('Secret Key', 'secret_key');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('secret_key')
        ->and($field->fieldData->label)->toBe('Secret Key');
});

it('has correct type and component', function () {
    $field = Hidden::make('Secret Key', 'secret_key');
    
    expect($field->getType())->toBe('hidden')
        ->and($field->getComponent())->toBe('FieldHidden');
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
        ->toHaveKey('label', 'Secret Key')
        ->toHaveKey('attribute', 'name')
        ->toHaveKey('type', 'hidden')
        ->toHaveKey('component', 'FieldHidden')
        ->toHaveKey('value', 'hidden-value')
        ->toHaveKey('showOnIndex', false)
        ->toHaveKey('showOnDetail', false);
});

it('is still filled and validated like any other field', function () {
    $field = Hidden::make('UUID', 'name')->required();
    $model = new TestModel();

    $field->fill(createNadotaRequest(['name' => 'u-1']), $model);

    expect($model->name)->toBe('u-1')
        ->and($field->getRules())->toBe(['required'])
        ->and($field->resolveForStore(createNadotaRequest(), $model, null, 'u-2'))->toBe('u-2');
});

it('offers its default to the create form', function () {
    $array = Hidden::make('Token', 'token')->default('abc')->toArray(createNadotaRequest(['action' => 'create']));

    expect($array['default'])->toBe('abc')
        ->and($array['showOnCreation'])->toBeTrue();
});
