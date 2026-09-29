<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Http\Controllers\ActionEventController;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The audit trail of a record carries full attribute snapshots (`original`,
 * `changes`, `fields`), so it must be at least as protected as the record's own
 * show endpoint: same resource query, same `view` authorization on the record.
 */
function actionEventFor(Model $model, array $attributes = []): ActionEvent
{
    return ActionEvent::create(array_merge([
        'name'            => 'update',
        'actionable_type' => TestResource::class,
        'actionable_id'   => 0,
        'target_type'     => get_class($model),
        'target_id'       => $model->getKey(),
        'model_type'      => get_class($model),
        'model_id'        => $model->getKey(),
        'fields'          => [],
        'status'          => 'finished',
        'original'        => ['name' => 'Before'],
        'changes'         => ['name' => 'After'],
    ], $attributes));
}

/**
 * Swaps the always-allow test double for one that decides per action and record.
 *
 * @param  Closure(string, mixed): bool  $decide
 */
function authorizeActionEventsWith(Closure $decide): void
{
    app()->bind(ResourceAuthorizationInterface::class, fn () => new class($decide) implements ResourceAuthorizationInterface {
        public $model;

        public function __construct(private Closure $decide)
        {
        }

        public function setModel($model): self
        {
            $this->model = $model;

            return $this;
        }

        public function authorizedTo(NadotaRequest $request, string $action, array $context = []): bool
        {
            return ($this->decide)($action, $this->model);
        }
    });
}

function actionEventsRequest(?TestResource $resource = null): NadotaRequest
{
    $request = new NadotaRequest();
    $request->setResource($resource ?? new TestResource());

    return $request;
}

it('index refuses the audit trail when the user cannot view the record', function () {
    $model = TestModel::create(['name' => 'Private']);
    actionEventFor($model);

    authorizeActionEventsWith(fn (string $action): bool => $action !== 'view');

    try {
        (new ActionEventController())->index(actionEventsRequest(), 'test', $model->id);
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);

        return;
    }

    $this->fail('The audit trail was served to a user who cannot view the record.');
});

it('show refuses a single event when the user cannot view the record', function () {
    $model = TestModel::create(['name' => 'Private']);
    $event = actionEventFor($model);

    authorizeActionEventsWith(fn (string $action): bool => $action !== 'view');

    try {
        (new ActionEventController())->show(actionEventsRequest(), 'test', $model->id, $event->id);
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);

        return;
    }

    $this->fail('The event was served to a user who cannot view the record.');
});

it('authorizes against the record itself, not against the model class', function () {
    $visible = TestModel::create(['name' => 'Visible']);
    $secret = TestModel::create(['name' => 'Secret']);
    actionEventFor($visible);
    actionEventFor($secret);

    authorizeActionEventsWith(
        fn (string $action, $model): bool => $model instanceof TestModel && $model->name !== 'Secret'
    );

    $controller = new ActionEventController();

    $response = $controller->index(actionEventsRequest(), 'test', $visible->id);

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true)['meta']['total'])->toBe(1)
        ->and(fn () => $controller->index(actionEventsRequest(), 'test', $secret->id))
        ->toThrow(HttpException::class);
});

it('index answers 404 for a record the resource query does not expose', function () {
    $hidden = TestModel::create(['name' => 'Other branch', 'is_active' => false]);
    actionEventFor($hidden);

    $scopedResource = new class extends TestResource {
        public function getQuery(NadotaRequest $request, ?Model $modelInstance = null): Builder
        {
            return parent::getQuery($request, $modelInstance)->where('is_active', true);
        }
    };

    expect(fn () => (new ActionEventController())->index(actionEventsRequest($scopedResource), 'test', $hidden->id))
        ->toThrow(ModelNotFoundException::class);
});

it('show answers 404 for a record the resource query does not expose', function () {
    $hidden = TestModel::create(['name' => 'Other branch', 'is_active' => false]);
    $event = actionEventFor($hidden);

    $scopedResource = new class extends TestResource {
        public function getQuery(NadotaRequest $request, ?Model $modelInstance = null): Builder
        {
            return parent::getQuery($request, $modelInstance)->where('is_active', true);
        }
    };

    expect(fn () => (new ActionEventController())->show(actionEventsRequest($scopedResource), 'test', $hidden->id, $event->id))
        ->toThrow(ModelNotFoundException::class);
});

it('index keeps serving the audit trail to a user who can view the record', function () {
    $model = TestModel::create(['name' => 'Open']);
    actionEventFor($model, ['name' => 'create']);
    actionEventFor($model, ['name' => 'update']);
    actionEventFor(TestModel::create(['name' => 'Someone else']));

    $response = (new ActionEventController())->index(actionEventsRequest(), 'test', $model->id);
    $body = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(200)
        ->and($body['meta']['total'])->toBe(2)
        ->and(array_column($body['data'], 'modelId'))->toBe([$model->id, $model->id]);
});
