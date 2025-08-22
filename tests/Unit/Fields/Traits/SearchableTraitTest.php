<?php

use SchoolAid\Nadota\Http\Fields\Input;

it('field is not searchable by default', function () {
    $field = Input::make('Name', 'name');
    
    expect($field->isSearchable())->toBeFalse();
});

it('can make field searchable', function () {
    $field = Input::make('Name', 'name')
        ->searchable();
    
    expect($field->isSearchable())->toBeTrue();
});

it('can make field not searchable', function () {
    $field = Input::make('Name', 'name')
        ->searchable()
        ->notSearchable();
    
    expect($field->isSearchable())->toBeFalse();
});

it('includes searchable in array representation', function () {
    $field = Input::make('Name', 'name')
        ->searchable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('searchable', true);
});

it('can set search callback', function () {
    $callbackSet = false;
    
    $field = Input::make('Name', 'name')
        ->searchable(function ($query, $value, $attribute) use (&$callbackSet) {
            $callbackSet = true;
            return $query->where($attribute, 'like', "%{$value}%");
        });
    
    expect($field->isSearchable())->toBeTrue()
        ->and($callbackSet)->toBeFalse(); // Callback not called during setup
});

it('can be globally searchable', function () {
    $field = Input::make('Name', 'name')
        ->searchableGlobally();
    
    expect($field->isSearchable())->toBeTrue();
});

it('can set search weight', function () {
    $field = Input::make('Name', 'name')
        ->searchable()
        ->searchWeight(10);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('searchWeight', 10);
});