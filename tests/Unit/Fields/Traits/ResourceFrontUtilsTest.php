<?php

use SchoolAid\Nadota\Http\Fields\Traits\ResourceFrontUtils;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentProfileResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

it('is part of every Resource', function () {
    expect(class_uses(Resource::class))->toContain(ResourceFrontUtils::class);
});

it('builds the API url from the configured prefix and the resource key', function () {
    // No leading slash, unlike the options/action-events/export URLs (see TRT-017).
    expect(StudentResource::apiUrl())->toBe('nadota-api/student/resource')
        ->and(StudentProfileResource::apiUrl())->toBe('nadota-api/student-profile/resource');
});

it('builds the frontend url from the configured frontend prefix', function () {
    expect(StudentResource::frontendUrl())->toBe('resources/student');
});

it('follows a customised prefix', function () {
    config()->set('nadota.api.prefix', 'admin-api');
    config()->set('nadota.frontend.prefix', 'admin');

    expect(StudentResource::apiUrl())->toBe('admin-api/student/resource')
        ->and(StudentResource::frontendUrl())->toBe('admin/student');
});

it('has no description unless the resource overrides it', function () {
    expect((new StudentResource())->description())->toBeNull();
});
