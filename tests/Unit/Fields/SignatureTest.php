<?php

use SchoolAid\Nadota\Http\Fields\Signature;

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
    $field = Signature::make('Signature', 'signature')
        ->disk('s3')
        ->path('form-signatures');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['storageMode'])->toBe('disk');
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
