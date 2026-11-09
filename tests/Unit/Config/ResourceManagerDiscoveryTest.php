<?php

use Illuminate\Support\Facades\File;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuAlphaResource;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuBetaResource;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuState;
use SchoolAid\Nadota\Tests\Models\TestModel;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * ResourceManager::registerResource() turns every *Resource.php path below
 * base_path($path) into a class name by stripping base_path() and upper-casing
 * the first letter. These tests build a throw-away application tree in the
 * system temp dir (app/Nadota/... with namespace App\Nadota\...) and point
 * base_path() at it.
 */

beforeEach(function () {
    MenuState::resetRegistry();
    $this->discoveryRoot = sys_get_temp_dir() . '/nadota-aud-discovery-' . bin2hex(random_bytes(4));
    File::makeDirectory($this->discoveryRoot . '/app/Nadota', 0755, true);
    $this->app->setBasePath($this->discoveryRoot);
});

afterEach(function () {
    MenuState::resetRegistry();
    File::deleteDirectory($this->discoveryRoot);
});

/**
 * Write app/<relative path>.php declaring $class (FQCN under App\) and load it,
 * as Composer would in an application.
 */
function audWriteDiscoverable(string $root, string $class, string $body): void
{
    $relative = str_replace('\\', '/', substr($class, strlen('App\\')));
    $path = "{$root}/app/{$relative}.php";
    $namespace = substr($class, 0, strrpos($class, '\\'));
    $short = substr($class, strrpos($class, '\\') + 1);

    File::ensureDirectoryExists(dirname($path));
    File::put($path, "<?php\n\nnamespace {$namespace};\n\nclass {$short} {$body}\n");

    if (! class_exists($class, false)) {
        require $path;
    }
}

function audResourceBody(?string $model = TestModel::class): string
{
    $property = $model ? "public string \$model = \\{$model}::class;" : 'public string $model;';

    return "extends \\SchoolAid\\Nadota\\Resource\n{\n    {$property}\n\n    public function fields(\\SchoolAid\\Nadota\\Http\\Requests\\NadotaRequest \$request): array\n    {\n        return [];\n    }\n}";
}

it('registers the *Resource.php classes whose namespace mirrors their path, keyed by uri', function () {
    audWriteDiscoverable($this->discoveryRoot, 'App\Nadota\AudWidgetResource', audResourceBody());
    audWriteDiscoverable($this->discoveryRoot, 'App\Nadota\Billing\AudInvoiceResource', audResourceBody());

    ResourceManager::registerResource('app/Nadota');

    expect(ResourceManager::getResources()->sortKeys()->all())->toBe([
        'aud-invoice' => ['class' => 'App\Nadota\Billing\AudInvoiceResource', 'model' => TestModel::class],
        'aud-widget' => ['class' => 'App\Nadota\AudWidgetResource', 'model' => TestModel::class],
    ]);
});

it('ignores files not named *Resource.php and classes that do not extend Resource', function () {
    audWriteDiscoverable($this->discoveryRoot, 'App\Nadota\AudHelper', audResourceBody());
    audWriteDiscoverable($this->discoveryRoot, 'App\Nadota\AudPlainResource', '{}');

    ResourceManager::registerResource('app/Nadota');

    expect(ResourceManager::getResources()->all())->toBe([]);
});

it('silently skips classes whose namespace does not mirror their path', function () {
    // Pins AUD-032: tests/Fixtures/Menu holds SchoolAid\Nadota\Tests\Fixtures\Menu\*Resource,
    // but the scan derives Tests\Fixtures\Menu\*Resource, which does not exist.
    $this->app->setBasePath(dirname(__DIR__, 3));

    ResourceManager::registerResource('tests/Fixtures/Menu');

    expect(ResourceManager::getResources()->all())->toBe([]);
});

it('refuses two discovered resources with the same uri key', function () {
    audWriteDiscoverable($this->discoveryRoot, 'App\Nadota\One\AudTwinResource', audResourceBody());
    audWriteDiscoverable($this->discoveryRoot, 'App\Nadota\Two\AudTwinResource', audResourceBody());

    expect(fn () => ResourceManager::registerResource('app/Nadota'))
        ->toThrow(Exception::class, 'Resource with uri key aud-twin already exists');
});

it('refuses a discovered resource without a model', function () {
    audWriteDiscoverable($this->discoveryRoot, 'App\Nadota\AudModellessResource', audResourceBody(null));

    expect(fn () => ResourceManager::registerResource('app/Nadota'))
        ->toThrow(Exception::class, 'Resource App\Nadota\AudModellessResource must have a model property');
});

it('registers a class directly once per key and refuses classes that are not resources', function () {
    ResourceManager::registerResourceClass(MenuAlphaResource::class);
    ResourceManager::registerResourceClass(MenuAlphaResource::class);

    expect(ResourceManager::getResources()->keys()->all())->toBe(['menu-alpha'])
        ->and(fn () => ResourceManager::registerResourceClass(TestModel::class))->toThrow(Exception::class, 'must extend Resource');
});

it('removes resources by class and answers 404 for unknown keys', function () {
    ResourceManager::registerResourceClass(MenuAlphaResource::class);
    ResourceManager::registerResourceClass(MenuBetaResource::class);

    ResourceManager::removeResourcesByClass([MenuAlphaResource::class]);

    expect(ResourceManager::exists('menu-alpha'))->toBeFalse()
        ->and(ResourceManager::getResourceByKey('menu-beta'))->toBe(MenuBetaResource::class)
        ->and(fn () => ResourceManager::getResourceByKey('menu-alpha'))->toThrow(NotFoundHttpException::class);
});

it('tolerates a resources directory that does not exist', function () {
    ResourceManager::registerResource('app/Missing');

    expect(ResourceManager::getResources()->all())->toBe([]);
})->skip('ISSUE AUD-031: Finder::in() throws DirectoryNotFoundException for a missing path_resources');

it('accepts an absolute resources path', function () {
    audWriteDiscoverable($this->discoveryRoot, 'App\Nadota\AudAbsoluteResource', audResourceBody());

    ResourceManager::registerResource($this->discoveryRoot . '/app/Nadota');

    expect(ResourceManager::exists('aud-absolute'))->toBeTrue();
})->skip('ISSUE AUD-031: base_path() is always prepended, so an absolute path_resources becomes <base>/<absolute> and does not exist');

it('keeps the resources registered before a directory scan', function () {
    ResourceManager::registerResourceClass(MenuAlphaResource::class);
    audWriteDiscoverable($this->discoveryRoot, 'App\Nadota\AudLateResource', audResourceBody());

    ResourceManager::registerResource('app/Nadota');

    expect(ResourceManager::exists('menu-alpha'))->toBeTrue()
        ->and(ResourceManager::exists('aud-late'))->toBeTrue();
})->skip('ISSUE AUD-032: registerResource() replaces the registry wholesale (static::$resources = collect(...))');
