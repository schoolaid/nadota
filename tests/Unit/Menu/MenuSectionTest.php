<?php

use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Menu\MenuItem;
use SchoolAid\Nadota\Menu\MenuSection;

it('defaults to no icon, no children, order 2 and search disabled', function () {
    $section = new MenuSection('Content');

    expect($section->getLabel())->toBe('Content')
        ->and($section->getIcon())->toBeNull()
        ->and($section->getChildren())->toBe([])
        ->and($section->getOrder())->toBe(2)
        ->and($section->isSearchEnabled())->toBeFalse();
});

it('takes title, icon, children, order and the search flag', function () {
    $child = MenuItem::make('Posts', 'posts');
    $section = new MenuSection('Content', 'Boxes', [$child], 5, true);

    expect($section->getIcon())->toBe('Boxes')
        ->and($section->getChildren())->toBe([$child])
        ->and($section->getOrder())->toBe(5)
        ->and($section->isSearchEnabled())->toBeTrue();
});

it('is a container, not a navigable target', function () {
    $section = new MenuSection('Content', 'Boxes');

    expect($section->getKey())->toBe('')
        ->and($section->getApiUrl())->toBeNull()
        ->and($section->getFrontendUrl())->toBeNull()
        ->and($section->getParent())->toBeNull()
        ->and($section->isResource())->toBeFalse();
});

it('replaces its children', function () {
    $section = new MenuSection('Content', null, [MenuItem::make('Old', 'old')]);
    $new = MenuItem::make('New', 'new');

    $section->setChildren([$new]);

    expect($section->getChildren())->toBe([$new]);
});

it('is visible until a canSee callback refuses', function () {
    $request = new NadotaRequest();
    $section = new MenuSection('Content');

    expect($section->isVisible($request))->toBeTrue();

    $section->canSee(fn () => false);

    expect($section->isVisible($request))->toBeFalse();
});
