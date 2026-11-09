<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Policies\OptionsAwareStudentPolicy;
use SchoolAid\Nadota\Tests\Fixtures\Resources\ScopedStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

/**
 * GET /{resourceKey}/resource/{id}/action-events
 * GET /{resourceKey}/resource/{id}/action-events/{eventId}
 *
 * Authorization, morph-alias lookup and ordering already have controller-level
 * tests (tests/ServiceIntegration/ActionEvent*Test.php); these drive the real
 * routes, with events produced by the panel's own endpoints where possible.
 *
 * The controller eager-loads the event user (config auth.providers.users.model,
 * table "users"), which the base test schema does not create.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
    DB::table('users')->insert(['id' => 1, 'name' => 'Ada', 'email' => 'ada@school.test']);

    $this->bootNadotaApi(array_merge($this->fixtureResources, [ScopedStudentResource::class]));

    $this->ana = Student::create(['name' => 'Ana']);
    $this->ben = Student::create(['name' => 'Ben']);
});

function actionEventControllerEvent(Student $student, array $attributes = []): ActionEvent
{
    return ActionEvent::create(array_merge([
        'name' => 'update',
        'actionable_type' => StudentResource::class,
        'actionable_id' => 0,
        'target_type' => Student::class,
        'target_id' => $student->id,
        'model_type' => Student::class,
        'model_id' => $student->id,
        'fields' => [],
        'status' => 'finished',
        'user_id' => 1,
    ], $attributes));
}

describe('index', function () {
    it('lists the events the panel recorded for the record, in the documented shape', function () {
        $this->postJson($this->apiUrl('student', 'actions/activate-students'), ['resources' => [$this->ana->id]])->assertOk();

        $event = ActionEvent::query()->sole();

        $this->getJson($this->apiUrl('student', "{$this->ana->id}/action-events"))
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => $event->id,
                    'batchId' => $event->batch_id,
                    'name' => 'action:activate-students',
                    'nameLabel' => 'Action:activate-students',
                    'status' => 'finished',
                    'user' => ['id' => 1, 'name' => 'Ada', 'email' => 'ada@school.test'],
                    'modelType' => Student::class,
                    'modelId' => $this->ana->id,
                    'fields' => ['resources' => [$this->ana->id]],
                    'original' => null,
                    'changes' => null,
                    'exception' => null,
                    'createdAt' => $event->created_at->toDateTimeString(),
                    'updatedAt' => $event->updated_at->toDateTimeString(),
                ]],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
            ]);
    });

    it('includes attachment events of the record', function () {
        $course = Course::create(['name' => 'Math']);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/courses"), ['items' => [$course->id]])->assertOk();

        $this->getJson($this->apiUrl('student', "{$this->ana->id}/action-events"))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'attach')
            ->assertJsonPath('data.0.nameLabel', 'Attach')
            ->assertJsonPath('data.0.changes', ['attached' => [$course->id]]);
    });

    it('lists only the events of that record, newest first', function () {
        $older = actionEventControllerEvent($this->ana, ['name' => 'create']);
        $newer = actionEventControllerEvent($this->ana, ['name' => 'update']);
        actionEventControllerEvent($this->ben);

        $response = $this->getJson($this->apiUrl('student', "{$this->ana->id}/action-events"))->assertOk();

        expect(collect($response->json('data'))->pluck('id')->all())->toBe([$newer->id, $older->id])
            ->and(collect($response->json('data'))->pluck('nameLabel')->all())->toBe(['Updated', 'Created']);
    });

    it('paginates with per_page and page', function () {
        foreach (range(1, 3) as $i) {
            actionEventControllerEvent($this->ana);
        }

        $this->getJson($this->apiUrl('student', "{$this->ana->id}/action-events?per_page=2&page=2"))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta', ['current_page' => 2, 'last_page' => 2, 'per_page' => 2, 'total' => 3]);
    });

    it('caps per_page at 100', function () {
        actionEventControllerEvent($this->ana);

        $this->getJson($this->apiUrl('student', "{$this->ana->id}/action-events?per_page=1000"))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    });

    it('filters by name, status and user id', function (string $query, array $names) {
        actionEventControllerEvent($this->ana, ['name' => 'create']);
        actionEventControllerEvent($this->ana, ['name' => 'update', 'status' => 'failed']);
        actionEventControllerEvent($this->ana, ['name' => 'delete', 'user_id' => null]);

        $response = $this->getJson($this->apiUrl('student', "{$this->ana->id}/action-events?{$query}"))->assertOk();

        expect(collect($response->json('data'))->pluck('name')->sort()->values()->all())->toBe($names);
    })->with([
        'name' => ['name=create', ['create']],
        'status' => ['status=failed', ['update']],
        'user' => ['user_id=1', ['create', 'update']],
    ]);

    it('serves a null user for unattributed events', function () {
        actionEventControllerEvent($this->ana, ['user_id' => null]);

        $this->getJson($this->apiUrl('student', "{$this->ana->id}/action-events"))
            ->assertOk()
            ->assertJsonPath('data.0.user', null);
    });
});

describe('show', function () {
    it('returns one event of the record', function () {
        $event = actionEventControllerEvent($this->ana, ['changes' => ['name' => 'Ana']]);

        $this->getJson($this->apiUrl('student', "{$this->ana->id}/action-events/{$event->id}"))
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.changes', ['name' => 'Ana']);
    });

    it('answers 404 for an event of another record or an unknown event', function (Closure $eventId) {
        $this->getJson($this->apiUrl('student', "{$this->ana->id}/action-events/" . $eventId($this)))->assertNotFound();
    })->with([
        'other record' => [fn ($test) => actionEventControllerEvent($test->ben)->id],
        'unknown' => [fn () => 999],
        'not numeric' => [fn () => 'abc'],
    ]);
});

describe('access', function () {
    it('answers 404 for an unknown record', function (string $path) {
        $this->getJson($this->apiUrl('student', $path))->assertNotFound();
    })->with(['999/action-events', '999/action-events/1']);

    it('answers 404 for a record the resource getQuery() hides', function () {
        $archived = Student::create(['name' => 'Archived', 'status' => 'archived']);
        actionEventControllerEvent($archived);

        $this->getJson($this->apiUrl('scoped-student', "{$archived->id}/action-events"))->assertNotFound();
    });

    it('answers 404, not 500, for a non-numeric record id', function () {
        $this->getJson($this->apiUrl('student', 'abc/action-events'))->assertNotFound();
    })->skip('ISSUE OPS-017: ActionEventController types $id as int, so a string id is a TypeError (500), same pattern as CORE-003');

    it('answers 403 when the user cannot view the record', function (Closure $path) {
        $event = actionEventControllerEvent($this->ana);
        $this->useRealAuthorization();
        Gate::policy(Student::class, OptionsAwareStudentPolicy::class);
        config(['nadota-tests.ops_denied' => ['view']]);

        $this->getJson($this->apiUrl('student', $path($this->ana, $event)))->assertForbidden();
    })->with([
        'index' => [fn ($student) => "{$student->id}/action-events"],
        'show' => [fn ($student, $event) => "{$student->id}/action-events/{$event->id}"],
    ]);
});
