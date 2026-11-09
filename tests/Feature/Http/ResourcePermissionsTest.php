<?php

use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Policies\ConfigurableAbilityPolicy;
use SchoolAid\Nadota\Tests\Fixtures\Resources\IndexScopedStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\LockedStudentResource;

/**
 * GET /{resourceKey}/resource/{id}/permissions — ResourceController::permissions
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi();
    ResourceManager::registerResourceClass(LockedStudentResource::class);
    ResourceManager::registerResourceClass(IndexScopedStudentResource::class);

    $this->student = Student::create(['name' => 'Ana', 'is_active' => true]);
});

it('returns the permission flags and the URL of every allowed operation', function () {
    $id = $this->student->id;

    $this->getJson($this->apiUrl('student', $id . '/permissions'))
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $id,
            'permissions' => [
                'view' => true, 'update' => true, 'delete' => true, 'forceDelete' => true, 'restore' => false,
                'attach' => true, 'detach' => true,
                'fields' => [
                    'courses' => ['attach' => true, 'detach' => true],
                    'notes' => ['attach' => true, 'detach' => true],
                    'labels' => ['attach' => true, 'detach' => true],
                ],
            ],
            'urls' => [
                'show' => "/nadota-api/student/resource/{$id}",
                'edit' => "/nadota-api/student/resource/{$id}/edit",
                'update' => "/nadota-api/student/resource/{$id}",
                'delete' => "/nadota-api/student/resource/{$id}",
                'forceDelete' => "/nadota-api/student/resource/{$id}/force",
                'restore' => null,
                'actionEvents' => "/nadota-api/student/resource/{$id}/action-events",
            ],
        ]]);
});

it('nulls the URLs of the operations the policy denies', function () {
    $this->useRealAuthorization();
    Gate::policy(Student::class, ConfigurableAbilityPolicy::class);
    config(['nadota-tests.denied_abilities' => ['update', 'delete', 'forceDelete']]);

    $response = $this->getJson($this->apiUrl('student', $this->student->id . '/permissions'))->assertOk();

    expect($response->json('data.permissions'))->toMatchArray(['view' => true, 'update' => false, 'delete' => false, 'forceDelete' => false])
        ->and($response->json('data.urls'))->toMatchArray(['edit' => null, 'update' => null, 'delete' => null, 'forceDelete' => null])
        ->and($response->json('data.urls.show'))->not->toBeNull();
});

it('reports forceDelete and restore as unavailable on a resource without soft deletes', function () {
    $grade = Grade::create(['name' => 'First']);

    $response = $this->getJson($this->apiUrl('grade', $grade->id . '/permissions'))->assertOk();

    expect($response->json('data.permissions.forceDelete'))->toBeFalse()
        ->and($response->json('data.permissions.restore'))->toBeFalse()
        ->and($response->json('data.urls.forceDelete'))->toBeNull();
});

it('applies the resource canDelete / canRestore / canForceDelete flags', function () {
    $response = $this->getJson($this->apiUrl('locked-student', $this->student->id . '/permissions'))->assertOk();

    expect($response->json('data.permissions'))->toMatchArray(['delete' => false, 'forceDelete' => false, 'restore' => false])
        ->and($response->json('data.urls.delete'))->toBeNull();
});

it('returns 404 for a missing record', function () {
    $this->getJson($this->apiUrl('student', '999/permissions'))->assertNotFound();
});

it('honours getQuery() scoping', function () {
    $inactive = Student::create(['name' => 'Inactive', 'is_active' => false]);

    $this->getJson($this->apiUrl('index-scoped-student', $inactive->id . '/permissions'))->assertNotFound();
});

it('returns the restore URL for a trashed record', function () {
    $this->student->delete();

    $this->getJson($this->apiUrl('student', $this->student->id . '/permissions'))
        ->assertOk()
        ->assertJsonPath('data.permissions.restore', true)
        ->assertJsonPath('data.urls.restore', "/nadota-api/student/resource/{$this->student->id}/restore");
})->skip('ISSUE HTTP-012: the permissions route loads the record without withTrashed, so a trashed record is a 404 and restore is never offered');

it('answers 404, not a TypeError, for a non-numeric id', function () {
    $this->getJson($this->apiUrl('student', 'abc/permissions'))->assertNotFound();
})->skip('ISSUE CORE-003: permissions() types $id as int, so a non-numeric id is a 500');
