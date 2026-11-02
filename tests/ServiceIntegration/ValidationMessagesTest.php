<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

function messagesResource(array $fields): Resource
{
    return new class($fields) extends Resource
    {
        public string $model = TestModel::class;

        public function __construct(private array $fieldList)
        {
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            return $this->fieldList;
        }
    };
}

function storeWith(Resource $resource, array $input): array
{
    $request = new NadotaRequest();
    $request->merge($input);
    $request->setResource($resource);

    $response = (new ResourceStoreService())->handle($request);

    expect($response->getStatusCode())->toBe(422);

    return $response->getData(true)['errors'];
}

it('keys rule-name messages by the field attribute', function () {
    $field = Input::make('Name', 'name')->validationMessages(['required' => 'Name is needed']);

    expect($field->getValidationMessages())->toBe(['name.required' => 'Name is needed']);
});

it('uses keys that contain a dot as full validator keys', function () {
    $field = Input::make('Photos', 'photos')->validationMessages(['photos.*.image' => 'Only images']);

    expect($field->getValidationMessages())->toBe(['photos.*.image' => 'Only images']);
});

it('returns the custom message from the store endpoint', function () {
    $resource = messagesResource([
        Input::make('Name', 'name')->required()->validationMessages(['required' => 'Every record needs a name.']),
        Input::make('Email', 'email')->rules(['email']),
    ]);

    $errors = storeWith($resource, ['email' => 'not-an-email']);

    expect($errors['name'])->toBe(['Every record needs a name.'])
        ->and($errors['email'][0])->toContain('valid email');
});

it('translates messages and applies placeholders', function () {
    app('translator')->addLines(['messages.name_required' => ':attribute is mandatory'], 'en');

    $resource = messagesResource([
        Input::make('Name', 'name')->required()->validationMessages(['required' => 'messages.name_required']),
    ]);

    expect(storeWith($resource, [])['name'])->toBe(['name is mandatory']);
});
