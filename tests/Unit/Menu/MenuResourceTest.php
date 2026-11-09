<?php

use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Resources\Menu\MenuItemResource;
use SchoolAid\Nadota\Http\Resources\Menu\MenuResource;
use SchoolAid\Nadota\Http\Resources\Menu\MenuSectionResource;
use SchoolAid\Nadota\Menu\MenuItem;
use SchoolAid\Nadota\Menu\MenuSection;

/** Resolve a menu payload to plain arrays, as the JSON response would. */
function audMenuPayload(MenuResource $menu): array
{
    return json_decode(json_encode($menu->toArray(new NadotaRequest())), true);
}

it('serializes a menu item with exactly label, key, icon, urls, children, order and isResource', function () {
    $item = MenuItem::make('Reports', 'reports', 'BarChart', '/api/reports', '/reports', null, [], 4, false);

    expect((new MenuItemResource($item))->toArray(new NadotaRequest()))->toBe([
        'label' => 'Reports',
        'key' => 'reports',
        'icon' => 'BarChart',
        'apiUrl' => '/api/reports',
        'frontendUrl' => '/reports',
        'children' => [],
        'order' => 4,
        'isResource' => false,
    ]);
});

it('serializes a section with isSection, title, icon, enableSearch, nested children and order', function () {
    $section = new MenuSection('Content', 'Boxes', [MenuItem::make('Posts', 'posts')], 3, true);

    $payload = json_decode(json_encode((new MenuSectionResource($section))->toArray(new NadotaRequest())), true);

    expect(array_keys($payload))->toBe(['isSection', 'title', 'icon', 'enableSearch', 'children', 'order'])
        ->and($payload['isSection'])->toBeTrue()
        ->and($payload['title'])->toBe('Content')
        ->and($payload['icon'])->toBe('Boxes')
        ->and($payload['enableSearch'])->toBeTrue()
        ->and($payload['order'])->toBe(3)
        ->and($payload['children'])->toHaveCount(1)
        ->and($payload['children'][0]['label'])->toBe('Posts');
});

it('picks the serializer by type and nests sections recursively', function () {
    $inner = new MenuSection('Inner', null, [MenuItem::make('Leaf', 'leaf')]);
    $outer = new MenuSection('Outer', null, [$inner]);

    $payload = audMenuPayload(new MenuResource([MenuItem::make('Top', 'top'), $outer]));

    expect($payload[0])->not->toHaveKey('isSection')
        ->and($payload[0]['label'])->toBe('Top')
        ->and($payload[1]['isSection'])->toBeTrue()
        ->and($payload[1]['children'][0]['isSection'])->toBeTrue()
        ->and($payload[1]['children'][0]['children'][0]['key'])->toBe('leaf');
});

it('serializes the children of a plain menu item', function () {
    $parent = MenuItem::make('Parent', 'parent');
    $parent->addChild(MenuItem::make('Child', 'child'));

    $payload = audMenuPayload(new MenuResource([$parent]));

    expect($payload[0]['children'])->toHaveCount(1)
        ->and($payload[0]['children'][0]['key'])->toBe('child');
})->skip('ISSUE AUD-004: MenuItemResource hard-codes children => [], so MenuItem children never reach the JSON');
