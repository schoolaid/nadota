<?php

use SchoolAid\Nadota\Http\Fields\Checkbox;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = Checkbox::make('Accept Terms', 'accept_terms');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('accept_terms')
        ->and($field->getName())->toBe('Accept Terms');
});

it('resolves boolean value from model', function () {
    $model = createTestModel(['is_active' => true]);
    $field = Checkbox::make('Active', 'is_active');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeTrue();
});

it('resolves false boolean value from model', function () {
    $model = createTestModel(['is_active' => false]);
    $field = Checkbox::make('Active', 'is_active');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeFalse();
});

it('can set checked value', function () {
    $field = Checkbox::make('Accept Terms', 'accept_terms')
        ->trueValue('yes');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('trueValue', 'yes');
});

it('can set unchecked value', function () {
    $field = Checkbox::make('Accept Terms', 'accept_terms')
        ->falseValue('no');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('falseValue', 'no');
});

it('can be made sortable', function () {
    $field = Checkbox::make('Active', 'is_active')
        ->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = Checkbox::make('Active', 'is_active')
        ->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('serializes to array correctly', function () {
    $model = createTestModel(['is_active' => true]);
    $field = Checkbox::make('Active', 'is_active')
        ->trueValue('yes')
        ->falseValue('no')
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Active')
        ->toHaveKey('attribute', 'is_active')
        ->toHaveKey('filterable', true)
        ->toHaveKey('value', true)
        ->toHaveKey('props')
        ->and($array['props']['trueValue'])->toBe('yes')
        ->and($array['props']['falseValue'])->toBe('no');
});

it('handles null values as false', function () {
    $model = createTestModel(['is_active' => null]);
    $field = Checkbox::make('Active', 'is_active');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeFalse();
});

it('recognizes custom true values', function () {
    // 'status' has no cast on TestModel. The previous version used accept_terms, which is cast
    // to boolean, so 'yes' was already true before the custom value was ever compared.
    $model = createTestModel(['status' => 'yes']);
    $field = Checkbox::make('Accept Terms', 'status')
        ->trueValue('yes')
        ->falseValue('no');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeTrue();
});

it('recognizes custom false values', function () {
    $model = createTestModel(['status' => 'no']);
    $field = Checkbox::make('Accept Terms', 'status')
        ->trueValue('yes')
        ->falseValue('no');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeFalse();
});
it('is a checkbox field rendered by the FieldCheckbox component', function () {
    $field = Checkbox::make('Accept Terms', 'accept_terms');

    expect($field->getType())->toBe('checkbox')
        ->and($field->getComponent())->toBe('FieldCheckbox')
        ->and($field->toArray(createNadotaRequest())['props'])->toBe(['trueValue' => 1, 'falseValue' => 0]);
});

it('treats a value that is neither custom value by its truthiness', function () {
    $field = Checkbox::make('Flag', 'status')->trueValue('yes')->falseValue('no');

    expect($field->resolve(createNadotaRequest(), createTestModel(['status' => 'maybe']), null))->toBeTrue()
        ->and($field->resolve(createNadotaRequest(), createTestModel(['status' => '0']), null))->toBeFalse();
});

it('converts submitted booleans and boolean-like strings to the stored values', function (mixed $input, mixed $expected) {
    $field = Checkbox::make('Accept Terms', 'accept_terms');
    $request = createNadotaRequest();

    expect($field->resolveForStore($request, new TestModel(), null, $input))->toBe($expected)
        ->and($field->resolveForUpdate($request, new TestModel(), null, $input))->toBe($expected);
})->with([
    'true' => [true, 1],
    'false' => [false, 0],
    'string true' => ['true', 1],
    'string false' => ['false', 0],
    'string one' => ['1', 1],
    'string zero' => ['0', 0],
    'on' => ['on', 1],
    'off' => ['off', 0],
    'yes' => ['yes', 1],
]);

it('stores the custom true and false values', function () {
    $field = Checkbox::make('Accept Terms', 'status')->trueValue('yes')->falseValue('no');
    $request = createNadotaRequest();

    expect($field->resolveForStore($request, new TestModel(), null, true))->toBe('yes')
        ->and($field->resolveForStore($request, new TestModel(), null, 'false'))->toBe('no')
        ->and($field->resolveForUpdate($request, new TestModel(), null, '1'))->toBe('yes');
});

it('keeps null on store unless there is a default', function () {
    $request = createNadotaRequest();

    expect(Checkbox::make('A', 'a')->resolveForStore($request, new TestModel(), null, null))->toBeNull()
        ->and(Checkbox::make('A', 'a')->default(true)->resolveForStore($request, new TestModel(), null, null))->toBeTrue()
        ->and(Checkbox::make('A', 'a')->default(true)->resolveForUpdate($request, new TestModel(), null, null))->toBeTrue();
});
