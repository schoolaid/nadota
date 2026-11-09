<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SchoolAid\Nadota\Http\Fields\Signature;
use SchoolAid\Nadota\Tests\Models\TestModel;

/** A PNG data URL as the canvas sends it. */
function signatureDataUrl(string $bytes = 'fake-png-bytes'): string
{
    return 'data:image/png;base64,' . base64_encode($bytes);
}

/** A TestModel whose original "description" (the signature column here) is $value. */
function signatureModel(?string $value): TestModel
{
    $model = new TestModel(['description' => $value]);
    $model->syncOriginal();

    return $model;
}

// =========================================================================
// BASIC SETUP
// =========================================================================

it('can create a signature field', function () {
    $field = Signature::make('Signature', 'signature');

    expect($field)->toBeInstanceOf(Signature::class)
        ->and($field->getAttribute())->toBe('signature');
});

it('sets the correct field type', function () {
    $field = Signature::make('Signature', 'signature');
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['type'])->toBe('signature');
});

it('has default component name', function () {
    $field = Signature::make('Signature', 'signature');
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['component'])->toBe('FieldSignature');
});

// =========================================================================
// STORAGE MODE
// =========================================================================

it('defaults to base64 storage mode', function () {
    $field = Signature::make('Signature', 'signature');
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['storageMode'])->toBe('base64');
});

it('can set disk storage mode', function () {
    $field = Signature::make('Signature', 'signature')
        ->storeOnDisk('s3', 'signatures');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['storageMode'])->toBe('disk');
});

it('can set disk using shorthand', function () {
    $field = Signature::make('Signature', 'signature')
        ->disk('s3');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['storageMode'])->toBe('disk');
});

it('can set storage path', function () {
    Storage::fake('fld-signatures-s3');
    $model = new TestModel();

    // The path is not part of the props; it shows up in where the file is written.
    Signature::make('Signature', 'description')
        ->disk('fld-signatures-s3')
        ->path('form-signatures')
        ->fill(new Request(['description' => signatureDataUrl()]), $model);

    expect($model->description)->toStartWith('form-signatures/signature_new_');
    Storage::disk('fld-signatures-s3')->assertExists($model->description);
});

it('can switch back to base64 mode', function () {
    $field = Signature::make('Signature', 'signature')
        ->disk('s3')
        ->storeAsBase64();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['storageMode'])->toBe('base64');
});

// =========================================================================
// IMAGE FORMAT
// =========================================================================

it('defaults to png format', function () {
    $field = Signature::make('Signature', 'signature');
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['format'])->toBe('png');
});

it('can set jpeg format with quality', function () {
    $field = Signature::make('Signature', 'signature')
        ->jpeg(85);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['format'])->toBe('jpeg')
        ->and($array['props']['quality'])->toBe(85);
});

it('can set webp format', function () {
    $field = Signature::make('Signature', 'signature')
        ->webp(80);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['format'])->toBe('webp')
        ->and($array['props']['quality'])->toBe(80);
});

it('can set svg format', function () {
    $field = Signature::make('Signature', 'signature')
        ->svg();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['format'])->toBe('svg');
});

it('clamps quality between 1 and 100', function () {
    $field = Signature::make('Signature', 'signature')
        ->quality(150);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['quality'])->toBe(100);

    $field2 = Signature::make('Signature', 'signature')
        ->quality(-10);

    $array2 = $field2->toArray($request, null, null);
    expect($array2['props']['quality'])->toBe(1);
});

// =========================================================================
// CANVAS CONFIGURATION
// =========================================================================

it('has default canvas dimensions', function () {
    $field = Signature::make('Signature', 'signature');
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['canvasWidth'])->toBe(400)
        ->and($array['props']['canvasHeight'])->toBe(200);
});

it('can set canvas dimensions', function () {
    $field = Signature::make('Signature', 'signature')
        ->dimensions(600, 300);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['canvasWidth'])->toBe(600)
        ->and($array['props']['canvasHeight'])->toBe(300);
});

it('can set width and height separately', function () {
    $field = Signature::make('Signature', 'signature')
        ->canvasWidth(500)
        ->canvasHeight(250);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['canvasWidth'])->toBe(500)
        ->and($array['props']['canvasHeight'])->toBe(250);
});

// =========================================================================
// PEN CONFIGURATION
// =========================================================================

it('has default pen settings', function () {
    $field = Signature::make('Signature', 'signature');
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['penColor'])->toBe('#000000')
        ->and($array['props']['penWidth'])->toBe(2);
});

it('can set pen color', function () {
    $field = Signature::make('Signature', 'signature')
        ->penColor('#0000ff');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['penColor'])->toBe('#0000ff');
});

it('can set pen width', function () {
    $field = Signature::make('Signature', 'signature')
        ->penWidth(5);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['penWidth'])->toBe(5);
});

// =========================================================================
// BACKGROUND
// =========================================================================

it('defaults to transparent background', function () {
    $field = Signature::make('Signature', 'signature');
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['backgroundColor'])->toBeNull();
});

it('can set background color', function () {
    $field = Signature::make('Signature', 'signature')
        ->backgroundColor('#f0f0f0');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['backgroundColor'])->toBe('#f0f0f0');
});

it('can set white background', function () {
    $field = Signature::make('Signature', 'signature')
        ->whiteBackground();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['backgroundColor'])->toBe('#ffffff');
});

it('can reset to transparent', function () {
    $field = Signature::make('Signature', 'signature')
        ->backgroundColor('#ffffff')
        ->transparent();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['backgroundColor'])->toBeNull();
});

// =========================================================================
// UI OPTIONS
// =========================================================================

it('is clearable by default', function () {
    $field = Signature::make('Signature', 'signature');
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['clearable'])->toBeTrue();
});

it('can disable clearing', function () {
    $field = Signature::make('Signature', 'signature')
        ->clearable(false);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['clearable'])->toBeFalse();
});

it('can set empty text', function () {
    $field = Signature::make('Signature', 'signature')
        ->emptyText('Click to sign');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['emptyText'])->toBe('Click to sign');
});

it('can allow typed signature', function () {
    $field = Signature::make('Signature', 'signature')
        ->allowTyped(true, 'Dancing Script');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['allowTypedSignature'])->toBeTrue()
        ->and($array['props']['typedFont'])->toBe('Dancing Script');
});

// =========================================================================
// FULL CONFIGURATION EXAMPLE
// =========================================================================

it('can be fully configured', function () {
    $field = Signature::make('Customer Signature', 'customer_signature')
        ->disk('s3')
        ->path('contracts/signatures')
        ->png()
        ->dimensions(500, 200)
        ->penColor('#1a1a1a')
        ->penWidth(3)
        ->whiteBackground()
        ->temporaryUrl(60)
        ->cache(30)
        ->clearable()
        ->emptyText('Sign here')
        ->allowTyped(true)
        ->required();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['storageMode'])->toBe('disk')
        ->and($array['props']['format'])->toBe('png')
        ->and($array['props']['canvasWidth'])->toBe(500)
        ->and($array['props']['canvasHeight'])->toBe(200)
        ->and($array['props']['penColor'])->toBe('#1a1a1a')
        ->and($array['props']['penWidth'])->toBe(3)
        ->and($array['props']['backgroundColor'])->toBe('#ffffff')
        ->and($array['props']['clearable'])->toBeTrue()
        ->and($array['props']['emptyText'])->toBe('Sign here')
        ->and($array['props']['allowTypedSignature'])->toBeTrue()
        ->and($array['required'])->toBeTrue();
});

// =========================================================================
// RESOLVE
// =========================================================================

it('resolves a data url as a base64 signature', function () {
    $value = Signature::make('Signature', 'description')->resolve(createNadotaRequest(), signatureModel(signatureDataUrl()), null);

    expect($value)->toBe(['type' => 'base64', 'data' => signatureDataUrl(), 'dataUrl' => signatureDataUrl()]);
});

it('adds the data url prefix matching the format to raw base64', function (callable $configure, string $mime) {
    $raw = 'iVBORw0KGgoAAAANSUhEUg==';

    $value = $configure(Signature::make('Signature', 'description'))
        ->resolve(createNadotaRequest(), signatureModel($raw), null);

    expect($value['type'])->toBe('base64')
        ->and($value['dataUrl'])->toBe("data:{$mime};base64,{$raw}");
})->with([
    'png' => [fn ($f) => $f, 'image/png'],
    'jpeg' => [fn ($f) => $f->jpeg(), 'image/jpeg'],
    'webp' => [fn ($f) => $f->webp(), 'image/webp'],
    'svg' => [fn ($f) => $f->svg(), 'image/svg+xml'],
]);

it('resolves a stored path as a file with its url', function () {
    Storage::fake('fld-signatures');

    $value = Signature::make('Signature', 'description')->disk('fld-signatures')
        ->resolve(createNadotaRequest(), signatureModel('signatures/s1.png'), null);

    expect($value)->toBe([
        'type' => 'file',
        'path' => 'signatures/s1.png',
        'url' => Storage::disk('fld-signatures')->url('signatures/s1.png'),
    ]);
});

it('uses a temporary url for disk signatures when configured', function () {
    Storage::fake('fld-signatures-s3')->buildTemporaryUrlsUsing(fn ($path) => 'https://signed.test/' . $path);

    $value = Signature::make('Signature', 'description')->disk('fld-signatures-s3')->temporaryUrl(5)
        ->resolve(createNadotaRequest(), signatureModel('signatures/s1.png'), null);

    expect($value['url'])->toBe('https://signed.test/signatures/s1.png');
});

it('resolves null for an empty signature and for unknown array shapes', function () {
    $field = Signature::make('Signature', 'metadata');
    $model = new TestModel(); // metadata is cast to array

    expect($field->resolve(createNadotaRequest(), $model, null))->toBeNull();

    $model->metadata = ['foo' => 'bar'];
    expect($field->resolve(createNadotaRequest(), $model, null))->toBeNull();

    $model->metadata = ['type' => 'file', 'path' => 'x.png'];
    expect($field->resolve(createNadotaRequest(), $model, null))->toBe(['type' => 'file', 'path' => 'x.png']);

    $model->metadata = ['value' => signatureDataUrl()];
    expect($field->resolve(createNadotaRequest(), $model, null)['type'])->toBe('base64');
});

it('recognizes raw base64 that contains slashes', function () {
    $raw = base64_encode("\xff\xfe\xfd binary/?");   // contains "/"

    $value = Signature::make('Signature', 'description')->resolve(createNadotaRequest(), signatureModel($raw), null);

    expect($value['type'])->toBe('base64');
})->skip('ISSUE FLD-027: isBase64() rejects any value containing "/", so most raw base64 strings resolve as file paths');

// =========================================================================
// FILL
// =========================================================================

it('keeps the data url in the column in base64 mode', function () {
    $model = new TestModel();

    Signature::make('Signature', 'description')->fill(new Request(['description' => signatureDataUrl()]), $model);

    expect($model->description)->toBe(signatureDataUrl());
});

it('writes the decoded image to the disk in disk mode', function () {
    Storage::fake('fld-signatures');
    $model = new TestModel();

    Signature::make('Signature', 'description')->storeOnDisk('fld-signatures', 'signatures')->webp()
        ->fill(new Request(['description' => signatureDataUrl('PNGDATA')]), $model);

    expect($model->description)->toMatch('#^signatures/signature_new_\d+_[A-Za-z0-9]{8}\.webp$#')
        ->and(Storage::disk('fld-signatures')->get($model->description))->toBe('PNGDATA');
});

it('replaces the previous signature file in disk mode', function () {
    Storage::fake('fld-signatures');
    Storage::disk('fld-signatures')->put('signatures/old.png', 'old');
    $model = signatureModel('signatures/old.png');

    Signature::make('Signature', 'description')->storeOnDisk('fld-signatures', 'signatures')
        ->fill(new Request(['description' => signatureDataUrl()]), $model);

    Storage::disk('fld-signatures')->assertMissing('signatures/old.png');
    Storage::disk('fld-signatures')->assertExists($model->description);
});

it('clears the signature and deletes its file', function (string $clear) {
    Storage::fake('fld-signatures');
    Storage::disk('fld-signatures')->put('signatures/old.png', 'old');
    $model = signatureModel('signatures/old.png');

    Signature::make('Signature', 'description')->storeOnDisk('fld-signatures')->fill(new Request(['description' => $clear]), $model);

    expect($model->description)->toBeNull();
    Storage::disk('fld-signatures')->assertMissing('signatures/old.png');
})->with(['empty string' => [''], 'clear keyword' => ['clear']]);

it('keeps an existing path sent back in disk mode', function () {
    $model = signatureModel('signatures/old.png');

    Signature::make('Signature', 'description')->storeOnDisk('fld-signatures')
        ->fill(new Request(['description' => 'signatures/other.png']), $model);

    expect($model->description)->toBe('signatures/other.png');
});

it('ignores a missing value and readonly or disabled fields', function () {
    $model = signatureModel(signatureDataUrl('old'));

    Signature::make('Signature', 'description')->fill(new Request([]), $model);
    Signature::make('Signature', 'description')->readonly()->fill(new Request(['description' => '']), $model);
    Signature::make('Signature', 'description')->disabled()->fill(new Request(['description' => '']), $model);

    expect($model->description)->toBe(signatureDataUrl('old'));
});

it('adds no validation rule of its own', function () {
    expect(Signature::make('Signature', 'signature')->getRules())->toBe([])
        ->and(Signature::make('Signature', 'signature')->required()->getRules())->toBe(['required']);
});

it('clamps the quality given to jpeg() and webp()', function () {
    $props = Signature::make('Signature', 'signature')->jpeg(150)->toArray(createNadotaRequest())['props'];

    expect($props['quality'])->toBe(100);
})->skip('ISSUE FLD-027: quality() clamps to 1..100 but jpeg()/webp() assign the quality unchecked');

