<?php

use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\IndexScopedStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\LockedStudentResource;

/**
 * DELETE /{resourceKey}/resource/{id} — ResourceDestroyService.
 * Hook order and failure handling: ResourceLifecycleHooksTest.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi();
    ResourceManager::registerResourceClass(LockedStudentResource::class);
    ResourceManager::registerResourceClass(IndexScopedStudentResource::class);
});

it('soft deletes a record of a soft-deleting resource', function () {
    $student = Student::create(['name' => 'Ana']);

    $this->deleteJson($this->apiUrl('student', $student->id))
        ->assertOk()
        ->assertExactJson(['message' => 'Resource deleted successfully']);

    expect(Student::find($student->id))->toBeNull()
        ->and(Student::withTrashed()->find($student->id)->trashed())->toBeTrue();
});

it('permanently deletes a record of a resource without soft deletes', function () {
    $grade = Grade::create(['name' => 'First']);

    $this->deleteJson($this->apiUrl('grade', $grade->id))->assertOk();

    expect(Grade::find($grade->id))->toBeNull();
});

it('returns 404 for a missing record', function () {
    $this->deleteJson($this->apiUrl('student', 999))->assertNotFound();
});

it('returns 404 when the record is already trashed', function () {
    $student = Student::create(['name' => 'Ana']);
    $student->delete();
    $deletedAt = Student::withTrashed()->find($student->id)->deleted_at;
    $this->travel(5)->minutes();

    $this->deleteJson($this->apiUrl('student', $student->id))->assertNotFound();

    expect(Student::withTrashed()->find($student->id)->deleted_at->equalTo($deletedAt))->toBeTrue();
});

it('honours getQuery() scoping', function () {
    $inactive = Student::create(['name' => 'Inactive', 'is_active' => false]);

    $this->deleteJson($this->apiUrl('index-scoped-student', $inactive->id))->assertNotFound();

    expect($inactive->fresh()->trashed())->toBeFalse();
});

it('only touches the requested record', function () {
    $ana = Student::create(['name' => 'Ana']);
    $bob = Student::create(['name' => 'Bob']);

    $this->deleteJson($this->apiUrl('student', $ana->id))->assertOk();

    expect(Student::pluck('name')->all())->toBe(['Bob']);
});

it('records a delete action event with the deleted attributes', function () {
    $student = Student::create(['name' => 'Ana']);

    $this->deleteJson($this->apiUrl('student', $student->id))->assertOk();

    $event = ActionEvent::query()->where('model_id', $student->id)->where('name', 'delete')->sole();

    expect($event->original['name'])->toBe('Ana');
});

it('refuses to delete when the resource disables deletion', function () {
    $student = Student::create(['name' => 'Ana']);

    $this->getJson($this->apiUrl('locked-student', $student->id . '/permissions'))
        ->assertOk()
        ->assertJsonPath('data.permissions.delete', false);

    $this->deleteJson($this->apiUrl('locked-student', $student->id))->assertForbidden();

    expect($student->fresh()->trashed())->toBeFalse();
});
