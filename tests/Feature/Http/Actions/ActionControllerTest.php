<?php

use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Actions\SendGuardianEmail;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Policies\OptionsAwareStudentPolicy;
use SchoolAid\Nadota\Tests\Fixtures\Resources\ScopedStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentActionsResource;

/**
 * GET  /{resourceKey}/resource/actions
 * GET  /{resourceKey}/resource/actions/{actionKey}/fields
 * POST /{resourceKey}/resource/actions/{actionKey}
 *
 * ActionExecutionService has unit tests (tests/Unit/Services/ActionExecutionServiceTest.php)
 * for the event logging; these drive the real routes and controller.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi(array_merge($this->fixtureResources, [
        ScopedStudentResource::class,
        StudentActionsResource::class,
    ]));
});

function actionControllerKeys($response): array
{
    return collect($response->json('actions'))->pluck('key')->all();
}

describe('index', function () {
    it('lists the index actions with their fields and flags', function () {
        $response = $this->getJson($this->apiUrl('student', 'actions'))->assertOk();

        expect(actionControllerKeys($response))->toBe(['activate-students', 'purge-inactive-students'])
            ->and(collect($response->json('actions.1'))->except('fields')->all())->toBe([
                'key' => 'purge-inactive-students',
                'name' => 'Purge Inactive Students',
                'showOnIndex' => true,
                'showOnDetail' => false,
                'destructive' => true,
                'standalone' => true,
                'confirmText' => 'Are you sure you want to run this action?',
                'confirmButtonText' => 'Delete',
                'cancelButtonText' => 'Cancel',
            ])
            ->and($response->json('actions.0.fields.0'))->toMatchArray([
                'key' => 'reason',
                'attribute' => 'reason',
                'type' => 'textarea',
                'component' => 'FieldTextarea',
            ]);
    });

    it('filters the actions by context', function (string $context, array $keys) {
        $response = $this->getJson($this->apiUrl('student-actions', "actions?context={$context}"))->assertOk();

        expect(actionControllerKeys($response))->toBe($keys);
    })->with([
        'index' => ['index', ['activate-students', 'send-guardian-emails', 'reply-with-response-types']],
        'detail' => ['detail', ['activate-students', 'archive-students', 'send-guardian-emails', 'reply-with-response-types']],
    ]);

    it('answers 404 for an unknown resource', function () {
        $this->getJson($this->apiUrl('nope', 'actions'))->assertNotFound();
    });
});

describe('fields', function () {
    it('returns the serialized fields of an action', function () {
        $response = $this->getJson($this->apiUrl('student-actions', 'actions/archive-students/fields'))->assertOk();

        expect($response->json('fields'))->toHaveCount(1)
            ->and($response->json('fields.0'))->toMatchArray([
                'key' => 'reason',
                'label' => 'Reason',
                'type' => 'text',
                'required' => true,
            ]);
    });

    it('returns an empty list for an action without fields', function () {
        $this->getJson($this->apiUrl('student', 'actions/purge-inactive-students/fields'))
            ->assertOk()
            ->assertExactJson(['fields' => []]);
    });

    it('answers 404 for an unknown action', function () {
        $this->getJson($this->apiUrl('student', 'actions/nope/fields'))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Action not found.']);
    });
});

describe('execute', function () {
    it('runs the action on the selected records and logs one event per record', function () {
        $ana = Student::create(['name' => 'Ana']);
        $ben = Student::create(['name' => 'Ben']);
        $carla = Student::create(['name' => 'Carla']);

        $this->postJson($this->apiUrl('student', 'actions/activate-students'), [
            'resources' => [$ana->id, $ben->id],
            'reason' => 'Paid the fee',
        ])
            ->assertOk()
            ->assertExactJson(['type' => 'message', 'message' => 'Activated 2 students']);

        expect(Student::query()->where('is_active', true)->pluck('id')->all())->toBe([$ana->id, $ben->id])
            ->and($carla->fresh()->is_active)->toBeFalse();

        $events = ActionEvent::query()->orderBy('id')->get();

        expect($events->pluck('model_id')->all())->toBe([$ana->id, $ben->id])
            ->and($events->pluck('name')->unique()->all())->toBe(['action:activate-students'])
            ->and($events->pluck('status')->unique()->all())->toBe(['finished'])
            ->and($events->first()->fields)->toBe(['resources' => [$ana->id, $ben->id], 'reason' => 'Paid the fee']);
    });

    it('redacts sensitive keys of the request payload in the logged event', function () {
        $ana = Student::create(['name' => 'Ana']);

        $this->postJson($this->apiUrl('student', 'actions/activate-students'), [
            'resources' => [$ana->id],
            'api_token' => 'tok-123',
        ])->assertOk();

        expect(ActionEvent::query()->sole()->fields['api_token'])->toBe('***REDACTED***');
    });

    it('answers 422 when a non-standalone action receives no records', function (array $payload) {
        Student::create(['name' => 'Ana']);

        $this->postJson($this->apiUrl('student', 'actions/activate-students'), $payload)
            ->assertStatus(422)
            ->assertExactJson(['message' => 'No resources selected.']);

        expect(ActionEvent::query()->count())->toBe(0);
    })->with([
        'missing' => [[]],
        'empty' => [['resources' => []]],
    ]);

    it('runs a standalone action without records and logs a single event without model id', function () {
        Student::create(['name' => 'Ana']);
        Student::create(['name' => 'Ben', 'is_active' => true]);

        $this->postJson($this->apiUrl('student', 'actions/purge-inactive-students'))
            ->assertOk()
            ->assertExactJson(['type' => 'danger', 'message' => 'Purged 1 students']);

        expect(Student::query()->pluck('name')->all())->toBe(['Ben'])
            ->and(ActionEvent::query()->sole()->only('name', 'model_type', 'model_id'))
            ->toBe(['name' => 'action:purge-inactive-students', 'model_type' => Student::class, 'model_id' => null]);
    });

    it('returns every ActionResponse type as JSON', function (string $reply, array $json) {
        $this->postJson($this->apiUrl('student-actions', 'actions/reply-with-response-types'), ['reply' => $reply])
            ->assertOk()
            ->assertExactJson($json);
    })->with([
        'message' => ['message', ['type' => 'message', 'message' => 'Selected 0']],
        'danger' => ['danger', ['type' => 'danger', 'message' => 'Nothing to do']],
        'redirect' => ['redirect', ['type' => 'redirect', 'url' => '/resources/student/1']],
        'download' => ['download', ['type' => 'download', 'url' => '/storage/report-cards.zip', 'filename' => 'report-cards.zip']],
        'openInNewTab' => ['openInNewTab', ['type' => 'openInNewTab', 'url' => 'https://example.test/transcript', 'openInNewTab' => true]],
        'data' => ['data', ['type' => 'message', 'message' => 'Done', 'data' => ['processed' => 0]]],
    ]);

    it('passes the selected records to a standalone action when there are some', function () {
        $ana = Student::create(['name' => 'Ana']);

        $this->postJson($this->apiUrl('student-actions', 'actions/reply-with-response-types'), ['resources' => [$ana->id]])
            ->assertOk()
            ->assertJsonPath('message', 'Selected 1');
    });

    it('answers the default success message when handle() returns nothing', function () {
        $ana = Student::create(['name' => 'Ana']);

        $this->postJson($this->apiUrl('student-actions', 'actions/archive-students'), [
            'resources' => [$ana->id],
            'reason' => 'Graduated early',
        ])
            ->assertOk()
            ->assertExactJson(['type' => 'message', 'message' => 'Action executed successfully.']);

        expect($ana->fresh()->status)->toBe('archived');
    });

    it('validates the action fields before running it', function (array $payload) {
        $ana = Student::create(['name' => 'Ana']);

        $this->postJson($this->apiUrl('student-actions', 'actions/archive-students'), ['resources' => [$ana->id]] + $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        expect($ana->fresh()->status)->toBeNull();
    })->with([
        'missing' => [[]],
        'too short' => [['reason' => 'no']],
    ])->skip('ISSUE OPS-004: action field rules (required, min:5) are never validated; the action runs with no reason');

    it('only hands the records allowed by canRun() to the action', function () {
        $ana = Student::create(['name' => 'Ana', 'accept_terms' => true]);
        $ben = Student::create(['name' => 'Ben', 'accept_terms' => false]);

        $this->postJson($this->apiUrl('student-actions', 'actions/activate-students'), ['resources' => [$ana->id, $ben->id]])
            ->assertOk()
            ->assertJsonPath('message', 'Activated 1 students');

        expect($ana->fresh()->is_active)->toBeTrue()
            ->and($ben->fresh()->is_active)->toBeFalse()
            ->and(ActionEvent::query()->pluck('model_id')->all())->toBe([$ana->id]);
    });

    it('answers a danger response and runs nothing when canRun() rejects every record', function () {
        $ben = Student::create(['name' => 'Ben', 'accept_terms' => false]);

        $this->postJson($this->apiUrl('student-actions', 'actions/activate-students'), ['resources' => [$ben->id]])
            ->assertOk()
            ->assertExactJson(['type' => 'danger', 'message' => 'You are not authorized to run this action on the selected resources.']);

        expect($ben->fresh()->is_active)->toBeFalse()
            ->and(ActionEvent::query()->count())->toBe(0);
    });

    it('answers the same danger response for ids that match no record', function () {
        $this->postJson($this->apiUrl('student', 'actions/activate-students'), ['resources' => [999]])
            ->assertOk()
            ->assertJsonPath('type', 'danger');
    });

    it('includes soft-deleted records, as documented', function () {
        $ana = Student::create(['name' => 'Ana']);
        $ana->delete();

        $this->postJson($this->apiUrl('student', 'actions/activate-students'), ['resources' => [$ana->id]])
            ->assertOk()
            ->assertJsonPath('message', 'Activated 1 students');

        expect(Student::withTrashed()->find($ana->id)->is_active)->toBeTrue();
    });

    it('cannot reach records the resource getQuery() hides', function () {
        $archived = Student::create(['name' => 'Archived', 'status' => 'archived']);

        $this->postJson($this->apiUrl('scoped-student', 'actions/activate-students'), ['resources' => [$archived->id]])
            ->assertOk()
            ->assertJsonPath('type', 'danger');

        expect($archived->fresh()->is_active)->toBeFalse();
    });

    it('answers 500 with a danger body and logs a failed event when handle() throws', function () {
        $ana = Student::create(['name' => 'Ana']);

        $this->postJson($this->apiUrl('student-actions', 'actions/send-guardian-emails'), ['resources' => [$ana->id]])
            ->assertStatus(500)
            ->assertJsonPath('type', 'danger');

        expect(ActionEvent::query()->sole()->only('name', 'model_id', 'status', 'exception'))->toBe([
            'name' => 'action:send-guardian-emails',
            'model_id' => $ana->id,
            'status' => 'failed',
            'exception' => SendGuardianEmail::FAILURE,
        ]);
    });

    it('does not echo the internal exception message to the client', function () {
        $ana = Student::create(['name' => 'Ana']);

        $response = $this->postJson($this->apiUrl('student-actions', 'actions/send-guardian-emails'), ['resources' => [$ana->id]])
            ->assertStatus(500);

        expect($response->getContent())->not->toContain('mail.internal');
    })->skip('ISSUE OPS-015: ActionController returns $e->getMessage() to the client (hosts, SQL, paths), as documented');

    it('answers 422, not 500, when resources is not a list', function () {
        Student::create(['name' => 'Ana']);

        $this->postJson($this->apiUrl('student', 'actions/activate-students'), ['resources' => '1'])
            ->assertStatus(422);
    })->skip('ISSUE OPS-016: a scalar resources value reaches execute(array $modelIds) and throws a TypeError (500)');

    it('answers 404 for an unknown action', function () {
        $this->postJson($this->apiUrl('student', 'actions/nope'), ['resources' => [1]])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Action not found.']);
    });
});

describe('authorization', function () {
    beforeEach(function () {
        $this->useRealAuthorization();
        Gate::policy(Student::class, OptionsAwareStudentPolicy::class);
        config(['nadota-tests.ops_denied' => ['viewAny']]);
    });

    it('answers 403 on every action route when viewAny is denied', function (string $method, string $path) {
        $ana = Student::create(['name' => 'Ana']);

        $this->json($method, $this->apiUrl('student', $path), ['resources' => [$ana->id]])->assertForbidden();

        expect($ana->fresh()->is_active)->toBeFalse();
    })->with([
        ['GET', 'actions'],
        ['GET', 'actions/activate-students/fields'],
        ['POST', 'actions/activate-students'],
    ]);
});
