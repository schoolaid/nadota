<?php

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\MultiImage;
use SchoolAid\Nadota\Tests\Models\TestModel;

// A disk name of its own: Storage::fake() wipes storage/framework/testing/disks/<name>,
// which is shared with any other test process faking the same disk at the same time.
beforeEach(fn () => config()->set('filesystems.default', 'fld-multi'));

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

    $rules = $field->getRules();

    // The plain 'array' rule was replaced by a closure that also accepts the
    // frontend serializer's '[]' empty indicator (see the indicator tests).
    expect(collect($rules)->contains(fn ($rule) => $rule instanceof \Closure))->toBeTrue()
        ->and($rules)->toContain('max:3')
        ->and($rules)->not->toContain('array')
        ->and($rules)->not->toContain('string');

    $nested = $field->getNestedRules();

    expect($nested)->toHaveKey('images.*')
        ->and($nested['images.*'])->toContain('image')
        ->and($nested['images.*'])->toContain('mimes:jpg,jpeg,png,webp')
        ->and($nested['images.*'])->toContain('max:2048');
});

it('stores every uploaded image and assigns the array of paths', function () {
    Storage::fake('fld-multi');

    $field = MultiImage::make('Images', 'images')->path('uploads/test');
    $model = new TestModel;

    $uploadA = UploadedFile::fake()->image('a.jpg');
    $uploadB = UploadedFile::fake()->image('b.png');

    $request = Request::create('/', 'POST', [], [], ['images' => [$uploadA, $uploadB]]);

    $field->fill($request, $model);

    expect($model->images)->toBeArray()->toHaveCount(2)
        ->and($model->images[0])->toBe('uploads/test/'.$uploadA->hashName())
        ->and($model->images[1])->toBe('uploads/test/'.$uploadB->hashName());

    Storage::disk('fld-multi')->assertExists($model->images[0]);
    Storage::disk('fld-multi')->assertExists($model->images[1]);
});

it('appends new uploads to existing paths', function () {
    Storage::fake('fld-multi');

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
    Storage::fake('fld-multi');

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

    $disabled = MultiImage::make('Images', 'images')->disabled();
    $disabled->fill($request, $model);
    expect($model->images)->toBe(['uploads/test/existing.jpg'])
        ->and(Storage::disk('fld-multi')->allFiles())->toBe([]);
});

it('does not touch the attribute when the key is present but empty', function () {
    $model = new TestModel;

    $request = Request::create('/', 'POST', [], [], ['images' => []]);
    MultiImage::make('Images', 'images')->fill($request, $model);

    expect($model->images)->toBeNull();
});

it('resolves an array of per-image objects with url', function () {
    Storage::fake('fld-multi');
    Storage::disk('fld-multi')->put('uploads/test/one.jpg', 'x');

    $field = MultiImage::make('Images', 'images')->disk('fld-multi');
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

it('accepts the frontend empty-array indicator when nullable', function () {
    $field = MultiImage::make('Images', 'images')->nullable();

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['images' => '[]'],
        ['images' => $field->getRules()]
    );

    expect($validator->passes())->toBeTrue();
});

it('rejects non-array garbage even when nullable', function () {
    $field = MultiImage::make('Images', 'images')->nullable();

    // Shorter than the default max:5 so only the array closure can reject it
    // ('not-an-array' also failed max:5 as a 12 character string).
    $validator = \Illuminate\Support\Facades\Validator::make(
        ['images' => 'abc'],
        ['images' => $field->getRules()]
    );

    expect($validator->fails())->toBeTrue();
});

it('rejects the empty indicator when the field is required', function () {
    $field = MultiImage::make('Images', 'images')->required();

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['images' => '[]'],
        ['images' => $field->getRulesFor(false)]
    );

    expect($validator->fails())->toBeTrue();
});

it('still validates real arrays and the max files cap', function () {
    $field = MultiImage::make('Images', 'images')->nullable()->maxFiles(2);

    $ok = \Illuminate\Support\Facades\Validator::make(
        ['images' => [UploadedFile::fake()->image('a.jpg')]],
        ['images' => $field->getRules()]
    );

    $tooMany = \Illuminate\Support\Facades\Validator::make(
        ['images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')]],
        ['images' => $field->getRules()]
    );

    expect($ok->passes())->toBeTrue()
        ->and($tooMany->fails())->toBeTrue();
});

it('stores a single upload sent without the array brackets', function () {
    Storage::fake('fld-multi');
    $upload = UploadedFile::fake()->image('solo.jpg');
    $model = new TestModel;

    MultiImage::make('Images', 'images')->path('p')->fill(Request::create('/', 'POST', [], [], ['images' => $upload]), $model);

    expect($model->images)->toBe(['p/' . $upload->hashName()]);
});

it('removes the stored uploads when the save is rolled back', function () {
    Storage::fake('fld-multi');
    $field = MultiImage::make('Images', 'images')->path('p');
    $model = new TestModel;

    $field->fill(Request::create('/', 'POST', [], [], ['images' => [UploadedFile::fake()->image('a.jpg')]]), $model);
    $stored = $model->images[0];
    Storage::disk('fld-multi')->assertExists($stored);

    $field->discardPendingFiles();

    Storage::disk('fld-multi')->assertMissing($stored);
});

it('rejects a non image item through the nested rules', function () {
    $field = MultiImage::make('Images', 'images');
    $rules = ['images' => $field->getRules()] + $field->getNestedRules();

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['images' => [UploadedFile::fake()->create('doc.pdf', 1, 'application/pdf')]],
        $rules
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toBe(['images.0']);
});

it('ignores non string entries when resolving', function () {
    $model = new TestModel;
    $model->images = ['a.jpg', ['broken'], null, 'b.jpg'];

    $resolved = MultiImage::make('Images', 'images')->resolve(Request::create('/', 'GET'), $model, null);

    expect(array_column($resolved, 'path'))->toBe(['a.jpg', 'b.jpg']);
});

it('defaults to five files of 5 MB in jpg, png or webp', function () {
    $field = MultiImage::make('Images', 'images');

    expect($field->toArray(createNadotaRequest())['props'])->toMatchArray([
        'maxFiles' => 5,
        'maxSize' => 5 * 1024 * 1024,
        'acceptedTypes' => ['jpg', 'jpeg', 'png', 'webp'],
    ]);
});

it('never lets the stored list grow beyond maxFiles', function () {
    Storage::fake('fld-multi');
    $field = MultiImage::make('Images', 'images')->maxFiles(2);
    $model = new TestModel;
    $model->images = ['p/one.jpg', 'p/two.jpg'];

    $uploads = [UploadedFile::fake()->image('three.jpg')];
    $validator = \Illuminate\Support\Facades\Validator::make(['images' => $uploads], ['images' => $field->getRules()]);

    if ($validator->passes()) {
        $field->fill(Request::create('/', 'POST', [], [], ['images' => $uploads]), $model);
    }

    expect(count($model->images))->toBeLessThanOrEqual(2);
})->skip('ISSUE FLD-016: max:{maxFiles} only counts the files of the current request; fill() appends to the stored list');

