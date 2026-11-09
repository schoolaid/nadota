<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceCreateService;
use SchoolAid\Nadota\Http\Services\ResourceEditService;
use SchoolAid\Nadota\Http\Services\ResourceShowService;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;

/**
 * Field defaults through the create/edit/show/store services, against the
 * fixture students table. The DefaultValueTrait API itself is unit-tested in
 * tests/Unit/Fields/Traits/DefaultValueTraitTest.php, and resolveForStore()'s
 * null handling in FieldResolveTraitTest.php.
 *
 * (Until 2026-11-02 this file was a verbatim copy of the unit test.)
 */

beforeEach(fn () => FixtureSchema::create());

function defaultsResource(): AdHocResource
{
    return AdHocResource::withFields([
        Input::make('Name', 'name'),
        Input::make('Status', 'status')->default('draft'),
        Input::make('Website', 'website')->defaultUsing(fn ($request, $model, $resource) => $resource::getKey() . '.example'),
        Input::make('Email', 'email')->defaultFromAttribute('name'),
    ]);
}

function defaultsRequest(array $input = []): NadotaRequest
{
    $request = createNadotaRequest($input);
    $request->setResource(defaultsResource());

    return $request;
}

function defaultsAttribute(array $payload, string $key): array
{
    return collect($payload['data']['attributes'])->firstWhere('key', $key);
}

it('pre-fills the create form with the resolved defaults', function () {
    $payload = (new ResourceCreateService())->handle(defaultsRequest())->getData(true);

    expect(defaultsAttribute($payload, 'status')['default'])->toBe('draft')
        ->and(defaultsAttribute($payload, 'website')['default'])->toBe('ad-hoc.example')
        // no model on the create form, so an attribute-based default has nothing to read
        ->and(defaultsAttribute($payload, 'email')['default'])->toBeNull()
        ->and(defaultsAttribute($payload, 'name')['default'])->toBeNull();
});

it('shows the stored value on the edit form, the default only for null columns', function () {
    $student = Student::create(['name' => 'Ana', 'status' => 'enrolled']);

    $payload = (new ResourceEditService())->handle(defaultsRequest(), $student->id)->getData(true);

    expect(defaultsAttribute($payload, 'status')['value'])->toBe('enrolled')
        ->and(defaultsAttribute($payload, 'website')['value'])->toBe('ad-hoc.example')
        ->and(defaultsAttribute($payload, 'email')['value'])->toBe('Ana')
        ->and(defaultsAttribute($payload, 'email')['default'])->toBe('Ana');
});

it('displays the default for a null column on the detail view', function () {
    $student = Student::create(['name' => 'Ana']);

    $payload = (new ResourceShowService())->handle(defaultsRequest(), $student->id)->getData(true);

    expect(defaultsAttribute($payload, 'status')['value'])->toBe('draft');
});

it('stores the defaults of fields submitted as null', function () {
    $response = (new ResourceStoreService())->handle(defaultsRequest([
        'name' => 'Ana', 'status' => null, 'website' => null, 'email' => null,
    ]));

    $student = Student::first();

    expect($response->getStatusCode())->toBe(201)
        ->and($student->status)->toBe('draft')
        ->and($student->website)->toBe('ad-hoc.example')
        // the attribute default reads the model being created, whose name was filled first
        ->and($student->email)->toBe('Ana');
});

it('stores what the user typed over any default', function () {
    (new ResourceStoreService())->handle(defaultsRequest([
        'name' => 'Ana', 'status' => 'enrolled', 'website' => 'ana.dev', 'email' => 'ana@example.com',
    ]));

    expect(Student::first()->only(['status', 'website', 'email']))->toBe([
        'status' => 'enrolled', 'website' => 'ana.dev', 'email' => 'ana@example.com',
    ]);
});
