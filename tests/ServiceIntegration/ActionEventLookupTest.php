<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Controllers\ActionEventController;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;

/**
 * `action_events` is written by more than the package: host apps log model events of their own
 * (and Nova did before). Those writers store `getMorphClass()`, which is the alias when the model
 * is in a morph map, while the package stores the class name. The audit trail of a record has to
 * find both spellings.
 */
afterEach(function () {
    Relation::morphMap([], false);
});

function lookupEvent(TestModel $model, string $modelType, string $name): ActionEvent
{
    return ActionEvent::create([
        'name'            => $name,
        'actionable_type' => $modelType,
        'actionable_id'   => $model->id,
        'target_type'     => $modelType,
        'target_id'       => $model->id,
        'model_type'      => $modelType,
        'model_id'        => $model->id,
        'fields'          => [],
        'status'          => 'finished',
    ]);
}

function lookupRequest(): NadotaRequest
{
    $request = new NadotaRequest();
    $request->setResource(new TestResource());

    return $request;
}

it('index includes the events stored under the morph alias of the model', function () {
    Relation::morphMap(['test-model' => TestModel::class]);

    $model = TestModel::create(['name' => 'Aliased']);
    lookupEvent($model, TestModel::class, 'update');
    lookupEvent($model, 'test-model', 'Updated');

    $body = json_decode(
        (new ActionEventController())->index(lookupRequest(), 'test', $model->id)->getContent(),
        true
    );

    expect($body['meta']['total'])->toBe(2)
        ->and(collect($body['data'])->pluck('name')->sort()->values()->all())->toBe(['Updated', 'update']);
});

it('show finds an event stored under the morph alias of the model', function () {
    Relation::morphMap(['test-model' => TestModel::class]);

    $model = TestModel::create(['name' => 'Aliased']);
    $event = lookupEvent($model, 'test-model', 'Updated');

    $response = (new ActionEventController())->show(lookupRequest(), 'test', $model->id, $event->id);

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true)['data']['id'])->toBe($event->id);
});

it('does not leak events of another model that shares the record id', function () {
    Relation::morphMap(['test-model' => TestModel::class]);

    $model = TestModel::create(['name' => 'Mine']);
    lookupEvent($model, 'test-model', 'Updated');
    lookupEvent($model, 'some-other-model', 'Updated');

    $body = json_decode(
        (new ActionEventController())->index(lookupRequest(), 'test', $model->id)->getContent(),
        true
    );

    expect($body['meta']['total'])->toBe(1);
});

/**
 * Host apps that widened `model_id` to a string column (UUID support) only get an index lookup
 * when the id is compared as a string: an integer binding against a varchar column makes MySQL
 * cast every row and skip the index.
 */
it('looks the record id up as a string', function () {
    $model = TestModel::create(['name' => 'Indexed']);
    lookupEvent($model, TestModel::class, 'update');

    DB::enableQueryLog();
    (new ActionEventController())->index(lookupRequest(), 'test', $model->id);
    $queries = collect(DB::getQueryLog())->filter(
        fn (array $query): bool => str_contains($query['query'], 'action_events')
    );
    DB::disableQueryLog();

    expect($queries)->not->toBeEmpty();

    $queries->each(function (array $query) use ($model) {
        expect($query['bindings'])->toContain((string) $model->id)
            ->and($query['bindings'])->not->toContain($model->id);
    });
});

/**
 * `created_at` has one-second resolution and a single request can write several events (a
 * detach followed by an attach). Without a tie-breaker the database returns them in any order,
 * and the timeline can show them backwards or shuffle them between pages.
 */
it('orders events written in the same second newest first', function () {
    $model = TestModel::create(['name' => 'Tied']);
    $first = lookupEvent($model, TestModel::class, 'detach');
    $second = lookupEvent($model, TestModel::class, 'attach');
    $third = lookupEvent($model, TestModel::class, 'sync');

    ActionEvent::query()
        ->whereKey([$first->id, $second->id, $third->id])
        ->update(['created_at' => '2026-09-27 23:50:54']);

    $body = json_decode(
        (new ActionEventController())->index(lookupRequest(), 'test', $model->id)->getContent(),
        true
    );

    expect(array_column($body['data'], 'id'))->toBe([$third->id, $second->id, $first->id]);
});
