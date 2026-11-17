<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use SchoolAid\Nadota\NadotaService;
use SchoolAid\Nadota\NadotaServiceProvider;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Resources\ActionEventResource;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuState;

/*
 * Boots the real NadotaServiceProvider inside the Testbench app. TestCase does
 * not load it and pre-fills nadota.* keys, so each test first replaces the
 * whole nadota config with what an application would have.
 *
 * path_resources points at tests/Fixtures/Menu: it exists, and its classes do
 * not mirror their path, so the scan registers nothing (see AUD-032).
 */

beforeEach(function () {
    MenuState::reset();
    MenuState::resetRegistry();
});

afterEach(function () {
    MenuState::reset();
    MenuState::resetRegistry();
});

/** Replace the nadota config with $config (an app that did not publish the file) and boot the package. */
function audBootNadota(array $config = []): void
{
    config()->set('nadota', array_merge(['path_resources' => 'tests/Fixtures/Menu'], $config));
    app()->register(NadotaServiceProvider::class, true);
    app('router')->getRoutes()->refreshNameLookups();
}

function audPackageMigrationsPath(): string
{
    return realpath(dirname(__DIR__, 3) . '/database/migrations');
}

it('merges the package defaults under the keys the application did not set', function () {
    audBootNadota(['export' => ['formats' => ['csv']]]);

    expect(config('nadota.path_resources'))->toBe('tests/Fixtures/Menu')
        ->and(config('nadota.export.formats'))->toBe(['csv'])   // the app's block wins as a whole (shallow merge)
        ->and(config('nadota.export.default_format'))->toBeNull()
        ->and(config('nadota.api.prefix'))->toBe('nadota-api')
        ->and(config('nadota.middlewares'))->toBe(['api', 'auth']);
});

it('mounts the API routes under nadota-api', function () {
    audBootNadota();

    expect(app('router')->getRoutes()->getByName('nadota.api.menu')->uri())->toBe('nadota-api/menu')
        ->and(app('router')->getRoutes()->getByName('nadota.api.global.options')->uri())->toBe('nadota-api/options');
});

it('applies the default api middleware to the protected routes when the config is not published', function () {
    audBootNadota();

    expect(app('router')->getRoutes()->getByName('nadota.api.menu')->middleware())->toContain('api');
});

it('loads the action events migrations and registers ActionEventResource while tracking is enabled', function () {
    audBootNadota();

    $paths = array_map('realpath', app('migrator')->paths());

    expect($paths)->toContain(audPackageMigrationsPath())
        ->and(ResourceManager::exists('action-event'))->toBeTrue();
});

it('skips the migrations and ActionEventResource when tracking is disabled', function () {
    audBootNadota(['action_events' => ['enabled' => false]]);

    $paths = array_map('realpath', app('migrator')->paths());

    expect($paths)->not->toContain(audPackageMigrationsPath())
        ->and(ResourceManager::exists('action-event'))->toBeFalse();
});

it('removes the resources excluded before the package boots', function () {
    NadotaService::excludeResources([ActionEventResource::class]);

    audBootNadota();

    expect(ResourceManager::exists('action-event'))->toBeFalse();
});

it('only applies exclusions registered before the package boots', function () {
    // Pins AUD-036: exclusions are applied once, inside ResourceServiceProvider::boot().
    audBootNadota();

    NadotaService::excludeResources([ActionEventResource::class]);

    expect(ResourceManager::exists('action-event'))->toBeTrue();
});

it('publishes the config file under the nadota-config tag', function () {
    audBootNadota();

    $paths = ServiceProvider::pathsToPublish(null, 'nadota-config');

    expect(array_map('realpath', array_keys($paths)))->toBe([realpath(dirname(__DIR__, 3) . '/config/nadota.php')]);
});

it('publishes the config through the documented --provider=NadotaServiceProvider --tag=nadota-config command', function () {
    audBootNadota();

    expect(ServiceProvider::pathsToPublish(NadotaServiceProvider::class, 'nadota-config'))->not->toBeEmpty();
})->skip('ISSUE AUD-034: publishes() is called from ResourceServiceProvider, so --provider=NadotaServiceProvider finds nothing');

it('ships the provider stub it offers under the nadota-provider tag', function () {
    audBootNadota();

    $sources = array_keys(ServiceProvider::pathsToPublish(null, 'nadota-provider'));

    expect($sources)->toHaveCount(1)
        ->and(file_exists($sources[0]))->toBeTrue();
})->skip('ISSUE AUD-034: Console/stubs/NadotaServiceProvider.stub does not exist in the package');

it('caches the discovered resource map under key_resources_cache in production', function () {
    config()->set('app.env', 'production');

    audBootNadota(['key_resources_cache' => 'aud_nadota_resources']);

    expect(Cache::has('aud_nadota_resources'))->toBeTrue();
})->skip('ISSUE AUD-033: the cache key is only checked, never written; production scans the directory twice per boot');

it('boots when the resources directory does not exist yet', function () {
    audBootNadota(['path_resources' => 'app/Nadota']); // does not exist under the package root

    expect(ResourceManager::exists('action-event'))->toBeTrue();
})->skip('ISSUE AUD-031: Finder::in() throws DirectoryNotFoundException, so a fresh install without app/Nadota cannot boot');
