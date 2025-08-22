<?php

use Said\Nadota\Http\Fields\Input;

it('field has no validation rules by default', function () {
    $field = Input::make('Name', 'name');
    
    expect($field->getRules())->toBe([]);
});

it('can add validation rules', function () {
    $field = Input::make('Email', 'email')
        ->rules(['email', 'unique:users,email']);
    
    expect($field->getRules())
        ->toContain('email')
        ->toContain('unique:users,email');
});

it('can make field required', function () {
    $field = Input::make('Name', 'name')
        ->required();
    
    expect($field->isRequired())->toBeTrue()
        ->and($field->getRules())->toContain('required');
});

it('can make field nullable', function () {
    $field = Input::make('Name', 'name')
        ->nullable();
    
    expect($field->getRules())->toContain('nullable');
});

it('can add single validation rule', function () {
    $field = Input::make('Age', 'age')
        ->rules('numeric');
    
    expect($field->getRules())->toContain('numeric');
});

it('can add rules with closure', function () {
    $field = Input::make('Name', 'name')
        ->rules(function () {
            return ['min:2', 'max:100'];
        });
    
    expect($field->getRules())
        ->toContain('min:2')
        ->toContain('max:100');
});

it('can add conditional rules', function () {
    $field = Input::make('Discount', 'discount')
        ->sometimes(function () {
            // Simulate condition where discount is required
            return ['required', 'numeric', 'min:0', 'max:100'];
        });
    
    $rules = $field->getRules();
    
    expect($rules)
        ->toContain('required')
        ->toContain('numeric')
        ->toContain('min:0')
        ->toContain('max:100');
});

it('can combine multiple rule methods', function () {
    $field = Input::make('Email', 'email')
        ->required()
        ->nullable()
        ->rules(['email', 'unique:users,email']);
    
    $rules = $field->getRules();
    
    expect($rules)
        ->toContain('required')
        ->toContain('nullable')
        ->toContain('email')
        ->toContain('unique:users,email');
});

it('includes validation rules in array representation', function () {
    $field = Input::make('Email', 'email')
        ->rules(['email', 'required']);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['rules'])
        ->toContain('email')
        ->toContain('required');
});

it('includes required in array representation', function () {
    $field = Input::make('Name', 'name')
        ->required();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['required'])->toBeTrue();
});

it('can use requiredIf validation', function () {
    $field = Input::make('Company', 'company')
        ->requiredIf('is_business', true);
    
    expect($field->getRules())->toContain('required_if:is_business,1');
});

it('can use requiredUnless validation', function () {
    $field = Input::make('Reason', 'reason')
        ->requiredUnless('approved', true);
    
    expect($field->getRules())->toContain('required_unless:approved,1');
});

it('removes duplicate rules', function () {
    $field = Input::make('Email', 'email')
        ->rules(['email'])
        ->rules(['email', 'unique:users,email']);
    
    $rules = $field->getRules();
    
    // Should only have one 'email' rule despite adding it twice
    $emailCount = count(array_filter($rules, fn($rule) => $rule === 'email'));
    expect($emailCount)->toBe(1);
});