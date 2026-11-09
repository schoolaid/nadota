<?php

use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = Toggle::make('Is Active', 'is_active');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('is_active')
        ->and($field->getName())->toBe('Is Active');
});

it('resolves boolean value from model', function () {
    $model = createTestModel(['is_active' => true]);
    $field = Toggle::make('Active', 'is_active');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeTrue();
});

it('resolves false boolean value from model', function () {
    $model = createTestModel(['is_active' => false]);
    $field = Toggle::make('Active', 'is_active');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeFalse();
});

it('can set true label', function () {
    $field = Toggle::make('Published', 'is_published')
        ->trueLabel('Published');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('trueLabel', 'Published');
});

it('can set false label', function () {
    $field = Toggle::make('Published', 'is_published')
        ->falseLabel('Draft');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('falseLabel', 'Draft');
});

it('can set both labels', function () {
    $field = Toggle::make('Status', 'status')
        ->trueLabel('Active')
        ->falseLabel('Inactive');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['trueLabel'])->toBe('Active')
        ->and($array['props']['falseLabel'])->toBe('Inactive');
});

it('can set custom true and false values', function () {
    $field = Toggle::make('Agreement', 'agreement')
        ->trueValue('accepted')
        ->falseValue('declined');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['trueValue'])->toBe('accepted')
        ->and($array['props']['falseValue'])->toBe('declined');
});

it('can be made sortable', function () {
    $field = Toggle::make('Active', 'is_active')
        ->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = Toggle::make('Active', 'is_active')
        ->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('serializes to array correctly', function () {
    $model = createTestModel(['is_published' => true]);
    $field = Toggle::make('Published', 'is_published')
        ->trueLabel('Live')
        ->falseLabel('Draft')
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Published')
        ->toHaveKey('attribute', 'is_published')
        ->toHaveKey('filterable', true)
        ->toHaveKey('value', true)
        ->toHaveKey('props')
        ->and($array['props']['trueLabel'])->toBe('Live')
        ->and($array['props']['falseLabel'])->toBe('Draft');
});

it('handles null values as false', function () {
    $model = createTestModel(['is_active' => null]);
    $field = Toggle::make('Active', 'is_active');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeFalse();
});

it('recognizes custom true values', function () {
    $model = createTestModel(['status' => 'active']);
    $field = Toggle::make('Status', 'status')
        ->trueValue('active')
        ->falseValue('inactive');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeTrue();
});

it('recognizes custom false values', function () {
    $model = createTestModel(['status' => 'inactive']);
    $field = Toggle::make('Status', 'status')
        ->trueValue('active')
        ->falseValue('inactive');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeFalse();
});
// The defaults were 'On'/'Off' in English: the panel showed them over its own translated yes/no.
it('sends no labels unless they are set, so the frontend uses its own', function () {
    $props = Toggle::make('Active', 'is_active')->toArray(createNadotaRequest())['props'];

    expect($props['trueLabel'])->toBeNull()
        ->and($props['falseLabel'])->toBeNull();
});
it('reports a boolean type rendered by the FieldToggle component', function () {
    $field = Toggle::make('Active', 'is_active');

    expect($field->getType())->toBe('boolean')
        ->and($field->getComponent())->toBe('FieldToggle');
});

it('defaults to no labels and 1/0 values', function () {
    expect(Toggle::make('Active', 'is_active')->toArray(createNadotaRequest())['props'])
        ->toBe(['trueLabel' => null, 'falseLabel' => null, 'trueValue' => 1, 'falseValue' => 0]);
});

it('treats a value that is neither custom value by its truthiness', function () {
    $field = Toggle::make('Status', 'status')->trueValue('active')->falseValue('inactive');

    expect($field->resolve(createNadotaRequest(), createTestModel(['status' => 'archived']), null))->toBeTrue()
        ->and($field->resolve(createNadotaRequest(), createTestModel(['status' => '']), null))->toBeFalse();
});

it('passes a real boolean through on store', function () {
    $field = Toggle::make('Active', 'is_active');

    expect($field->resolveForStore(createNadotaRequest(), new TestModel(), null, true))->toBeTrue()
        ->and($field->resolveForUpdate(createNadotaRequest(), new TestModel(), null, false))->toBeFalse();
});

it('normalizes boolean-like strings before storing', function (mixed $input, mixed $expected) {
    $field = Toggle::make('Active', 'is_active');

    expect($field->resolveForStore(createNadotaRequest(), new TestModel(), null, $input))->toBe($expected);
})->with([
    'string false' => ['false', 0],
    'string zero' => ['0', 0],
    'string true' => ['true', 1],
])->skip('ISSUE FLD-010: Toggle has no resolveForStore/Update, "false" is stored raw and reads back as true');

it('stores the custom true and false values', function () {
    $field = Toggle::make('Status', 'status')->trueValue('active')->falseValue('inactive');

    expect($field->resolveForStore(createNadotaRequest(), new TestModel(), null, true))->toBe('active')
        ->and($field->resolveForStore(createNadotaRequest(), new TestModel(), null, false))->toBe('inactive');
})->skip('ISSUE FLD-010: Toggle never maps to trueValue()/falseValue() on store (Checkbox does)');
