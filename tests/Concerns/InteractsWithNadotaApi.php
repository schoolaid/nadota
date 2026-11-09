<?php

namespace SchoolAid\Nadota\Tests\Concerns;

use Illuminate\Foundation\Auth\User;
use ReflectionProperty;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Providers\RouteServiceProvider;
use SchoolAid\Nadota\Providers\ServiceBindingServiceProvider;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Resources\CourseResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\LabelResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\NoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\SchoolResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentProfileResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;
use SchoolAid\Nadota\Tests\Mocks\MockResourceAuthorizationService;

/**
 * Boots the package the way an application would (service bindings, routes,
 * registered resources) so tests can hit the real HTTP endpoints with
 * $this->getJson('/nadota-api/student/resource') and friends.
 *
 * Usage (Pest):
 *
 *     uses(InteractsWithNadotaApi::class);
 *     beforeEach(fn () => $this->bootNadotaApi());
 *
 * Authorization stays mocked to "always allow" unless a test calls
 * $this->useRealAuthorization() and registers policies itself.
 */
trait InteractsWithNadotaApi
{
    /** @var array<class-string> */
    protected array $fixtureResources = [
        SchoolResource::class,
        GradeResource::class,
        StudentResource::class,
        StudentProfileResource::class,
        CourseResource::class,
        NoteResource::class,
        LabelResource::class,
    ];

    /**
     * @param array<class-string>|null $resources Resource classes to register; defaults to the whole fixture domain.
     */
    protected function bootNadotaApi(?array $resources = null): void
    {
        FixtureSchema::create();

        (new ServiceBindingServiceProvider($this->app))->register();
        $this->app->register(RouteServiceProvider::class, true);
        $this->app['router']->getRoutes()->refreshNameLookups();

        // ServiceBindingServiceProvider binds the real authorization service; keep tests on the mock.
        $this->app->singleton(ResourceAuthorizationInterface::class, MockResourceAuthorizationService::class);

        static::resetResourceManager();
        foreach ($resources ?? $this->fixtureResources as $resourceClass) {
            ResourceManager::registerResourceClass($resourceClass);
        }

        $this->actingAsNadotaUser();
    }

    protected function useRealAuthorization(): void
    {
        $this->app->singleton(
            ResourceAuthorizationInterface::class,
            \SchoolAid\Nadota\Http\Services\ResourceAuthorizationService::class
        );
    }

    protected function actingAsNadotaUser(int $id = 1): User
    {
        $user = new User();
        $user->id = $id;
        $this->actingAs($user);

        return $user;
    }

    protected function apiUrl(string $resourceKey, string $path = ''): string
    {
        return '/nadota-api/' . $resourceKey . '/resource' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    public static function resetResourceManager(): void
    {
        $property = new ReflectionProperty(ResourceManager::class, 'resources');
        $property->setValue(null, null);
    }
}
