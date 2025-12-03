<?php

use SchoolAid\Nadota\Http\Fields\Image;
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
    
    expect($field->fieldData->type->value)->toBe('image')
        ->and($field->fieldData->component)->toBe('field-image');
});

it('has default image accepted types', function () {
    $field = Image::make('Avatar', 'avatar');
    
    expect($field->acceptedTypes)->toBe(['jpg', 'jpeg', 'png', 'gif', 'webp']);
});

it('can set maximum dimensions', function () {
    $field = Image::make('Avatar', 'avatar')
        ->maxDimensions(800, 600);
    
    expect($field->maxWidth)->toBe(800)
        ->and($field->maxHeight)->toBe(600);
});

it('can set maximum width only', function () {
    $field = Image::make('Avatar', 'avatar')
        ->maxWidth(1200);
    
    expect($field->maxWidth)->toBe(1200)
        ->and($field->maxHeight)->toBeNull();
});

it('can set maximum height only', function () {
    $field = Image::make('Avatar', 'avatar')
        ->maxHeight(800);
    
    expect($field->maxHeight)->toBe(800)
        ->and($field->maxWidth)->toBeNull();
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
        ->maxDimensions(800, 600);
    
    $rules = $field->getRules();
    expect($rules)->toContain('dimensions:max_width=800,max_height=600');
});

it('adds only width dimension validation when only max width is set', function () {
    $field = Image::make('Avatar', 'avatar')
        ->maxWidth(1200);
    
    $rules = $field->getRules();
    expect($rules)->toContain('dimensions:max_width=1200');
});

it('adds only height dimension validation when only max height is set', function () {
    $field = Image::make('Avatar', 'avatar')
        ->maxHeight(800);
    
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
        ->toHaveKey('label', 'avatar.jpg')
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
        ->maxDimensions(800, 600)
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
        ->toHaveKey('component', 'field-image')
        ->toHaveKey('value')
        ->toHaveKey('props')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true);
        
    expect($array['props'])
        ->toHaveKey('maxWidth', 800)
        ->toHaveKey('maxHeight', 600)
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
        ->maxDimensions(1200, 800)
        ->maxSizeMB(3)
        ->required();
    
    $rules = $field->getRules();
    expect($rules)->toContain('file')
        ->and($rules)->toContain('image')
        ->and($rules)->toContain('required')
        ->and($rules)->toContain('max:3072')
        ->and($rules)->toContain('mimes:jpeg,png')
        ->and($rules)->toContain('dimensions:max_width=1200,max_height=800');
});