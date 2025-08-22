<?php

use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Tests\Models\TestModel;
use Illuminate\Http\UploadedFile;

it('can be instantiated', function () {
    $field = File::make('Document', 'document');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('document')
        ->and($field->fieldData->name)->toBe('Document');
});

it('has correct type and component', function () {
    $field = File::make('Document', 'document');
    
    expect($field->fieldData->type->value)->toBe('file')
        ->and($field->fieldData->component)->toBe('field-file');
});

it('can set accepted file types', function () {
    $field = File::make('Document', 'document')
        ->accept(['pdf', 'doc', 'docx']);
    
    expect($field->acceptedTypes)->toBe(['pdf', 'doc', 'docx']);
});

it('can set max size in bytes', function () {
    $field = File::make('Document', 'document')
        ->maxSize(5242880); // 5MB
    
    expect($field->maxSize)->toBe(5242880);
});

it('can set max size in megabytes', function () {
    $field = File::make('Document', 'document')
        ->maxSizeMB(10);
    
    expect($field->maxSize)->toBe(10485760); // 10MB in bytes
});

it('can set storage disk', function () {
    $field = File::make('Document', 'document')
        ->disk('public');
    
    expect($field->disk)->toBe('public');
});

it('can set storage path', function () {
    $field = File::make('Document', 'document')
        ->path('documents');
    
    expect($field->path)->toBe('documents');
});

it('can be made downloadable', function () {
    $field = File::make('Document', 'document')
        ->downloadable();
    
    expect($field->downloadable)->toBeTrue();
});

it('can be made not downloadable', function () {
    $field = File::make('Document', 'document')
        ->downloadable(false);
    
    expect($field->downloadable)->toBeFalse();
});

it('can set custom download route', function () {
    $field = File::make('Document', 'document')
        ->downloadRoute('custom.download');
    
    expect($field->downloadRoute)->toBe('custom.download');
});

it('adds file validation rule by default', function () {
    $field = File::make('Document', 'document');
    
    $rules = $field->getRules();
    expect($rules)->toContain('file');
});

it('adds max size validation rule when set', function () {
    $field = File::make('Document', 'document')
        ->maxSizeMB(5);
    
    $rules = $field->getRules();
    expect($rules)->toContain('max:5120'); // 5MB in KB
});

it('adds mime type validation when accepted types are set', function () {
    $field = File::make('Document', 'document')
        ->accept(['pdf', 'doc']);
    
    $rules = $field->getRules();
    expect($rules)->toContain('mimes:pdf,doc');
});

it('converts file extensions to mime types correctly', function () {
    $field = File::make('Document', 'document')
        ->accept(['jpg', 'png', 'pdf']);
    
    $rules = $field->getRules();
    expect($rules)->toContain('mimes:jpeg,png,pdf');
});

it('handles mixed mime types and extensions', function () {
    $field = File::make('Document', 'document')
        ->accept(['image/jpeg', 'png', 'application/pdf']);
    
    $rules = $field->getRules();
    expect($rules[2])->toContain('mimes:');
    expect($rules[2])->toContain('jpeg');
    expect($rules[2])->toContain('png');
    expect($rules[2])->toContain('pdf');
});

it('resolves file value from model correctly', function () {
    $model = TestModel::factory()->make(['name' => 'documents/test.pdf']);
    $field = File::make('Document', 'name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeArray()
        ->toHaveKey('path', 'documents/test.pdf')
        ->toHaveKey('name', 'test.pdf')
        ->toHaveKey('downloadable', true);
});

it('returns null for empty file value', function () {
    $model = TestModel::factory()->make(['name' => null]);
    $field = File::make('Document', 'name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeNull();
});

it('can be made sortable', function () {
    $field = File::make('Document', 'document')->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = File::make('Document', 'document')->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('can be made required', function () {
    $field = File::make('Document', 'document')->required();
    
    $rules = $field->getRules();
    expect($rules)->toContain('required');
});

it('serializes to array correctly', function () {
    $model = TestModel::factory()->make(['name' => 'documents/test.pdf']);
    $field = File::make('Document', 'name')
        ->accept(['pdf', 'doc'])
        ->maxSizeMB(10)
        ->disk('public')
        ->path('documents')
        ->downloadable()
        ->sortable()
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('name', 'Document')
        ->toHaveKey('attribute', 'name')
        ->toHaveKey('type', 'file')
        ->toHaveKey('component', 'field-file')
        ->toHaveKey('value')
        ->toHaveKey('props')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true);
        
    expect($array['props'])
        ->toHaveKey('acceptedTypes', ['pdf', 'doc'])
        ->toHaveKey('maxSize', 10485760)
        ->toHaveKey('maxSizeMB', 10.0)
        ->toHaveKey('disk', 'public')
        ->toHaveKey('path', 'documents')
        ->toHaveKey('downloadable', true);
});

it('has default max size from config', function () {
    $field = File::make('Document', 'document');
    
    // Should have default max size of 10MB
    expect($field->maxSize)->toBe(10485760);
});

it('is downloadable by default', function () {
    $field = File::make('Document', 'document');
    
    expect($field->downloadable)->toBeTrue();
});

it('handles all validation rules together', function () {
    $field = File::make('Document', 'document')
        ->accept(['pdf', 'doc'])
        ->maxSizeMB(5)
        ->required();
    
    $rules = $field->getRules();
    expect($rules)->toContain('file')
        ->and($rules)->toContain('required')
        ->and($rules)->toContain('max:5120')
        ->and($rules)->toContain('mimes:pdf,doc');
});