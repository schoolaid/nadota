<?php

use Illuminate\Routing\Route;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;
use Symfony\Component\HttpKernel\Exception\HttpException;

function prepareResourceRequest(string $resourceKey): NadotaRequest
{
    $request = NadotaRequest::create("/nadota-api/{$resourceKey}/resource");
    $route = (new Route('GET', 'nadota-api/{resourceKey}/resource', fn () => null))->bind($request);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

beforeEach(function () {
    InteractsWithNadotaApi::resetResourceManager();
    ResourceManager::registerResourceClass(StudentResource::class);
    ResourceManager::registerResourceClass(GradeResource::class);
});

afterEach(fn () => InteractsWithNadotaApi::resetResourceManager());

it('resolves the resource named by the {resourceKey} route parameter', function () {
    expect(prepareResourceRequest('student')->getResource())->toBeInstanceOf(StudentResource::class)
        ->and(prepareResourceRequest('grade')->getResource())->toBeInstanceOf(GradeResource::class);
});

it('builds the resource through the container', function () {
    $marker = new StudentResource();
    $this->app->instance(StudentResource::class, $marker);

    expect(prepareResourceRequest('student')->getResource())->toBe($marker);
});

it('resolves once and keeps the instance', function () {
    $request = prepareResourceRequest('student');

    expect($request->getResource())->toBe($request->getResource());
});

it('answers 404 for a key no resource is registered under', function () {
    try {
        prepareResourceRequest('nope')->getResource();
        $this->fail('Expected an HTTP 404');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }
});

it('lets a manually set resource win over the route', function () {
    $request = prepareResourceRequest('student');
    $grade = new GradeResource();

    $request->setResource($grade);

    expect($request->getResource())->toBe($grade);
});

it('can swap the resource after it was resolved', function () {
    $request = prepareResourceRequest('student');
    $request->getResource();
    $grade = new GradeResource();

    $request->setResource($grade);

    expect($request->getResource())->toBe($grade);
});

it('needs no route at all once a resource is set', function () {
    $request = new NadotaRequest();
    $request->setResource(new GradeResource());

    $request->prepareResource();

    expect($request->getResource())->toBeInstanceOf(GradeResource::class);
});
