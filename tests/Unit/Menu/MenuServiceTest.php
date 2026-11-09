<?php

use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Resources\Menu\MenuResource;
use SchoolAid\Nadota\Http\Services\MenuService;
use SchoolAid\Nadota\Menu\MenuItem;
use SchoolAid\Nadota\Menu\MenuSection;
use SchoolAid\Nadota\Menu\MenuSectionDefinition;
use SchoolAid\Nadota\NadotaService;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuAlphaResource;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuBetaResource;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuDeltaResource;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuGammaResource;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuState;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;

beforeEach(function () {
    MenuState::reset();
    MenuState::resetRegistry();
});

afterEach(function () {
    MenuState::reset();
    MenuState::resetRegistry();
});

/**
 * Register the given resource classes (in order) and return the built menu as plain arrays.
 *
 * @param array<class-string> $resources
 */
function audBuildMenu(array $resources = [], ?NadotaRequest $request = null): array
{
    foreach ($resources as $resource) {
        ResourceManager::registerResourceClass($resource);
    }

    $menu = (new MenuService())->build($request ?? new NadotaRequest());

    return json_decode(json_encode($menu->toArray($request ?? new NadotaRequest())), true);
}

it('lists every registered resource as a top-level resource item', function () {
    $menu = audBuildMenu([MenuAlphaResource::class]);

    expect($menu)->toBe([[
        'label' => 'Menu Alpha Resources',
        'key' => 'menu-alpha',
        'icon' => 'LayoutPanelTop',
        'apiUrl' => 'nadota-api/menu-alpha/resource',
        'frontendUrl' => 'resources/menu-alpha',
        'children' => [],
        'order' => 1,
        'isResource' => true,
    ]]);
});

it('takes label, icon and order from title(), displayIcon() and orderInMenu()', function () {
    MenuAlphaResource::configureMenu(['title' => 'Schools', 'icon' => 'School', 'order' => 4]);

    $item = audBuildMenu([MenuAlphaResource::class])[0];

    expect($item['label'])->toBe('Schools')
        ->and($item['icon'])->toBe('School')
        ->and($item['order'])->toBe(4);
});

it('builds the item urls from api.prefix and frontend.prefix', function () {
    config()->set('nadota.api.prefix', 'admin-api');
    config()->set('nadota.frontend.prefix', 'admin');

    $item = audBuildMenu([MenuAlphaResource::class])[0];

    expect($item['apiUrl'])->toBe('admin-api/menu-alpha/resource')
        ->and($item['frontendUrl'])->toBe('admin/menu-alpha');
});

it('leaves out the resources the user may not viewAny', function () {
    $this->app->instance(ResourceAuthorizationInterface::class, new class implements ResourceAuthorizationInterface {
        public $model;

        public function setModel($model): self
        {
            $this->model = $model;

            return $this;
        }

        public function authorizedTo(NadotaRequest $request, string $action, array $context = []): bool
        {
            return ! ($action === 'viewAny' && $this->model === Grade::class);
        }
    });

    $keys = array_column(audBuildMenu([MenuAlphaResource::class, MenuBetaResource::class]), 'key');

    expect($keys)->toBe(['menu-alpha']);
});

it('leaves out the resources whose displayInMenu() answers false', function () {
    MenuBetaResource::configureMenu(['inMenu' => false]);

    $keys = array_column(audBuildMenu([MenuAlphaResource::class, MenuBetaResource::class]), 'key');

    expect($keys)->toBe(['menu-alpha']);
});

it('sorts by order ascending and keeps registration order on ties', function () {
    MenuAlphaResource::configureMenu(['order' => 3]);
    MenuBetaResource::configureMenu(['order' => 1]);
    MenuGammaResource::configureMenu(['order' => 3]);
    MenuDeltaResource::configureMenu(['order' => 2]);

    $keys = array_column(audBuildMenu([MenuAlphaResource::class, MenuBetaResource::class, MenuGammaResource::class, MenuDeltaResource::class]), 'key');

    expect($keys)->toBe(['menu-beta', 'menu-delta', 'menu-alpha', 'menu-gamma']);
});

it('nests a resource under a fallback section labelled with the key, with the Boxes icon and order 2', function () {
    MenuAlphaResource::configureMenu(['section' => 'Content']);

    $menu = audBuildMenu([MenuAlphaResource::class, MenuBetaResource::class]);

    expect(array_map(fn ($entry) => $entry['key'] ?? null, $menu))->toBe(['menu-beta', null])
        ->and($menu[1]['isSection'])->toBeTrue()
        ->and($menu[1]['title'])->toBe('Content')
        ->and($menu[1]['icon'])->toBe('Boxes')
        ->and($menu[1]['order'])->toBe(2)
        ->and(array_column($menu[1]['children'], 'key'))->toBe(['menu-alpha']);
});

it('trims the section key before looking it up', function () {
    MenuAlphaResource::configureMenu(['section' => '  Content  ']);

    $menu = audBuildMenu([MenuAlphaResource::class]);

    expect($menu[0]['title'])->toBe('Content');
});

it('nests resources under a configured section definition with its label, icon and order', function () {
    NadotaService::configureMenuSections(fn () => [
        MenuSectionDefinition::make('content', 'Content')->icon('Folder')->order(0),
    ]);
    MenuAlphaResource::configureMenu(['section' => 'content', 'order' => 2]);
    MenuBetaResource::configureMenu(['section' => 'content', 'order' => 1]);

    $menu = audBuildMenu([MenuAlphaResource::class, MenuBetaResource::class, MenuGammaResource::class]);

    expect($menu[0]['title'])->toBe('Content')
        ->and($menu[0]['icon'])->toBe('Folder')
        ->and($menu[0]['order'])->toBe(0)
        ->and(array_column($menu[0]['children'], 'key'))->toBe(['menu-beta', 'menu-alpha'])
        ->and($menu[1]['key'])->toBe('menu-gamma');
});

it('drops a configured section, and the resources in it, when visibleWhen refuses', function () {
    $received = null;
    NadotaService::configureMenuSections(function () use (&$received) {
        return [
            MenuSectionDefinition::make('admin', 'Admin')->visibleWhen(function ($request) use (&$received) {
                $received = $request;

                return false;
            }),
        ];
    });
    MenuAlphaResource::configureMenu(['section' => 'admin']);
    $request = new NadotaRequest();

    $menu = audBuildMenu([MenuAlphaResource::class, MenuBetaResource::class], $request);

    expect(array_column($menu, 'key'))->toBe(['menu-beta'])
        ->and($received)->toBe($request);
});

it('appends custom items at the top level, after resources of a lower order', function () {
    NadotaService::addMenuItems(fn () => [
        MenuItem::make('Reports', 'reports', 'BarChart', null, '/reports'),
        MenuItem::make('Home', 'home')->order(0),
    ]);

    $menu = audBuildMenu([MenuAlphaResource::class]);

    expect(array_column($menu, 'key'))->toBe(['home', 'menu-alpha', 'reports'])
        ->and($menu[2]['isResource'])->toBeFalse();
});

it('skips the custom items whose canSee refuses and hands the request to the addMenuItems callback', function () {
    $received = null;
    NadotaService::addMenuItems(function ($request) use (&$received) {
        $received = $request;

        return [
            MenuItem::make('Hidden', 'hidden')->canSee(fn () => false),
            MenuItem::make('Shown', 'shown'),
        ];
    });
    $request = new NadotaRequest();

    $menu = audBuildMenu([], $request);

    expect(array_column($menu, 'key'))->toBe(['shown'])
        ->and($received)->toBe($request);
});

it('places a custom item with a parent into the matching section, next to the resources there', function () {
    NadotaService::configureMenuSections(fn () => [MenuSectionDefinition::make('content', 'Content')]);
    NadotaService::addMenuItems(fn () => [
        MenuItem::make('Drafts', 'drafts', null, null, '/drafts', 'content', [], 0),
    ]);
    MenuAlphaResource::configureMenu(['section' => 'content']);

    $menu = audBuildMenu([MenuAlphaResource::class]);

    expect($menu)->toHaveCount(1)
        ->and(array_column($menu[0]['children'], 'key'))->toBe(['drafts', 'menu-alpha']);
});

it('returns the prepareMenuUsing() callback result verbatim, without building or authorizing anything', function () {
    $this->app->instance(ResourceAuthorizationInterface::class, new class implements ResourceAuthorizationInterface {
        public function setModel($model): self
        {
            throw new RuntimeException('authorization must not run');
        }

        public function authorizedTo(NadotaRequest $request, string $action, array $context = []): bool
        {
            return false;
        }
    });
    $custom = ['my' => 'menu'];
    NadotaService::prepareMenuUsing(fn () => $custom);
    ResourceManager::registerResourceClass(MenuAlphaResource::class);

    expect((new MenuService())->build(new NadotaRequest()))->toBe($custom);
});

it('returns a MenuResource collection when it builds the menu itself', function () {
    expect((new MenuService())->build(new NadotaRequest()))->toBeInstanceOf(MenuResource::class);
});

it('keeps two resources that share a title', function () {
    MenuAlphaResource::configureMenu(['title' => 'Records']);
    MenuBetaResource::configureMenu(['title' => 'Records']);

    $keys = array_column(audBuildMenu([MenuAlphaResource::class, MenuBetaResource::class]), 'key');

    expect($keys)->toBe(['menu-alpha', 'menu-beta']);
})->skip('ISSUE AUD-001: top-level items are keyed by title, so the second resource overwrites the first');

it('keeps every top-level custom section', function () {
    NadotaService::addMenuItems(fn () => [
        new MenuSection('One', null, [MenuItem::make('A', 'a')]),
        new MenuSection('Two', null, [MenuItem::make('B', 'b')]),
    ]);

    $titles = array_column(audBuildMenu(), 'title');

    expect($titles)->toBe(['One', 'Two']);
})->skip("ISSUE AUD-002: custom items are keyed by getKey() and every MenuSection returns '', so only the last section survives");

it('keeps a resource nested in a section whose key equals the title of a resource registered later', function () {
    MenuAlphaResource::configureMenu(['title' => 'Alpha', 'section' => 'Beta']);
    MenuBetaResource::configureMenu(['title' => 'Beta']);

    $menu = audBuildMenu([MenuAlphaResource::class, MenuBetaResource::class]);
    $keys = collect($menu)->flatMap(fn ($entry) => isset($entry['isSection']) ? array_column($entry['children'], 'key') : [$entry['key']])->all();

    expect($keys)->toContain('menu-alpha')->toContain('menu-beta');
})->skip('ISSUE AUD-003: the later top-level item overwrites the section built for the earlier resource');

it('keeps a resource nested in a configured section whose key equals the title of a top-level resource', function () {
    NadotaService::configureMenuSections(fn () => [MenuSectionDefinition::make('Beta', 'Beta section')]);
    MenuAlphaResource::configureMenu(['title' => 'Beta']);
    MenuBetaResource::configureMenu(['title' => 'Other', 'section' => 'Beta']);

    $menu = audBuildMenu([MenuAlphaResource::class, MenuBetaResource::class]);
    $keys = collect($menu)->flatMap(fn ($entry) => isset($entry['isSection']) ? array_column($entry['children'], 'key') : [$entry['key']])->all();

    expect($keys)->toContain('menu-alpha')->toContain('menu-beta');
})->skip('ISSUE AUD-003: the resource is appended to the children of the MenuItem that holds the key, which are never serialized (AUD-004)');

it('keeps a resource whose section key is blank, as a top-level item', function () {
    MenuAlphaResource::configureMenu(['section' => '   ']);

    expect(array_column(audBuildMenu([MenuAlphaResource::class]), 'key'))->toBe(['menu-alpha']);
})->skip('ISSUE AUD-003: a blank (non-null) section key makes addMenuItemToSection() return early and the resource vanishes');

it('hides the children of a custom section whose canSee refuses', function () {
    NadotaService::addMenuItems(fn () => [
        new MenuSection('Tools', null, [
            MenuItem::make('Secret', 'secret')->canSee(fn () => false),
            MenuItem::make('Public', 'public'),
        ]),
    ]);

    $menu = audBuildMenu();

    expect(array_column($menu[0]['children'], 'key'))->toBe(['public']);
})->skip('ISSUE AUD-005: only top-level custom items are checked with isVisible(); nested children are emitted unconditionally');
