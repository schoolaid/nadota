<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use SchoolAid\Nadota\Http\Fields\Contracts\FillableFieldInterface;
use SchoolAid\Nadota\Http\Fields\Password;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated and is form-only', function () {
    $field = Password::make('Password', 'password');

    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('password')
        ->and($field)->toBeInstanceOf(FillableFieldInterface::class);
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