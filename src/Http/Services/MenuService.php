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
    public function handle(NadotaRequest $request)
    {
        if (NadotaService::$prepareMenuUsing) {
            return call_user_func(NadotaService::$prepareMenuUsing, $request);
        }

        $resources = ResourceManager::getResources();
        $resourceAuthorization = app(ResourceAuthorizationInterface::class);

        $resources = $resources->filter(function ($resource) use ($request, $resourceAuthorization) {
            return $resourceAuthorization
                ->setModel($resource['model'])
                ->authorizedTo($request, 'viewAny');
        });

        $menuItems = [];

        foreach ($resources as $resource) {
            $resourceInstance = new $resource['class'];

            if(!$resourceInstance->displayInMenu($request)) {
                continue;
            }

            $menuItem = new MenuItem(
                $resourceInstance->title(),
                $resourceInstance->getKey(),
                $resourceInstance->displayIcon(),
                $resourceInstance->apiUrl(),
                $resourceInstance->frontendUrl(),
                $resourceInstance->displayInSubMenu(),
                [],
                $resourceInstance->orderInMenu(),
                true
            );

            $menuItems[$menuItem->getLabel()] = $menuItem;
        }

        foreach ($menuItems as $label => $menuItem) {
            $parentLabel = $menuItem->getParent();
            if ($parentLabel && isset($menuItems[$parentLabel])) {
                $parentItem = $menuItems[$parentLabel];
                $parentItem->addChild($menuItem);
            }
        }

        foreach ($menuItems as $label => &$menuItem) {
            $this->convertToMenuSectionIfHasChildren($menuItem);
        }

        unset($menuItem);

        $finalMenu = [];
        foreach ($menuItems as $label => $menuItem) {
            $parentLabel = $menuItem->getParent();
            if (!$parentLabel || !isset($menuItems[$parentLabel])) {
                $this->filterInvisibleChildren($menuItem);
                $finalMenu[] = $menuItem;
            }
        }
        if (NadotaService::$addMenuItems) {
            $additionalMenuItems = call_user_func(NadotaService::$addMenuItems, $request);
            foreach ($additionalMenuItems as $additionalMenuItem) {
                $finalMenu[] = $additionalMenuItem;
            }
        }

        usort($finalMenu, function ($a, $b) {
            return $a->getOrder() <=> $b->getOrder();
        });

        foreach ($finalMenu as $menuItem) {
            $children = $menuItem->getChildren();
            usort($children, function ($a, $b) {
                return $a->getOrder() <=> $b->getOrder();
            });
            $menuItem->setChildren($children);
        }


        return new MenuResource($finalMenu);
    }

    private function convertToMenuSectionIfHasChildren(MenuItemInterface &$menuItem): void
    {
        $children = $menuItem->getChildren();
        if (count($children) > 0) {
            $menuSection = new MenuSection(
                $menuItem->getLabel(),
                $menuItem->getIcon(),
                [],
                $menuItem->getOrder(),
                false
            );

            $newChildren = [];
            foreach ($children as &$child) {
                $this->convertToMenuSectionIfHasChildren($child);
                $newChildren[] = $child;
            }
            $menuSection->setChildren($newChildren);
            $menuItem = $menuSection;
        }
    }

    private function filterInvisibleChildren(MenuItemInterface $menuItem): void
    {
        $children = $menuItem->getChildren();
        $visibleChildren = [];

        foreach ($children as $child) {
            $this->filterInvisibleChildren($child);
            $visibleChildren[] = $child;
        }

        $menuItem->setChildren($visibleChildren);
    }
}
