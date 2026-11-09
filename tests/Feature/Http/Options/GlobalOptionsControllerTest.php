<?php

use Illuminate\Support\Facades\Auth;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Resources\ScopedStudentResource;

/**
 * GET /nadota-api/options (routes/public.php) — every resource that opts in with
 * $availableInGlobalOptions, with the key and label of its filters. The route
 * is registered with the "api" middleware only, never with nadota.middlewares.
 */
uses(InteractsWithNadotaApi::class);

it('lists only the resources available in global options, with their filter keys and labels', function () {
    $this->bootNadotaApi(array_merge($this->fixtureResources, [ScopedStudentResource::class]));

    $this->getJson('/nadota-api/options')
        ->assertOk()
        ->assertExactJson([
            [
                'key' => 'scoped-student',
                'filters' => [
                    ['key' => 'age', 'label' => 'Age'],
                    ['key' => 'status', 'label' => 'Status Filter'],
                ],
            ],
        ]);
});

it('answers an empty list when no resource opts in', function () {
    $this->bootNadotaApi();

    $this->getJson('/nadota-api/options')->assertOk()->assertExactJson([]);
});

it('stays public when the resource routes require authentication', function () {
    config(['nadota.middlewares' => ['api', 'auth']]);
    $this->bootNadotaApi(array_merge($this->fixtureResources, [ScopedStudentResource::class]));
    Auth::forgetGuards();

    $this->getJson($this->apiUrl('scoped-student', 'options'))->assertUnauthorized();
    $this->getJson('/nadota-api/options')
        ->assertOk()
        ->assertJsonPath('0.key', 'scoped-student');
});

it('exposes filter metadata only, never records or filter option values', function () {
    $this->bootNadotaApi(array_merge($this->fixtureResources, [ScopedStudentResource::class]));
    \SchoolAid\Nadota\Tests\Fixtures\Models\Student::create(['name' => 'Ana', 'status' => 'enrolled']);

    $content = $this->getJson('/nadota-api/options')->assertOk()->getContent();

    expect($content)->not->toContain('Ana')
        ->and($content)->not->toContain('Enrolled');
});
