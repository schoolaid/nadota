<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use SchoolAid\Nadota\Http\Actions\Action;
use SchoolAid\Nadota\Http\Actions\ActionResponse;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ActionExecutionService;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

beforeEach(function () {
    FixtureSchema::create();
    config()->set('nadota.action_events.enabled', true);
    config()->set('nadota.action_events.queue', false);
    config()->set('nadota.action_events.dispatch_events', false);
});

/** An action that records the keys of the models handle() received. */
function audRecordingAction(?Closure $canRun = null): Action
{
    $action = new class extends Action {
        public array $handled = [];

        public function handle(Collection $models, NadotaRequest $request): mixed
        {
            $this->handled = $models->map(fn (Model $model) => $model->getKey())->values()->all();

            return null;
        }
    };

    return $canRun ? $action->canRun($canRun) : $action;
}

/** A request bound to a resource over $modelClass, optionally scoping its query. */
function audExecutionRequest(string $modelClass = Student::class, ?Closure $scope = null): NadotaRequest
{
    $resource = new class extends Resource {
        public string $model = Student::class;
        public ?Closure $scope = null;

        public function fields(NadotaRequest $request): array
        {
            return [];
        }

        public function getQuery(NadotaRequest $request, ?Model $modelInstance = null): Builder
        {
            $query = parent::getQuery($request, $modelInstance);

            return $this->scope ? ($this->scope)($query) : $query;
        }
    };
    $resource->model = $modelClass;
    $resource->scope = $scope;

    $request = new NadotaRequest();
    $request->setResource($resource);

    return $request;
}

it('hands only the models canRun() authorizes to handle()', function () {
    $ana = Student::create(['name' => 'Ana']);
    $bob = Student::create(['name' => 'Bob']);
    $action = audRecordingAction(fn ($request, Model $model) => $model->name === 'Ana');

    app(ActionExecutionService::class)->execute(audExecutionRequest(), $action, [$ana->id, $bob->id]);

    expect($action->handled)->toBe([$ana->id]);
});

it('answers danger without running handle() when no selected model is authorized', function () {
    $ana = Student::create(['name' => 'Ana']);
    $action = audRecordingAction(fn () => false);

    $response = app(ActionExecutionService::class)->execute(audExecutionRequest(), $action, [$ana->id]);

    expect($response->toArray())->toBe([
        'type' => 'danger',
        'message' => 'You are not authorized to run this action on the selected resources.',
    ])->and($action->handled)->toBe([]);
});

it('runs a standalone action with an empty collection', function () {
    $action = audRecordingAction()->standalone();

    $response = app(ActionExecutionService::class)->execute(audExecutionRequest(), $action, []);

    expect($action->handled)->toBe([])
        ->and($response->getType())->toBe('message');
});

it('answers a default success message when handle() returns something other than an ActionResponse', function () {
    $ana = Student::create(['name' => 'Ana']);

    $response = app(ActionExecutionService::class)->execute(audExecutionRequest(), audRecordingAction(), [$ana->id]);

    expect($response)->toBeInstanceOf(ActionResponse::class)
        ->and($response->toArray())->toBe(['type' => 'message', 'message' => 'Action executed successfully.']);
});

it('includes soft-deleted models when the resource uses soft deletes', function () {
    $ana = Student::create(['name' => 'Ana']);
    $ana->delete();
    $action = audRecordingAction();

    app(ActionExecutionService::class)->execute(audExecutionRequest(), $action, [$ana->id]);

    expect($action->handled)->toBe([$ana->id]);
});

it('records nothing in action_events while action_events.enabled is false', function () {
    config()->set('nadota.action_events.enabled', false);
    $ana = Student::create(['name' => 'Ana']);

    app(ActionExecutionService::class)->execute(audExecutionRequest(), audRecordingAction(), [$ana->id]);

    expect(ActionEvent::count())->toBe(0);
})->skip('ISSUE AUD-024: ActionExecutionService calls ActionEventService::logAction() directly and never checks action_events.enabled');

it('loads the selected models through their own primary key', function () {
    Schema::create('aud_tokens', function (Blueprint $table) {
        $table->string('code')->primary();
        $table->string('name');
    });
    $token = new class extends Model {
        protected $table = 'aud_tokens';
        protected $primaryKey = 'code';
        protected $keyType = 'string';
        public $incrementing = false;
        public $timestamps = false;
        protected $guarded = [];
    };
    $token::query()->create(['code' => 'ABC', 'name' => 'first']);
    $action = audRecordingAction();

    app(ActionExecutionService::class)->execute(audExecutionRequest($token::class), $action, ['ABC']);

    expect($action->handled)->toBe(['ABC']);
})->skip("ISSUE AUD-025: getModels() hard-codes whereIn('id', ...) instead of the model's key name");

it("never hands handle() a model outside the resource's getQuery() scope", function () {
    $mine = Student::create(['name' => 'Mine']);
    $other = Student::create(['name' => 'Other']);
    $action = audRecordingAction();

    app(ActionExecutionService::class)->execute(
        audExecutionRequest(Student::class, fn (Builder $query) => $query->where('name', 'Mine')),
        $action,
        [$mine->id, $other->id]
    );

    expect($action->handled)->toBe([$mine->id]);
})->skip('ISSUE AUD-025: getModels() queries $modelClass::query() directly, bypassing getQuery()/queryIndex() scoping');
