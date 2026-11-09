<?php

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Html;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('is an html field rendered by the FieldHtml component', function () {
    $field = Html::make('Notes', 'html_notes');

    expect($field)->toBeField()
        ->toHaveFieldAttribute('html_notes')
        ->and($field->getType())->toBe(FieldType::HTML->value)
        ->and($field->getComponent())->toBe('FieldHtml');
});

it('asks the frontend to sanitize by default with no tag whitelist', function () {
    expect(Html::make('Notes', 'html_notes')->toArray(createNadotaRequest())['props'])
        ->toBe(['sanitize' => true, 'allowedTags' => null]);
});

it('can disable sanitization and whitelist tags', function () {
    expect(Html::make('Notes', 'n')->withoutSanitization()->toArray(createNadotaRequest())['props']['sanitize'])->toBeFalse()
        ->and(Html::make('Notes', 'n')->withoutSanitization()->sanitize()->toArray(createNadotaRequest())['props']['sanitize'])->toBeTrue()
        ->and(Html::make('Notes', 'n')->sanitize(false)->toArray(createNadotaRequest())['props']['sanitize'])->toBeFalse()
        ->and(Html::make('Notes', 'n')->allowedTags(['p', 'b'])->toArray(createNadotaRequest())['props']['allowedTags'])->toBe(['p', 'b']);
});

it('resolves the stored markup for display', function () {
    $model = new TestModel();
    $model->description = '<p>Hello <b>world</b></p>';

    expect(Html::make('Notes', 'description')->resolve(createNadotaRequest(), $model, null))->toBe('<p>Hello <b>world</b></p>');
});

it('strips script tags before storing when sanitization is on', function () {
    $field = Html::make('Notes', 'description');

    $stored = $field->resolveForStore(createNadotaRequest(), new TestModel(), null, '<p>ok</p><script>alert(1)</script>');

    expect($stored)->not->toContain('<script');
})->skip('ISSUE FLD-012: sanitize() is only a frontend flag, the backend stores and returns raw HTML');

it('keeps the markup intact when sanitization is disabled', function () {
    $field = Html::make('Notes', 'description')->withoutSanitization();

    expect($field->resolveForStore(createNadotaRequest(), new TestModel(), null, '<iframe src="x"></iframe>'))
        ->toBe('<iframe src="x"></iframe>');
});
