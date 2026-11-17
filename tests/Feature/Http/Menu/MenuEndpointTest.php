<?php

use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Menu\MenuItem;
use SchoolAid\Nadota\Menu\MenuSectionDefinition;
use SchoolAid\Nadota\NadotaService;
use SchoolAid\Nadota\Resources\ActionEventResource;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Menu\DenyViewAnyPolicy;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuAlphaResource;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuBetaResource;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuState;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;

uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    MenuState::reset();
    $this->bootNadotaApi();
});

afterEach(fn () => MenuState::reset());

it('answers GET /menu with every fixture resource, wrapped in a data key', function () {
    $response = $this->getJson('/nadota-api/menu')->assertOk();

    expect(array_keys($response->json()))->toBe(['data'])
        ->and(array_column($response->json('data'), 'key'))
        ->toBe(['school', 'grade', 'student', 'student-profile', 'course', 'note', 'label']);
});

it('uses singular route keys and the default titles for the items', function () {
    $this->getJson('/nadota-api/menu')
        ->assertJsonPath('data.2.key', 'student')
        ->assertJsonPath('data.2.label', 'Student Resources') // CORE-006
        ->assertJsonPath('data.2.apiUrl', 'nadota-api/student/resource')
        ->assertJsonPath('data.2.frontendUrl', 'resources/student')
        ->assertJsonPath('data.2.isResource', true);
});

it('hides a resource whose policy refuses viewAny and keeps the ones without a policy', function () {
    $this->useRealAuthorization();
    Gate::policy(Grade::class, DenyViewAnyPolicy::class);

    $keys = array_column($this->getJson('/nadota-api/menu')->assertOk()->json('data'), 'key');

    expect($keys)->not->toContain('grade')
        ->and($keys)->toContain('school', 'student', 'course');
});

it('leaves the built-in ActionEventResource out of the menu while keeping it routable', function () {
    $this->bootNadotaApi([MenuAlphaResource::class, ActionEventResource::class]);

    expect(array_column($this->getJson('/nadota-api/menu')->json('data'), 'key'))->toBe(['menu-alpha']);
    // Routable (403, not 404): closed until the app registers an ActionEvent policy (AUD-028)
    $this->getJson('/nadota-api/action-event/resource/info')->assertForbidden();
});

it('hands the HTTP request to displayInMenu()', function () {
    $this->bootNadotaApi([MenuAlphaResource::class, MenuBetaResource::class]);
    MenuBetaResource::configureMenu(['inMenu' => fn (NadotaRequest $request) => $request->query('show') === 'beta']);

    expect(array_column($this->getJson('/nadota-api/menu')->json('data'), 'key'))->toBe(['menu-alpha'])
        ->and(array_column($this->getJson('/nadota-api/menu?show=beta')->json('data'), 'key'))->toBe(['menu-alpha', 'menu-beta']);
});

it('serves configured sections and custom items through the endpoint', function () {
    $this->bootNadotaApi([MenuAlphaResource::class, MenuBetaResource::class]);
    NadotaService::configureMenuSections(fn () => [MenuSectionDefinition::make('content', 'Content')->icon('Folder')->order(5)]);
    NadotaService::addMenuItems(fn () => [MenuItem::make('Docs', 'docs', 'Book', null, '/docs', 'content')]);
    MenuAlphaResource::configureMenu(['section' => 'content']);

    $this->getJson('/nadota-api/menu')
        ->assertOk()
        ->assertJsonPath('data.0.key', 'menu-beta')
        ->assertJsonPath('data.1.isSection', true)
        ->assertJsonPath('data.1.title', 'Content')
        ->assertJsonPath('data.1.icon', 'Folder')
        ->assertJsonPath('data.1.children.0.key', 'menu-alpha')
        ->assertJsonPath('data.1.children.1.key', 'docs')
        ->assertJsonPath('data.1.children.1.frontendUrl', '/docs');
});

it('returns the prepareMenuUsing() payload as the response body', function () {
    NadotaService::prepareMenuUsing(fn () => response()->json(['menu' => 'custom']));

    $this->getJson('/nadota-api/menu')->assertOk()->assertExactJson(['menu' => 'custom']);
});

it('does not serve the menu to guests under the default middleware stack (AUD-012)', function () {
    // middlewares = ['api', 'auth'] by default: a guest is turned away before any resource runs.
    // The suite's TestCase sets ['api']; load the package default and register the routes with it.
    config()->set('nadota.middlewares', (require dirname(__DIR__, 4) . '/config/nadota.php')['middlewares']);
    $this->bootNadotaApi();
    $this->app['auth']->forgetGuards();

    expect(auth()->check())->toBeFalse();

    $this->useRealAuthorization();
    $this->getJson('/nadota-api/menu')->assertUnauthorized();
});
