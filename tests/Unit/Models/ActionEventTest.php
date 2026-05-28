<?php

use Illuminate\Support\Str;
use SchoolAid\Nadota\Models\ActionEvent;

function makeActionEvent(array $overrides = []): ActionEvent
{
    return ActionEvent::create(array_merge([
        'batch_id'        => (string) Str::uuid(),
        'name'            => 'update',
        'actionable_type' => 'TestResource',
        'actionable_id'   => 0,
        'target_type'     => 'TestModel',
        'target_id'       => 1,
        'model_type'      => 'TestModel',
        'model_id'        => 1,
        'fields'          => [],
        'status'          => 'finished',
    ], $overrides));
}

// ── getChangedFields ────────────────────────────────────────────────────────

it('getChangedFields returns empty array when original is null', function () {
    $event = new ActionEvent(['original' => null, 'changes' => ['name' => 'New']]);
    expect($event->getChangedFields())->toBe([]);
});

it('getChangedFields returns empty array when changes is null', function () {
    $event = new ActionEvent(['original' => ['name' => 'Old'], 'changes' => null]);
    expect($event->getChangedFields())->toBe([]);
});

it('getChangedFields returns only fields that differ between original and changes', function () {
    $event = makeActionEvent([
        'original' => ['name' => 'Old', 'email' => 'same@test.com'],
        'changes'  => ['name' => 'New', 'email' => 'same@test.com'],
    ]);

    $changed = $event->getChangedFields();

    expect($changed)->toHaveKey('name')
        ->and($changed['name'])->toMatchArray(['old' => 'Old', 'new' => 'New'])
        ->and($changed)->not->toHaveKey('email');
});

it('getChangedFields includes fields present in changes but not in original', function () {
    $event = makeActionEvent([
        'original' => ['name' => 'Old'],
        'changes'  => ['name' => 'Old', 'email' => 'new@test.com'],
    ]);

    $changed = $event->getChangedFields();

    expect($changed)->toHaveKey('email')
        ->and($changed['email'])->toMatchArray(['old' => null, 'new' => 'new@test.com']);
});

// ── Status methods ──────────────────────────────────────────────────────────

it('markAsFinished updates status to finished in DB', function () {
    $event = makeActionEvent(['status' => 'running']);

    $event->markAsFinished();

    expect($event->fresh()->status)->toBe('finished');
});

it('markAsFailed updates status and exception in DB', function () {
    $event = makeActionEvent(['status' => 'running']);

    $event->markAsFailed('Something went wrong');

    $fresh = $event->fresh();
    expect($fresh->status)->toBe('failed')
        ->and($fresh->exception)->toBe('Something went wrong');
});

it('markAsFailed accepts an Exception object', function () {
    $event = makeActionEvent(['status' => 'running']);

    $event->markAsFailed(new \RuntimeException('DB error'));

    expect($event->fresh()->exception)->toBe('DB error');
});

it('isRunning returns true only for running status', function () {
    expect(makeActionEvent(['status' => 'running'])->isRunning())->toBeTrue();
    expect(makeActionEvent(['status' => 'finished'])->isRunning())->toBeFalse();
});

it('isFinished returns true only for finished status', function () {
    expect(makeActionEvent(['status' => 'finished'])->isFinished())->toBeTrue();
    expect(makeActionEvent(['status' => 'running'])->isFinished())->toBeFalse();
});

it('isFailed returns true only for failed status', function () {
    expect(makeActionEvent(['status' => 'failed'])->isFailed())->toBeTrue();
    expect(makeActionEvent(['status' => 'finished'])->isFailed())->toBeFalse();
});

// ── getActionDisplayName ────────────────────────────────────────────────────

it('getActionDisplayName formats snake_case action names', function () {
    $event = new ActionEvent(['name' => 'force_delete']);
    expect($event->getActionDisplayName())->toBe('Force Delete');
});

it('getActionDisplayName formats single word names', function () {
    $event = new ActionEvent(['name' => 'create']);
    expect($event->getActionDisplayName())->toBe('Create');
});

// ── Scopes ──────────────────────────────────────────────────────────────────

it('byUser scope filters by user_id', function () {
    makeActionEvent(['user_id' => 1]);
    makeActionEvent(['user_id' => 2]);

    expect(ActionEvent::byUser(1)->count())->toBe(1)
        ->and(ActionEvent::byUser(1)->first()->user_id)->toBe(1);
});

it('byStatus scope filters by status', function () {
    makeActionEvent(['status' => 'finished']);
    makeActionEvent(['status' => 'failed']);
    makeActionEvent(['status' => 'running']);

    expect(ActionEvent::byStatus('failed')->count())->toBe(1);
});

it('byBatch scope filters by batch_id', function () {
    $batchId = (string) Str::uuid();
    makeActionEvent(['batch_id' => $batchId]);
    makeActionEvent(); // different batch_id

    expect(ActionEvent::byBatch($batchId)->count())->toBe(1);
});

it('byActionName scope filters by name', function () {
    makeActionEvent(['name' => 'create']);
    makeActionEvent(['name' => 'delete']);

    expect(ActionEvent::byActionName('create')->count())->toBe(1);
});

it('byActionableType scope filters by actionable_type', function () {
    makeActionEvent(['actionable_type' => 'ResourceA']);
    makeActionEvent(['actionable_type' => 'ResourceB']);

    expect(ActionEvent::byActionableType('ResourceA')->count())->toBe(1);
});

it('recent scope orders by created_at descending', function () {
    $first = makeActionEvent(['name' => 'create']);
    $first->forceFill(['created_at' => now()->subSeconds(5)])->save();

    $second = makeActionEvent(['name' => 'update']);

    $ids = ActionEvent::recent()->pluck('id')->toArray();

    expect($ids[0])->toBe($second->id)
        ->and($ids[1])->toBe($first->id);
});

// ── Boot: auto batch_id ─────────────────────────────────────────────────────

it('auto-generates batch_id when not provided', function () {
    $event = ActionEvent::create([
        'name'            => 'create',
        'actionable_type' => 'Resource',
        'actionable_id'   => 0,
        'target_type'     => 'Model',
        'target_id'       => 1,
        'model_type'      => 'Model',
        'model_id'        => 1,
        'fields'          => [],
        'status'          => 'finished',
    ]);

    expect($event->batch_id)->not->toBeEmpty()
        ->and($event->batch_id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});
