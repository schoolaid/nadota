<?php

use SchoolAid\Nadota\Http\Fields\Json;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Models\User;
use Illuminate\Http\Request;

it('can create a json field', function () {
    $field = new Json('Settings', 'settings');

    expect($field->getName())->toBe('Settings');
    expect($field->getAttribute())->toBe('settings');
    expect($field->getType())->toBe(FieldType::JSON->value);
});

it('can set pretty print', function () {
    $field = Json::make('Settings', 'settings')
        ->prettyPrint(false);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['prettyPrint'])->toBeFalse();
});

it('can set max height', function () {
    $field = Json::make('Settings', 'settings')
        ->maxHeight(300);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['maxHeight'])->toBe(300);
});

it('can set editable', function () {
    $field = Json::make('Settings', 'settings')
        ->editable(false);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['editable'])->toBeFalse();
});

it('can set indent size', function () {
    $field = Json::make('Settings', 'settings')
        ->indentSize(4);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['indentSize'])->toBe(4);
});

it('can set show line numbers', function () {
    $field = Json::make('Settings', 'settings')
        ->showLineNumbers(true);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['showLineNumbers'])->toBeTrue();
});

it('resolves json string to array', function () {
    $field = Json::make('Settings', 'settings');

    $user = new User();
    $user->settings = '{"theme": "dark", "notifications": true}';

    $request = NadotaRequest::create('/');
    $resolved = $field->resolve($request, $user, null);

    expect($resolved)->toBeArray();
    expect($resolved['theme'])->toBe('dark');
    expect($resolved['notifications'])->toBeTrue();
});

it('resolves array as is', function () {
    $field = Json::make('Settings', 'settings');

    $user = new User();
    $user->settings = ['theme' => 'dark', 'notifications' => true];

    $request = NadotaRequest::create('/');
    $resolved = $field->resolve($request, $user, null);

    expect($resolved)->toBeArray();
    expect($resolved['theme'])->toBe('dark');
    expect($resolved['notifications'])->toBeTrue();
});

it('fills model with json data', function () {
    $field = Json::make('Settings', 'settings');

    $user = new User();
    $request = Request::create('/', 'POST', [
        'settings' => '{"theme": "dark", "notifications": true}'
    ]);

    $field->fill($request, $user);

    // Since User model doesn't cast settings to json, it should store as string
    expect($user->settings)->toBe('{"theme": "dark", "notifications": true}');
});

it('handles invalid json gracefully', function () {
    $field = Json::make('Settings', 'settings');

    $user = new User();
    $user->settings = 'invalid json';

    $request = NadotaRequest::create('/');
    $resolved = $field->resolve($request, $user, null);

    expect($resolved)->toBe('invalid json');
});

it('can chain multiple settings', function () {
    $field = Json::make('Config', 'config')
        ->prettyPrint(false)
        ->maxHeight(500)
        ->editable(false)
        ->indentSize(2)
        ->showLineNumbers(true);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['prettyPrint'])->toBeFalse();
    expect($array['props']['maxHeight'])->toBe(500);
    expect($array['props']['editable'])->toBeFalse();
    expect($array['props']['indentSize'])->toBe(2);
    expect($array['props']['showLineNumbers'])->toBeTrue();
});