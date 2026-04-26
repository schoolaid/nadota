<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use SchoolAid\Nadota\Http\Actions\Action;
use SchoolAid\Nadota\Http\Actions\ActionResponse;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ActionEventService;
use SchoolAid\Nadota\Http\Services\ActionExecutionService;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;

beforeEach(function () {
    if (!Schema::hasTable('action_events')) {
        Schema::create('action_events', function (Blueprint $table) {
            $table->id();
            $table->char('batch_id', 36);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('actionable_type');
            $table->unsignedBigInteger('actionable_id')->nullable();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('fields');
            $table->string('status', 25)->default('running');
            $table->text('exception')->nullable();
            $table->text('original')->nullable();
            $table->text('changes')->nullable();
            $table->timestamps();
        });
    }

    $this->resource = new TestResource();
    $this->request = new NadotaRequest();
    $this->request->setResource($this->resource);

    $this->executionService = new ActionExecutionService(new ActionEventService());
});

function makeAction(\Closure $handle, bool $standalone = false): Action
{
    return new class($handle, $standalone) extends Action {
        public function __construct(
            private \Closure $handleCallback,
            bool $standalone,
        ) {
            $this->standalone = $standalone;
        }

        public function handle(Collection $models, NadotaRequest $request): mixed
        {
            return ($this->handleCallback)($models, $request);
        }
    };
}

it('logs one finished ActionEvent per affected model on success', function () {
    $models = collect([
        TestModel::query()->create(['name' => 'A']),
        TestModel::query()->create(['name' => 'B']),
        TestModel::query()->create(['name' => 'C']),
    ]);

    $action = makeAction(fn () => Action::message('ok'));

    $response = $this->executionService->execute(
        $this->request,
        $action,
        $models->pluck('id')->all()
    );

    expect($response)->toBeInstanceOf(ActionResponse::class)
        ->and($response->getType())->toBe('message');

    $events = ActionEvent::query()->get();
    expect($events)->toHaveCount(3)
        ->and($events->pluck('status')->unique()->all())->toBe(['finished'])
        ->and($events->pluck('model_id')->sort()->values()->all())
            ->toBe($models->pluck('id')->sort()->values()->all())
        ->and($events->pluck('name')->unique()->first())
            ->toStartWith('action:');
});

it('logs failed ActionEvents and returns danger when handle() throws', function () {
    $models = collect([
        TestModel::query()->create(['name' => 'A']),
        TestModel::query()->create(['name' => 'B']),
    ]);

    $action = makeAction(function () {
        throw new \RuntimeException('boom');
    });

    $response = $this->executionService->execute(
        $this->request,
        $action,
        $models->pluck('id')->all()
    );

    expect($response->getType())->toBe('danger')
        ->and($response->getMessage())->toBe('boom');

    $events = ActionEvent::query()->get();
    expect($events)->toHaveCount(2)
        ->and($events->pluck('status')->unique()->all())->toBe(['failed'])
        ->and($events->pluck('exception')->unique()->all())->toBe(['boom']);
});

it('logs a single ActionEvent without model_id for standalone actions on success', function () {
    $action = makeAction(fn () => Action::message('done'), standalone: true);

    $response = $this->executionService->execute($this->request, $action, []);

    expect($response->getType())->toBe('message');

    $events = ActionEvent::query()->get();
    expect($events)->toHaveCount(1);

    $event = $events->first();
    expect($event->status)->toBe('finished')
        ->and($event->model_id)->toBeNull()
        ->and($event->target_type)->toBe(TestResource::class)
        ->and($event->name)->toStartWith('action:');
});

it('logs a single failed ActionEvent without model_id for standalone actions that throw', function () {
    $action = makeAction(function () {
        throw new \RuntimeException('standalone failure');
    }, standalone: true);

    $response = $this->executionService->execute($this->request, $action, []);

    expect($response->getType())->toBe('danger')
        ->and($response->getMessage())->toBe('standalone failure');

    $events = ActionEvent::query()->get();
    expect($events)->toHaveCount(1);

    $event = $events->first();
    expect($event->status)->toBe('failed')
        ->and($event->exception)->toBe('standalone failure')
        ->and($event->model_id)->toBeNull();
});

it('shares the same batch_id across all events of one execution', function () {
    $models = collect([
        TestModel::query()->create(['name' => 'A']),
        TestModel::query()->create(['name' => 'B']),
    ]);

    $action = makeAction(fn () => null);

    $this->executionService->execute(
        $this->request,
        $action,
        $models->pluck('id')->all()
    );

    $batches = ActionEvent::query()->pluck('batch_id')->unique();
    expect($batches)->toHaveCount(1);
});
