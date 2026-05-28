<?php

use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Controllers\ActionEventController;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceDestroyService;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;

beforeEach(function () {
    config()->set('nadota.action_events.enabled', true);
    config()->set('nadota.action_events.queue', false);
    config()->set('nadota.action_events.dispatch_events', false);
    config()->set('nadota.action_events.system_user_id', null);
    config()->set('nadota.action_events.exclude_fields', [
        'password', 'remember_token', 'api_token', 'token', 'secret', 'api_key', 'private_key',
    ]);
});

// ── Store ────────────────────────────────────────────────────────────────────

it('Store creates an action_events record with name=create', function () {
    $request = new NadotaRequest();
    $request->merge(['name' => 'New Model']);
    $request->setResource(new TestResource());

    (new ResourceStoreService())->handle($request);

    $this->assertDatabaseHas('action_events', [
        'name'       => 'create',
        'status'     => 'finished',
        'model_type' => TestModel::class,
    ]);
});

it('Store record contains the submitted fields (sanitized)', function () {
    $request = new NadotaRequest();
    $request->merge(['name' => 'Secure Model']);
    $request->setResource(new TestResource());

    (new ResourceStoreService())->handle($request);

    $event = ActionEvent::where('name', 'create')->first();

    expect($event)->not->toBeNull()
        ->and($event->fields)->toHaveKey('name', 'Secure Model');
});

it('Store record has no original data', function () {
    $request = new NadotaRequest();
    $request->merge(['name' => 'Fresh']);
    $request->setResource(new TestResource());

    (new ResourceStoreService())->handle($request);

    $event = ActionEvent::where('name', 'create')->first();

    expect($event->original)->toBeNull();
});

// ── Update ───────────────────────────────────────────────────────────────────

it('Update creates an action_events record with name=update', function () {
    $model = TestModel::create(['name' => 'Original']);

    $request = new NadotaRequest();
    $request->merge(['name' => 'Updated']);
    $request->setResource(new TestResource());

    (new ResourceUpdateService())->handle($request, $model->id);

    $this->assertDatabaseHas('action_events', [
        'name'       => 'update',
        'status'     => 'finished',
        'model_type' => TestModel::class,
        'model_id'   => $model->id,
    ]);
});

it('Update record captures original value before the change', function () {
    $model = TestModel::create(['name' => 'Original']);

    $request = new NadotaRequest();
    $request->merge(['name' => 'Updated']);
    $request->setResource(new TestResource());

    (new ResourceUpdateService())->handle($request, $model->id);

    $event = ActionEvent::where('name', 'update')
        ->where('model_id', $model->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->original)->toHaveKey('name', 'Original');
});

it('Update record captures only changed fields in changes', function () {
    $model = TestModel::create(['name' => 'Original', 'email' => 'same@test.com']);

    $request = new NadotaRequest();
    $request->merge(['name' => 'Updated', 'email' => 'same@test.com']);
    $request->setResource(new TestResource());

    (new ResourceUpdateService())->handle($request, $model->id);

    $event = ActionEvent::where('name', 'update')
        ->where('model_id', $model->id)
        ->first();

    expect($event->changes)->toHaveKey('name', 'Updated');
});

// ── Destroy ──────────────────────────────────────────────────────────────────

it('Destroy creates an action_events record with name=delete', function () {
    $model = TestModel::create(['name' => 'ToDelete']);

    $request = new NadotaRequest();
    $request->setResource(new TestResource());

    (new ResourceDestroyService())->handle($request, $model->id);

    $this->assertDatabaseHas('action_events', [
        'name'       => 'delete',
        'status'     => 'finished',
        'model_type' => TestModel::class,
        'model_id'   => $model->id,
    ]);
});

it('Destroy record captures the original model attributes before deletion', function () {
    $model = TestModel::create(['name' => 'ToDelete', 'email' => 'del@test.com']);

    $request = new NadotaRequest();
    $request->setResource(new TestResource());

    (new ResourceDestroyService())->handle($request, $model->id);

    $event = ActionEvent::where('name', 'delete')
        ->where('model_id', $model->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->original)->toHaveKey('name', 'ToDelete')
        ->and($event->original)->toHaveKey('email', 'del@test.com')
        ->and($event->changes)->toBeNull();
});

// ── Tracking disabled ────────────────────────────────────────────────────────

it('no action_events record is created when tracking is disabled', function () {
    config()->set('nadota.action_events.enabled', false);

    $request = new NadotaRequest();
    $request->merge(['name' => 'Silent']);
    $request->setResource(new TestResource());

    (new ResourceStoreService())->handle($request);

    $this->assertDatabaseCount('action_events', 0);
});

it('no action_events record is created on Update when tracking is disabled', function () {
    $model = TestModel::create(['name' => 'Original']);
    config()->set('nadota.action_events.enabled', false);

    $request = new NadotaRequest();
    $request->merge(['name' => 'Updated']);
    $request->setResource(new TestResource());

    (new ResourceUpdateService())->handle($request, $model->id);

    $this->assertDatabaseCount('action_events', 0);
});

it('no action_events record is created on Destroy when tracking is disabled', function () {
    $model = TestModel::create(['name' => 'ToDelete']);
    config()->set('nadota.action_events.enabled', false);

    $request = new NadotaRequest();
    $request->setResource(new TestResource());

    (new ResourceDestroyService())->handle($request, $model->id);

    $this->assertDatabaseCount('action_events', 0);
});

// ── Multiple events ───────────────────────────────────────────────────────────

it('multiple operations on the same model create separate action_events records', function () {
    $request = new NadotaRequest();
    $request->merge(['name' => 'First']);
    $request->setResource(new TestResource());

    $storeResponse = (new ResourceStoreService())->handle($request);
    $modelId = json_decode($storeResponse->getContent(), true)['data']['id'];

    $updateRequest = new NadotaRequest();
    $updateRequest->merge(['name' => 'Second']);
    $updateRequest->setResource(new TestResource());

    (new ResourceUpdateService())->handle($updateRequest, $modelId);

    expect(ActionEvent::where('model_id', $modelId)->count())->toBe(2)
        ->and(ActionEvent::where('name', 'create')->where('model_id', $modelId)->exists())->toBeTrue()
        ->and(ActionEvent::where('name', 'update')->where('model_id', $modelId)->exists())->toBeTrue();
});

// ── ActionEventController show ────────────────────────────────────────────────

it('show returns the action event data for the given model and event IDs', function () {
    $model = TestModel::create(['name' => 'Show Test', 'email' => 'show@test.com']);

    $event = ActionEvent::create([
        'name'            => 'create',
        'actionable_type' => TestResource::class,
        'actionable_id'   => 0,
        'target_type'     => TestModel::class,
        'target_id'       => $model->id,
        'model_type'      => TestModel::class,
        'model_id'        => $model->id,
        'fields'          => ['name' => 'Show Test'],
        'status'          => 'finished',
        'original'        => null,
        'changes'         => ['name' => 'Show Test'],
    ]);

    $request = new NadotaRequest();
    $request->setResource(new TestResource());

    $controller = new ActionEventController();
    $response = $controller->show($request, 'test', $model->id, $event->id);

    $data = json_decode($response->getContent(), true)['data'];

    expect($response->getStatusCode())->toBe(200)
        ->and($data['id'])->toBe($event->id)
        ->and($data['name'])->toBe('create')
        ->and($data['nameLabel'])->toBe('Created')
        ->and($data['status'])->toBe('finished')
        ->and($data['modelId'])->toBe($model->id)
        ->and($data['fields'])->toMatchArray(['name' => 'Show Test']);
});

it('show returns 404 when the event does not belong to the given model', function () {
    $modelA = TestModel::create(['name' => 'Model A']);
    $modelB = TestModel::create(['name' => 'Model B']);

    $event = ActionEvent::create([
        'name'            => 'create',
        'actionable_type' => TestResource::class,
        'actionable_id'   => 0,
        'target_type'     => TestModel::class,
        'target_id'       => $modelA->id,
        'model_type'      => TestModel::class,
        'model_id'        => $modelA->id,
        'fields'          => [],
        'status'          => 'finished',
    ]);

    $request = new NadotaRequest();
    $request->setResource(new TestResource());

    $controller = new ActionEventController();

    // Request for modelB's action events but passing modelA's event ID
    expect(fn () => $controller->show($request, 'test', $modelB->id, $event->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('show returns 404 when the event ID does not exist', function () {
    $model = TestModel::create(['name' => 'No Event']);

    $request = new NadotaRequest();
    $request->setResource(new TestResource());

    $controller = new ActionEventController();

    expect(fn () => $controller->show($request, 'test', $model->id, 99999))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
