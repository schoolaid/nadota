<?php
namespace SchoolAid\Nadota;

class NadotaService
{
    public static mixed $prepareMenuUsing = null;
    public static mixed $addMenuItems = null;
    public static mixed $menuSectionsUsing = null;
    protected static array $menuSections = [];

    public static function prepareMenuUsing(callable $callback): void
    {
        static::$prepareMenuUsing = $callback;
    }

    public static function addMenuItems(callable $callback): void
    {
        static::$addMenuItems = $callback;
    }

    public static function configureMenuSections(callable $callback): void
    {
        static::$menuSectionsUsing = $callback;
    }

    public static function getMenuSections(): array
    {
        if (empty(static::$menuSections) && static::$menuSectionsUsing) {
            $definitions = call_user_func(static::$menuSectionsUsing);

            foreach ($definitions as $definition) {
                static::$menuSections[$definition->getKey()] = $definition;
            }
        }

        return static::$menuSections;
    }

    public static function getMenuSection(string $key): ?Menu\MenuSectionDefinition
    {
        $sections = static::getMenuSections();

        return $sections[$key] ?? null;
    }
}
