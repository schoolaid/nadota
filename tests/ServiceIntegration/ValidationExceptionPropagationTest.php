<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * Regression tests for exception handling inside the persist pipeline.
 *
 * Historically, AbstractResourcePersistService::handle() wrapped every
 * \Exception into a 500 JsonResponse. That swallowed ValidationException
 * thrown from beforeStore/beforeUpdate hooks, so consumers could not
 * surface a proper 422 with field-level errors. These tests lock in the
 * new behavior: ValidationException propagates, everything else is
 * still converted to a 500 response.
 */

function makeUpdateResourceThrowing(\Throwable $exception): Resource
{
    return new class($exception) extends Resource
    {
        public string $model = TestModel::class;

        public function __construct(private \Throwable $exception)
        {
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name')->required(),
            ];
        }

        public function beforeUpdate(Model $model, NadotaRequest $request): void
        {
            throw $this->exception;
        }
    };
}

function makeCreateResourceThrowing(\Throwable $exception): Resource
{
    return new class($exception) extends Resource
    {
        public string $model = TestModel::class;

        public function __construct(private \Throwable $exception)
        {
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name')->required(),
            ];
        }

        public function beforeStore(Model $model, NadotaRequest $request): void
        {
            throw $this->exception;
        }
    };
}

it('propagates ValidationException thrown from beforeUpdate as 422', function () {
    $model = TestModel::create(['name' => 'Original']);

    $resource = makeUpdateResourceThrowing(
        ValidationException::withMessages(['name' => 'Name is already taken.'])
    );

    $request = new NadotaRequest();
    $request->merge(['name' => 'Updated']);
    $request->setResource($resource);

    $service = new ResourceUpdateService();

    $service->handle($request, $model->id);
})->throws(ValidationException::class);

it('still converts generic exceptions from beforeUpdate into a 500 response', function () {
    $model = TestModel::create(['name' => 'Original']);

    $resource = makeUpdateResourceThrowing(new \RuntimeException('boom'));

    $request = new NadotaRequest();
    $request->merge(['name' => 'Updated']);
    $request->setResource($resource);

    $service = new ResourceUpdateService();

    $response = $service->handle($request, $model->id);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true))->toMatchArray([
            'message' => 'Failed to update resource',
            'error' => 'boom',
        ]);
});

it('propagates ValidationException thrown from beforeStore as 422', function () {
    $resource = makeCreateResourceThrowing(
        ValidationException::withMessages(['name' => 'Name is already taken.'])
    );

    $request = new NadotaRequest();
    $request->merge(['name' => 'New']);
    $request->setResource($resource);

    $service = new ResourceStoreService();

    $service->handle($request);
})->throws(ValidationException::class);

it('rolls back the transaction when ValidationException is thrown', function () {
    $model = TestModel::create(['name' => 'Original']);

    $resource = new class extends Resource
    {
        public string $model = TestModel::class;

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name')->required(),
            ];
        }

        public function beforeUpdate(Model $model, NadotaRequest $request): void
        {
            $model->name = 'Dirty';
            $model->save();

            throw ValidationException::withMessages(['name' => 'nope']);
        }
    };

    $request = new NadotaRequest();
    $request->merge(['name' => 'Updated']);
    $request->setResource($resource);

    $service = new ResourceUpdateService();

    try {
        $service->handle($request, $model->id);
    } catch (ValidationException) {
        // expected
    }

    expect($model->fresh()->name)->toBe('Original');
});
