<?php

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Tests\Models\TestModel;

/** A request carrying one uploaded file under $attribute. */
function fileUploadRequest(string $attribute, UploadedFile $file): Request
{
    return Request::create('/', 'POST', [], [], [$attribute => $file]);
}

/** A TestModel whose original "name" (used as the file column) is $path. */
function fileModelWithStoredPath(?string $path): TestModel
{
    $model = new TestModel(['name' => $path]);
    $model->syncOriginal();

    return $model;
}

it('can be instantiated', function () {
    $field = File::make('Document', 'document');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('document')
        ->and($field->fieldData->label)->toBe('Document');
});

it('has correct type and component', function () {
    $field = File::make('Document', 'document');
    
    expect($field->fieldData->type)->toBe('file')
        ->and($field->fieldData->component)->toBe('FieldFile');
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

it('passes accepted extensions to the mimes rule unchanged', function () {
    // `mimes` takes extensions; mapping docx/xlsx/txt to MIME subtypes made those uploads always fail
    $field = File::make('Document', 'document')
        ->accept(['.jpg', 'PNG', 'docx', 'xlsx', 'txt']);

    expect($field->getRules())->toContain('mimes:jpg,png,docx,xlsx,txt');
});

it('uses a single mimetypes rule when MIME types are accepted', function () {
    $field = File::make('Document', 'document')
        ->accept(['image/*', 'application/pdf']);

    $rules = $field->getRules();
    expect($rules)->toContain('mimetypes:image/*,application/pdf')
        ->and(collect($rules)->contains(fn ($rule) => is_string($rule) && str_starts_with($rule, 'mimes:')))->toBeFalse();
});

it('handles mixed mime types and extensions', function () {
    $field = File::make('Document', 'document')
        ->accept(['image/jpeg', 'png', 'application/pdf']);
    
    // mimes + mimetypes would both have to pass, so a mixed list collapses into one mimetypes rule
    $rules = $field->getRules();
    $typeRules = collect($rules)->filter(fn ($rule) => is_string($rule) && preg_match('/^mime(s|types):/', $rule))->values();

    expect($typeRules)->toHaveCount(1)
        ->and($typeRules[0])->toStartWith('mimetypes:')
        ->and(explode(',', substr($typeRules[0], strlen('mimetypes:'))))
        ->toContain('image/jpeg', 'image/png', 'application/pdf');
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
        ->toHaveKey('label', 'Document')
        ->toHaveKey('attribute', 'name')
        ->toHaveKey('type', 'file')
        ->toHaveKey('component', 'FieldFile')
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
it('makes the upload rules nullable unless the field is required', function () {
    // The form sends null for a file input the user left untouched on edit
    expect(File::make('Document', 'document')->getRules())->toContain('nullable')
        ->and(File::make('Document', 'document')->required()->getRules())->not->toContain('nullable')
        ->and(File::make('Document', 'document')->creationRules(['required'])->getRulesFor(false))
        ->toContain('required')->not->toContain('nullable')
        ->and(File::make('Document', 'document')->creationRules(['required'])->getRulesFor(true))
        ->toContain('nullable');
});

it('reports a required file as required on creation only when using creationRules', function () {
    $field = File::make('Document', 'document')->creationRules(['required']);

    expect($field->isRequired(false))->toBeTrue()
        ->and($field->isRequired(true))->toBeFalse();
});

it('stores the upload on the configured disk and path under its hash name', function () {
    Storage::fake('fld-files');
    $upload = UploadedFile::fake()->create('report.pdf', 20, 'application/pdf');
    $model = new TestModel();

    File::make('Document', 'name')->disk('fld-files')->path('docs')->fill(fileUploadRequest('name', $upload), $model);

    expect($model->name)->toBe('docs/' . $upload->hashName());
    Storage::disk('fld-files')->assertExists($model->name);
});

it('falls back to the default disk and the uploads directory', function () {
    config()->set('filesystems.default', 'fld-files-default');
    Storage::fake('fld-files-default');
    $upload = UploadedFile::fake()->create('a.txt', 1, 'text/plain');
    $model = new TestModel();

    File::make('Document', 'name')->fill(fileUploadRequest('name', $upload), $model);

    expect($model->name)->toStartWith('uploads/');
    Storage::disk('fld-files-default')->assertExists($model->name);
});

it('leaves the stored file alone when no upload is sent', function (array $input) {
    Storage::fake('fld-files');
    $model = fileModelWithStoredPath('docs/old.pdf');

    File::make('Document', 'name')->disk('fld-files')->fill(Request::create('/', 'POST', $input), $model);

    expect($model->name)->toBe('docs/old.pdf');
})->with([
    'key absent' => [[]],
    'null (untouched input)' => [['name' => null]],
    'existing path sent back' => [['name' => 'docs/old.pdf']],
]);

it('does not store anything for a readonly or disabled field', function (callable $configure) {
    Storage::fake('fld-files');
    $model = new TestModel();

    $configure(File::make('Document', 'name')->disk('fld-files'))
        ->fill(fileUploadRequest('name', UploadedFile::fake()->create('a.pdf', 1)), $model);

    expect($model->name)->toBeNull()
        ->and(Storage::disk('fld-files')->allFiles())->toBe([]);
})->with([
    'readonly' => [fn ($field) => $field->readonly()],
    'disabled' => [fn ($field) => $field->disabled()],
]);

it('uses the storeAs() callback for the file name', function () {
    Storage::fake('fld-files');
    $model = new TestModel();

    File::make('Document', 'name')->disk('fld-files')->path('docs')
        ->storeAs(fn (Request $request, string $attribute) => 'custom-' . $attribute . '.pdf')
        ->fill(fileUploadRequest('name', UploadedFile::fake()->create('a.pdf', 1)), $model);

    expect($model->name)->toBe('docs/custom-name.pdf');
    Storage::disk('fld-files')->assertExists('docs/custom-name.pdf');
});

it('hands full control to the store() callback', function () {
    Storage::fake('fld-files');
    $model = new TestModel();
    $received = null;

    File::make('Document', 'name')->disk('fld-files')->path('docs')
        ->store(function ($request, $model, $attribute, $requestAttribute, $disk, $path) use (&$received) {
            $received = [$attribute, $requestAttribute, $disk, $path];

            return 'external://file-1';
        })
        ->fill(fileUploadRequest('name', UploadedFile::fake()->create('a.pdf', 1)), $model);

    expect($model->name)->toBe('external://file-1')
        ->and($received)->toBe(['name', 'name', 'fld-files', 'docs'])
        ->and(Storage::disk('fld-files')->allFiles())->toBe([]);
});

it('stores the public url instead of the path when storedAsUrl()', function () {
    Storage::fake('fld-files');
    $upload = UploadedFile::fake()->create('a.pdf', 1);
    $model = new TestModel();

    File::make('Document', 'name')->disk('fld-files')->path('docs')->storedAsUrl()->fill(fileUploadRequest('name', $upload), $model);

    expect($model->name)->toBe(Storage::disk('fld-files')->url('docs/' . $upload->hashName()));
});

it('passes the configured visibility to the storage', function () {
    Storage::fake('fld-files');
    $upload = UploadedFile::fake()->create('a.pdf', 1);
    $model = new TestModel();

    File::make('Document', 'name')->disk('fld-files')->path('docs')->private()->fill(fileUploadRequest('name', $upload), $model);

    expect(Storage::disk('fld-files')->getVisibility($model->name))->toBe('private');
});

it('deletes the replaced file only once the save is committed', function () {
    Storage::fake('fld-files');
    Storage::disk('fld-files')->put('docs/old.pdf', 'old');
    $model = fileModelWithStoredPath('docs/old.pdf');
    $field = File::make('Document', 'name')->disk('fld-files')->path('docs');

    $field->fill(fileUploadRequest('name', UploadedFile::fake()->create('new.pdf', 1)), $model);

    Storage::disk('fld-files')->assertExists('docs/old.pdf');

    $field->commitPendingFiles($model);

    Storage::disk('fld-files')->assertMissing('docs/old.pdf');
    Storage::disk('fld-files')->assertExists($model->name);
});

it('removes the new upload and keeps the old file when the save is rolled back', function () {
    Storage::fake('fld-files');
    Storage::disk('fld-files')->put('docs/old.pdf', 'old');
    $model = fileModelWithStoredPath('docs/old.pdf');
    $field = File::make('Document', 'name')->disk('fld-files')->path('docs');

    $field->fill(fileUploadRequest('name', UploadedFile::fake()->create('new.pdf', 1)), $model);
    $newPath = $model->name;

    $field->discardPendingFiles();

    Storage::disk('fld-files')->assertMissing($newPath);
    Storage::disk('fld-files')->assertExists('docs/old.pdf');
});

it('does not delete the old file when the new one is stored over it', function () {
    Storage::fake('fld-files');
    Storage::disk('fld-files')->put('docs/same.pdf', 'old');
    $model = fileModelWithStoredPath('docs/same.pdf');
    $field = File::make('Document', 'name')->disk('fld-files')->path('docs')->storeAs(fn () => 'same.pdf');

    $field->fill(fileUploadRequest('name', UploadedFile::fake()->create('x.pdf', 1)), $model);
    $field->commitPendingFiles($model);

    Storage::disk('fld-files')->assertExists('docs/same.pdf');
});

it('uses the deleteUsing() callback for replaced files', function () {
    Storage::fake('fld-files');
    Storage::disk('fld-files')->put('docs/old.pdf', 'old');
    $model = fileModelWithStoredPath('docs/old.pdf');
    $deleted = [];
    $field = File::make('Document', 'name')->disk('fld-files')->path('docs')
        ->deleteUsing(function ($model, $disk, $path) use (&$deleted) {
            $deleted[] = [$disk, $path];
        });

    $field->fill(fileUploadRequest('name', UploadedFile::fake()->create('new.pdf', 1)), $model);
    $field->commitPendingFiles($model);

    expect($deleted)->toBe([['fld-files', 'docs/old.pdf']]);
    Storage::disk('fld-files')->assertExists('docs/old.pdf');
});

it('resolves the public url and the download url of the stored file', function () {
    Storage::fake('fld-files');
    $model = fileModelWithStoredPath('docs/report.pdf');

    $value = File::make('Document', 'name')->disk('fld-files')->resolve(createNadotaRequest(), $model, null);

    expect($value)->toBe([
        'path' => 'docs/report.pdf',
        'name' => 'report.pdf',
        'url' => Storage::disk('fld-files')->url('docs/report.pdf'),
        'downloadable' => true,
        'downloadUrl' => Storage::disk('fld-files')->url('docs/report.pdf'),
        'cached' => false,
        'temporary' => false,
    ]);
});

it('has no download url when not downloadable', function () {
    Storage::fake('fld-files');

    $value = File::make('Document', 'name')->disk('fld-files')->downloadable(false)
        ->resolve(createNadotaRequest(), fileModelWithStoredPath('docs/report.pdf'), null);

    expect($value['downloadable'])->toBeFalse()
        ->and($value['downloadUrl'])->toBeNull();
});

it('returns a stored url untouched when storedAsUrl()', function () {
    $value = File::make('Document', 'name')->storedAsUrl()
        ->resolve(createNadotaRequest(), fileModelWithStoredPath('https://cdn.test/a.pdf'), null);

    expect($value['url'])->toBe('https://cdn.test/a.pdf')
        ->and($value['name'])->toBe('a.pdf');
});

it('resolves array values with a path or a value key', function () {
    $model = new TestModel();
    $model->metadata = ['path' => 'docs/a.pdf', 'name' => 'A'];
    expect(File::make('Doc', 'metadata')->resolve(createNadotaRequest(), $model, null))->toBe(['path' => 'docs/a.pdf', 'name' => 'A']);

    $model->metadata = ['value' => 'docs/b.pdf'];
    expect(File::make('Doc', 'metadata')->resolve(createNadotaRequest(), $model, null)['path'])->toBe('docs/b.pdf');

    $model->metadata = ['other' => 'x'];
    expect(File::make('Doc', 'metadata')->resolve(createNadotaRequest(), $model, null))->toBeNull();
});

it('uses a temporary url when the disk supports it', function () {
    Storage::fake('fld-files-s3')->buildTemporaryUrlsUsing(fn ($path) => 'https://signed.test/' . $path);

    $value = File::make('Doc', 'name')->disk('fld-files-s3')->temporaryUrl(10)
        ->resolve(createNadotaRequest(), fileModelWithStoredPath('docs/a.pdf'), null);

    expect($value['url'])->toBe('https://signed.test/docs/a.pdf')
        ->and($value['temporary'])->toBeTrue();
});

it('falls back to the plain url when signing is disabled', function () {
    Storage::fake('fld-files-s3')->buildTemporaryUrlsUsing(fn ($path) => 'https://signed.test/' . $path);

    $value = File::make('Doc', 'name')->disk('fld-files-s3')->temporaryUrl()->withoutSigning()
        ->resolve(createNadotaRequest(), fileModelWithStoredPath('docs/a.pdf'), null);

    expect($value['url'])->toBe(Storage::disk('fld-files-s3')->url('docs/a.pdf'));
});

it('validates the upload size and type', function () {
    $rules = ['doc' => File::make('Doc', 'doc')->accept(['pdf'])->maxSize(10 * 1024)->getRules()];

    expect(Validator::make(['doc' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['doc' => UploadedFile::fake()->create('a.pdf', 50, 'application/pdf')], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['doc' => UploadedFile::fake()->create('a.exe', 5, 'application/x-msdownload')], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['doc' => 'docs/old.pdf'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['doc' => null], $rules)->passes())->toBeTrue();
});

it('exposes every storage option in props', function () {
    $props = File::make('Doc', 'doc')->disk('s3')->path('p')->public()->temporaryUrl()->withoutSigning()->storedAsUrl()->cache()
        ->toArray(createNadotaRequest())['props'];

    expect($props)->toMatchArray([
        'disk' => 's3', 'path' => 'p', 'visibility' => 'public', 'useTemporaryUrl' => true,
        'signUrl' => false, 'storedAsUrl' => true, 'cacheUrl' => true, 'maxSizeMB' => 10.0,
    ]);
});

it('can clear the stored file', function () {
    Storage::fake('fld-files');
    Storage::disk('fld-files')->put('docs/old.pdf', 'old');
    $model = fileModelWithStoredPath('docs/old.pdf');
    $field = File::make('Document', 'name')->disk('fld-files');

    $field->fill(Request::create('/', 'POST', ['name' => '']), $model);
    $field->commitPendingFiles($model);

    expect($model->name)->toBeNull();
    Storage::disk('fld-files')->assertMissing('docs/old.pdf');
})->skip('ISSUE FLD-028: File::fill() only reacts to uploads, there is no way to remove a stored file through the API');

