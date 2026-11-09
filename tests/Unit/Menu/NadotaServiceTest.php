<?php

use SchoolAid\Nadota\Menu\MenuSectionDefinition;
use SchoolAid\Nadota\NadotaService;
use SchoolAid\Nadota\Tests\Fixtures\Menu\MenuState;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\SchoolResource;

beforeEach(fn () => MenuState::reset());
afterEach(fn () => MenuState::reset());

it('stores the menu callbacks as given', function () {
    $prepare = fn () => [];
    $items = fn () => [];
    $sections = fn () => [];

    NadotaService::prepareMenuUsing($prepare);
    NadotaService::addMenuItems($items);
    NadotaService::configureMenuSections($sections);

    expect(NadotaService::$prepareMenuUsing)->toBe($prepare)
        ->and(NadotaService::$addMenuItems)->toBe($items)
        ->and(NadotaService::$menuSectionsUsing)->toBe($sections);
});

it('evaluates the section callback lazily, once, and indexes definitions by key', function () {
    $calls = 0;
    NadotaService::configureMenuSections(function () use (&$calls) {
        $calls++;

        return [MenuSectionDefinition::make('content', 'Content'), MenuSectionDefinition::make('admin', 'Admin')];
    });

    expect($calls)->toBe(0);

    $sections = NadotaService::getMenuSections();
    NadotaService::getMenuSections();

    expect($calls)->toBe(1)
        ->and(array_keys($sections))->toBe(['content', 'admin'])
        ->and(NadotaService::getMenuSection('admin')->getLabel())->toBe('Admin')
        ->and(NadotaService::getMenuSection('missing'))->toBeNull();
});

it('returns no sections when none are configured', function () {
    expect(NadotaService::getMenuSections())->toBe([]);
});

it('accumulates excluded resources across calls', function () {
    NadotaService::excludeResources([SchoolResource::class]);
    NadotaService::excludeResources([GradeResource::class]);

    expect(NadotaService::getExcludedResources())->toBe([SchoolResource::class, GradeResource::class]);
});

it('uses the latest configureMenuSections() callback even after sections were read', function () {
    NadotaService::configureMenuSections(fn () => [MenuSectionDefinition::make('content', 'First')]);
    NadotaService::getMenuSections();

    NadotaService::configureMenuSections(fn () => [MenuSectionDefinition::make('content', 'Second')]);

    expect(NadotaService::getMenuSection('content')->getLabel())->toBe('Second');
})->skip('ISSUE AUD-009: getMenuSections() caches the first evaluation in a static and never re-reads the callback');
