<?php

namespace SchoolAid\Nadota;

class NadotaService
{
	public static mixed $prepareMenuUsing = null;
	public static mixed $addMenuItems = null;
	public static mixed $menuSectionsUsing = null;
	protected static array $menuSections = [];
	protected static array $excludedResources = [];

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

	/**
	 * Exclude resource classes from being registered.
	 *
	 * @param array<class-string> $resourceClasses
	 */
	public static function excludeResources(array $resourceClasses): void
	{
		static::$excludedResources = array_merge(static::$excludedResources, $resourceClasses);
	}

	/**
	 * Get the list of excluded resource classes.
	 *
	 * @return array<class-string>
	 */
	public static function getExcludedResources(): array
	{
		return static::$excludedResources;
	}
}
