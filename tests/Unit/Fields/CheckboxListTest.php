<?php

use SchoolAid\Nadota\Http\Fields\CheckboxList;

it('can be instantiated', function () {
    $field = CheckboxList::make('Features', 'features');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('features')
        ->and($field->getName())->toBe('Features');
});

it('can set options', function () {
    $field = CheckboxList::make('Features', 'features')
        ->options([
            'wifi' => 'WiFi',
            'parking' => 'Parking',
            'pool' => 'Swimming Pool'
        ]);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('options')
        ->and($array['props']['options'])->toHaveCount(3);
});

it('can set minimum selections', function () {
    $field = CheckboxList::make('Skills', 'skills')
        ->min(2);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['minSelections'])->toBe(2);
});

it('can set maximum selections', function () {
    $field = CheckboxList::make('Interests', 'interests')
        ->max(5);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['maxSelections'])->toBe(5);
});

it('can set limit using limit method', function () {
    $field = CheckboxList::make('Tags', 'tags')
        ->limit(3);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['maxSelections'])->toBe(3);
});

it('can set inline display', function () {
    $field = CheckboxList::make('Options', 'options')
        ->inline();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['inline'])->toBeTrue();
});

it('formats options correctly', function () {
    $field = CheckboxList::make('Features', 'features')
        ->options([
            'wifi' => 'WiFi',
            'parking' => 'Parking'
        ]);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['options'])->toBe([
        ['value' => 'wifi', 'label' => 'WiFi'],
        ['value' => 'parking', 'label' => 'Parking']
    ]);
});

it('accepts pre-formatted options', function () {
    $options = [
        ['value' => 'a', 'label' => 'Option A'],
        ['value' => 'b', 'label' => 'Option B']
    ];
    
    $field = CheckboxList::make('Choices', 'choices')
        ->options($options);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['options'])->toBe($options);
});

it('resolves array value from model', function () {
    $model = createTestModel(['features' => ['wifi', 'parking']]);
    $field = CheckboxList::make('Features', 'features');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe(['wifi', 'parking']);
});

it('resolves JSON string value from model', function () {
    $model = createTestModel(['features' => '["wifi","parking","pool"]']);
    $field = CheckboxList::make('Features', 'features');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe(['wifi', 'parking', 'pool']);
});

it('handles null value as empty array', function () {
    $model = createTestModel(['features' => null]);
    $field = CheckboxList::make('Features', 'features');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe([]);
});

it('adds validation rules for min and max', function () {
    $field = CheckboxList::make('Skills', 'skills')
        ->min(2)
        ->max(5);
    
    $rules = $field->getRules();
    
    expect($rules)
        ->toContain('array')
        ->toContain('min:2')
        ->toContain('max:5');
});

it('serializes to array correctly', function () {
    $model = createTestModel(['features' => ['wifi', 'pool']]);
    $field = CheckboxList::make('Features', 'features')
        ->options([
            'wifi' => 'WiFi',
            'parking' => 'Parking',
            'pool' => 'Swimming Pool'
        ])
        ->min(1)
        ->max(3)
        ->inline();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Features')
        ->toHaveKey('attribute', 'features')
        ->toHaveKey('value', ['wifi', 'pool'])
        ->toHaveKey('props')
        ->and($array['props']['inline'])->toBeTrue()
        ->and($array['props']['minSelections'])->toBe(1)
        ->and($array['props']['maxSelections'])->toBe(3)
        ->and($array['props']['options'])->toHaveCount(3);
});