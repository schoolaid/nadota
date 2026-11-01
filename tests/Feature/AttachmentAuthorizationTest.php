<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Http\Controllers\AttachmentController;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceAuthorizationService;
use SchoolAid\Nadota\Tests\Models\Tag;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Policies\AttachMethodTestModelPolicy;
use SchoolAid\Nadota\Tests\Policies\BeforeHookTestModelPolicy;
use SchoolAid\Nadota\Tests\Policies\FieldSpecificTestModelPolicy;
use SchoolAid\Nadota\Tests\Policies\UpdateOnlyTestModelPolicy;
use SchoolAid\Nadota\Tests\Resources\AttachableTestResource;

/**
 * D32: attach / detach / sync / attachable used to be authorized as `true`
 * whenever the parent's policy had no `attach`/`detach` method, so any panel
 * session could e.g. assign itself roles. They now fall back to the parent's
 * `update` ability (through the Gate, so `before()` runs).
 */
beforeEach(function () {
    app()->bind(ResourceAuthorizationInterface::class, ResourceAuthorizationService::class);
    UpdateOnlyTestModelPolicy::$canUpdate = true;

    $this->user = new User();
    $this->user->id = 1;

    $this->parent = TestModel::create(['name' => 'Parent']);
    $this->tag = Tag::create(['name' => 'Admin']);
});

/**
 * Calls one attachment endpoint on the `simpleTags` relation of the parent.
 */
function callAttachmentEndpoint(string $endpoint, TestModel $parent, Tag $tag, User $user): JsonResponse
{
    $request = new NadotaRequest();
    $request->setResource(new AttachableTestResource());
    $request->setUserResolver(fn () => $user);
    $request->merge(['items' => [$tag->id]]);

    if ($endpoint === 'detach') {
        $parent->simpleTags()->attach($tag->id);
    }

    $controller = app(AttachmentController::class);

    return $controller->{$endpoint}($request, 'test', (string) $parent->id, 'simpleTags');
}

dataset('attachment endpoints', ['attach', 'detach', 'sync', 'attachable']);

it('denies attaching when the policy has no attach method and update is denied', function (string $endpoint) {
    Gate::policy(TestModel::class, UpdateOnlyTestModelPolicy::class);
    UpdateOnlyTestModelPolicy::$canUpdate = false;

    $response = callAttachmentEndpoint($endpoint, $this->parent, $this->tag, $this->user);

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getData(true))->toBe(['success' => false, 'message' => 'Unauthorized']);
})->with('attachment endpoints');

it('allows attaching when the policy has no attach method and update is allowed', function (string $endpoint) {
    Gate::policy(TestModel::class, UpdateOnlyTestModelPolicy::class);

    $response = callAttachmentEndpoint($endpoint, $this->parent, $this->tag, $this->user);

    expect($response->getStatusCode())->toBe(200);
})->with('attachment endpoints');

it('uses the policy attach method over update when it exists', function (string $endpoint) {
    Gate::policy(TestModel::class, AttachMethodTestModelPolicy::class);

    $response = callAttachmentEndpoint($endpoint, $this->parent, $this->tag, $this->user);

    expect($response->getStatusCode())->toBe(200);
})->with('attachment endpoints');

it('uses the field-specific method over the generic one', function (string $endpoint) {
    Gate::policy(TestModel::class, FieldSpecificTestModelPolicy::class);

    $response = callAttachmentEndpoint($endpoint, $this->parent, $this->tag, $this->user);

    expect($response->getStatusCode())->toBe(403);
})->with('attachment endpoints');

it('runs the policy before hook on the update fallback', function (string $endpoint) {
    Gate::policy(TestModel::class, BeforeHookTestModelPolicy::class);

    $response = callAttachmentEndpoint($endpoint, $this->parent, $this->tag, $this->user);

    expect($response->getStatusCode())->toBe(403);
})->with('attachment endpoints');

it('keeps allowing attach on a resource without policy', function (string $endpoint) {
    $response = callAttachmentEndpoint($endpoint, $this->parent, $this->tag, $this->user);

    expect($response->getStatusCode())->toBe(200);
})->with('attachment endpoints');

it('actually attaches nothing when the update fallback denies', function () {
    Gate::policy(TestModel::class, UpdateOnlyTestModelPolicy::class);
    UpdateOnlyTestModelPolicy::$canUpdate = false;

    callAttachmentEndpoint('attach', $this->parent, $this->tag, $this->user);
    callAttachmentEndpoint('sync', $this->parent, $this->tag, $this->user);

    expect($this->parent->simpleTags()->count())->toBe(0);
});

it('reports attach and detach permissions with the same fallback', function (bool $canUpdate) {
    Gate::policy(TestModel::class, UpdateOnlyTestModelPolicy::class);
    UpdateOnlyTestModelPolicy::$canUpdate = $canUpdate;

    $request = new NadotaRequest();
    $request->setUserResolver(fn () => $this->user);

    $permissions = (new AttachableTestResource())->getPermissionsForResource($request, $this->parent);

    expect($permissions['attach'])->toBe($canUpdate)
        ->and($permissions['detach'])->toBe($canUpdate)
        ->and($permissions['fields']['simpleTags'])->toBe(['attach' => $canUpdate, 'detach' => $canUpdate]);
})->with(['update denied' => false, 'update allowed' => true]);

it('leaves the other actions untouched when the policy lacks their method', function () {
    Gate::policy(TestModel::class, UpdateOnlyTestModelPolicy::class);
    UpdateOnlyTestModelPolicy::$canUpdate = false;

    $request = new NadotaRequest();
    $request->setUserResolver(fn () => $this->user);

    $resource = new AttachableTestResource();

    expect($resource->authorizedTo($request, 'view', $this->parent))->toBeTrue()
        ->and($resource->authorizedTo($request, 'delete', $this->parent))->toBeTrue()
        ->and($resource->authorizedTo($request, 'update', $this->parent))->toBeFalse();
});

it('hides the attach and detach buttons of a relation field with the same fallback', function (bool $canUpdate) {
    Gate::policy(TestModel::class, UpdateOnlyTestModelPolicy::class);
    UpdateOnlyTestModelPolicy::$canUpdate = $canUpdate;

    $request = new NadotaRequest();
    $request->setUserResolver(fn () => $this->user);

    $resource = new AttachableTestResource();
    $field = $resource->flattenFields($request)->first(fn ($f) => $f->key() === 'simpleTags');

    $parent = $this->parent;
    $props = (fn () => $this->getProps($request, $parent, $resource))->call($field);

    expect($props['canAttach'])->toBe($canUpdate)
        ->and($props['canDetach'])->toBe($canUpdate);
})->with(['update denied' => false, 'update allowed' => true]);
