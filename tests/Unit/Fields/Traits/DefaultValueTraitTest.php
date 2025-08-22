<?php

use Said\Nadota\Http\Fields\Input;

it('field has no default value by default', function () {
    $field = Input::make('Name', 'name');
    
    expect($field->hasDefault())->toBeFalse();
});

it('can set default value', function () {
    $field = Input::make('Status', 'status')
        ->default('active');
    
    expect($field->hasDefault())->toBeTrue();
});

it('resolves default value when model attribute is empty', function () {
    $model = createTestModel(['name' => null]);
    $field = Input::make('Name', 'name')
        ->default('Default Name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('Default Name');
});

it('uses model value when available instead of default', function () {
    $model = createTestModel(['name' => 'Actual Name']);
    $field = Input::make('Name', 'name')
        ->default('Default Name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('Actual Name');
});

it('can use callback for default value', function () {
    $model = createTestModel(['name' => null]);
    $field = Input::make('Created At', 'created_at')
        ->default(function ($request, $model, $resource) {
            return now()->format('Y-m-d');
        });
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe(now()->format('Y-m-d'));
});

it('can use model attribute as default', function () {
    $model = createTestModel(['email' => 'test@example.com', 'name' => null]);
    $field = Input::make('Name', 'name')
        ->defaultFromAttribute('email');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('test@example.com');
});

it('can resolve nested default values', function () {
    $model = createTestModel(['metadata' => ['title' => 'Test Title'], 'name' => null]);
    $field = Input::make('Name', 'name')
        ->defaultFromAttribute('metadata.title');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('Test Title');
});

it('handles null default gracefully', function () {
    $model = createTestModel(['name' => null]);
    $field = Input::make('Name', 'name')
        ->default(null);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeNull();
});

it('resolves boolean default values correctly', function () {
    $model = createTestModel(['is_active' => null]);
    $field = Input::make('Active', 'is_active')
        ->default(true);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeTrue();
});

it('resolves array default values correctly', function () {
    $model = createTestModel(['metadata' => null]);
    $defaultArray = ['key' => 'value'];
    $field = Input::make('Metadata', 'metadata')
        ->default($defaultArray);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe($defaultArray);
});