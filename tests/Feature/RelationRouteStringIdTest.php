<?php

use Illuminate\Http\Request;
use SchoolAid\Nadota\Providers\RouteServiceProvider;

/**
 * Relation/action endpoints must resolve resources keyed by a non-numeric
 * identifier (string / UUID), not just auto-incrementing integers. The route
 * `{id}` used to carry a `->where('id', '[0-9]+')` constraint that 404'd every
 * string-keyed resource; disambiguation is provided by each route's distinct
 * literal segment and declaration order, not by a numeric constraint.
 */
beforeEach(function () {
    app()->register(RouteServiceProvider::class);
});

it('matches relation/action routes for a non-numeric (string) resource id', function (string $method, string $path, string $expectedName) {
    $route = app('router')->getRoutes()->match(Request::create($path, $method));

    expect($route->getName())->toBe($expectedName)
        ->and($route->parameter('id'))->toBe('antiguainternationalschool');
})->with([
    'attachable'    => ['GET',  '/nadota-api/tenant/resource/antiguainternationalschool/attachable/schools', 'nadota.api.resource.attachable'],
    'attach'        => ['POST', '/nadota-api/tenant/resource/antiguainternationalschool/attach/schools', 'nadota.api.resource.attach'],
    'detach'        => ['POST', '/nadota-api/tenant/resource/antiguainternationalschool/detach/schools', 'nadota.api.resource.detach'],
    'sync'          => ['POST', '/nadota-api/tenant/resource/antiguainternationalschool/sync/schools', 'nadota.api.resource.sync'],
    'relation'      => ['GET',  '/nadota-api/tenant/resource/antiguainternationalschool/relation/schools', 'nadota.api.resource.relation.index'],
    'action-events' => ['GET',  '/nadota-api/tenant/resource/antiguainternationalschool/action-events', 'nadota.api.resource.action-events'],
    'permissions'   => ['GET',  '/nadota-api/tenant/resource/antiguainternationalschool/permissions', 'nadota.api.resource.permissions'],
    'restore'       => ['POST', '/nadota-api/tenant/resource/antiguainternationalschool/restore', 'nadota.api.resource.restore'],
    'force'         => ['DELETE', '/nadota-api/tenant/resource/antiguainternationalschool/force', 'nadota.api.resource.forceDelete'],
]);

it('still matches numeric ids on the same routes', function () {
    $route = app('router')->getRoutes()->match(
        Request::create('/nadota-api/tenant/resource/123/attach/schools', 'POST')
    );

    expect($route->getName())->toBe('nadota.api.resource.attach')
        ->and($route->parameter('id'))->toBe('123');
});

it('keeps literal subresource paths from being captured as a resource id', function (string $method, string $path, string $expectedName) {
    $route = app('router')->getRoutes()->match(Request::create($path, $method));

    expect($route->getName())->toBe($expectedName);
})->with([
    'create'  => ['GET', '/nadota-api/tenant/resource/create', 'nadota.api.resource.create'],
    'options' => ['GET', '/nadota-api/tenant/resource/options', 'nadota.api.resource.options'],
    'config'  => ['GET', '/nadota-api/tenant/resource/config', 'nadota.api.resource.config'],
]);

it('still constrains action-event ids to integers', function () {
    $route = app('router')->getRoutes()->match(
        Request::create('/nadota-api/tenant/resource/antiguainternationalschool/action-events/55', 'GET')
    );

    expect($route->getName())->toBe('nadota.api.resource.action-events.show')
        ->and($route->parameter('id'))->toBe('antiguainternationalschool')
        ->and($route->parameter('eventId'))->toBe('55');
});
