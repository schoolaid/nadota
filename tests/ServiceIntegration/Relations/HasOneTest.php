<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\HasOne;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceCreateService;
use SchoolAid\Nadota\Http\Services\ResourceEditService;
use SchoolAid\Nadota\Http\Services\ResourceShowService;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Models\StudentProfile;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentProfileResource;

/**
 * HasOne driven through the CRUD services (no HTTP layer): read-only on
 * store/update, eager loaded with the related foreign key on show, absent from
 * the create and edit forms.
 */
beforeEach(function () {
    FixtureSchema::create();
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name')->required(),
        HasOne::make('Profile', 'profile', StudentProfileResource::class)->displayAttribute('nickname'),
    ]);
});

afterEach(function () {
    RelStudentResource::reset();
});

function hasOneServiceRequest(array $data = []): NadotaRequest
{
    $request = createNadotaRequest($data);
    $request->setResource(new RelStudentResource());

    return $request;
}

function hasOneServiceKeys(array $payload): array
{
    return collect($payload['data']['attributes'] ?? $payload['data']['fields'] ?? [])->pluck('key')->all();
}

it('eager loads the profile on show, selecting the foreign key it needs', function () {
    $student = Student::create(['name' => 'Ana']);
    $profile = StudentProfile::create(['student_id' => $student->id, 'nickname' => 'Annie']);

    DB::enableQueryLog();
    $payload = app(ResourceShowService::class)->handle(hasOneServiceRequest(), $student->id)->getData(true);
    $queries = collect(DB::getQueryLog())->pluck('query');
    $value = collect($payload['data']['attributes'])->firstWhere('key', 'profile')['value'];

    expect($value)->toBe([
        'id' => $profile->id,
        'key' => $profile->id,
        'label' => 'Annie',
        'resource' => 'student-profile',
        'deletedAt' => null,
    ])
        ->and($queries)->toHaveCount(2)
        ->and($queries->last())->toContain('"fx_student_profiles"."student_id"', '"fx_student_profiles"."nickname"');
});

it('shows a null value when the profile does not exist', function () {
    $student = Student::create(['name' => 'Ana']);

    $payload = app(ResourceShowService::class)->handle(hasOneServiceRequest(), $student->id)->getData(true);

    expect(collect($payload['data']['attributes'])->firstWhere('key', 'profile')['value'])->toBeNull();
});

it('ignores a profile payload on store', function () {
    $response = app(ResourceStoreService::class)
        ->handle(hasOneServiceRequest(['name' => 'Ana', 'profile' => ['nickname' => 'Annie']]));

    expect($response->getStatusCode())->toBe(201)
        ->and(Student::count())->toBe(1)
        ->and(StudentProfile::count())->toBe(0);
});

it('keeps the existing profile untouched on update', function () {
    $student = Student::create(['name' => 'Ana']);
    StudentProfile::create(['student_id' => $student->id, 'nickname' => 'Annie']);

    app(ResourceUpdateService::class)
        ->handle(hasOneServiceRequest(['name' => 'Ana B.', 'profile' => ['nickname' => 'Changed']]), $student->id);

    expect($student->fresh()->name)->toBe('Ana B.')
        ->and(StudentProfile::first()->nickname)->toBe('Annie');
});

it('is not part of the create or edit forms', function () {
    $student = Student::create(['name' => 'Ana']);

    $create = app(ResourceCreateService::class)->handle(hasOneServiceRequest())->getData(true);
    $edit = app(ResourceEditService::class)->handle(hasOneServiceRequest(), $student->id)->getData(true);

    expect(json_encode($create))->not->toContain('"key":"profile"')
        ->and(hasOneServiceKeys($edit))->toBe(['name']);
});
