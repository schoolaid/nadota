<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\Image;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * Upload validation through the real store/update pipeline.
 *
 * File::getRules() and Image::getRules() had their rule generation commented
 * out, so any file of any type or size was stored regardless of accept(),
 * maxSize() or maxImageDimensions(). These tests lock the rules back in, and
 * make sure an edit that leaves the file untouched (the form sends null) still
 * passes.
 */

beforeEach(function () {
    Storage::fake('local');
});

function uploadResource(\Closure $field): Resource
{
    return new class($field) extends Resource
    {
        public string $model = TestModel::class;

        public function __construct(private \Closure $field)
        {
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name')->required(),
                ($this->field)(),
            ];
        }
    };
}

function uploadRequest(Resource $resource, array $input, array $files = []): NadotaRequest
{
    $request = new NadotaRequest();
    $request->merge($input);
    foreach ($files as $key => $file) {
        $request->files->set($key, $file);
    }
    $request->setResource($resource);

    return $request;
}

it('rejects an upload whose type is not accepted', function () {
    $resource = uploadResource(fn () => File::make('Document', 'description')->disk('local')->accept(['pdf']));
    $php = UploadedFile::fake()->createWithContent('shell.php', '<?php echo "pwned";');

    $response = (new ResourceStoreService())->handle(uploadRequest($resource, ['name' => 'A'], ['description' => $php]));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey('description')
        ->and(TestModel::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects an upload larger than maxSize', function () {
    $resource = uploadResource(fn () => File::make('Document', 'description')->disk('local')->maxSizeMB(1));
    $big = UploadedFile::fake()->create('big.pdf', 2048, 'application/pdf');

    $response = (new ResourceStoreService())->handle(uploadRequest($resource, ['name' => 'A'], ['description' => $big]));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey('description');
});

it('stores an accepted upload', function () {
    $resource = uploadResource(fn () => File::make('Document', 'description')->disk('local')->accept(['pdf'])->maxSizeMB(1));
    $pdf = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    $response = (new ResourceStoreService())->handle(uploadRequest($resource, ['name' => 'A'], ['description' => $pdf]));

    expect($response->getStatusCode())->toBeLessThan(300)
        ->and(TestModel::first()->description)->toStartWith('uploads/');
});

it('keeps the stored file when an edit sends null for an untouched file input', function () {
    $model = TestModel::create(['name' => 'A', 'description' => 'uploads/existing.pdf']);
    $resource = uploadResource(fn () => File::make('Document', 'description')->disk('local')->accept(['pdf']));

    $response = (new ResourceUpdateService())->handle(
        uploadRequest($resource, ['name' => 'B', 'description' => null]),
        $model->id
    );

    expect($response->getStatusCode())->toBeLessThan(300)
        ->and($model->fresh()->name)->toBe('B')
        ->and($model->fresh()->description)->toBe('uploads/existing.pdf');
});

it('requires the upload on creation when declared with creationRules', function () {
    $resource = uploadResource(fn () => File::make('Document', 'description')->disk('local')->creationRules(['required']));

    $response = (new ResourceStoreService())->handle(uploadRequest($resource, ['name' => 'A', 'description' => null]));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey('description');
});

it('rejects a non-image upload on an Image field', function () {
    $resource = uploadResource(fn () => Image::make('Avatar', 'description')->disk('local'));
    $pdf = UploadedFile::fake()->create('avatar.pdf', 100, 'application/pdf');

    $response = (new ResourceStoreService())->handle(uploadRequest($resource, ['name' => 'A'], ['description' => $pdf]));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey('description');
});

it('rejects an image larger than maxImageDimensions', function () {
    $resource = uploadResource(fn () => Image::make('Avatar', 'description')->disk('local')->maxImageDimensions(100, 100));
    $image = UploadedFile::fake()->image('avatar.png', 200, 50);

    $response = (new ResourceStoreService())->handle(uploadRequest($resource, ['name' => 'A'], ['description' => $image]));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey('description');
});
