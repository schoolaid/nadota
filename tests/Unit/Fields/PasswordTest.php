<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use SchoolAid\Nadota\Http\Fields\Contracts\FillableFieldInterface;
use SchoolAid\Nadota\Http\Fields\Password;
use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated and is form-only', function () {
    $field = Password::make('Password', 'password');
    $request = createNadotaRequest();

    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('password')
        ->and($field)->toBeInstanceOf(FillableFieldInterface::class)
        ->and($field->getType())->toBe('password')
        ->and($field->getComponent())->toBe('FieldPassword')
        ->and($field->isShowOnCreation($request))->toBeTrue()
        ->and($field->isShowOnUpdate($request))->toBeTrue()
        ->and($field->isShowOnIndex($request))->toBeFalse()
        ->and($field->isShowOnDetail($request))->toBeFalse();
});

it('hashes a non-empty password when filling', function () {
    Hash::shouldReceive('make')->once()->with('secret')->andReturn('hashed-secret');

    $field = Password::make('Password', 'password');
    $model = new TestModel();

    $field->fill(new Request(['password' => 'secret']), $model);

    expect($model->password)->toBe('hashed-secret');
});

it('does not touch the attribute when password is null (no update)', function () {
    Hash::shouldReceive('make')->never();

    $field = Password::make('Password', 'password');
    $model = new TestModel();
    $model->password = 'existing-hash';

    $field->fill(new Request(['password' => null]), $model);

    expect($model->password)->toBe('existing-hash');
});

it('does not touch the attribute when password is an empty string', function () {
    Hash::shouldReceive('make')->never();

    $field = Password::make('Password', 'password');
    $model = new TestModel();
    $model->password = 'existing-hash';

    $field->fill(new Request(['password' => '']), $model);

    expect($model->password)->toBe('existing-hash');
});

it('does not touch the attribute when password key is absent', function () {
    Hash::shouldReceive('make')->never();

    $field = Password::make('Password', 'password');
    $model = new TestModel();
    $model->password = 'existing-hash';

    $field->fill(new Request([]), $model);

    expect($model->password)->toBe('existing-hash');
});

it('returns null from resolveForUpdate when value is null instead of throwing', function () {
    $field = Password::make('Password', 'password');
    $model = new TestModel();

    expect($field->resolveForUpdate(new Request(), $model, null, null))->toBeNull();
});
it('is required on creation and optional on update', function () {
    $field = Password::make('Password', 'password');

    expect($field->getRulesFor(false))->toBe(['string', 'required'])
        ->and($field->getRulesFor(true))->toBe(['string', 'nullable'])
        ->and($field->isRequired(false))->toBeTrue()
        ->and($field->isRequired(true))->toBeFalse();
});

it('adds min length and confirmation rules', function () {
    $field = Password::make('Password', 'password')->minLength(8)->confirmable();

    expect($field->getRules())->toBe(['min:8', 'string', 'confirmed']);
});

it('enforces length and confirmation when validating', function () {
    $rules = ['password' => Password::make('Password', 'password')->minLength(8)->confirmable()->getRulesFor(false)];

    expect(Validator::make(['password' => 'short', 'password_confirmation' => 'short'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['password' => 'long-enough', 'password_confirmation' => 'different'], $rules)->fails())->toBeTrue()
        ->and(Validator::make([], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['password' => 'long-enough', 'password_confirmation' => 'long-enough'], $rules)->passes())->toBeTrue();
});

it('lets an update omit the password', function () {
    $rules = ['password' => Password::make('Password', 'password')->minLength(8)->getRulesFor(true)];

    expect(Validator::make(['password' => null], $rules)->passes())->toBeTrue()
        ->and(Validator::make([], $rules)->passes())->toBeTrue();
});

it('exposes its options in props', function () {
    expect(Password::make('Password', 'password')->toArray(createNadotaRequest())['props'])
        ->toBe(['confirmable' => false, 'minLength' => null, 'showStrengthIndicator' => false])
        ->and(Password::make('Password', 'password')->confirmable()->minLength(10)->showStrengthIndicator()->toArray(createNadotaRequest())['props'])
        ->toBe(['confirmable' => true, 'minLength' => 10, 'showStrengthIndicator' => true]);
});

it('never resolves the stored hash', function () {
    $model = new TestModel();
    $model->password = 'stored-hash';

    $array = Password::make('Password', 'password')->toArray(createNadotaRequest(), $model);

    expect($array['value'])->toBeNull()
        ->and(Password::make('Password', 'password')->resolve(createNadotaRequest(), $model, null))->toBeNull();
});

it('hashes the value on store and update', function () {
    $field = Password::make('Password', 'password');
    $stored = $field->resolveForStore(new Request(), new TestModel(), null, 'secret');
    $updated = $field->resolveForUpdate(new Request(), new TestModel(), null, 'secret');

    expect($stored)->not->toBe('secret')
        ->and(Hash::check('secret', $stored))->toBeTrue()
        ->and(Hash::check('secret', $updated))->toBeTrue()
        ->and($field->resolveForStore(new Request(), new TestModel(), null, ''))->toBeNull();
});

it('uses a custom hashing callback', function () {
    $field = Password::make('Password', 'password')->hashUsing(fn (string $value) => 'custom:' . strrev($value));
    $model = new TestModel();

    $field->fill(new Request(['password' => 'abc']), $model);

    expect($model->password)->toBe('custom:cba')
        ->and($field->resolveForStore(new Request(), $model, null, 'xyz'))->toBe('custom:zyx');
});

it('does not fill a readonly, disabled or computed password', function (callable $configure) {
    Hash::shouldReceive('make')->never();

    $model = new TestModel();
    $model->password = 'existing-hash';

    $configure(Password::make('Password', 'password'))->fill(new Request(['password' => 'new']), $model);

    expect($model->password)->toBe('existing-hash');
})->with([
    'readonly' => [fn ($field) => $field->readonly()],
    'disabled' => [fn ($field) => $field->disabled()],
    'computed' => [fn ($field) => $field->computed()],
]);
