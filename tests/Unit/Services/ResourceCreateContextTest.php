<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Password;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceCreateService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

/*
 * The create form is built from GET /resource/create, which carries no `action`, so the fields were
 * serialized without the store context: creationRules() (a password required only on create) did
 * not reach `required` nor `rules`, and the form found out from the 422 after submitting.
 */
function createContextResource(): Resource
{
    return new class extends Resource
    {
        public string $model = TestModel::class;

        public function authorizedTo(NadotaRequest $request, string $action, $model = null, array $context = []): bool
        {
            return true;
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name')->required(),
                Password::make('Password', 'password')->creationRules(['required'])->updateRules(['nullable']),
            ];
        }
    };
}

it('serializes the create form with the creation rules', function () {
    $request = new NadotaRequest();
    $request->setResource(createContextResource());

    $fields = collect((new ResourceCreateService())->handle($request)->getData(true)['data']['attributes']);
    $password = $fields->firstWhere('key', 'password');

    expect($password['required'])->toBeTrue()
        ->and($password['rules'])->toContain('required')
        ->and($password['rules'])->not->toContain('nullable');
});

it('keeps an explicit action', function () {
    $request = new NadotaRequest();
    $request->merge(['action' => 'update']);
    $request->setResource(createContextResource());

    $password = collect((new ResourceCreateService())->handle($request)->getData(true)['data']['attributes'])->firstWhere('key', 'password');

    expect($password['required'])->toBeFalse();
});
