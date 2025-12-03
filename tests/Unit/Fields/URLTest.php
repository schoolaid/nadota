<?php

use SchoolAid\Nadota\Http\Fields\URL;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = URL::make('Website', 'website_url');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('website_url')
        ->and($field->fieldData->label)->toBe('Website');
});

it('has correct type and component', function () {
    $field = URL::make('Website', 'website_url');
    
    expect($field->fieldData->type->value)->toBe('url')
        ->and($field->fieldData->component)->toBe('field-url');
});

it('adds url validation rule by default', function () {
    $field = URL::make('Website', 'website_url');
    
    $rules = $field->getRules();
    expect($rules)->toContain('url');
});

it('can add additional validation rules', function () {
    $field = URL::make('Website', 'website_url')
        ->required()
        ->rules(['starts_with:https://']);
    
    $rules = $field->getRules();
    expect($rules)->toContain('url')
        ->and($rules)->toContain('required')
        ->and($rules)->toContain('starts_with:https://');
});

it('resolves url value from model', function () {
    $model = TestModel::factory()->make(['name' => 'https://example.com']);
    $field = URL::make('Website', 'name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('https://example.com');
});

it('can be made sortable', function () {
    $field = URL::make('Website', 'website_url')->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = URL::make('Website', 'website_url')->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('can be made searchable', function () {
    $field = URL::make('Website', 'website_url')->searchable();
    
    expect($field->isSearchable())->toBeTrue();
});

it('serializes to array correctly', function () {
    $model = TestModel::factory()->make(['name' => 'https://www.example.com']);
    $field = URL::make('Website', 'name')
        ->required()
        ->sortable()
        ->filterable()
        ->searchable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Website')
        ->toHaveKey('attribute', 'name')
        ->toHaveKey('type', 'url')
        ->toHaveKey('component', 'field-url')
        ->toHaveKey('value', 'https://www.example.com')
        ->toHaveKey('required', true)
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->toHaveKey('searchable', true);
});