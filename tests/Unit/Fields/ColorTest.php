<?php

use SchoolAid\Nadota\Http\Fields\Color;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('is a color field rendered by the FieldColor component', function () {
    $field = Color::make('Favorite Color', 'favorite_color');

    expect($field)->toBeField()
        ->toHaveFieldAttribute('favorite_color')
        ->and($field->getLabel())->toBe('Favorite Color')
        ->and($field->getType())->toBe(FieldType::COLOR->value)
        ->and($field->getComponent())->toBe('FieldColor');
});

it('takes its component from nadota.fields.color.component when configured', function () {
    config()->set('nadota.fields.color.component', 'MyColorPicker');

    expect(Color::make('Color', 'color')->getComponent())->toBe('MyColorPicker');
});

it('resolves the stored hex value untouched', function () {
    $model = new TestModel();
    $model->color = '#FF00aa';

    expect(Color::make('Color', 'color')->resolve(createNadotaRequest(), $model, null))->toBe('#FF00aa');
});

it('falls back to its default when the model has no color', function () {
    $model = new TestModel();

    $field = Color::make('Color', 'color')->default('#000000');

    expect($field->resolve(createNadotaRequest(), $model, null))->toBe('#000000');
});

it('stores the submitted value as is', function () {
    $field = Color::make('Color', 'color');

    expect($field->resolveForStore(createNadotaRequest(), new TestModel(), null, '#123456'))->toBe('#123456')
        ->and($field->resolveForUpdate(createNadotaRequest(), new TestModel(), null, '#abcdef'))->toBe('#abcdef');
});

it('has no validation rules unless they are declared', function () {
    expect(Color::make('Color', 'color')->getRules())->toBe([])
        ->and(Color::make('Color', 'color')->rules(['regex:/^#[0-9a-f]{6}$/i'])->getRules())
        ->toBe(['regex:/^#[0-9a-f]{6}$/i']);
});

it('serializes with no field specific props and the current value', function () {
    $model = new TestModel();
    $model->color = '#00ff00';

    $array = Color::make('Color', 'color')->toArray(createNadotaRequest(), $model, null);

    expect($array)
        ->toHaveKey('type', 'color')
        ->toHaveKey('component', 'FieldColor')
        ->toHaveKey('value', '#00ff00')
        ->and($array['props'])->toBe([]);
});
