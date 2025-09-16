<?php

namespace SchoolAid\Nadota\Http\Services;

use SchoolAid\Nadota\Contracts\MenuServiceInterface;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\NadotaService;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Menu\MenuItem;
use SchoolAid\Nadota\Menu\MenuSection;
use SchoolAid\Nadota\Http\Resources\Menu\MenuResource;
use SchoolAid\Nadota\Contracts\MenuItemInterface;

class MenuService implements MenuServiceInterface
{
    public function build(NadotaRequest $request)
    {
        if (NadotaService::$prepareMenuUsing) {
            return call_user_func(NadotaService::$prepareMenuUsing, $request);
        }

        $resources = ResourceManager::getResources();
        $resourceAuthorization = app(ResourceAuthorizationInterface::class);

        // Filter resources by authorization
        $resources = $resources->filter(function ($resource) use ($request, $resourceAuthorization) {
            return $resourceAuthorization
                ->setModel($resource['model'])
                ->authorizedTo($request, 'viewAny');
        });

        // Build menu structure
        $menuStructure = [];
        
        foreach ($resources as $resource) {
            $resourceInstance = new $resource['class'];

            if (!$resourceInstance->displayInMenu($request)) {
                continue;
            }

            $menuItem = new MenuItem(
                $resourceInstance->title(),
                $resourceInstance->getKey(),
                $resourceInstance->displayIcon(),
                $resourceInstance->apiUrl(),
                $resourceInstance->frontendUrl(),
                null, // Remove parent from the constructor
                [],
                $resourceInstance->orderInMenu(),
                true
            );

            $parentPath = $resourceInstance->displayInSubMenu();
            if ($parentPath === null) {
                // Top-level menu item
                $menuStructure[$resourceInstance->title()] = $menuItem;
            } else {
                // Handle dot notation for nested menus
                $this->addToMenuPath($menuStructure, $parentPath, $menuItem);
            }
        }

        // Convert structure to final menu
        $finalMenu = $this->buildFinalMenu($menuStructure);

        // Add additional menu items if configured
        if (NadotaService::$addMenuItems) {
            $additionalMenuItems = call_user_func(NadotaService::$addMenuItems, $request);

            foreach ($additionalMenuItems as $additionalMenuItem) {
                if ($additionalMenuItem->isVisible($request)) {
                    $finalMenu[] = $additionalMenuItem;
                }
            }
        }

        // Sort menu items
        $this->sortMenuRecursively($finalMenu);

        return new MenuResource($finalMenu);
    }

    /**
     * Add a menu item to the structure using dot notation path
     */
    private function addToMenuPath(array &$menuStructure, string $path, MenuItem $menuItem): void
    {
        $segments = array_filter(explode('.', $path)); // Remove empty segments
        
        if (empty($segments)) {
            return;
        }

        // Get or create the section path
        $current = &$menuStructure;
        
        foreach ($segments as $segment) {
            // Create a section if it doesn't exist
            if (!isset($current[$segment])) {
                $current[$segment] = new MenuSection(
                    $segment,
                    'Boxes'
                );
            } elseif ($current[$segment] instanceof MenuItem) {
                // Convert MenuItem to MenuSection if it needs to hold children
                $existingItem = $current[$segment];
                $current[$segment] = new MenuSection(
                    $existingItem->getLabel(),
                    $existingItem->getIcon(),
                    [$existingItem], // Keep existing item as a first child
                    $existingItem->getOrder(),
                    false
                );
            }
        }
        // Now add the menu item to the last section
        $targetSection = $menuStructure;
        foreach ($segments as $segment) {
            $targetSection = $targetSection[$segment];
        }
        
        if ($targetSection instanceof MenuSection) {
            $children = $targetSection->getChildren();
            $children[] = $menuItem;
            $targetSection->setChildren($children);
        }
    }
    
    /**
     * Build the final menu array from the structure
     */
    private function buildFinalMenu(array $menuStructure): array
    {
        $finalMenu = [];
        
        foreach ($menuStructure as $key => $item) {
            if ($item instanceof MenuItemInterface) {
                $finalMenu[] = $item;
            }
        }
        
        return $finalMenu;
    }
    
    /**
     * Sort menu items recursively
     */
    private function sortMenuRecursively(array &$menuItems): void
    {
        usort($menuItems, function ($a, $b) {
            return $a->getOrder() <=> $b->getOrder();
        });
        
        foreach ($menuItems as $menuItem) {
            if ($menuItem instanceof MenuItemInterface) {
                $children = $menuItem->getChildren();
                if (!empty($children)) {
                    $this->sortMenuRecursively($children);
                    $menuItem->setChildren($children);
                }
            }
        }
    }
}
