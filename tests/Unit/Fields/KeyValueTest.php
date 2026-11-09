<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\KeyValue;
use SchoolAid\Nadota\Tests\Models\TestModel;

/** metadata is cast to array on TestModel, description is a plain text column. */
function keyValueSchemaField(string $attribute = 'metadata'): KeyValue
{
    return KeyValue::make('Settings', $attribute)->schema([
        'theme' => ['label' => 'Theme', 'type' => 'select', 'options' => ['light' => 'Light', 'dark' => 'Dark'], 'default' => 'light'],
        'locale' => ['label' => 'Locale', 'readonly' => true],
        'token' => ['label' => 'Token', 'hidden' => true],
        'age' => ['label' => 'Age', 'type' => 'number', 'rules' => ['integer', 'min:0']],
        'nickname' => 'Nickname',
    ]);
}

it('is a key-value field rendered by the FieldKeyValue component and hidden from index', function () {
    $field = KeyValue::make('Settings', 'settings');

    expect($field)->toBeField()
        ->and($field->getType())->toBe(FieldType::KEY_VALUE->value)
        ->and($field->getComponent())->toBe('FieldKeyValue')
        ->and($field->isShowOnIndex(createNadotaRequest()))->toBeFalse()
        ->and($field->isShowOnDetail(createNadotaRequest()))->toBeTrue();
});

it('extracts labels, types, options, defaults, readonly and hidden keys from the schema', function () {
    $props = keyValueSchemaField()->toArray(createNadotaRequest())['props'];

    expect($props['labels'])->toBe(['theme' => 'Theme', 'locale' => 'Locale', 'token' => 'Token', 'age' => 'Age', 'nickname' => 'Nickname'])
        ->and($props['inputTypes'])->toBe(['theme' => 'select', 'age' => 'number'])
        ->and($props['keyOptions'])->toBe(['theme' => ['light' => 'Light', 'dark' => 'Dark']])
        ->and($props['defaults'])->toBe(['theme' => 'light'])
        ->and($props['readonlyKeys'])->toBe(['locale'])
        ->and($props['hiddenKeys'])->toBe(['token'])
        ->and(array_keys($props['schema']))->toBe(['theme', 'locale', 'token', 'age', 'nickname']);
});

it('has sensible defaults in props', function () {
    expect(KeyValue::make('Settings', 'settings')->toArray(createNadotaRequest())['props'])->toBe([
        'schema' => [],
        'allowNewKeys' => false,
        'showLabels' => true,
        'labels' => [],
        'readonlyKeys' => [],
        'hiddenKeys' => [],
        'inputTypes' => [],
        'keyOptions' => [],
        'defaults' => [],
        'groups' => [],
        'asTable' => false,
    ]);
});

it('configures keys fluently', function () {
    $props = KeyValue::make('Settings', 'settings')
        ->keyLabels(['a' => 'A'])
        ->keyLabel('b', 'B')
        ->readonlyKeys('a')
        ->readonlyKeys(['a', 'b'])
        ->hideKeys('secret')
        ->inputType('b', 'select', ['x' => 'X'])
        ->inputType('c', 'textarea')
        ->group('General', ['a', 'b'])
        ->defaults(['a' => 1])
        ->defaults(['b' => 2])
        ->allowNewKeys()
        ->asTable()
        ->useKeys()
        ->toArray(createNadotaRequest())['props'];

    expect($props['labels'])->toBe(['a' => 'A', 'b' => 'B'])
        ->and(array_values($props['readonlyKeys']))->toBe(['a', 'b'])
        ->and(array_values($props['hiddenKeys']))->toBe(['secret'])
        ->and($props['inputTypes'])->toBe(['b' => 'select', 'c' => 'textarea'])
        ->and($props['keyOptions'])->toBe(['b' => ['x' => 'X']])
        ->and($props['groups'])->toBe(['General' => ['a', 'b']])
        ->and($props['defaults'])->toBe(['a' => 1, 'b' => 2])
        ->and($props['allowNewKeys'])->toBeTrue()
        ->and($props['asTable'])->toBeTrue()
        ->and($props['showLabels'])->toBeFalse();
});

it('resolves stored pairs, filling defaults and dropping hidden keys', function () {
    $model = new TestModel();
    $model->metadata = ['locale' => 'es', 'token' => 'secret', 'nickname' => 'Ani'];

    expect(keyValueSchemaField()->resolve(createNadotaRequest(), $model, null))
        ->toBe(['locale' => 'es', 'nickname' => 'Ani', 'theme' => 'light']);
});

it('decodes a JSON string column and treats garbage as empty', function (mixed $stored, array $expected) {
    $model = new TestModel();
    $model->description = $stored;

    expect(KeyValue::make('Settings', 'description')->resolve(createNadotaRequest(), $model, null))->toBe($expected);
})->with([
    'json object' => ['{"a":"1","b":"2"}', ['a' => '1', 'b' => '2']],
    'invalid json' => ['not json', []],
    'null' => [null, []],
]);

it('keeps readonly and hidden keys and drops keys outside the schema when filling', function () {
    $model = new TestModel();
    $model->metadata = ['locale' => 'es', 'token' => 'secret'];
    $model->syncOriginal();

    keyValueSchemaField()->fill(new Request(['metadata' => [
        'theme' => 'dark',
        'locale' => 'en',     // readonly: ignored
        'token' => 'hacked',  // hidden: ignored
        'evil' => 'x',        // not in schema: dropped
    ]]), $model);

    expect($model->metadata)->toBe(['theme' => 'dark', 'locale' => 'es', 'token' => 'secret']);
});

it('keeps unknown keys when new keys are allowed', function () {
    $model = new TestModel();

    keyValueSchemaField()->allowNewKeys()->fill(new Request(['metadata' => ['custom' => 'yes']]), $model);

    expect($model->metadata)->toBe(['custom' => 'yes']);
});

it('decodes a JSON string payload when filling', function () {
    $model = new TestModel();

    KeyValue::make('Settings', 'metadata')->fill(new Request(['metadata' => '{"a":1}']), $model);

    expect($model->metadata)->toBe(['a' => 1]);
});

it('json-encodes the pairs for a column without an array cast', function () {
    $model = new TestModel();

    KeyValue::make('Settings', 'description')->fill(new Request(['description' => ['a' => 1]]), $model);

    expect($model->description)->toBe('{"a":1}');
});

it('stores null for an empty payload when nullable', function (mixed $payload) {
    $model = new TestModel();
    $model->metadata = ['a' => 1];

    KeyValue::make('Settings', 'metadata')->nullable()->fill(new Request(['metadata' => $payload]), $model);

    expect($model->metadata)->toBeNull();
})->with(['empty string' => [''], 'empty object' => ['{}'], 'empty array' => ['[]'], 'null' => [null]]);

it('does not fill a readonly or disabled field', function () {
    $model = new TestModel();
    $model->metadata = ['a' => 1];

    KeyValue::make('Settings', 'metadata')->readonly()->fill(new Request(['metadata' => ['a' => 2]]), $model);
    KeyValue::make('Settings', 'metadata')->disabled()->fill(new Request(['metadata' => ['a' => 3]]), $model);

    expect($model->metadata)->toBe(['a' => 1]);
});

it('accepts arrays and JSON encoded objects and rejects anything else', function (mixed $value, bool $passes, bool $nullable) {
    $field = KeyValue::make('Settings', 'settings');

    if ($nullable) {
        $field->nullable();
    }

    $validator = Validator::make(['settings' => $value], ['settings' => $field->getRules()]);

    expect($validator->passes())->toBe($passes);
})->with([
    'array' => [['a' => 1], true, false],
    'json string' => ['{"a":1}', true, false],
    'json scalar' => ['"text"', false, false],
    'plain text' => ['hello', false, false],
    'integer' => [5, false, false],
    'empty object when nullable' => ['{}', true, true],
    'null when nullable' => [null, true, true],
]);

it('validates each key against the rules declared in the schema', function () {
    $field = keyValueSchemaField('settings');
    $rules = ['settings' => $field->getRules()];

    if (method_exists($field, 'getNestedRules')) {
        $rules += $field->getNestedRules();
    }

    expect(Validator::make(['settings' => ['age' => 'old']], $rules)->fails())->toBeTrue();
})->skip('ISSUE FLD-017: KeyValue schema/keyRules() rules are stored but never turned into validation rules');

it('sends the frontend only rules it can read', function () {
    $rules = KeyValue::make('Settings', 'settings')->nullable()->toArray(createNadotaRequest())['rules'];

    expect(json_encode($rules))->not->toContain('{}');
})->skip('ISSUE FLD-025: closure rules (KeyValue, MultiImage, VariableText) end up in toArray()["rules"] and reach the frontend as {}');
