<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * File uploads against a save that commits or rolls back.
 *
 * File::fill() used to delete the old file before the model was saved, so a
 * save that rolled back left the record pointing at a file that no longer
 * existed, and the new upload stayed on disk as an orphan.
 */

beforeEach(function () {
    Storage::fake('local');
});

function rollbackResource(Closure $field, bool $failAfterSave = false): Resource
{
    return new class($field, $failAfterSave) extends Resource
    {
        public string $model = TestModel::class;

        public function __construct(private Closure $field, private bool $failAfterSave)
        {
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name'),
                ($this->field)(),
            ];
        }

        public function afterStore(Model $model, NadotaRequest $request): void
        {
            if ($this->failAfterSave) {
                throw new RuntimeException('afterStore failed');
            }
        }

        public function afterUpdate(Model $model, NadotaRequest $request, array $originalData): void
        {
            if ($this->failAfterSave) {
                throw new RuntimeException('afterUpdate failed');
            }
        }
    };
}

function rollbackRequest(Resource $resource, array $input, array $files): NadotaRequest
{
    $request = new NadotaRequest();
    $request->merge($input);
    foreach ($files as $key => $file) {
        $request->files->set($key, $file);
    }
    $request->setResource($resource);

    return $request;
}

function documentField(): File
{
    return File::make('Document', 'description')->disk('local')->path('docs');
}

it('deletes the replaced file once the update commits', function () {
    Storage::disk('local')->put('docs/old.pdf', 'old');
    $model = TestModel::create(['name' => 'A', 'description' => 'docs/old.pdf']);

    $response = (new ResourceUpdateService())->handle(
        rollbackRequest(rollbackResource(fn () => documentField()), ['name' => 'A'], [
            'description' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
        ]),
        $model->id
    );

    $path = $model->fresh()->description;

    expect($response->getStatusCode())->toBe(200)
        ->and($path)->not->toBe('docs/old.pdf')
        ->and(Storage::disk('local')->allFiles())->toBe([$path]);
});

it('keeps the old file and drops the new upload when the update rolls back', function () {
    Storage::disk('local')->put('docs/old.pdf', 'old');
    $model = TestModel::create(['name' => 'A', 'description' => 'docs/old.pdf']);

    $response = (new ResourceUpdateService())->handle(
        rollbackRequest(rollbackResource(fn () => documentField(), failAfterSave: true), ['name' => 'B'], [
            'description' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
        ]),
        $model->id
    );

    expect($response->getStatusCode())->toBe(500)
        ->and($model->fresh()->description)->toBe('docs/old.pdf')
        ->and(Storage::disk('local')->allFiles())->toBe(['docs/old.pdf']);
});

it('leaves no upload behind when the store rolls back', function () {
    $response = (new ResourceStoreService())->handle(
        rollbackRequest(rollbackResource(fn () => documentField(), failAfterSave: true), ['name' => 'A'], [
            'description' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
        ])
    );

    expect($response->getStatusCode())->toBe(500)
        ->and(TestModel::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('keeps a file stored over the old path when the update commits', function () {
    Storage::disk('local')->put('docs/report.pdf', 'old');
    $model = TestModel::create(['name' => 'A', 'description' => 'docs/report.pdf']);
    $field = fn () => documentField()->storeAs(fn () => 'report.pdf');

    (new ResourceUpdateService())->handle(
        rollbackRequest(rollbackResource($field), ['name' => 'A'], [
            'description' => UploadedFile::fake()->createWithContent('report.pdf', 'new'),
        ]),
        $model->id
    );

    expect($model->fresh()->description)->toBe('docs/report.pdf')
        ->and(Storage::disk('local')->get('docs/report.pdf'))->toBe('new');
});
