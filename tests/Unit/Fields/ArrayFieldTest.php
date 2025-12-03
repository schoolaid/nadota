<?php

use SchoolAid\Nadota\Http\Fields\ArrayField;

it('can be instantiated', function () {
    $field = ArrayField::make('Numbers', 'numbers');

    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('numbers')
        ->and($field->getName())->toBe('Numbers');
});

it('can set value type', function () {
    $field = ArrayField::make('Numbers', 'numbers')
        ->valueType('integer');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['valueType'])->toBe('integer');
});

it('has shortcut for strings', function () {
    $field = ArrayField::make('Tags', 'tags')->strings();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['valueType'])->toBe('string');
});

it('has shortcut for numbers', function () {
    $field = ArrayField::make('Scores', 'scores')->numbers();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['valueType'])->toBe('number');
});

it('has shortcut for integers', function () {
    $field = ArrayField::make('IDs', 'ids')->integers();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['valueType'])->toBe('integer');
});

it('has shortcut for emails', function () {
    $field = ArrayField::make('Emails', 'emails')->emails();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['valueType'])->toBe('email')
        ->and($array['props']['itemRules'])->toContain('email');
});

it('has shortcut for urls', function () {
    $field = ArrayField::make('Links', 'links')->urls();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['valueType'])->toBe('url')
        ->and($array['props']['itemRules'])->toContain('url');
});

it('can allow or disallow duplicates', function () {
    $field = ArrayField::make('Numbers', 'numbers')
        ->allowDuplicates(false);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['allowDuplicates'])->toBeFalse();
});

it('has unique shortcut', function () {
    $field = ArrayField::make('Numbers', 'numbers')->unique();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['allowDuplicates'])->toBeFalse();
});

it('can set minimum items', function () {
    $field = ArrayField::make('Tags', 'tags')->min(3);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['minItems'])->toBe(3);
});

it('can set maximum items', function () {
    $field = ArrayField::make('Tags', 'tags')->max(10);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['maxItems'])->toBe(10);
});

it('can set fixed length', function () {
    $field = ArrayField::make('Coordinates', 'coords')->length(2);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['minItems'])->toBe(2)
        ->and($array['props']['maxItems'])->toBe(2);
});

it('can set sortable', function () {
    $field = ArrayField::make('Numbers', 'numbers')
        ->sortable(false);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['sortable'])->toBeFalse();
});

it('can set item placeholder', function () {
    $field = ArrayField::make('Tags', 'tags')
        ->itemPlaceholder('Enter tag...');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['itemPlaceholder'])->toBe('Enter tag...');
});

it('can set default values', function () {
    $field = ArrayField::make('Numbers', 'numbers')
        ->defaultValues([1, 2, 3]);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['defaultValues'])->toBe([1, 2, 3]);
});

it('can set item rules', function () {
    $field = ArrayField::make('Scores', 'scores')
        ->itemRules(['min:0', 'max:100']);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['itemRules'])
        ->toContain('min:0')
        ->toContain('max:100');
});

it('can set options', function () {
    $field = ArrayField::make('Categories', 'categories')
        ->options(['tech', 'sports', 'music']);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['options'])->toBe(['tech', 'sports', 'music']);
});

it('can display as chips', function () {
    $field = ArrayField::make('Tags', 'tags')
        ->displayAsChips();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['displayAsChips'])->toBeTrue();
});

it('can set add button text', function () {
    $field = ArrayField::make('Items', 'items')
        ->addButtonText('Add New Item');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['addButtonText'])->toBe('Add New Item');
});

it('resolves array value from model', function () {
    $model = createTestModel(['numbers' => [1, 3, 5, 4]]);
    $field = ArrayField::make('Numbers', 'numbers');
    $request = createNadotaRequest();

    $value = $field->resolve($request, $model, null);

    expect($value)->toBe([1, 3, 5, 4]);
});

it('resolves JSON string value from model', function () {
    $model = createTestModel(['numbers' => '[1,3,5,4]']);
    $field = ArrayField::make('Numbers', 'numbers');
    $request = createNadotaRequest();

    $value = $field->resolve($request, $model, null);

    expect($value)->toBe([1, 3, 5, 4]);
});

it('handles null value as empty array', function () {
    $model = createTestModel(['numbers' => null]);
    $field = ArrayField::make('Numbers', 'numbers');
    $request = createNadotaRequest();

    $value = $field->resolve($request, $model, null);

    expect($value)->toBe([]);
});

it('applies default values when empty', function () {
    $model = createTestModel(['numbers' => null]);
    $field = ArrayField::make('Numbers', 'numbers')
        ->defaultValues([1, 2, 3]);
    $request = createNadotaRequest();

    $value = $field->resolve($request, $model, null);

    expect($value)->toBe([1, 2, 3]);
});

it('casts values to integer type', function () {
    $model = createTestModel(['numbers' => ['1', '3', '5', '4']]);
    $field = ArrayField::make('Numbers', 'numbers')->integers();
    $request = createNadotaRequest();

    $value = $field->resolve($request, $model, null);

    expect($value)->toBe([1, 3, 5, 4])
        ->and($value[0])->toBeInt()
        ->and($value[1])->toBeInt();
});

it('casts values to string type', function () {
    $model = createTestModel(['tags' => [1, 2, 3]]);
    $field = ArrayField::make('Tags', 'tags')->strings();
    $request = createNadotaRequest();

    $value = $field->resolve($request, $model, null);

    expect($value)->toBe(['1', '2', '3'])
        ->and($value[0])->toBeString();
});

it('removes duplicates when unique is set', function () {
    $model = createTestModel(['numbers' => [1, 2, 2, 3, 3, 3]]);
    $field = ArrayField::make('Numbers', 'numbers')->unique();
    $request = createNadotaRequest();

    $value = $field->resolve($request, $model, null);

    expect($value)->toBe([1, 2, 3]);
});

it('adds array validation rule', function () {
    $field = ArrayField::make('Numbers', 'numbers');

    $rules = $field->getRules();

    expect($rules)->toContain('array');
});

it('adds min and max validation rules', function () {
    $field = ArrayField::make('Numbers', 'numbers')
        ->min(2)
        ->max(5);

    $rules = $field->getRules();

    expect($rules)
        ->toContain('array')
        ->toContain('min:2')
        ->toContain('max:5');
});

it('adds distinct validation when unique is set', function () {
    $field = ArrayField::make('Numbers', 'numbers')->unique();

    $rules = $field->getRules();

    expect($rules)->toContain('distinct');
});

it('fills model with array data', function () {
    $model = createTestModel();
    $request = createNadotaRequest(['numbers' => [1, 3, 5, 4]]);

    $field = ArrayField::make('Numbers', 'numbers');
    $field->fill($request, $model);

    expect($model->numbers)->toBe([1, 3, 5, 4]);
});

it('fills model and removes duplicates when unique is set', function () {
    $model = createTestModel();
    $request = createNadotaRequest(['numbers' => [1, 2, 2, 3]]);

    $field = ArrayField::make('Numbers', 'numbers')->unique();
    $field->fill($request, $model);

    expect($model->numbers)->toBe([1, 2, 3]);
});

it('fills model and casts values to type', function () {
    $model = createTestModel();
    $request = createNadotaRequest(['numbers' => ['1', '3', '5']]);

    $field = ArrayField::make('Numbers', 'numbers')->integers();
    $field->fill($request, $model);

    expect($model->numbers)->toBe([1, 3, 5])
        ->and($model->numbers[0])->toBeInt();
});

it('filters out empty values when filling', function () {
    $model = createTestModel();
    $request = createNadotaRequest(['tags' => ['tag1', '', 'tag2', null, 'tag3']]);

    $field = ArrayField::make('Tags', 'tags');
    $field->fill($request, $model);

    expect($model->tags)->toBe(['tag1', 'tag2', 'tag3']);
});

it('is hidden from index by default', function () {
    $field = ArrayField::make('Numbers', 'numbers');
    $request = createNadotaRequest();

    $array = $field->toArray($request, null, null);

    expect($array['showOnIndex'])->toBeFalse();
});

it('serializes to array correctly', function () {
    $model = createTestModel(['numbers' => [1, 3, 5, 4]]);
    $field = ArrayField::make('Numbers', 'numbers')
        ->integers()
        ->unique()
        ->min(1)
        ->max(10)
        ->sortable()
        ->itemPlaceholder('Enter number...')
        ->displayAsChips();

    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);

    expect($array)
        ->toHaveKey('label', 'Numbers')
        ->toHaveKey('attribute', 'numbers')
        ->toHaveKey('value', [1, 3, 5, 4])
        ->toHaveKey('props')
        ->and($array['props']['valueType'])->toBe('integer')
        ->and($array['props']['allowDuplicates'])->toBeFalse()
        ->and($array['props']['minItems'])->toBe(1)
        ->and($array['props']['maxItems'])->toBe(10)
        ->and($array['props']['sortable'])->toBeTrue()
        ->and($array['props']['displayAsChips'])->toBeTrue()
        ->and($array['props']['itemPlaceholder'])->toBe('Enter number...');
});