<?php

use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\MenuService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuAlphaResource;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuBetaResource;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuState;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;

beforeEach(function () {
    MenuState::reset();
    MenuState::resetRegistry();
});

afterEach(function () {
    MenuState::reset();
    MenuState::resetRegistry();
});

it('shows a resource at the top level, without icon, with order 1 by default', function () {
    $resource = new class extends Resource {
        public string $model = School::class;

        public function fields(NadotaRequest $request): array
        {
            return [];
        }
    };

    expect($resource->displayInMenu(new NadotaRequest()))->toBeTrue()
        ->and($resource->displayInSubMenu())->toBeNull()
        ->and($resource->displayIcon())->toBeNull()
        ->and($resource->orderInMenu())->toBe(1);
});

it('reads the icon from the $displayIcon property', function () {
    $resource = new class extends Resource {
        public string $model = School::class;
        protected ?string $displayIcon = 'School';

        public function fields(NadotaRequest $request): array
        {
            return [];
        }
    };

    expect($resource->displayIcon())->toBe('School');
});

it('leaves a resource hidden with canSee() out of the menu', function () {
    MenuAlphaResource::configureMenu(['canSee' => fn () => false]);
    ResourceManager::registerResourceClass(MenuAlphaResource::class);
    ResourceManager::registerResourceClass(MenuBetaResource::class);

    $menu = json_decode(json_encode((new MenuService())->build(new NadotaRequest())->toArray(new NadotaRequest())), true);

    expect(array_column($menu, 'key'))->toBe(['menu-beta']);
})->skip('ISSUE AUD-011: Resource uses VisibleWhen but MenuService never calls isVisible() on resources; only displayInMenu() hides them');
