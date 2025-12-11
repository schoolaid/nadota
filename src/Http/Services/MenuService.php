<?php

namespace SchoolAid\Nadota\Http\Services;

use SchoolAid\Nadota\Contracts\MenuServiceInterface;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\NadotaService;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Menu\MenuItem;
use SchoolAid\Nadota\Menu\MenuSection;
use SchoolAid\Nadota\Menu\MenuSectionDefinition;
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

            $sectionKey = $resourceInstance->displayInSubMenu();
            if ($sectionKey === null) {
                // Top-level menu item
                $menuStructure[$resourceInstance->title()] = $menuItem;
            } else {
                // Add to configured section
                $this->addToMenuPath($menuStructure, $sectionKey, $menuItem, $request);
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
     * Add a menu item to the structure using section key
     */
    private function addToMenuPath(array &$menuStructure, string $sectionKey, MenuItem $menuItem, NadotaRequest $request): void
    {
        $sectionKey = trim($sectionKey);
        if ($sectionKey === '') {
            return;
        }

        // Try to get configured section definition
        $sectionDefinition = NadotaService::getMenuSection($sectionKey);

        if ($sectionDefinition !== null) {
            // Check visibility
            if (!$sectionDefinition->isVisible($request)) {
                return;
            }

            // Create section from definition if not exists
            if (!isset($menuStructure[$sectionKey])) {
                $menuStructure[$sectionKey] = $sectionDefinition->toMenuSection();
            }
        } else {
            // Fallback: create section with key as label (backwards compatibility)
            if (!isset($menuStructure[$sectionKey])) {
                $menuStructure[$sectionKey] = new MenuSection($sectionKey, 'Boxes');
            } elseif ($menuStructure[$sectionKey] instanceof MenuItem) {
                // Convert existing MenuItem into a MenuSection
                $existingItem = $menuStructure[$sectionKey];
                $menuStructure[$sectionKey] = new MenuSection(
                    $existingItem->getLabel(),
                    $existingItem->getIcon(),
                    [$existingItem],
                    $existingItem->getOrder(),
                    false
                );
            }
        }

        $section = $menuStructure[$sectionKey];

        // Append the menu item as a child of this section
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
