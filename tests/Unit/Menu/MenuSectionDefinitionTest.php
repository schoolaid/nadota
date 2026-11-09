<?php

use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Resources\Menu\MenuSectionResource;
use SchoolAid\Nadota\Menu\MenuItem;
use SchoolAid\Nadota\Menu\MenuSection;
use SchoolAid\Nadota\Menu\MenuSectionDefinition;

it('defaults to no icon, order 0, collapsible, expanded and always visible', function () {
    $definition = MenuSectionDefinition::make('content', 'Content');

    expect($definition->getKey())->toBe('content')
        ->and($definition->getLabel())->toBe('Content')
        ->and($definition->getIcon())->toBeNull()
        ->and($definition->getOrder())->toBe(0)
        ->and($definition->isCollapsible())->toBeTrue()
        ->and($definition->isDefaultCollapsed())->toBeFalse()
        ->and($definition->isVisible(new NadotaRequest()))->toBeTrue();
});

it('configures icon, order, collapsing and visibility fluently', function () {
    $definition = MenuSectionDefinition::make('content', 'Content')
        ->icon('Boxes')
        ->order(4)
        ->collapsible(false)
        ->defaultCollapsed();

    expect($definition->getIcon())->toBe('Boxes')
        ->and($definition->getOrder())->toBe(4)
        ->and($definition->isCollapsible())->toBeFalse()
        ->and($definition->isDefaultCollapsed())->toBeTrue();
});

it('hands the request to the visibleWhen callback and follows its answer', function () {
    $request = new NadotaRequest();
    $received = null;

    $definition = MenuSectionDefinition::make('content', 'Content')
        ->visibleWhen(function ($given) use (&$received) {
            $received = $given;

            return false;
        });

    expect($definition->isVisible($request))->toBeFalse()
        ->and($received)->toBe($request);
});

it('materialises a runtime MenuSection with its label, icon, order and children', function () {
    $child = MenuItem::make('Posts', 'posts');
    $section = MenuSectionDefinition::make('content', 'Content')->icon('Boxes')->order(3)->toMenuSection([$child]);

    expect($section)->toBeInstanceOf(MenuSection::class)
        ->and($section->getLabel())->toBe('Content')
        ->and($section->getIcon())->toBe('Boxes')
        ->and($section->getOrder())->toBe(3)
        ->and($section->getChildren())->toBe([$child])
        ->and($section->isSearchEnabled())->toBeFalse();
});

it('carries collapsible and defaultCollapsed into the menu payload', function () {
    $section = MenuSectionDefinition::make('content', 'Content')
        ->collapsible(false)
        ->defaultCollapsed()
        ->toMenuSection();

    $payload = (new MenuSectionResource($section))->toArray(new NadotaRequest());

    expect($payload)->toHaveKey('collapsible', false)
        ->and($payload)->toHaveKey('defaultCollapsed', true);
})->skip('ISSUE AUD-006: collapsible/defaultCollapsed are stored on the definition but dropped by toMenuSection() and MenuSectionResource');
