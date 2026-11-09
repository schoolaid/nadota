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

it('takes no search callback: an argument to searchable() is silently dropped', function () {
    // The previous version of this test passed a closure and only asserted it
    // was not called, which read as "custom search callbacks are supported".
    // They are not (see TRT-012); the closure is discarded by PHP.
    $called = false;

    $field = Input::make('Name', 'name')
        ->searchable(function () use (&$called) {
            $called = true;
        });

    expect((new ReflectionMethod($field, 'searchable'))->getNumberOfParameters())->toBe(0)
        ->and($field->isSearchable())->toBeTrue()
        ->and($called)->toBeFalse();
});

it('can be globally searchable, which implies searchable', function () {
    $field = Input::make('Name', 'name');

    expect($field->isSearchableGlobally())->toBeFalse();

    $field->searchableGlobally();

    expect($field->isSearchable())->toBeTrue()
        ->and($field->isSearchableGlobally())->toBeTrue();
});

it('keeps the global flag when notSearchable() is called afterwards', function () {
    // notSearchable() only clears the plain flag (see TRT-012).
    $field = Input::make('Name', 'name')->searchableGlobally()->notSearchable();

    expect($field->isSearchable())->toBeFalse()
        ->and($field->isSearchableGlobally())->toBeTrue();
});

it('can set search weight', function () {
    $field = Input::make('Name', 'name')
        ->searchable()
        ->searchWeight(10);

    expect($field->getSearchWeight())->toBe(10);
});

it('has no search weight by default', function () {
    $field = Input::make('Name', 'name')->searchable();

    expect($field->getSearchWeight())->toBeNull();
});