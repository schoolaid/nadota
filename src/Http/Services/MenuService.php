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
        // Treat the whole path literal as a single section label (preserve
        // dot characters for i18n keys like "resource.title").
        $label = trim($path);
        if ($label === '') {
            return;
        }

        // If there's no top-level section with this exact label, create one.
        if (!isset($menuStructure[$label])) {
            $menuStructure[$label] = new MenuSection($label, 'Boxes');
        } elseif ($menuStructure[$label] instanceof MenuItem) {
            // Convert existing MenuItem into a MenuSection so it can hold children.
            $existingItem = $menuStructure[$label];
            $menuStructure[$label] = new MenuSection(
                $existingItem->getLabel(),
                $existingItem->getIcon(),
                [$existingItem],
                $existingItem->getOrder(),
                false
            );
        }

        $section = $menuStructure[$label];

        // Append the menu item as a child of this section.
        $children = $section->getChildren();
        $children[] = $menuItem;
        $section->setChildren($children);
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
