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


it('accepts a callback per context and hands it the request and the record', function () {
    $received = null;
    $field = Input::make('Grade', 'grade')->showOnDetail(function ($request, $record) use (&$received) {
        $received = [$request, $record];

        return $record === 'visible-record';
    });
    $request = createNadotaRequest();

    expect($field->isShowOnDetail($request, 'visible-record'))->toBeTrue()
        ->and($received)->toBe([$request, 'visible-record'])
        ->and($field->isShowOnDetail($request, 'other'))->toBeFalse()
        // the other contexts keep their own flag
        ->and($field->isShowOnIndex($request, 'other'))->toBeTrue();
});

it('casts callback results to bool', function () {
    $field = Input::make('Grade', 'grade')
        ->showOnIndex(fn () => 1)
        ->showOnDetail(fn () => null);

    expect($field->isShowOnIndex())->toBeTrue()
        ->and($field->isShowOnDetail())->toBeFalse();
});

it('applies showWhen and hideWhen to every context', function () {
    $hidden = Input::make('Secret', 'secret')->hideWhen(fn () => true);
    $shown = Input::make('Admin', 'admin')->showWhen(fn () => false);

    foreach (['isShowOnIndex', 'isShowOnDetail', 'isShowOnCreation', 'isShowOnUpdate'] as $method) {
        expect($hidden->{$method}())->toBeFalse()
            ->and($shown->{$method}())->toBeFalse();
    }
});

it('lets hideWhen win over showWhen', function () {
    $field = Input::make('Secret', 'secret')
        ->showWhen(fn () => true)
        ->hideWhen(fn () => true);

    expect($field->isShowOnIndex())->toBeFalse();
});

it('cannot make a field visible in a context it was hidden from', function () {
    $field = Input::make('Secret', 'secret')->hideFromIndex()->showWhen(fn () => true);

    expect($field->isShowOnIndex())->toBeFalse()
        ->and($field->isShowOnDetail())->toBeTrue();
});

it('hands the request and the record to showWhen and hideWhen', function () {
    $received = [];
    $field = Input::make('Secret', 'secret')
        ->hideWhen(function (...$args) use (&$received) {
            $received['hide'] = $args;

            return false;
        })
        ->showWhen(function (...$args) use (&$received) {
            $received['show'] = $args;

            return true;
        });
    $request = createNadotaRequest();

    $field->isShowOnUpdate($request, 'record');

    expect($received)->toBe(['hide' => [$request, 'record'], 'show' => [$request, 'record']]);
});

it('treats onlyWhen as an alias of showWhen', function () {
    $field = Input::make('Admin', 'admin')->onlyWhen(fn ($request) => $request?->get('role') === 'admin');

    expect($field->isShowOnIndex(createNadotaRequest(['role' => 'admin'])))->toBeTrue()
        ->and($field->isShowOnIndex(createNadotaRequest(['role' => 'guest'])))->toBeFalse();
});

it('can be re-enabled for a context after an only* shortcut', function () {
    $field = Input::make('Status', 'status')->onlyOnDetail()->showOnIndex();

    expect($field->isShowOnIndex())->toBeTrue()
        ->and($field->isShowOnCreation())->toBeFalse();
});

it('evaluates callbacks in toArray against the given model', function () {
    $field = Input::make('Grade', 'grade')->showOnUpdate(fn ($request, $model) => $model?->name === 'Ana');

    expect($field->toArray(createNadotaRequest(), createTestModel(['name' => 'Ana']))['showOnUpdate'])->toBeTrue()
        ->and($field->toArray(createNadotaRequest(), createTestModel(['name' => 'Bob']))['showOnUpdate'])->toBeFalse();
});
