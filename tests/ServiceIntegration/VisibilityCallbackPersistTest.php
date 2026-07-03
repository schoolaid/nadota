<?php

use SchoolAid\Nadota\Http\Fields\Checkbox;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * Regression tests for request-dependent visibility callbacks in the
 * persist pipeline.
 *
 * filterFieldsForStore/filterFieldsForUpdate used to call
 * isShowOnCreation()/isShowOnUpdate() without the request, so a callback
 * like showOnUpdate(fn($request) => $request?->user()?->isAdmin()) was
 * evaluated with a null request during persist. The callback returned
 * false, the field was silently dropped, and its value never saved even
 * though the same callback made the field visible on the rendered form.
 */

function makeAdminUser(): object
{
    return new class {
        public function isAdmin(): bool
        {
            return true;
        }
    };
}

function makeVisibilityCallbackResource(): Resource
{
    return new class extends Resource
    {
        public string $model = TestModel::class;

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name')->required(),
                Checkbox::make('Is Active', 'is_active')
                    ->showOnCreation(fn($request) => $request?->user()?->isAdmin() ?? false)
                    ->showOnUpdate(fn($request) => $request?->user()?->isAdmin() ?? false),
            ];
        }
    };
}

it('persists a field whose showOnUpdate callback depends on the request user', function () {
    $model = TestModel::create(['name' => 'Original', 'is_active' => false]);

    $request = new NadotaRequest();
    $request->merge(['name' => 'Updated', 'is_active' => true]);
    $request->setUserResolver(fn() => makeAdminUser());
    $request->setResource(makeVisibilityCallbackResource());

    $response = (new ResourceUpdateService())->handle($request, $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and($model->fresh()->is_active)->toBeTrue();
});

it('persists a field whose showOnCreation callback depends on the request user', function () {
    $request = new NadotaRequest();
    $request->merge(['name' => 'New', 'is_active' => true]);
    $request->setUserResolver(fn() => makeAdminUser());
    $request->setResource(makeVisibilityCallbackResource());

    $response = (new ResourceStoreService())->handle($request);

    expect($response->getStatusCode())->toBe(201)
        ->and(TestModel::where('name', 'New')->first()->is_active)->toBeTrue();
});

it('still excludes the field when the visibility callback returns false', function () {
    $model = TestModel::create(['name' => 'Original', 'is_active' => false]);

    $request = new NadotaRequest();
    $request->merge(['name' => 'Updated', 'is_active' => true]);
    $request->setUserResolver(fn() => new class {
        public function isAdmin(): bool
        {
            return false;
        }
    });
    $request->setResource(makeVisibilityCallbackResource());

    $response = (new ResourceUpdateService())->handle($request, $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and($model->fresh()->is_active)->toBeFalse()
        ->and($model->fresh()->name)->toBe('Updated');
});
