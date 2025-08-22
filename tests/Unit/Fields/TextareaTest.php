<?php

use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = Textarea::make('Description', 'description');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('description')
        ->and($field->fieldData->name)->toBe('Description');
});

it('has correct type and component', function () {
    $field = Textarea::make('Description', 'description');
    
    expect($field->fieldData->type->value)->toBe('textarea')
        ->and($field->fieldData->component)->toBe('field-textarea');
});

it('can set rows', function () {
    $field = Textarea::make('Description', 'description')->rows(5);
    
    expect($field->rows)->toBe(5);
});

it('can set cols', function () {
    $field = Textarea::make('Description', 'description')->cols(50);
    
    expect($field->cols)->toBe(50);
});

it('can set both rows and cols', function () {
    $field = Textarea::make('Description', 'description')
        ->rows(8)
        ->cols(60);
    
    expect($field->rows)->toBe(8)
        ->and($field->cols)->toBe(60);
});

it('resolves value from model', function () {
    $model = TestModel::factory()->make(['description' => 'This is a long description that spans multiple lines.']);
    $field = Textarea::make('Description', 'description');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('This is a long description that spans multiple lines.');
});

it('can be made sortable', function () {
    $field = Textarea::make('Description', 'description')->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = Textarea::make('Description', 'description')->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('can be made required', function () {
    $field = Textarea::make('Description', 'description')->required();
    
    $rules = $field->getRules();
    expect($rules)->toContain('required');
});

it('serializes to array correctly', function () {
    $model = TestModel::factory()->make(['description' => 'Multi-line content here']);
    $field = Textarea::make('Description', 'description')
        ->rows(6)
        ->cols(80)
        ->sortable()
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('name', 'Description')
        ->toHaveKey('attribute', 'description')
        ->toHaveKey('type', 'textarea')
        ->toHaveKey('component', 'field-textarea')
        ->toHaveKey('value', 'Multi-line content here')
        ->toHaveKey('props')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true);
        
    expect($array['props'])
        ->toHaveKey('rows', 6)
        ->toHaveKey('cols', 80);
});