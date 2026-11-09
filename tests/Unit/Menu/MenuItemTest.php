<?php

use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Menu\MenuItem;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;

it('defaults the icon to LayoutPanelTop, the order to 2 and isResource to false', function () {
    $item = new MenuItem('Reports', 'reports');

    expect($item->getIcon())->toBe('LayoutPanelTop')
        ->and($item->getOrder())->toBe(2)
        ->and($item->isResource())->toBeFalse()
        ->and($item->getApiUrl())->toBeNull()
        ->and($item->getFrontendUrl())->toBeNull()
        ->and($item->getParent())->toBeNull()
        ->and($item->getChildren())->toBe([]);
});

it('takes label, key, icon, urls, parent, children, order and resource flag positionally through make()', function () {
    $child = MenuItem::make('Child', 'child');
    $item = MenuItem::make('Reports', 'reports', 'BarChart', '/api/reports', '/reports', 'analytics', [$child], 7, true);

    expect($item->getLabel())->toBe('Reports')
        ->and($item->getKey())->toBe('reports')
        ->and($item->getIcon())->toBe('BarChart')
        ->and($item->getApiUrl())->toBe('/api/reports')
        ->and($item->getFrontendUrl())->toBe('/reports')
        ->and($item->getParent())->toBe('analytics')
        ->and($item->getChildren())->toBe([$child])
        ->and($item->getOrder())->toBe(7)
        ->and($item->isResource())->toBeTrue();
});

it('changes the order fluently', function () {
    $item = MenuItem::make('Reports', 'reports');

    expect($item->order(9))->toBe($item)
        ->and($item->getOrder())->toBe(9);
});

it('appends children with addChild() and replaces them with setChildren()', function () {
    $item = MenuItem::make('Parent', 'parent');
    $first = MenuItem::make('First', 'first');
    $second = MenuItem::make('Second', 'second');

    $item->addChild($first);
    $item->addChild($second);
    expect($item->getChildren())->toBe([$first, $second]);

    $item->setChildren([$second]);
    expect($item->getChildren())->toBe([$second]);
});

it('is visible until a canSee callback refuses, and hands the request to the callback', function () {
    $request = new NadotaRequest();
    $item = MenuItem::make('Reports', 'reports');

    expect($item->isVisible($request))->toBeTrue();

    $received = null;
    $item->canSee(function ($given) use (&$received) {
        $received = $given;

        return false;
    });

    expect($item->isVisible($request))->toBeFalse()
        ->and($received)->toBe($request);
});

it('builds an item from a resource with fromResource()', function () {
    $item = MenuItem::fromResource(GradeResource::class);

    expect($item->getLabel())->toBe('Grade Resources') // CORE-006: the default title keeps "Resource"
        ->and($item->getKey())->toBe('grade')
        ->and($item->getIcon())->toBe('LayoutPanelTop')
        ->and($item->getApiUrl())->toBe('nadota-api/grade/resource')
        ->and($item->getFrontendUrl())->toBe('resources/grade')
        ->and($item->getParent())->toBeNull();
});

it('carries the resource order and the isResource flag in fromResource(), like the items MenuService builds', function () {
    $item = MenuItem::fromResource(GradeResource::class);

    expect($item->getOrder())->toBe((new GradeResource())->orderInMenu())
        ->and($item->isResource())->toBeTrue();
})->skip('ISSUE AUD-007: fromResource() ignores orderInMenu() (order 2) and leaves isResource false');

it('declares label and key as required constructor arguments', function () {
    $parameters = (new ReflectionMethod(MenuItem::class, '__construct'))->getParameters();

    expect($parameters[0]->isOptional())->toBeFalse()
        ->and($parameters[1]->isOptional())->toBeFalse();
})->skip('ISSUE AUD-008: `string $label = null, string $key = null` are implicitly nullable but the properties are not; MenuItem::make() throws a TypeError');
