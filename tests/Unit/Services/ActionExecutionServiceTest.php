<?php

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Actions\Action;
use SchoolAid\Nadota\Http\Actions\ActionResponse;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ActionEventService;
use SchoolAid\Nadota\Http\Services\ActionExecutionService;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;

beforeEach(function () {
    config()->set('nadota.action_events.enabled', true);
    config()->set('nadota.action_events.queue', false);
    config()->set('nadota.action_events.dispatch_events', false);
    config()->set('nadota.action_events.system_user_id', null);

    $this->request = new NadotaRequest();
    $this->request->setResource(new TestResource());

    $this->service = new ActionExecutionService(new ActionEventService());
});

function runnableAction(Closure $handle, bool $standalone = false): Action
{
    return (new class($handle) extends Action {
        public function __construct(private Closure $callback)
        {
        }

        public function handle(Collection $models, NadotaRequest $request): mixed
        {
            return ($this->callback)($models, $request);
        }
    })->standalone($standalone);
}

it('logs one finished event per model', function () {
    $models = collect([TestModel::create(['name' => 'A']), TestModel::create(['name' => 'B'])]);
    $action = runnableAction(fn () => ActionResponse::message('ok'));

    $response = $this->service->execute($this->request, $action, $models->pluck('id')->all());

    $events = ActionEvent::orderBy('id')->get();

    expect($response->toArray()['message'])->toBe('ok')
        ->and($events)->toHaveCount(2)
        ->and($events->pluck('status')->unique()->all())->toBe(['finished'])
        ->and($events->pluck('name')->unique()->all())->toBe(['action:' . $action::getKey()])
        ->and($events->pluck('model_id')->map(fn ($id) => (int) $id)->all())->toBe($models->pluck('id')->all());
});

it('logs a failed event per model with the exception message and rethrows', function () {
    $models = collect([TestModel::create(['name' => 'A']), TestModel::create(['name' => 'B'])]);
    $action = runnableAction(fn () => throw new RuntimeException('Mail server down'));

    expect(fn () => $this->service->execute($this->request, $action, $models->pluck('id')->all()))
        ->toThrow(RuntimeException::class, 'Mail server down');

    $events = ActionEvent::all();

    expect($events)->toHaveCount(2)
        ->and($events->pluck('status')->unique()->all())->toBe(['failed'])
        ->and($events->pluck('exception')->unique()->all())->toBe(['Mail server down']);
});

it('logs a single event without a model for a standalone action run with none selected', function () {
    $action = runnableAction(fn () => null, standalone: true);

    $response = $this->service->execute($this->request, $action, []);

    $event = ActionEvent::sole();

    expect($response->toArray()['message'])->toBe('Action executed successfully.')
        ->and($event->status)->toBe('finished')
        ->and($event->model_type)->toBe(TestModel::class)
        ->and($event->model_id)->toBeNull()
        ->and($event->actionable_type)->toBe(TestResource::class);
});

it('logs a failed standalone run', function () {
    $action = runnableAction(fn () => throw new RuntimeException('Import file missing'), standalone: true);

    expect(fn () => $this->service->execute($this->request, $action, []))
        ->toThrow(RuntimeException::class);

    $event = ActionEvent::sole();

    expect($event->status)->toBe('failed')
        ->and($event->exception)->toBe('Import file missing')
        ->and($event->model_id)->toBeNull();
});

it('logs earlier batches as finished and the failing batch as failed', function () {
    $models = collect(range(1, 3))->map(fn ($i) => TestModel::create(['name' => "M{$i}"]));
    $calls = 0;
    $action = runnableAction(function () use (&$calls) {
        if (++$calls === 2) {
            throw new RuntimeException('Second batch failed');
        }
    });

    expect(fn () => $this->service->executeBatched($this->request, $action, $models->pluck('id')->all(), batchSize: 2))
        ->toThrow(RuntimeException::class, 'Second batch failed');

    $statuses = ActionEvent::orderBy('id')->get()
        ->mapWithKeys(fn ($event) => [(int) $event->model_id => $event->status])
        ->all();

    expect($statuses)->toBe([
        $models[0]->id => 'finished',
        $models[1]->id => 'finished',
        $models[2]->id => 'failed',
    ]);
});
