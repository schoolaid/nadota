<?php

use SchoolAid\Nadota\Http\Fields\CheckboxList;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * The frontend submits forms as multipart, which cannot carry an empty array, so it sends the
 * string '[]' instead. Fields that expect an array must read it as their empty value before
 * validation; otherwise the `array` rule rejects it and the field can never be cleared.
 */
function makeEmptyArrayResource(bool $required = false): Resource
{
    return new class($required) extends Resource
    {
        public string $model = TestModel::class;

        public function __construct(private bool $required)
        {
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            // Same rules as marketaid's MaintenanceTaskTemplate weekdays.
            $features = CheckboxList::make('Features', 'metadata')
                ->options(['a' => 'A', 'b' => 'B'])
                ->rules(['array', 'min:1']);

            return [
                Input::make('Name', 'name')->required(),
                $this->required ? $features->required() : $features->nullable(),
            ];
        }
    };
}

it('clears a checkbox list when the form sends "[]" on update', function () {
    $model = TestModel::create(['name' => 'Original', 'metadata' => ['a', 'b']]);

    $request = new NadotaRequest();
    $request->merge(['name' => 'Original', 'metadata' => '[]']);
    $request->setResource(makeEmptyArrayResource());

    $response = (new ResourceUpdateService())->handle($request, $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and($model->fresh()->metadata)->toBeNull();
});

it('stores an untouched checkbox list sent as "[]" on create', function () {
    $request = new NadotaRequest();
    $request->merge(['name' => 'New', 'metadata' => '[]']);
    $request->setResource(makeEmptyArrayResource());

    $response = (new ResourceStoreService())->handle($request);

    expect($response->getStatusCode())->toBe(201)
        ->and(TestModel::where('name', 'New')->first()->metadata)->toBeNull();
});

it('still fails `required` when a required checkbox list arrives as "[]"', function () {
    $request = new NadotaRequest();
    $request->merge(['name' => 'New', 'metadata' => '[]']);
    $request->setResource(makeEmptyArrayResource(required: true));

    $response = (new ResourceStoreService())->handle($request);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey('metadata');
});

it('leaves "[]" untouched for fields that do not expect an array', function () {
    $request = new NadotaRequest();
    $request->merge(['name' => '[]', 'metadata' => ['a']]);
    $request->setResource(makeEmptyArrayResource());

    $response = (new ResourceStoreService())->handle($request);

    expect($response->getStatusCode())->toBe(201)
        ->and(TestModel::where('name', '[]')->exists())->toBeTrue();
});
