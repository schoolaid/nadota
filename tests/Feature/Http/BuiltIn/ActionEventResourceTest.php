<?php

use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Resources\ActionEventResource;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

/*
 * The package registers ActionEventResource itself (ResourceServiceProvider)
 * whenever action_events.enabled is true. It is hidden from the menu but its
 * whole CRUD surface is routable under /nadota-api/action-event/resource.
 *
 * Events are created with a null user_id: the BelongsTo "user" field would
 * otherwise query a users table the test schema does not have.
 */

uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi([StudentResource::class, ActionEventResource::class]);
    $this->useRealAuthorization(); // no ActionEvent policy registered, as in a fresh install

    $this->event = ActionEvent::create([
        'user_id' => null,
        'name' => 'update',
        'actionable_type' => StudentResource::class,
        'target_type' => Student::class,
        'model_type' => Student::class,
        'model_id' => 99,
        'fields' => ['email' => 'secret@example.com'],
        'original' => ['email' => 'old@example.com'],
        'changes' => ['email' => 'secret@example.com'],
    ]);
});

it('is routable under the action-event key once the app grants it a policy', function () {
    Gate::policy(ActionEvent::class, ActionEventResourceAllowAllPolicy::class);

    $this->getJson('/nadota-api/action-event/resource/info')
        ->assertOk()
        ->assertJsonPath('data.key', 'action-event')
        ->assertJsonPath('data.title', 'Action Events');
});

it('does not list the global audit log to a user without an ActionEvent policy', function () {
    $this->getJson('/nadota-api/action-event/resource')->assertForbidden();
});

it('does not let a user without an ActionEvent policy delete an audit event', function () {
    $this->deleteJson('/nadota-api/action-event/resource/' . $this->event->id)->assertForbidden();

    expect(ActionEvent::whereKey($this->event->id)->exists())->toBeTrue();
});

it('does not let a user without an ActionEvent policy update an audit event', function () {
    $this->putJson('/nadota-api/action-event/resource/' . $this->event->id, ['name' => 'tampered'])->assertForbidden();
});

it('searches the audit log by the columns it declares in $search', function () {
    expect((new ActionEventResource())->isSearchable())->toBeTrue()
        ->and((new ActionEventResource())->getSearchableAttributes())->toContain('name', 'batch_id');
})->skip('ISSUE AUD-029: ActionEventResource declares static $search (and $softDelete, $with), which the base Resource never reads');

it('lists the audit log when the app policy allows it, but never lets it be written', function () {
    Gate::policy(ActionEvent::class, ActionEventResourceAllowAllPolicy::class);

    $this->getJson('/nadota-api/action-event/resource')->assertOk()->assertJsonPath('data.0.id', $this->event->id);
    $this->putJson('/nadota-api/action-event/resource/' . $this->event->id, ['name' => 'tampered'])->assertForbidden();
    $this->deleteJson('/nadota-api/action-event/resource/' . $this->event->id)->assertForbidden();

    expect(ActionEvent::whereKey($this->event->id)->value('name'))->toBe('update');
});

/** A policy that allows reading and writing, to show the resource refuses writes on its own. */
class ActionEventResourceAllowAllPolicy
{
    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, $event): bool
    {
        return true;
    }

    public function update($user, $event): bool
    {
        return true;
    }

    public function delete($user, $event): bool
    {
        return true;
    }
}
