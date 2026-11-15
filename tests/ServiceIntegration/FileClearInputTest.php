<?php

use Illuminate\Support\Facades\Storage;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\Image;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * `clear` removes a stored file (File::fill). Since File/Image validate the upload on the server
 * (`file`, `mimes:`…), the literal `clear` failed those rules, so removing a file from the form was a
 * 422. A non-required field now skips the upload rules when it receives `clear`.
 */
function makeFileClearResource(string $class, bool $required): Resource
{
    return new class($class, $required) extends Resource
    {
        public string $model = TestModel::class;

        public function __construct(private string $class, private bool $required)
        {
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            $file = $this->class::make('Photo', 'description')->disk('local')->accept(['png']);

            return [
                Input::make('Name', 'name')->required(),
                $this->required ? $file->required() : $file->nullable(),
            ];
        }
    };
}

function fileClearRequest(array $input, string $class = File::class, bool $required = false): NadotaRequest
{
    $request = new NadotaRequest();
    $request->merge($input);
    $request->setResource(makeFileClearResource($class, $required));

    return $request;
}

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/old.png', 'old');
    $this->model = TestModel::create(['name' => 'Original', 'description' => 'uploads/old.png']);
});

it('removes the stored file through the update service', function (string $class) {
    $response = (new ResourceUpdateService())->handle(fileClearRequest(['name' => 'Original', 'description' => 'clear'], $class), $this->model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and($this->model->fresh()->description)->toBeNull();
    Storage::disk('local')->assertMissing('uploads/old.png');
})->with([File::class, Image::class]);

it('still validates other text on a file field', function () {
    $response = (new ResourceUpdateService())->handle(fileClearRequest(['name' => 'Original', 'description' => 'uploads/other.png']), $this->model->id);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey('description');
    Storage::disk('local')->assertExists('uploads/old.png');
});

it('does not let `clear` empty a required file', function () {
    $response = (new ResourceUpdateService())->handle(fileClearRequest(['name' => 'Original', 'description' => 'clear'], required: true), $this->model->id);

    expect($response->getStatusCode())->toBe(422)
        ->and($this->model->fresh()->description)->toBe('uploads/old.png');
    Storage::disk('local')->assertExists('uploads/old.png');
});

it('sends the upload rules to the frontend', function () {
    $field = File::make('Photo', 'description')->nullable()->accept(['png']);

    expect($field->toArray(new NadotaRequest())['rules'])->toContain('file');
});
