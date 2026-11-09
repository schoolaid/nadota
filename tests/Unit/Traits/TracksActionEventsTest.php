<?php

use SchoolAid\Nadota\Http\Services\ActionEventService;
use SchoolAid\Nadota\Http\Traits\TracksActionEvents;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

/**
 * Trait-level behaviour only: the gate (config / override), the lazy service
 * lookup and what each track*() forwards. What ActionEventService writes is
 * covered by tests/Unit/Services/ActionEventServiceTest.php and
 * tests/ServiceIntegration/ActionEventIntegrationTest.php.
 */

function actionEventSpy(): ActionEventService
{
    return new class extends ActionEventService {
        public array $calls = [];

        public function logCreate($model, $resource, $request, array $fields = []): ActionEvent
        {
            $this->calls[] = ['create', $model, $resource, $request, $fields];

            return new ActionEvent();
        }

        public function logUpdate($model, $resource, $request, array $fields = [], ?array $originalData = null): ActionEvent
        {
            $this->calls[] = ['update', $model, $resource, $request, $fields, $originalData];

            return new ActionEvent();
        }

        public function logDelete($model, $resource, $request): ActionEvent
        {
            $this->calls[] = ['delete', $model, $resource, $request];

            return new ActionEvent();
        }

        public function logRestore($model, $resource, $request): ActionEvent
        {
            $this->calls[] = ['restore', $model, $resource, $request];

            return new ActionEvent();
        }

        public function logAction(string $action, $model, $resource, $request, array $fields = [], array $metadata = []): ActionEvent
        {
            $this->calls[] = [$action, $model, $resource, $request, $fields, $metadata];

            return new ActionEvent();
        }
    };
}

function actionEventTracker(bool $track = true): object
{
    return new class($track) {
        use TracksActionEvents;

        public function __construct(private bool $track)
        {
        }

        protected function shouldTrackActions(): bool
        {
            return $this->track && config('nadota.action_events.enabled', true);
        }

        public function __call($method, $arguments)
        {
            return $this->{$method}(...$arguments);
        }
    };
}

beforeEach(function () {
    $this->spy = actionEventSpy();
    $this->app->instance(ActionEventService::class, $this->spy);

    $this->resource = new StudentResource();
    $this->request = createNadotaRequest();
    $this->request->setResource($this->resource);
    $this->model = new Student(['name' => 'Ana']);
});

it('forwards each tracked operation with the request resource', function () {
    $tracker = actionEventTracker();

    $tracker->trackCreate($this->model, $this->request, ['name' => 'Ana']);
    $tracker->trackUpdate($this->model, $this->request, ['name' => 'Bea'], ['name' => 'Ana']);
    $tracker->trackDelete($this->model, $this->request);
    $tracker->trackRestore($this->model, $this->request);
    $tracker->trackCustomAction('activate', $this->model, $this->request, ['x' => 1], ['status' => 'failed']);

    expect(array_column($this->spy->calls, 0))->toBe(['create', 'update', 'delete', 'restore', 'activate'])
        ->and(array_column($this->spy->calls, 2))->each->toBe($this->resource)
        ->and(array_column($this->spy->calls, 1))->each->toBe($this->model)
        ->and($this->spy->calls[0][4])->toBe(['name' => 'Ana'])
        ->and($this->spy->calls[1][5])->toBe(['name' => 'Ana'])
        ->and($this->spy->calls[4][5])->toBe(['status' => 'failed']);
});

it('passes a null original through so the service can fall back to getOriginal()', function () {
    actionEventTracker()->trackUpdate($this->model, $this->request);

    expect($this->spy->calls[0][4])->toBe([])
        ->and($this->spy->calls[0][5])->toBeNull();
});

it('tracks nothing while action_events.enabled is false', function () {
    config()->set('nadota.action_events.enabled', false);
    $tracker = actionEventTracker();

    $tracker->trackCreate($this->model, $this->request);
    $tracker->trackUpdate($this->model, $this->request);
    $tracker->trackDelete($this->model, $this->request);
    $tracker->trackRestore($this->model, $this->request);
    $tracker->trackCustomAction('activate', $this->model, $this->request);

    expect($this->spy->calls)->toBe([]);
});

it('tracks by default when the config key is absent', function () {
    config()->set('nadota.action_events', []);

    actionEventTracker()->trackCreate($this->model, $this->request);

    expect($this->spy->calls)->toHaveCount(1);
});

it('lets the host service opt out by overriding shouldTrackActions()', function () {
    config()->set('nadota.action_events.enabled', true);

    actionEventTracker(track: false)->trackCreate($this->model, $this->request);

    expect($this->spy->calls)->toBe([]);
});

it('resolves the service from the container once and reuses it', function () {
    $resolutions = 0;
    $this->app->bind(ActionEventService::class, function () use (&$resolutions) {
        $resolutions++;

        return $this->spy;
    });
    $tracker = actionEventTracker();

    $tracker->trackCreate($this->model, $this->request);
    $tracker->trackDelete($this->model, $this->request);

    expect($resolutions)->toBe(1)
        ->and($this->spy->calls)->toHaveCount(2);
});

it('does not touch the container when tracking is off', function () {
    config()->set('nadota.action_events.enabled', false);
    $resolutions = 0;
    $this->app->bind(ActionEventService::class, function () use (&$resolutions) {
        $resolutions++;

        return $this->spy;
    });

    actionEventTracker()->trackCreate($this->model, $this->request);

    expect($resolutions)->toBe(0);
});
