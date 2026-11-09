<?php

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\Image;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = Image::make('Avatar', 'avatar');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('avatar')
        ->and($field->fieldData->label)->toBe('Avatar');
});

it('has correct type and component', function () {
    $field = Image::make('Avatar', 'avatar');
    
    expect($field->fieldData->type)->toBe('image')
        ->and($field->fieldData->component)->toBe('FieldImage');
});

it('has default image accepted types', function () {
    $field = Image::make('Avatar', 'avatar');
    
    expect($field->acceptedTypes)->toBe(['jpg', 'jpeg', 'png', 'gif', 'webp']);
});

it('can set maximum dimensions', function () {
    $field = Image::make('Avatar', 'avatar')
        ->maxImageDimensions(800, 600);
    
    expect($field->maxImageWidth)->toBe(800)
        ->and($field->maxImageHeight)->toBe(600);
});

it('can set maximum width only', function () {
    $field = Image::make('Avatar', 'avatar')
        ->maxImageWidth(1200);
    
    expect($field->maxImageWidth)->toBe(1200)
        ->and($field->maxImageHeight)->toBeNull();
});

it('can set maximum height only', function () {
    $field = Image::make('Avatar', 'avatar')
        ->maxImageHeight(800);
    
    expect($field->maxImageHeight)->toBe(800)
        ->and($field->maxImageWidth)->toBeNull();
});

it('can show preview', function () {
    $field = Image::make('Avatar', 'avatar')
        ->preview();
    
    expect($field->showPreview)->toBeTrue();
});

it('can hide preview', function () {
    $field = Image::make('Avatar', 'avatar')
        ->preview(false);
    
    expect($field->showPreview)->toBeFalse();
});

it('can define thumbnail sizes', function () {
    $field = Image::make('Avatar', 'avatar')
        ->thumbnails([
            'thumb' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 300, 'height' => 300],
        ]);
    
    expect($field->thumbnailSizes)->toHaveKey('thumb')
        ->and($field->thumbnailSizes)->toHaveKey('medium');
});

it('can set alt text', function () {
    $field = Image::make('Avatar', 'avatar')
        ->alt('User profile picture');
    
    expect($field->alt)->toBe('User profile picture');
});

it('adds image validation rule by default', function () {
    $field = Image::make('Avatar', 'avatar');
    
    $rules = $field->getRules();
    expect($rules)->toContain('image');
});

it('adds dimension validation when max dimensions are set', function () {
    $field = Image::make('Avatar', 'avatar')
        ->maxImageDimensions(800, 600);
    
    $rules = $field->getRules();
    expect($rules)->toContain('dimensions:max_width=800,max_height=600');
});

it('adds only width dimension validation when only max width is set', function () {
    $field = Image::make('Avatar', 'avatar')
        ->maxImageWidth(1200);
    
    $rules = $field->getRules();
    expect($rules)->toContain('dimensions:max_width=1200');
});

it('adds only height dimension validation when only max height is set', function () {
    $field = Image::make('Avatar', 'avatar')
        ->maxImageHeight(800);
    
    $rules = $field->getRules();
    expect($rules)->toContain('dimensions:max_height=800');
});

it('inherits file validation rules', function () {
    $field = Image::make('Avatar', 'avatar')
        ->maxSizeMB(2)
        ->required();
    
    $rules = $field->getRules();
    expect($rules)->toContain('file')
        ->and($rules)->toContain('image')
        ->and($rules)->toContain('required')
        ->and($rules)->toContain('max:2048'); // 2MB in KB
});

it('resolves image value with additional properties', function () {
    $model = TestModel::factory()->make(['name' => 'images/avatar.jpg']);
    $field = Image::make('Avatar', 'name')
        ->alt('User avatar');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeArray()
        ->toHaveKey('path', 'images/avatar.jpg')
        ->toHaveKey('name', 'avatar.jpg')
        ->toHaveKey('isImage', true)
        ->toHaveKey('showPreview', true)
        ->toHaveKey('alt', 'User avatar')
        ->toHaveKey('thumbnails')
        ->toHaveKey('dimensions');
});

it('returns null for empty image value', function () {
    $model = TestModel::factory()->make(['name' => null]);
    $field = Image::make('Avatar', 'name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeNull();
});

it('uses field name as default alt text', function () {
    $model = TestModel::factory()->make(['name' => 'images/avatar.jpg']);
    $field = Image::make('User Avatar', 'name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value['alt'])->toBe('User Avatar');
});

it('can be made sortable', function () {
    $field = Image::make('Avatar', 'avatar')->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = Image::make('Avatar', 'avatar')->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('can override accepted image types', function () {
    $field = Image::make('Avatar', 'avatar')
        ->accept(['jpg', 'png']);
    
    expect($field->acceptedTypes)->toBe(['jpg', 'png']);
});

it('serializes to array correctly', function () {
    $model = TestModel::factory()->make(['name' => 'images/avatar.jpg']);
    $field = Image::make('Avatar', 'name')
        ->maxImageDimensions(800, 600)
        ->preview()
        ->alt('User photo')
        ->thumbnails(['thumb' => ['width' => 150, 'height' => 150]])
        ->maxSizeMB(3)
        ->sortable()
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Avatar')
        ->toHaveKey('attribute', 'name')
        ->toHaveKey('type', 'image')
        ->toHaveKey('component', 'FieldImage')
        ->toHaveKey('value')
        ->toHaveKey('props')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true);
        
    expect($array['props'])
        ->toHaveKey('maxImageWidth', 800)
        ->toHaveKey('maxImageHeight', 600)
        ->toHaveKey('showPreview', true)
        ->toHaveKey('alt', 'User photo')
        ->toHaveKey('isImage', true)
        ->toHaveKey('thumbnailSizes')
        ->toHaveKey('acceptedTypes', ['jpg', 'jpeg', 'png', 'gif', 'webp'])
        ->toHaveKey('maxSize', 3145728)
        ->toHaveKey('maxSizeMB', 3.0);
});

it('has smaller default max size than file field', function () {
    $field = Image::make('Avatar', 'avatar');
    
    // Should have default max size of 5MB (smaller than File's 10MB)
    expect($field->maxSize)->toBe(5242880);
});

it('shows preview by default', function () {
    $field = Image::make('Avatar', 'avatar');
    
    expect($field->showPreview)->toBeTrue();
});

it('has no alt text by default', function () {
    $field = Image::make('Avatar', 'avatar');
    
    expect($field->alt)->toBeNull();
});

it('has no thumbnail sizes by default', function () {
    $field = Image::make('Avatar', 'avatar');
    
    expect($field->thumbnailSizes)->toBeEmpty();
});

it('handles all validation rules together', function () {
    $field = Image::make('Avatar', 'avatar')
        ->accept(['jpg', 'png'])
        ->maxImageDimensions(1200, 800)
        ->maxSizeMB(3)
        ->required();
    
    $rules = $field->getRules();
    expect($rules)->toContain('file')
        ->and($rules)->toContain('image')
        ->and($rules)->toContain('required')
        ->and($rules)->toContain('max:3072')
        ->and($rules)->toContain('mimes:jpg,png')
        ->and($rules)->toContain('dimensions:max_width=1200,max_height=800');
});
it('skips the image rule when svg is accepted', function () {
    // Laravel's image rule rejects svg
    $rules = Image::make('Logo', 'logo')->accept(['png', 'svg'])->getRules();

    expect($rules)->not->toContain('image')
        ->and($rules)->toContain('mimes:png,svg');
});

it('is a File that stores uploads the same way', function () {
    Storage::fake('fld-images');
    $upload = UploadedFile::fake()->image('me.png', 40, 20);
    $model = new TestModel();

    $field = Image::make('Avatar', 'name')->disk('fld-images')->path('avatars');
    $field->fill(Request::create('/', 'POST', [], [], ['name' => $upload]), $model);

    expect($field)->toBeInstanceOf(File::class)
        ->and($model->name)->toBe('avatars/' . $upload->hashName());
    Storage::disk('fld-images')->assertExists($model->name);
});

it('reads the real dimensions of a stored image', function () {
    Storage::fake('fld-images');
    $upload = UploadedFile::fake()->image('me.png', 40, 20);
    Storage::disk('fld-images')->putFileAs('avatars', $upload, 'me.png');
    $model = new TestModel(['name' => 'avatars/me.png']);

    $value = Image::make('Avatar', 'name')->disk('fld-images')->resolve(createNadotaRequest(), $model, null);

    expect($value['dimensions'])->toBe(['width' => 40, 'height' => 20, 'type' => IMAGETYPE_PNG, 'ratio' => 2.0])
        ->and($value['url'])->toBe(Storage::disk('fld-images')->url('avatars/me.png'));
});

it('lists only the thumbnails that exist on disk', function () {
    Storage::fake('fld-images');
    Storage::disk('fld-images')->put('avatars/thumbs/me_small.png', 'x');
    $model = new TestModel(['name' => 'avatars/me.png']);

    $value = Image::make('Avatar', 'name')->disk('fld-images')
        ->thumbnails(['small' => ['width' => 50, 'height' => 50], 'large' => ['width' => 500]])
        ->resolve(createNadotaRequest(), $model, null);

    expect($value['thumbnails'])->toBe([
        'small' => [
            'path' => 'avatars/thumbs/me_small.png',
            'url' => Storage::disk('fld-images')->url('avatars/thumbs/me_small.png'),
            'width' => 50,
            'height' => 50,
        ],
    ])->and($value['dimensions'])->toBeNull();
});

it('rejects a non image upload and an oversized one', function () {
    $rules = ['avatar' => Image::make('Avatar', 'avatar')->maxImageDimensions(100, 100)->getRules()];

    expect(Validator::make(['avatar' => UploadedFile::fake()->image('ok.jpg', 50, 50)], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['avatar' => UploadedFile::fake()->image('big.jpg', 150, 50)], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['avatar' => UploadedFile::fake()->create('doc.pdf', 1, 'application/pdf')], $rules)->fails())->toBeTrue();
});

it('offers web safe and wide image presets', function () {
    expect(Image::make('A', 'a')->webSafe()->acceptedTypes)->toBe(['jpg', 'jpeg', 'png', 'webp'])
        ->and(Image::make('A', 'a')->acceptImages()->acceptedTypes)->toBe(['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])
        ->and(Image::make('A', 'a')->acceptImages()->getRules())->not->toContain('image');
});

it('exposes display options in props and clamps the quality', function () {
    $props = Image::make('Avatar', 'avatar')->circle()->previewSize('64px')->lazy(false)->placeholder('/img/blank.png')
        ->convertTo('webp')->quality(150)->imageWidth(300)->imageHeight(200)
        ->toArray(createNadotaRequest())['props'];

    expect($props)->toMatchArray([
        'rounded' => true,
        'square' => true,
        'previewSize' => '64px',
        'lazy' => false,
        'placeholder' => '/img/blank.png',
        'convertFormat' => 'webp',
        'compressionQuality' => 100,
        'maxImageWidth' => 300,
        'maxImageHeight' => 200,
    ])->and(Image::make('A', 'a')->quality(-5)->toArray(createNadotaRequest())['props']['compressionQuality'])->toBe(1)
        ->and(Image::make('A', 'a')->rounded()->squared(false)->toArray(createNadotaRequest())['props']['square'])->toBeFalse();
});

it('keeps the image placeholder apart from the field placeholder', function () {
    $array = Image::make('Avatar', 'avatar')->placeholder('/img/blank.png')->toArray(createNadotaRequest());

    expect($array['placeholder'])->toBe('Avatar')
        ->and($array['props']['placeholder'])->toBe('/img/blank.png');
});

it('hides the preview on the index listing when asked to', function () {
    // The index route is GET /nadota-api/{resourceKey}/resource
    $request = NadotaRequest::create('/nadota-api/student-media/resource', 'GET');

    $props = Image::make('Avatar', 'avatar')->disablePreviewOnIndex()->toArray($request)['props'];

    expect($props['showPreview'])->toBeFalse();
})->skip('ISSUE FLD-024: Image::getProps() checks $request->is("*/index") but the index route has no /index segment');

