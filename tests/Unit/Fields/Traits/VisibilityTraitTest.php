<?php

use SchoolAid\Nadota\Http\Fields\Input;

it('field is visible everywhere by default', function () {
    $field = Input::make('Name', 'name');
    $request = createNadotaRequest();
    
    expect($field->isShowOnIndex($request, null))->toBeTrue()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeTrue()
        ->and($field->isShowOnUpdate($request, null))->toBeTrue();
});

it('can hide field from index', function () {
    $field = Input::make('Password', 'password')
        ->hideFromIndex();
    $request = createNadotaRequest();
    
    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeTrue();
});

it('can hide field from detail', function () {
    $field = Input::make('Password', 'password')
        ->hideFromDetail();
    $request = createNadotaRequest();
    
    expect($field->isShowOnDetail($request, null))->toBeFalse()
        ->and($field->isShowOnIndex($request, null))->toBeTrue();
});

it('can hide field from creation', function () {
    $field = Input::make('ID', 'id')
        ->hideFromCreation();
    $request = createNadotaRequest();
    
    expect($field->isShowOnCreation($request, null))->toBeFalse()
        ->and($field->isShowOnUpdate($request, null))->toBeTrue();
});

it('can hide field from update', function () {
    $field = Input::make('Created At', 'created_at')
        ->hideFromUpdate();
    $request = createNadotaRequest();
    
    expect($field->isShowOnUpdate($request, null))->toBeFalse()
        ->and($field->isShowOnCreation($request, null))->toBeTrue();
});

it('can show only on index', function () {
    $field = Input::make('Status', 'status')
        ->onlyOnIndex();
    $request = createNadotaRequest();
    
    expect($field->isShowOnIndex($request, null))->toBeTrue()
        ->and($field->isShowOnDetail($request, null))->toBeFalse()
        ->and($field->isShowOnCreation($request, null))->toBeFalse()
        ->and($field->isShowOnUpdate($request, null))->toBeFalse();
});

it('can show only on detail', function () {
    $field = Input::make('Details', 'details')
        ->onlyOnDetail();
    $request = createNadotaRequest();
    
    expect($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnCreation($request, null))->toBeFalse()
        ->and($field->isShowOnUpdate($request, null))->toBeFalse();
});

it('can show only on forms', function () {
    $field = Input::make('Password', 'password')
        ->onlyOnForms();
    $request = createNadotaRequest();
    
    expect($field->isShowOnCreation($request, null))->toBeTrue()
        ->and($field->isShowOnUpdate($request, null))->toBeTrue()
        ->and($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeFalse();
});

it('can show except on forms', function () {
    $field = Input::make('Created At', 'created_at')
        ->exceptOnForms();
    $request = createNadotaRequest();
    
    expect($field->isShowOnIndex($request, null))->toBeTrue()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeFalse()
        ->and($field->isShowOnUpdate($request, null))->toBeFalse();
});

it('includes visibility flags in array representation', function () {
    $field = Input::make('Name', 'name')
        ->hideFromIndex()
        ->hideFromCreation();
    $request = createNadotaRequest();
    
    $array = $field->toArray($request, null, null);
    
    expect($array)
        ->toHaveKey('showOnIndex', false)
        ->toHaveKey('showOnDetail', true)
        ->toHaveKey('showOnCreation', false)
        ->toHaveKey('showOnUpdate', true);
});

it('can use callback for conditional visibility', function () {
    $field = Input::make('Admin Only', 'admin_field')
        ->showWhen(function ($request, $model) {
            return false; // Simulate non-admin user
        });
    
    $request = createNadotaRequest();
    
    // Field should be hidden when callback returns false
    expect($field->isShowOnIndex($request, null))->toBeFalse();
});

it('can hide based on callback', function () {
    $field = Input::make('Secret', 'secret')
        ->hideWhen(function ($request, $model) {
            return true; // Always hide
        });
    
    $request = createNadotaRequest();
    
    expect($field->isShowOnIndex($request, null))->toBeFalse();
});

