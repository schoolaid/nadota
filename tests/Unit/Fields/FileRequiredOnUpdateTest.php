<?php

use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\Image;

/*
 * The upload rules (`file`, `max:`…) made every edit of a resource with a required File/Image fail:
 * the form sends the saved value back or nothing when the file is not replaced, and `fill()` keeps the
 * stored file in both cases, but `required` rejected the empty value and `file` the saved one. On
 * update a required file now keeps the stored one: only a new upload replaces it, and `clear` still
 * cannot empty it.
 */

/** @return array<int, string> */
function fileErrors(File $field, mixed $value, bool $isUpdate): array
{
    $field->normalizeInput($value);

    return Validator::make(['doc' => $value], ['doc' => $field->getRulesFor($isUpdate)])->errors()->get('doc');
}

it('keeps a required file on update when none is sent', function (string $class, mixed $empty) {
    $field = $class::make('Doc', 'doc')->required();

    expect(fileErrors($field, $empty, true))->toBe([])
        ->and($field->isRequired(true))->toBeFalse();
})->with([File::class, Image::class])->with([null, '']);

it('still requires the file on create', function (string $class) {
    $field = $class::make('Doc', 'doc')->required();

    expect(fileErrors($field, null, false))->not->toBe([])
        ->and($field->isRequired(false))->toBeTrue()
        ->and($field->getRulesFor(false))->toContain('required');
})->with([File::class, Image::class]);

it('does not let clear empty a required file on update', function () {
    $field = File::make('Doc', 'doc')->required();

    expect($field->isClearable())->toBeFalse()
        ->and(fileErrors($field, 'clear', true))->not->toBe([]);
});

it('lets clear empty an optional file, and one required only on creation', function (callable $make) {
    $field = $make();

    expect($field->isClearable())->toBeTrue()
        ->and(fileErrors($field, 'clear', true))->toBe([]);
})->with([
    'nullable' => [fn () => File::make('Doc', 'doc')->nullable()],
    'creation only' => [fn () => File::make('Doc', 'doc')->creationRules(['required'])],
]);

it('tells the form whether the stored file can be removed', function () {
    $request = createNadotaRequest(['action' => 'update']);

    expect(File::make('Doc', 'doc')->required()->toArray($request)['props']['clearable'])->toBeFalse()
        ->and(File::make('Doc', 'doc')->nullable()->toArray($request)['props']['clearable'])->toBeTrue();
});

it('still validates a new upload on update', function () {
    $field = File::make('Doc', 'doc')->required()->accept(['pdf']);

    expect(fileErrors($field, \Illuminate\Http\UploadedFile::fake()->create('x.png', 10, 'image/png'), true))->not->toBe([])
        ->and(fileErrors($field, \Illuminate\Http\UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), true))->toBe([]);
});
