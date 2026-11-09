<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\LifecycleStudentResource;

/**
 * Resource lifecycle hooks as the HTTP routes run them: order, what each hook
 * sees, and the transaction / error handling around them. The fixture logs
 * every hook into the "lifecycle-hooks.log" binding and fails on demand
 * through config('lifecycle-hooks.fail') — see LifecycleStudentResource.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi();
    ResourceManager::registerResourceClass(LifecycleStudentResource::class);

    $this->hooks = new ArrayObject();
    $this->app->instance('lifecycle-hooks.log', $this->hooks);

    $this->hookNames = fn () => array_column($this->hooks->getArrayCopy(), 'hook');
    $this->failIn = fn (string $hook, string $with = 'exception') => config(['lifecycle-hooks.fail' => ['hook' => $hook, 'with' => $with]]);
});

describe('order and arguments', function () {
    it('runs beforeStore on the unsaved, still unfilled model and afterStore on the saved one', function () {
        $this->postJson($this->apiUrl('lifecycle-student'), ['name' => 'Ana'])->assertCreated();

        expect($this->hooks->getArrayCopy())->toBe([
            ['hook' => 'beforeStore', 'name' => null, 'exists' => false, 'trashed' => false],
            ['hook' => 'afterStore', 'name' => 'Ana', 'exists' => true, 'trashed' => false],
        ]);
    });

    it('runs beforeUpdate before the fill and gives afterUpdate the original attributes', function () {
        $student = Student::create(['name' => 'Ana']);

        $this->putJson($this->apiUrl('lifecycle-student', $student->id), ['name' => 'Ana Maria'])->assertOk();

        expect($this->hooks->getArrayCopy())->toBe([
            ['hook' => 'beforeUpdate', 'name' => 'Ana', 'exists' => true, 'trashed' => false],
            ['hook' => 'afterUpdate', 'name' => 'Ana Maria', 'exists' => true, 'trashed' => false, 'original_name' => 'Ana'],
        ]);
    });

    it('runs beforeDelete, performDelete and afterDelete in order', function () {
        $student = Student::create(['name' => 'Ana']);

        $this->deleteJson($this->apiUrl('lifecycle-student', $student->id))->assertOk();

        expect(($this->hookNames)())->toBe(['beforeDelete', 'performDelete', 'afterDelete'])
            ->and($this->hooks[2]['trashed'])->toBeTrue();
    });

    it('runs beforeRestore, performRestore and afterRestore in order, afterRestore on the refreshed model', function () {
        $student = Student::create(['name' => 'Ana']);
        $student->delete();

        $this->postJson($this->apiUrl('lifecycle-student', $student->id . '/restore'))->assertOk();

        expect(($this->hookNames)())->toBe(['beforeRestore', 'performRestore', 'afterRestore'])
            ->and($this->hooks[0]['trashed'])->toBeTrue()
            ->and($this->hooks[2]['trashed'])->toBeFalse();
    });

    it('runs beforeForceDelete, performForceDelete and afterForceDelete in order', function () {
        $student = Student::create(['name' => 'Ana']);

        $this->deleteJson($this->apiUrl('lifecycle-student', $student->id . '/force'))->assertOk();

        expect(($this->hookNames)())->toBe(['beforeForceDelete', 'performForceDelete', 'afterForceDelete'])
            ->and($this->hooks[2]['exists'])->toBeFalse();
    });

    it('runs no hook when validation fails', function () {
        $this->postJson($this->apiUrl('lifecycle-student'), [])->assertStatus(422);

        expect($this->hooks->getArrayCopy())->toBe([]);
    });
});

describe('store and update failures', function () {
    it('rolls back the record, the hook writes and the action event when a hook throws', function (string $hook) {
        ($this->failIn)($hook);
        config(['lifecycle-hooks.write_note_in' => 'beforeStore']);

        $this->postJson($this->apiUrl('lifecycle-student'), ['name' => 'Ana'])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to create resource');

        expect(Student::withTrashed()->count())->toBe(0)
            ->and(Note::count())->toBe(0)
            ->and(ActionEvent::count())->toBe(0);
    })->with(['beforeStore', 'afterStore']);

    it('keeps the old values when an update hook throws', function (string $hook) {
        $student = Student::create(['name' => 'Ana']);
        ($this->failIn)($hook);

        $this->putJson($this->apiUrl('lifecycle-student', $student->id), ['name' => 'Changed'])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to update resource');

        expect($student->fresh()->name)->toBe('Ana')
            ->and(ActionEvent::count())->toBe(0);
    })->with(['beforeUpdate', 'afterUpdate']);

    it('turns a ValidationException from a hook into a 422 and rolls back', function () {
        ($this->failIn)('afterStore', 'validation');

        $this->postJson($this->apiUrl('lifecycle-student'), ['name' => 'Ana'])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'afterStore rejected');

        expect(Student::count())->toBe(0);
    });

    it('keeps the status of an HTTP exception thrown by a hook', function () {
        $student = Student::create(['name' => 'Ana']);
        ($this->failIn)('beforeUpdate', 'abort403');

        $this->putJson($this->apiUrl('lifecycle-student', $student->id), ['name' => 'Changed'])->assertForbidden();

        expect($student->fresh()->name)->toBe('Ana');
    })->skip('ISSUE HTTP-006: the persist/destroy/restore/force services catch \Exception, so abort(403) inside a hook becomes a 500 "Failed to ..." response');

    it('rolls the transaction back when a hook throws an \Error', function () {
        $level = DB::transactionLevel();
        ($this->failIn)('beforeStore', 'error');

        $this->postJson($this->apiUrl('lifecycle-student'), ['name' => 'Ana'])->assertStatus(500);

        expect(DB::transactionLevel())->toBe($level);
    })->skip('ISSUE HTTP-006: only \Exception is caught, so an \Error (TypeError, ...) escapes after DB::beginTransaction() and leaves the transaction open');

    it('does not echo the exception message to the client when debug is off', function () {
        config(['app.debug' => false]);
        ($this->failIn)('beforeStore');

        $response = $this->postJson($this->apiUrl('lifecycle-student'), ['name' => 'Ana'])->assertStatus(500);

        expect($response->getContent())->not->toContain('beforeStore failed');
    })->skip('ISSUE HTTP-014: every CRUD service returns $e->getMessage() as "error" in its 500 response, whatever app.debug says');
});

describe('delete failures', function () {
    it('rolls back and calls onDeleteFailed when a delete hook throws', function (string $hook) {
        $student = Student::create(['name' => 'Ana']);
        ($this->failIn)($hook);
        config(['lifecycle-hooks.write_note_in' => 'beforeDelete']);

        $this->deleteJson($this->apiUrl('lifecycle-student', $student->id))
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to delete resource');

        expect($student->fresh()->trashed())->toBeFalse()
            ->and(Note::count())->toBe(0)
            ->and(ActionEvent::count())->toBe(0)
            ->and($this->hooks[count($this->hooks) - 1])->toMatchArray(['hook' => 'onDeleteFailed', 'exception' => "{$hook} failed"]);
    })->with(['beforeDelete', 'performDelete', 'afterDelete']);

    it('treats performDelete returning false as a failure', function () {
        $student = Student::create(['name' => 'Ana']);
        ($this->failIn)('performDelete', 'false');

        $this->deleteJson($this->apiUrl('lifecycle-student', $student->id))
            ->assertStatus(500)
            ->assertJsonPath('error', 'Delete operation failed');

        expect($student->fresh()->trashed())->toBeFalse()
            ->and(($this->hookNames)())->toBe(['beforeDelete', 'performDelete', 'onDeleteFailed']);
    });

    it('turns a ValidationException from a delete hook into a 422', function () {
        $student = Student::create(['name' => 'Ana']);
        ($this->failIn)('beforeDelete', 'validation');

        $this->deleteJson($this->apiUrl('lifecycle-student', $student->id))
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'beforeDelete rejected');
    })->skip('ISSUE HTTP-006: destroy/restore/force catch every \Exception, so a ValidationException becomes a 500 (store/update rethrow it as 422)');
});

describe('restore and force delete failures', function () {
    it('keeps the record trashed when a restore hook fails', function (string $hook, string $with) {
        $student = Student::create(['name' => 'Ana']);
        $student->delete();
        ($this->failIn)($hook, $with);

        $this->postJson($this->apiUrl('lifecycle-student', $student->id . '/restore'))
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to restore resource');

        expect(Student::withTrashed()->find($student->id)->trashed())->toBeTrue()
            ->and(ActionEvent::count())->toBe(0);
    })->with([
        'beforeRestore throws' => ['beforeRestore', 'exception'],
        'performRestore returns false' => ['performRestore', 'false'],
        'afterRestore throws' => ['afterRestore', 'exception'],
    ]);

    it('keeps the record when a force delete hook fails', function (string $hook, string $with) {
        $student = Student::create(['name' => 'Ana']);
        ($this->failIn)($hook, $with);

        $this->deleteJson($this->apiUrl('lifecycle-student', $student->id . '/force'))
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to permanently delete resource');

        expect(Student::find($student->id))->not->toBeNull()
            ->and(ActionEvent::count())->toBe(0);
    })->with([
        'beforeForceDelete throws' => ['beforeForceDelete', 'exception'],
        'performForceDelete returns false' => ['performForceDelete', 'false'],
        'afterForceDelete throws' => ['afterForceDelete', 'exception'],
    ]);
});
