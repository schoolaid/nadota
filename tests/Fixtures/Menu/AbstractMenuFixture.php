<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Menu;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;

/**
 * Base for the menu fixture resources. Each subclass reads its menu options
 * (title, section, icon, order, visibility) from a static map so a test can
 * shape the menu without declaring new classes:
 *
 *     MenuAlphaResource::configureMenu(['title' => 'Alpha', 'section' => 'content', 'order' => 3]);
 *
 * Call MenuState::reset() in afterEach — the map is static.
 *
 * Not suffixed with "Resource" on purpose so a directory scan never tries to
 * instantiate this abstract class.
 */
abstract class AbstractMenuFixture extends Resource
{
    /** @var array<class-string, array<string, mixed>> */
    public static array $menuOptions = [];

    /**
     * `inMenu` may be a bool or a closure receiving the NadotaRequest; `canSee`
     * is handed to Resource::canSee() when the resource is instantiated.
     *
     * @param array{title?: string, section?: string|null, icon?: string|null, order?: int, inMenu?: bool|\Closure, canSee?: \Closure} $options
     */
    public static function configureMenu(array $options): void
    {
        static::$menuOptions[static::class] = $options;
    }

    public function __construct()
    {
        parent::__construct();

        if ($canSee = $this->menuOption('canSee', null)) {
            $this->canSee($canSee);
        }
    }

    protected function menuOption(string $key, mixed $default): mixed
    {
        $options = static::$menuOptions[static::class] ?? [];

        return array_key_exists($key, $options) ? $options[$key] : $default;
    }

    public function fields(NadotaRequest $request): array
    {
        return [];
    }

    public function title(): string
    {
        return $this->menuOption('title', parent::title());
    }

    public function displayInMenu(NadotaRequest $request): bool
    {
        $inMenu = $this->menuOption('inMenu', true);

        return $inMenu instanceof \Closure ? (bool) $inMenu($request) : (bool) $inMenu;
    }

    public function displayInSubMenu(): string|null
    {
        return $this->menuOption('section', null);
    }

    public function displayIcon(): string|null
    {
        return $this->menuOption('icon', parent::displayIcon());
    }

    public function orderInMenu(): int
    {
        return $this->menuOption('order', parent::orderInMenu());
    }
}
