<?php

use SchoolAid\Nadota\Http\Fields\Json;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Models\TestModel;
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

    $model = new TestModel();
    $model->settings = '{"theme": "dark", "notifications": true}';

    $request = NadotaRequest::create('/');
    $resolved = $field->resolve($request, $model, null);

    expect($resolved)->toBeArray();
    expect($resolved['theme'])->toBe('dark');
    expect($resolved['notifications'])->toBeTrue();
});

it('resolves array as is', function () {
    $field = Json::make('Settings', 'settings');

    $model = new TestModel();
    $model->settings = ['theme' => 'dark', 'notifications' => true];

    $request = NadotaRequest::create('/');
    $resolved = $field->resolve($request, $model, null);

    expect($resolved)->toBeArray();
    expect($resolved['theme'])->toBe('dark');
    expect($resolved['notifications'])->toBeTrue();
});

it('fills model with json data', function () {
    $field = Json::make('Settings', 'settings');

    $model = new TestModel();
    $request = Request::create('/', 'POST', [
        'settings' => '{"theme": "dark", "notifications": true}'
    ]);

    $field->fill($request, $model);

    // TestModel doesn't cast settings to json: the text is decoded and stored as JSON text again
    expect($model->settings)->toBe('{"theme":"dark","notifications":true}');
});

it('handles invalid json gracefully', function () {
    $field = Json::make('Settings', 'settings');

    $model = new TestModel();
    $model->settings = 'invalid json';

    $request = NadotaRequest::create('/');
    $resolved = $field->resolve($request, $model, null);

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

it('is rendered by the FieldJson component and hidden from index', function () {
    $field = Json::make('Settings', 'settings');

    expect($field->getComponent())->toBe('FieldJson')
        ->and($field->isShowOnIndex(createNadotaRequest()))->toBeFalse()
        ->and($field->toArray(createNadotaRequest())['props'])->toBe([
            'prettyPrint' => true, 'editable' => true, 'indentSize' => 2, 'showLineNumbers' => false,
        ]);
});

it('resolves null and scalars untouched', function () {
    $model = new TestModel();

    expect(Json::make('Meta', 'metadata')->resolve(createNadotaRequest(), $model, null))->toBeNull();

    $model->age = 5;
    expect(Json::make('Age', 'age')->resolve(createNadotaRequest(), $model, null))->toBe(5);
});

it('decodes a JSON string payload when filling an array cast attribute', function () {
    $model = new TestModel();

    Json::make('Meta', 'metadata')->fill(Request::create('/', 'POST', ['metadata' => '{"a":1}']), $model);

    expect($model->metadata)->toBe(['a' => 1]);
});

it('keeps an array payload as is when filling', function () {
    $model = new TestModel();

    Json::make('Meta', 'metadata')->fill(Request::create('/', 'POST', ['metadata' => ['a' => 1]]), $model);

    expect($model->metadata)->toBe(['a' => 1]);
});

it('leaves the attribute alone when the request does not carry it', function () {
    $model = new TestModel();
    $model->metadata = ['keep' => true];

    Json::make('Meta', 'metadata')->fill(Request::create('/', 'POST', []), $model);

    expect($model->metadata)->toBe(['keep' => true]);
});

it('decodes a JSON string before storing it in an array cast column', function () {
    $model = new TestModel(); // metadata is cast to array
    $field = Json::make('Meta', 'metadata');

    // The persist service runs normalizeInput() on the request before validating and storing
    $stored = $field->resolveForStore(createNadotaRequest(), $model, null, $field->normalizeInput('{"a":1}'));

    expect($stored)->toBe(['a' => 1]);
});

it('rejects malformed JSON', function () {
    $field = Json::make('S', 'settings');
    $value = $field->normalizeInput('{oops');

    $validator = \Illuminate\Support\Facades\Validator::make(['settings' => $value], ['settings' => $field->getRules()]);

    expect($validator->fails())->toBeTrue();
});
