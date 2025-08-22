<?php

use SchoolAid\Nadota\Http\Fields\Select;

it('can be instantiated', function () {
    $field = Select::make('Status', 'status');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('status')
        ->and($field->getName())->toBe('Status');
});

it('can set options', function () {
    $field = Select::make('Status', 'status')
        ->options([
            'active' => 'Active',
            'inactive' => 'Inactive',
            'pending' => 'Pending'
        ]);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('options')
        ->and($array['props']['options'])->toHaveCount(3);
});

it('can enable multiple selection', function () {
    $field = Select::make('Tags', 'tags')
        ->multiple();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['multiple'])->toBeTrue();
});

it('can make select clearable', function () {
    $field = Select::make('Category', 'category_id')
        ->clearable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['clearable'])->toBeTrue();
});

it('can set placeholder', function () {
    $field = Select::make('Country', 'country')
        ->placeholder('Select a country');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['placeholder'])->toBe('Select a country');
});

it('formats options correctly', function () {
    $field = Select::make('Status', 'status')
        ->options([
            'active' => 'Active',
            'inactive' => 'Inactive'
        ]);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['options'])->toBe([
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'inactive', 'label' => 'Inactive']
    ]);
});

it('accepts pre-formatted options', function () {
    $options = [
        ['value' => 1, 'label' => 'Option 1'],
        ['value' => 2, 'label' => 'Option 2']
    ];
    
    $field = Select::make('Choice', 'choice')
        ->options($options);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['options'])->toBe($options);
});

it('can be made sortable', function () {
    $field = Select::make('Priority', 'priority')
        ->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = Select::make('Status', 'status')
        ->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('resolves single value from model', function () {
    $model = createTestModel(['status' => 'active']);
    $field = Select::make('Status', 'status');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('active');
});

it('resolves multiple values from model', function () {
    $model = createTestModel(['tags' => '["php","laravel"]']);
    $field = Select::make('Tags', 'tags')
        ->multiple();
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe(['php', 'laravel']);
});

it('serializes to array correctly', function () {
    $model = createTestModel(['status' => 'active']);
    $field = Select::make('Status', 'status')
        ->options([
            'active' => 'Active',
            'inactive' => 'Inactive'
        ])
        ->clearable()
        ->placeholder('Choose status');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('name', 'Status')
        ->toHaveKey('attribute', 'status')
        ->toHaveKey('value', 'active')
        ->toHaveKey('props')
        ->and($array['props']['clearable'])->toBeTrue()
        ->and($array['props']['placeholder'])->toBe('Choose status')
        ->and($array['props']['options'])->toHaveCount(2);
});