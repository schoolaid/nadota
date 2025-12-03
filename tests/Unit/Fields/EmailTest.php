<?php

use SchoolAid\Nadota\Http\Fields\Email;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = Email::make('Email Address', 'email');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('email')
        ->and($field->fieldData->label)->toBe('Email Address');
});

it('has correct type and component', function () {
    $field = Email::make('Email Address', 'email');
    
    expect($field->fieldData->type->value)->toBe('email')
        ->and($field->fieldData->component)->toBe('field-email');
});

it('adds email validation rule by default', function () {
    $field = Email::make('Email Address', 'email');
    
    $rules = $field->getRules();
    expect($rules)->toContain('email');
});

it('can add additional validation rules', function () {
    $field = Email::make('Email Address', 'email')
        ->required()
        ->rules(['unique:users,email']);
    
    $rules = $field->getRules();
    expect($rules)->toContain('email')
        ->and($rules)->toContain('required')
        ->and($rules)->toContain('unique:users,email');
});

it('resolves email value from model', function () {
    $model = TestModel::factory()->make(['email' => 'test@example.com']);
    $field = Email::make('Email Address', 'email');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('test@example.com');
});

it('can be made sortable', function () {
    $field = Email::make('Email Address', 'email')->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = Email::make('Email Address', 'email')->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('can be made searchable', function () {
    $field = Email::make('Email Address', 'email')->searchable();
    
    expect($field->isSearchable())->toBeTrue();
});

it('serializes to array correctly', function () {
    $model = TestModel::factory()->make(['email' => 'user@example.com']);
    $field = Email::make('Email Address', 'email')
        ->required()
        ->sortable()
        ->filterable()
        ->searchable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Email Address')
        ->toHaveKey('attribute', 'email')
        ->toHaveKey('type', 'email')
        ->toHaveKey('component', 'field-email')
        ->toHaveKey('value', 'user@example.com')
        ->toHaveKey('required', true)
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->toHaveKey('searchable', true);
});