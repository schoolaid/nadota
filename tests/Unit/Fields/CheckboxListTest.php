<?php

use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\CheckboxList;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = CheckboxList::make('Features', 'features');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('features')
        ->and($field->getName())->toBe('Features');
});

it('can set options', function () {
    $field = CheckboxList::make('Features', 'features')
        ->options([
            'wifi' => 'WiFi',
            'parking' => 'Parking',
            'pool' => 'Swimming Pool'
        ]);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('options')
        ->and($array['props']['options'])->toHaveCount(3);
});

it('can set minimum selections', function () {
    $field = CheckboxList::make('Skills', 'skills')
        ->min(2);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['minSelections'])->toBe(2);
});

it('can set maximum selections', function () {
    $field = CheckboxList::make('Interests', 'interests')
        ->max(5);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['maxSelections'])->toBe(5);
});

it('can set limit using limit method', function () {
    $field = CheckboxList::make('Tags', 'tags')
        ->limit(3);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['maxSelections'])->toBe(3);
});

it('can set inline display', function () {
    $field = CheckboxList::make('Options', 'options')
        ->inline();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['inline'])->toBeTrue();
});

it('formats options correctly', function () {
    $field = CheckboxList::make('Features', 'features')
        ->options([
            'wifi' => 'WiFi',
            'parking' => 'Parking'
        ]);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['options'])->toBe([
        ['value' => 'wifi', 'label' => 'WiFi'],
        ['value' => 'parking', 'label' => 'Parking']
    ]);
});

it('accepts pre-formatted options', function () {
    $options = [
        ['value' => 'a', 'label' => 'Option A'],
        ['value' => 'b', 'label' => 'Option B']
    ];
    
    $field = CheckboxList::make('Choices', 'choices')
        ->options($options);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['options'])->toBe($options);
});

it('resolves array value from model', function () {
    $model = createTestModel(['features' => ['wifi', 'parking']]);
    $field = CheckboxList::make('Features', 'features');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe(['wifi', 'parking']);
});

it('resolves JSON string value from model', function () {
    // 'description' is a plain text column; on 'features' (array cast) Eloquent would double encode
    // the string and the field would only see it after the cast decoded it back.
    $model = createTestModel(['description' => '["wifi","parking","pool"]']);
    $field = CheckboxList::make('Features', 'description');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe(['wifi', 'parking', 'pool']);
});

it('handles null value as empty array', function () {
    $model = createTestModel(['features' => null]);
    $field = CheckboxList::make('Features', 'features');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe([]);
});

it('adds validation rules for min and max', function () {
    $field = CheckboxList::make('Skills', 'skills')
        ->min(2)
        ->max(5);
    
    $rules = $field->getRules();
    
    expect($rules)
        ->toContain('array')
        ->toContain('min:2')
        ->toContain('max:5');
});

it('serializes to array correctly', function () {
    $model = createTestModel(['features' => ['wifi', 'pool']]);
    $field = CheckboxList::make('Features', 'features')
        ->options([
            'wifi' => 'WiFi',
            'parking' => 'Parking',
            'pool' => 'Swimming Pool'
        ])
        ->min(1)
        ->max(3)
        ->inline();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Features')
        ->toHaveKey('attribute', 'features')
        ->toHaveKey('value', ['wifi', 'pool'])
        ->toHaveKey('props')
        ->and($array['props']['inline'])->toBeTrue()
        ->and($array['props']['minSelections'])->toBe(1)
        ->and($array['props']['maxSelections'])->toBe(3)
        ->and($array['props']['options'])->toHaveCount(3);
});
it('is a checkbox list rendered by the FieldCheckboxList component', function () {
    $field = CheckboxList::make('Features', 'features');

    expect($field->getType())->toBe('checkbox_list')
        ->and($field->getComponent())->toBe('FieldCheckboxList');
});

it('resolves an empty string or non JSON text as no selection', function () {
    $field = CheckboxList::make('Features', 'description');

    expect($field->resolve(createNadotaRequest(), createTestModel(['description' => '']), null))->toBe([])
        ->and($field->resolve(createNadotaRequest(), createTestModel(['description' => 'wifi']), null))->toBe([]);
});

it('keeps an array for a column cast to array and re-indexes it', function () {
    $field = CheckboxList::make('Features', 'features');

    expect($field->resolveForStore(createNadotaRequest(), new TestModel(), null, [2 => 'wifi', 5 => 'pool']))->toBe(['wifi', 'pool'])
        ->and($field->resolveForUpdate(createNadotaRequest(), new TestModel(), null, '["wifi"]'))->toBe(['wifi']);
});

it('json-encodes the selection for a column without an array cast', function () {
    $field = CheckboxList::make('Features', 'description');

    expect($field->resolveForStore(createNadotaRequest(), new TestModel(), null, ['wifi', 'pool']))->toBe('["wifi","pool"]')
        ->and($field->resolveForUpdate(createNadotaRequest(), new TestModel(), null, '["a","b"]'))->toBe('["a","b"]');
});

it('wraps a single scalar selection in an array', function () {
    $field = CheckboxList::make('Features', 'features');

    expect($field->resolveForStore(createNadotaRequest(), new TestModel(), null, 'wifi'))->toBe(['wifi'])
        ->and($field->resolveForStore(createNadotaRequest(), new TestModel(), null, 7))->toBe([7]);
});

it('stores null as null', function () {
    expect(CheckboxList::make('Features', 'features')->resolveForStore(createNadotaRequest(), new TestModel(), null, null))->toBeNull();
});

it('recognizes encrypted array casts', function () {
    $model = new TestModel();
    $model->mergeCasts(['description' => 'encrypted:array']);

    expect(CheckboxList::make('Features', 'description')->resolveForStore(createNadotaRequest(), $model, null, ['a']))->toBe(['a']);
});

it('adds no array rule without min or max', function () {
    expect(CheckboxList::make('Features', 'features')->getRules())->toBe([])
        ->and(CheckboxList::make('Features', 'features')->required()->min(1)->getRules())->toBe(['required', 'array', 'min:1']);
});

it('enforces the selection count', function () {
    $rules = ['features' => CheckboxList::make('Features', 'features')->min(1)->max(2)->getRules()];

    expect(Validator::make(['features' => []], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['features' => ['a', 'b', 'c']], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['features' => 'a'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['features' => ['a', 'b']], $rules)->passes())->toBeTrue();
});

it('accepts closure rules next to string rules', function () {
    $field = CheckboxList::make('Features', 'features')->required()->rules([fn ($attribute, $value, $fail) => null]);

    expect($field->getRules())->toHaveCount(2);
})->skip('ISSUE FLD-009: CheckboxList::getRules() runs array_unique() over the rules, a Closure throws "could not be converted to string"');
