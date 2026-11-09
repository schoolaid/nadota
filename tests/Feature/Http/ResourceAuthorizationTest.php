<?php

use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Policies\ConfigurableAbilityPolicy;

/**
 * Authorization and resource resolution across every CRUD / index route
 * served by ResourceController and ResourceIndexController, with the real
 * ResourceAuthorizationService and a policy.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi();

    $this->active = Student::create(['name' => 'Active', 'password' => 'hash']);
    $this->trashed = Student::create(['name' => 'Trashed']);
    $this->trashed->delete();
});

/**
 * [method, path, ability checked]. {active} / {trashed} are replaced with the ids of
 * the records created in beforeEach.
 */
function resourceAuthorizationTestRoutes(): array
{
    return [
        'index' => ['GET', '', 'viewAny'],
        'config' => ['GET', 'config', 'viewAny'],
        'info' => ['GET', 'info', 'viewAny'],
        'fields' => ['GET', 'fields', 'viewAny'],
        'filters' => ['GET', 'filters', 'viewAny'],
        'data' => ['GET', 'data?fields=id', 'viewAny'],
        'create' => ['GET', 'create', 'create'],
        'store' => ['POST', '', 'create'],
        'show' => ['GET', '{active}', 'view'],
        'show as update' => ['GET', '{active}?action=update', 'update'],
        'edit' => ['GET', '{active}/edit', 'update'],
        'update (PUT)' => ['PUT', '{active}', 'update'],
        'update (PATCH)' => ['PATCH', '{active}', 'update'],
        'destroy' => ['DELETE', '{active}', 'delete'],
        'force delete' => ['DELETE', '{active}/force', 'forceDelete'],
        'restore' => ['POST', '{trashed}/restore', 'restore'],
        'permissions' => ['GET', '{active}/permissions', 'view'],
    ];
}

function resourceAuthorizationTestPath(string $path, Student $active, Student $trashed): string
{
    return str_replace(['{active}', '{trashed}'], [$active->id, $trashed->id], $path);
}

describe('with a policy', function () {
    beforeEach(function () {
        $this->useRealAuthorization();
        Gate::policy(Student::class, ConfigurableAbilityPolicy::class);
    });

    it('answers 403 when the policy denies the ability the route checks', function (string $method, string $path, string $ability) {
        config(['nadota-tests.denied_abilities' => [$ability]]);

        $this->json($method, $this->apiUrl('student', resourceAuthorizationTestPath($path, $this->active, $this->trashed)), [
            'name' => 'Changed', 'password' => 'secret',
        ])->assertForbidden();

        expect(Student::withTrashed()->count())->toBe(2)
            ->and($this->active->fresh()->name)->toBe('Active')
            ->and($this->active->fresh()->trashed())->toBeFalse()
            ->and(Student::withTrashed()->find($this->trashed->id)->trashed())->toBeTrue();
    })->with(resourceAuthorizationTestRoutes());

    it('lets the request through when the policy allows every ability', function (string $method, string $path) {
        $response = $this->json($method, $this->apiUrl('student', resourceAuthorizationTestPath($path, $this->active, $this->trashed)), [
            'name' => 'Changed', 'password' => 'secret',
        ]);

        expect($response->status())->toBeIn([200, 201]);
    })->with(resourceAuthorizationTestRoutes());

    it('checks authorization before validating the payload', function (string $method, string $path, string $ability) {
        config(['nadota-tests.denied_abilities' => [$ability]]);

        $this->json($method, $this->apiUrl('student', resourceAuthorizationTestPath($path, $this->active, $this->trashed)), ['email' => 'invalid'])
            ->assertForbidden();
    })->with([
        'store' => ['POST', '', 'create'],
        'update' => ['PUT', '{active}', 'update'],
    ]);

    it('reports canCreate as false in info and config when create is denied', function () {
        config(['nadota-tests.denied_abilities' => ['create']]);

        $this->getJson($this->apiUrl('student', 'info'))->assertOk()->assertJsonPath('data.canCreate', false);
        $this->getJson($this->apiUrl('student', 'config'))->assertOk()->assertJsonPath('resource.canCreate', false);
    });

    it('reflects the denied abilities in every index row', function () {
        config(['nadota-tests.denied_abilities' => ['update', 'delete', 'attach']]);

        $permissions = $this->getJson($this->apiUrl('student'))->assertOk()->json('data.0.permissions');

        expect($permissions)->toMatchArray(['view' => true, 'update' => false, 'delete' => false, 'attach' => false, 'detach' => true])
            ->and($permissions['fields']['courses'])->toBe(['attach' => false, 'detach' => true]);
    });

    it('checks that the record exists before asking the policy', function () {
        config(['nadota-tests.denied_abilities' => ['view']]);

        $this->getJson($this->apiUrl('student', 999))->assertNotFound();
        $this->getJson($this->apiUrl('student', $this->active->id))->assertForbidden();
    });

    it('denies a guest because the policy needs a user', function () {
        $this->app['auth']->forgetGuards();

        $this->getJson($this->apiUrl('student'))->assertForbidden();
    });
});

it('allows an ability the policy does not define (permissive fallback)', function () {
    $this->useRealAuthorization();
    $policy = new class {
        public function viewAny($user): bool
        {
            return true;
        }
    };
    $this->app->instance(get_class($policy), $policy);
    Gate::policy(Student::class, get_class($policy));

    $this->deleteJson($this->apiUrl('student', $this->active->id))->assertOk();

    expect($this->active->fresh()->trashed())->toBeTrue();
});

it('allows everything when the model has no policy', function () {
    $this->useRealAuthorization();

    $this->getJson($this->apiUrl('student'))->assertOk();
    $this->deleteJson($this->apiUrl('student', $this->active->id))->assertOk();
});

it('answers 404 for an unknown resource key', function (string $method, string $path) {
    $this->json($method, $this->apiUrl('unknown-thing', resourceAuthorizationTestPath($path, $this->active, $this->trashed)), [
        'name' => 'Changed',
    ])->assertNotFound();

    expect($this->active->fresh()->name)->toBe('Active');
})->with(resourceAuthorizationTestRoutes());

it('resolves keys exactly, so a plural or class-like key is unknown', function (string $key) {
    $this->getJson($this->apiUrl($key))->assertNotFound();
})->with(['students', 'Student', 'student-resource', 'StudentResource']);
