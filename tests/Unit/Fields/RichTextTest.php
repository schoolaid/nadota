<?php

use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\RichText;

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
