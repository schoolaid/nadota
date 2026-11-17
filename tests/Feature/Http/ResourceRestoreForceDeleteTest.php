<?php

use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\IndexScopedStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\LockedStudentResource;

/**
 * POST   /{resourceKey}/resource/{id}/restore — ResourceRestoreService
 * DELETE /{resourceKey}/resource/{id}/force   — ResourceForceDeleteService
 * Hook order and failure handling: ResourceLifecycleHooksTest.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi();
    ResourceManager::registerResourceClass(LockedStudentResource::class);
    ResourceManager::registerResourceClass(IndexScopedStudentResource::class);

    $this->trashed = Student::create(['name' => 'Trashed', 'is_active' => true]);
    $this->trashed->delete();
    $this->active = Student::create(['name' => 'Active', 'is_active' => true]);
});

describe('restore', function () {
    it('restores a trashed record and returns it', function () {
        $this->postJson($this->apiUrl('student', $this->trashed->id . '/restore'))
            ->assertOk()
            ->assertJsonPath('message', 'Resource restored successfully')
            ->assertJsonPath('data.id', $this->trashed->id)
            ->assertJsonPath('data.deleted_at', null);

        expect(Student::find($this->trashed->id))->not->toBeNull();
    });

    it('returns 404 for a record that is not trashed', function () {
        $this->postJson($this->apiUrl('student', $this->active->id . '/restore'))->assertNotFound();
    });

    it('returns 404 for a missing record', function () {
        $this->postJson($this->apiUrl('student', '999/restore'))->assertNotFound();
    });

    it('answers 400 on a resource without soft deletes', function () {
        $grade = Grade::create(['name' => 'First']);

        $this->postJson($this->apiUrl('grade', $grade->id . '/restore'))
            ->assertStatus(400)
            ->assertExactJson(['message' => 'This resource does not support restore']);
    });

    it('honours getQuery() scoping', function () {
        $inactive = Student::create(['name' => 'Inactive', 'is_active' => false]);
        $inactive->delete();

        $this->postJson($this->apiUrl('index-scoped-student', $inactive->id . '/restore'))->assertNotFound();

        expect(Student::withTrashed()->find($inactive->id)->trashed())->toBeTrue();
    });

    it('records a restore action event', function () {
        $this->postJson($this->apiUrl('student', $this->trashed->id . '/restore'))->assertOk();

        expect(ActionEvent::query()->where('model_id', $this->trashed->id)->where('name', 'restore')->count())->toBe(1);
    });

    it('refuses to restore when the resource disables restoring', function () {
        $this->postJson($this->apiUrl('locked-student', $this->trashed->id . '/restore'))->assertForbidden();

        expect(Student::withTrashed()->find($this->trashed->id)->trashed())->toBeTrue();
    });
});

describe('force delete', function () {
    it('permanently deletes a trashed record', function () {
        $this->deleteJson($this->apiUrl('student', $this->trashed->id . '/force'))
            ->assertOk()
            ->assertExactJson(['message' => 'Resource permanently deleted']);

        expect(Student::withTrashed()->find($this->trashed->id))->toBeNull();
    });

    it('permanently deletes a record that was never trashed', function () {
        $this->deleteJson($this->apiUrl('student', $this->active->id . '/force'))->assertOk();

        expect(Student::withTrashed()->find($this->active->id))->toBeNull();
    });

    it('returns 404 for a missing record', function () {
        $this->deleteJson($this->apiUrl('student', '999/force'))->assertNotFound();
    });

    it('answers 400 on a resource without soft deletes and keeps the record', function () {
        $grade = Grade::create(['name' => 'First']);

        $this->deleteJson($this->apiUrl('grade', $grade->id . '/force'))
            ->assertStatus(400)
            ->assertExactJson(['message' => 'This resource does not support force delete']);

        expect(Grade::find($grade->id))->not->toBeNull();
    });

    it('honours getQuery() scoping', function () {
        $inactive = Student::create(['name' => 'Inactive', 'is_active' => false]);

        $this->deleteJson($this->apiUrl('index-scoped-student', $inactive->id . '/force'))->assertNotFound();

        expect(Student::find($inactive->id))->not->toBeNull();
    });

    it('records a forceDelete action event that survives the record', function () {
        $this->deleteJson($this->apiUrl('student', $this->trashed->id . '/force'))->assertOk();

        $event = ActionEvent::query()->where('model_id', $this->trashed->id)->where('name', 'forceDelete')->sole();

        expect($event->changes)->toBe(['permanently_deleted' => true])
            ->and($event->original['name'])->toBe('Trashed');
    });

    it('refuses to force delete when the resource disables it', function () {
        $this->deleteJson($this->apiUrl('locked-student', $this->active->id . '/force'))->assertForbidden();

        expect(Student::find($this->active->id))->not->toBeNull();
    });
});
