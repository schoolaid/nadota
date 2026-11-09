<?php

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Http\Actions\Action;
use SchoolAid\Nadota\Http\Controllers\ActionController;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ActionExecutionService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Actions\ActivateStudents;
use SchoolAid\Nadota\Tests\Fixtures\Actions\PurgeInactiveStudents;
use SchoolAid\Nadota\Tests\Fixtures\Actions\SendReminder;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

beforeEach(function () {
    FixtureSchema::create();
    config()->set('nadota.action_events.dispatch_events', false);
    SendReminder::$received = [];
});

afterEach(fn () => SendReminder::$received = []);

/**
 * A Student resource exposing the given actions, attached to a fresh request.
 *
 * @param array<int, Action> $actions
 */
function audActionRequest(array $actions, array $input = [], string $method = 'POST'): NadotaRequest
{
    $resource = new class extends Resource {
        public string $model = Student::class;
        public array $auditActions = [];

        public function fields(NadotaRequest $request): array
        {
            return [];
        }

        public function actions(NadotaRequest $request): array
        {
            return $this->auditActions;
        }
    };
    $resource->auditActions = $actions;

    $request = NadotaRequest::create('/nadota-api/student/resource/actions', $method, $input);
    $request->setResource($resource);

    return $request;
}

function audActionController(NadotaRequest $request): ActionController
{
    return new ActionController($request, app(ActionExecutionService::class));
}

/** Run the controller and return the HTTP status it would produce, whether it returns or throws. */
function audActionStatus(Closure $call): int
{
    try {
        return $call()->getStatusCode();
    } catch (ValidationException) {
        return 422;
    } catch (HttpExceptionInterface $e) {
        return $e->getStatusCode();
    }
}

it('lists the index actions by default and the detail actions for context=detail', function () {
    $actions = [ActivateStudents::make(), PurgeInactiveStudents::make(), SendReminder::make()->onlyOnDetail()];

    $index = audActionController($request = audActionRequest($actions, [], 'GET'))->index($request)->getData(true);
    $detail = audActionController($request = audActionRequest($actions, ['context' => 'detail'], 'GET'))->index($request)->getData(true);

    expect(array_column($index['actions'], 'key'))->toBe([ActivateStudents::getKey(), PurgeInactiveStudents::getKey()])
        ->and(array_column($detail['actions'], 'key'))->toBe([ActivateStudents::getKey(), SendReminder::getKey()]);
});

it('refuses to list actions without viewAny', function () {
    $this->app->instance(ResourceAuthorizationInterface::class, new class implements ResourceAuthorizationInterface {
        public function setModel($model): self
        {
            return $this;
        }

        public function authorizedTo(NadotaRequest $request, string $action, array $context = []): bool
        {
            return $action !== 'viewAny';
        }
    });
    $request = audActionRequest([ActivateStudents::make()], [], 'GET');

    expect(audActionStatus(fn () => audActionController($request)->index($request)))->toBe(403);
});

it('returns the fields of one action and 404 for an unknown key', function () {
    $request = audActionRequest([SendReminder::make()], [], 'GET');
    $controller = audActionController($request);

    $fields = $controller->fields($request, 'student', SendReminder::getKey())->getData(true)['fields'];
    $missing = $controller->fields($request, 'student', 'nope');

    expect(array_column($fields, 'key'))->toBe(['subject'])
        ->and($missing->getStatusCode())->toBe(404)
        ->and($missing->getData(true))->toBe(['message' => 'Action not found.']);
});

it('answers 404 for an unknown action key on execute', function () {
    $request = audActionRequest([ActivateStudents::make()], ['resources' => [1]]);

    $response = audActionController($request)->execute($request, 'student', 'nope');

    expect($response->getStatusCode())->toBe(404);
});

it('answers 422 when no resources are selected for a non-standalone action', function () {
    $request = audActionRequest([ActivateStudents::make()]);

    $response = audActionController($request)->execute($request, 'student', ActivateStudents::getKey());

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe(['message' => 'No resources selected.']);
});

it('runs a standalone action with no resources and returns its ActionResponse', function () {
    Student::create(['name' => 'Idle', 'is_active' => false]);
    $request = audActionRequest([PurgeInactiveStudents::make()]);

    $response = audActionController($request)->execute($request, 'student', PurgeInactiveStudents::getKey());

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['type' => 'danger', 'message' => 'Purged 1 students']);
});

it('turns an unexpected exception from handle() into a 500 danger response', function () {
    $student = Student::create(['name' => 'Ana']);
    $action = new class extends Action {
        public function handle(Collection $models, NadotaRequest $request): mixed
        {
            throw new RuntimeException('boom');
        }
    };
    $request = audActionRequest([$action], ['resources' => [$student->id]]);

    $response = audActionController($request)->execute($request, 'student', $action::getKey());

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true)['type'])->toBe('danger');
});

it('validates the action field rules before handle() runs', function () {
    $student = Student::create(['name' => 'Ana']);
    $request = audActionRequest([SendReminder::make()], ['resources' => [$student->id], 'subject' => 'far too long']);

    $status = audActionStatus(fn () => audActionController($request)->execute($request, 'student', SendReminder::getKey()));

    expect($status)->toBe(422)
        ->and(SendReminder::$received)->toBe([]);
})->skip("ISSUE AUD-021: nothing validates the action fields' rules(); handle() runs with the invalid input and the call answers 200");

it('lets a ValidationException thrown by handle() surface as a 422', function () {
    $student = Student::create(['name' => 'Ana']);
    $action = new class extends Action {
        public function handle(Collection $models, NadotaRequest $request): mixed
        {
            throw ValidationException::withMessages(['subject' => 'Subject is taken.']);
        }
    };
    $request = audActionRequest([$action], ['resources' => [$student->id]]);

    expect(audActionStatus(fn () => audActionController($request)->execute($request, 'student', $action::getKey())))->toBe(422);
})->skip('ISSUE AUD-022: ActionController catches every \Exception and answers 500 with the raw message');

it('lets an HTTP exception thrown by handle() keep its status code', function () {
    $student = Student::create(['name' => 'Ana']);
    $action = new class extends Action {
        public function handle(Collection $models, NadotaRequest $request): mixed
        {
            abort(403, 'Not yours.');
        }
    };
    $request = audActionRequest([$action], ['resources' => [$student->id]]);

    expect(audActionStatus(fn () => audActionController($request)->execute($request, 'student', $action::getKey())))->toBe(403);
})->skip('ISSUE AUD-022: abort(403) inside handle() is caught as \Exception and answered as 500');
