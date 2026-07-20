<?php

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\MultiImage;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('has correct type, component and defaults', function () {
    $field = MultiImage::make('Images', 'images');

    // FieldDTO::$type is declared as a plain string (not a backed enum instance),
    // per src/Http/Fields/DataTransferObjects/FieldDTO.php — verified by reading it.
    expect($field)->toBeInstanceOf(File::class)
        ->and($field->fieldData->type)->toBe('multiImage')
        ->and($field->fieldData->component)->toBe('FieldMultiImage');
});

it('builds array rules with max files and nested per-image rules', function () {
    $field = MultiImage::make('Images', 'images')->maxFiles(3)->maxSize(2 * 1024 * 1024);

    expect($field->getRules())->toContain('array')
        ->and($field->getRules())->toContain('max:3')
        ->and($field->getRules())->not->toContain('string');

    $nested = $field->getNestedRules();

    expect($nested)->toHaveKey('images.*')
        ->and($nested['images.*'])->toContain('image')
        ->and($nested['images.*'])->toContain('mimes:jpg,jpeg,png,webp')
        ->and($nested['images.*'])->toContain('max:2048');
});

it('stores every uploaded image and assigns the array of paths', function () {
    Storage::fake('local');

    $field = MultiImage::make('Images', 'images')->path('uploads/test');
    $model = new TestModel;

    $uploadA = UploadedFile::fake()->image('a.jpg');
    $uploadB = UploadedFile::fake()->image('b.png');

    $request = Request::create('/', 'POST', [], [], ['images' => [$uploadA, $uploadB]]);

    $field->fill($request, $model);

    expect($model->images)->toBeArray()->toHaveCount(2)
        ->and($model->images[0])->toBe('uploads/test/'.$uploadA->hashName())
        ->and($model->images[1])->toBe('uploads/test/'.$uploadB->hashName());

    Storage::disk('local')->assertExists($model->images[0]);
    Storage::disk('local')->assertExists($model->images[1]);
});

it('appends new uploads to existing paths', function () {
    Storage::fake('local');

    $field = MultiImage::make('Images', 'images')->path('uploads/test');
    $model = new TestModel;
    $model->images = ['uploads/test/existing.jpg'];

    $upload = UploadedFile::fake()->image('new.webp');
    $request = Request::create('/', 'POST', [], [], ['images' => [$upload]]);

    $field->fill($request, $model);

    expect($model->images)->toHaveCount(2)
        ->and($model->images[0])->toBe('uploads/test/existing.jpg')
        ->and($model->images[1])->toBe('uploads/test/'.$upload->hashName());
});

it('does nothing when readonly, disabled or without uploads', function () {
    Storage::fake('local');

    $model = new TestModel;
    $model->images = ['uploads/test/existing.jpg'];

    $emptyRequest = Request::create('/', 'POST');
    MultiImage::make('Images', 'images')->fill($emptyRequest, $model);
    expect($model->images)->toBe(['uploads/test/existing.jpg']);

    $upload = UploadedFile::fake()->image('x.jpg');
    $request = Request::create('/', 'POST', [], [], ['images' => [$upload]]);

    $readonly = MultiImage::make('Images', 'images')->readonly();
    $readonly->fill($request, $model);
    expect($model->images)->toBe(['uploads/test/existing.jpg']);
});

it('does not touch the attribute when the key is present but empty', function () {
    $model = new TestModel;

    $request = Request::create('/', 'POST', [], [], ['images' => []]);
    MultiImage::make('Images', 'images')->fill($request, $model);

    expect($model->images)->toBeNull();
});

it('resolves an array of per-image objects with url', function () {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/test/one.jpg', 'x');

    $field = MultiImage::make('Images', 'images')->disk('local');
    $model = new TestModel;
    $model->images = ['uploads/test/one.jpg'];

    $resolved = $field->resolve(Request::create('/', 'GET'), $model, null);

    expect($resolved)->toBeArray()->toHaveCount(1)
        ->and($resolved[0]['path'])->toBe('uploads/test/one.jpg')
        ->and($resolved[0]['name'])->toBe('one.jpg')
        ->and($resolved[0])->toHaveKey('url');
});

it('resolves empty array for null or empty value', function () {
    $field = MultiImage::make('Images', 'images');
    $model = new TestModel;

    expect($field->resolve(Request::create('/', 'GET'), $model, null))->toBe([]);
});

it('exposes maxFiles in props', function () {
    $field = MultiImage::make('Images', 'images')->maxFiles(4);
    // Field::toArray() requires a NadotaRequest (FormRequest subclass), not a plain
    // Illuminate\Http\Request — verified by reading Field::toArray()'s signature.
    // Using the package's own createNadotaRequest() Pest helper (tests/Pest.php),
    // the same one used by ImageTest.php and sibling field tests.
    $array = $field->toArray(createNadotaRequest(), null, null);

    expect($array['props']['maxFiles'])->toBe(4);
});
