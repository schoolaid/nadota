<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Menu;

use ReflectionProperty;
use SchoolAid\Nadota\NadotaService;
use SchoolAid\Nadota\ResourceManager;

/**
 * Resets every static the menu relies on: the NadotaService callbacks, the
 * cached section definitions, the excluded-resource list and the fixture menu
 * options. Call it in afterEach (and beforeEach if a test may run first).
 *
 * resetRegistry() additionally empties the ResourceManager registry, for
 * tests that register resources without InteractsWithNadotaApi.
 */
final class MenuState
{
    public static function reset(): void
    {
        NadotaService::$prepareMenuUsing = null;
        NadotaService::$addMenuItems = null;
        NadotaService::$menuSectionsUsing = null;

        foreach (['menuSections', 'excludedResources'] as $property) {
            (new ReflectionProperty(NadotaService::class, $property))->setValue(null, []);
        }

        AbstractMenuFixture::$menuOptions = [];
    }

    public static function resetRegistry(): void
    {
        (new ReflectionProperty(ResourceManager::class, 'resources'))->setValue(null, null);
    }
}
