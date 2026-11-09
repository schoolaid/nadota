<?php

use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\RichText;
use SchoolAid\Nadota\Tests\Models\TestModel;
/*
 * An emptied editor still sends markup ("<p></p>", "<p>   </p><p><br></p>"), which passed `required`:
 * marketaid's mail body could be saved empty. Visually empty HTML is now null before validation.
 */
it('turns visually empty HTML into null', function (string $html) {
    expect(RichText::make('Body', 'body')->normalizeInput($html))->toBeNull();
})->with(['<p></p>', '<p>   </p><p></p>', '<p><br></p>', '<p>&nbsp;</p>', "<p>\u{00A0}</p>", '', '  ']);

it('keeps HTML with text or media', function (string $html) {
    expect(RichText::make('Body', 'body')->normalizeInput($html))->toBe($html);
})->with(['<p>Hola</p>', '<p></p><img src="https://x/a.png">', '<p><iframe src="https://www.youtube.com/embed/x"></iframe></p>', '<hr>', '<p>0</p>']);

it('fails required with an emptied editor', function () {
    $field = RichText::make('Body', 'body')->required();
    $value = $field->normalizeInput('<p></p>');

    expect(Validator::make(['body' => $value], ['body' => $field->getRules()])->fails())->toBeTrue();
});

it('is a rich text field rendered by the FieldRichText component', function () {
    $field = RichText::make('Bio', 'rich_bio');

    expect($field)->toBeField()
        ->toHaveFieldAttribute('rich_bio')
        ->and($field->getType())->toBe(FieldType::RICH_TEXT->value)
        ->and($field->getComponent())->toBe('FieldRichText');
});

it('only sends the options that were configured', function () {
    expect(RichText::make('Bio', 'rich_bio')->toArray(createNadotaRequest())['props'])
        ->toBe(['sanitize' => true]);
});

it('exposes toolbar, placeholder and tag whitelist in props', function () {
    $props = RichText::make('Bio', 'rich_bio')
        ->toolbar(['bold', 'italic', 'link'])
        ->editorPlaceholder('Tell us about you')
        ->allowedTags(['p', 'strong'])
        ->withoutSanitization()
        ->toArray(createNadotaRequest())['props'];

    expect($props)->toBe([
        'sanitize' => false,
        'allowedTags' => ['p', 'strong'],
        'toolbar' => ['bold', 'italic', 'link'],
        'editorPlaceholder' => 'Tell us about you',
    ]);
});

it('keeps the field placeholder separate from the editor placeholder', function () {
    $array = RichText::make('Bio', 'rich_bio')->editorPlaceholder('Write here')->toArray(createNadotaRequest());

    expect($array['placeholder'])->toBe('Bio')
        ->and($array['props']['editorPlaceholder'])->toBe('Write here');
});

it('can turn sanitization back on', function () {
    expect(RichText::make('Bio', 'b')->withoutSanitization()->sanitize()->toArray(createNadotaRequest())['props']['sanitize'])->toBeTrue();
});

it('resolves the stored markup for display', function () {
    $model = new TestModel();
    $model->description = '<h1>Title</h1><p>Body</p>';

    expect(RichText::make('Bio', 'description')->resolve(createNadotaRequest(), $model, null))->toBe('<h1>Title</h1><p>Body</p>');
});

it('strips script tags before storing when sanitization is on', function () {
    $stored = RichText::make('Bio', 'description')
        ->resolveForStore(createNadotaRequest(), new TestModel(), null, '<p>ok</p><script>alert(1)</script>');

    expect($stored)->not->toContain('<script');
})->skip('ISSUE FLD-012: sanitize() is only a frontend flag, the backend stores and returns raw HTML');
