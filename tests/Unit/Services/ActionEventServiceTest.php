<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use SchoolAid\Nadota\Events\ActionLogged;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ActionEventService;
use SchoolAid\Nadota\Jobs\LogActionEvent;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;

beforeEach(function () {
    $this->service  = new ActionEventService();
    $this->resource = new TestResource();
    $this->request  = new NadotaRequest();
    $this->request->setResource($this->resource);

    config()->set('nadota.action_events.enabled', true);
    config()->set('nadota.action_events.queue', false);
    config()->set('nadota.action_events.dispatch_events', false);
    config()->set('nadota.action_events.system_user_id', null);
    config()->set('nadota.action_events.exclude_fields', [
        'password', 'remember_token', 'api_token', 'token', 'secret', 'api_key', 'private_key',
    ]);
});

afterEach(function () {
    if (Auth::check()) {
        Auth::logout();
    }
});

// ── logCreate ───────────────────────────────────────────────────────────────

it('logCreate persists a finished record with name=create', function () {
    $model = TestModel::create(['name' => 'John', 'email' => 'john@test.com']);

    $event = $this->service->logCreate($model, $this->resource, $this->request, ['name' => 'John']);

    expect($event->name)->toBe('create')
        ->and($event->status)->toBe('finished')
        ->and($event->model_type)->toBe(TestModel::class)
        ->and($event->model_id)->toBe($model->id)
        ->and($event->actionable_type)->toBe(TestResource::class)
        ->and($event->original)->toBeNull()
        ->and($event->fields)->toMatchArray(['name' => 'John']);

    $this->assertDatabaseHas('action_events', [
        'name'       => 'create',
        'status'     => 'finished',
        'model_type' => TestModel::class,
        'model_id'   => $model->id,
    ]);
});

it('logCreate stores all model attributes as changes', function () {
    $model = TestModel::create(['name' => 'Jane', 'email' => 'jane@test.com']);

    $event = $this->service->logCreate($model, $this->resource, $this->request);

    expect($event->changes)->toHaveKey('name', 'Jane')
        ->and($event->changes)->toHaveKey('email', 'jane@test.com');
});

// ── logUpdate ───────────────────────────────────────────────────────────────

it('logUpdate persists original and changes correctly', function () {
    $model        = TestModel::create(['name' => 'Original', 'email' => 'old@test.com']);
    $originalData = $model->getAttributes();

    $model->name = 'Updated';
    $model->save();

    $event = $this->service->logUpdate($model, $this->resource, $this->request, ['name' => 'Updated'], $originalData);

    expect($event->name)->toBe('update')
        ->and($event->status)->toBe('finished')
        ->and($event->original)->toHaveKey('name', 'Original')
        ->and($event->changes)->toHaveKey('name', 'Updated');
});

it('logUpdate falls back to model getOriginal when no originalData is passed', function () {
    $model = TestModel::create(['name' => 'Original']);

    $model->name = 'Updated';
    $model->save();

    $event = $this->service->logUpdate($model, $this->resource, $this->request);

    expect($event->name)->toBe('update')
        ->and($event->changes)->toHaveKey('name', 'Updated');
});

// ── logDelete ───────────────────────────────────────────────────────────────

it('logDelete persists original attributes with no changes', function () {
    $model = TestModel::create(['name' => 'ToDelete', 'email' => 'del@test.com']);

    $event = $this->service->logDelete($model, $this->resource, $this->request);

    expect($event->name)->toBe('delete')
        ->and($event->status)->toBe('finished')
        ->and($event->original)->toHaveKey('name', 'ToDelete')
        ->and($event->changes)->toBeNull();
});

// ── logRestore ──────────────────────────────────────────────────────────────

it('logRestore persists a finished record with name=restore', function () {
    $model = TestModel::create(['name' => 'ToRestore']);

    $event = $this->service->logRestore($model, $this->resource, $this->request);

    expect($event->name)->toBe('restore')
        ->and($event->status)->toBe('finished')
        ->and($event->model_id)->toBe($model->id);
});

// ── logAction (custom) ──────────────────────────────────────────────────────

it('logAction persists a custom action record', function () {
    $model = TestModel::create(['name' => 'Test']);

    $event = $this->service->logAction('forceDelete', $model, $this->resource, $this->request, [], [
        'original' => ['name' => 'Test'],
        'changes'  => ['permanently_deleted' => true],
    ]);

    expect($event->name)->toBe('forceDelete')
        ->and($event->status)->toBe('finished')
        ->and($event->original)->toHaveKey('name', 'Test')
        ->and($event->changes)->toHaveKey('permanently_deleted', true);
});

// ── Sanitization ────────────────────────────────────────────────────────────

it('redacts sensitive keys from the fields array', function () {
    $model = TestModel::create(['name' => 'Secure']);

    $event = $this->service->logCreate($model, $this->resource, $this->request, [
        'name'           => 'Secure',
        'password'       => 'secret123',
        'api_token'      => 'tok_abc',
        'remember_token' => 'rem_xyz',
        'api_key'        => 'key_123',
    ]);

    expect($event->fields['name'])->toBe('Secure')
        ->and($event->fields['password'])->toBe('***REDACTED***')
        ->and($event->fields['api_token'])->toBe('***REDACTED***')
        ->and($event->fields['remember_token'])->toBe('***REDACTED***')
        ->and($event->fields['api_key'])->toBe('***REDACTED***');
});

it('redacts sensitive keys from original and changes data', function () {
    $model        = TestModel::create(['name' => 'Test']);
    $originalData = ['name' => 'Test', 'password' => 'old_password', 'token' => 'abc'];

    $model->name = 'Updated';
    $model->save();

    $event = $this->service->logUpdate($model, $this->resource, $this->request, [], $originalData);

    expect($event->original['password'])->toBe('***REDACTED***')
        ->and($event->original['token'])->toBe('***REDACTED***')
        ->and($event->original['name'])->toBe('Test');
});

it('redaction is case-insensitive on key names', function () {
    $model = TestModel::create(['name' => 'Test']);

    $event = $this->service->logCreate($model, $this->resource, $this->request, [
        'Password'    => 'abc',
        'API_KEY'     => 'xyz',
        'user_secret' => 'shhh',
    ]);

    expect($event->fields['Password'])->toBe('***REDACTED***')
        ->and($event->fields['API_KEY'])->toBe('***REDACTED***')
        ->and($event->fields['user_secret'])->toBe('***REDACTED***');
});

// ── Batch ID ─────────────────────────────────────────────────────────────────

it('getBatchId returns the same UUID across multiple calls', function () {
    $id1 = $this->service->getBatchId();
    $id2 = $this->service->getBatchId();

    expect($id1)->toBe($id2)
        ->and($id1)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('resetBatchId causes a new UUID to be generated', function () {
    $id1 = $this->service->getBatchId();
    $this->service->resetBatchId();
    $id2 = $this->service->getBatchId();

    expect($id1)->not->toBe($id2);
});

it('multiple logged actions share the same batch_id within one service instance', function () {
    $model = TestModel::create(['name' => 'Test']);

    $event1 = $this->service->logCreate($model, $this->resource, $this->request);
    $event2 = $this->service->logDelete($model, $this->resource, $this->request);

    expect($event1->batch_id)->toBe($event2->batch_id);
});

// ── User resolution ──────────────────────────────────────────────────────────

it('resolves user_id from the authenticated user', function () {
    $user     = new User();
    $user->id = 42;
    $this->actingAs($user);

    $model = TestModel::create(['name' => 'Auth Test']);
    $event = $this->service->logCreate($model, $this->resource, $this->request);

    expect($event->user_id)->toBe(42);
});

it('resolves user_id to null when no user is authenticated and no system_user_id is set', function () {
    $model = TestModel::create(['name' => 'No Auth']);
    $event = $this->service->logCreate($model, $this->resource, $this->request);

    expect($event->user_id)->toBeNull();
});

it('resolves user_id to system_user_id when no user is authenticated', function () {
    config()->set('nadota.action_events.system_user_id', 999);

    $model = TestModel::create(['name' => 'System Action']);
    $event = $this->service->logCreate($model, $this->resource, $this->request);

    expect($event->user_id)->toBe(999);
});

// ── Queue mode ───────────────────────────────────────────────────────────────

it('dispatches LogActionEvent job when queue mode is enabled', function () {
    Queue::fake();
    config()->set('nadota.action_events.queue', true);

    $model = TestModel::create(['name' => 'Queued']);
    $this->service->logCreate($model, $this->resource, $this->request);

    Queue::assertPushed(LogActionEvent::class);
});

it('does not persist to DB when queue mode is enabled', function () {
    Queue::fake();
    config()->set('nadota.action_events.queue', true);

    $model = TestModel::create(['name' => 'Queued']);
    $this->service->logCreate($model, $this->resource, $this->request);

    $this->assertDatabaseCount('action_events', 0);
});

it('returns unsaved ActionEvent with status=running when queue mode is enabled', function () {
    Queue::fake();
    config()->set('nadota.action_events.queue', true);

    $model = TestModel::create(['name' => 'Queued']);
    $event = $this->service->logCreate($model, $this->resource, $this->request);

    expect($event->status)->toBe('running')
        ->and($event->exists)->toBeFalse();
});

it('dispatches job on the configured queue name', function () {
    Queue::fake();
    config()->set('nadota.action_events.queue', true);
    config()->set('nadota.action_events.queue_name', 'audit');

    $model = TestModel::create(['name' => 'Queued']);
    $this->service->logCreate($model, $this->resource, $this->request);

    Queue::assertPushedOn('audit', LogActionEvent::class);
});

// ── Events ───────────────────────────────────────────────────────────────────

it('dispatches ActionLogged event when dispatch_events is enabled', function () {
    Event::fake();
    config()->set('nadota.action_events.dispatch_events', true);

    $model = TestModel::create(['name' => 'Event Test']);
    $this->service->logCreate($model, $this->resource, $this->request);

    Event::assertDispatched(ActionLogged::class, fn ($e) => $e->action === 'create');
});

it('does not dispatch ActionLogged event when dispatch_events is disabled', function () {
    Event::fake();
    config()->set('nadota.action_events.dispatch_events', false);

    $model = TestModel::create(['name' => 'No Event']);
    $this->service->logCreate($model, $this->resource, $this->request);

    Event::assertNotDispatched(ActionLogged::class);
});

// ── Error resilience ─────────────────────────────────────────────────────────

it('does not throw when the action_events table is unavailable', function () {
    // SQLite DDL inside a transaction is rolled back after the test, so this
    // temporarily makes the table unavailable only for this test.
    Schema::drop('action_events');

    $model = TestModel::create(['name' => 'Resilient']);

    expect(fn () => $this->service->logCreate($model, $this->resource, $this->request))
        ->not->toThrow(\Exception::class);
});

// ── record() context-free entry point ────────────────────────────────────────

it('record persists a finished event without an HTTP request or resource', function () {
    $model = TestModel::create(['name' => 'FromJob']);

    $event = $this->service->record(
        'roster:import',
        $model,
        changes: ['name' => 'FromJob'],
        original: ['name' => 'Old'],
        fields: ['source' => 'nightly-job']
    );

    expect($event->exists)->toBeTrue()
        ->and($event->name)->toBe('roster:import')
        ->and($event->status)->toBe('finished')
        ->and($event->model_type)->toBe(TestModel::class)
        ->and($event->model_id)->toBe($model->id)
        ->and($event->changes)->toMatchArray(['name' => 'FromJob'])
        ->and($event->original)->toMatchArray(['name' => 'Old'])
        ->and($event->fields)->toMatchArray(['source' => 'nightly-job']);
});

it('record defaults actionable_type to the model class when none is given', function () {
    $model = TestModel::create(['name' => 'NoOrigin']);

    $event = $this->service->record('update', $model);

    expect($event->actionable_type)->toBe(TestModel::class);
});

it('record uses the explicit actionable_type when provided', function () {
    $model = TestModel::create(['name' => 'WithOrigin']);

    $event = $this->service->record('update', $model, actionableType: 'App\\Console\\Commands\\SyncRoster');

    expect($event->actionable_type)->toBe('App\\Console\\Commands\\SyncRoster');
});

it('record resolves user from system_user_id when no user is authenticated', function () {
    config()->set('nadota.action_events.system_user_id', 99);

    $model = TestModel::create(['name' => 'SystemAction']);

    $event = $this->service->record('cleanup', $model);

    expect($event->user_id)->toBe(99);
});

it('record redacts sensitive keys from changes', function () {
    $model = TestModel::create(['name' => 'Secure']);

    $event = $this->service->record('update', $model, changes: ['password' => 'plaintext', 'name' => 'ok']);

    expect($event->changes['password'])->toBe('***REDACTED***')
        ->and($event->changes['name'])->toBe('ok');
});
