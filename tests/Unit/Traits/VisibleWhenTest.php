<?php

use SchoolAid\Nadota\Http\Traits\VisibleWhen;
use SchoolAid\Nadota\Menu\MenuItem;
use SchoolAid\Nadota\Menu\MenuSection;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

function visibleWhenHost(): object
{
    return new class {
        use VisibleWhen;
    };
}

it('is visible without a callback', function () {
    expect(visibleWhenHost()->isVisible(createNadotaRequest()))->toBeTrue();
});

it('asks the callback, handing it the request', function () {
    $received = null;
    $host = visibleWhenHost()->canSee(function ($request) use (&$received) {
        $received = $request;

        return $request->get('role') === 'admin';
    });
    $admin = createNadotaRequest(['role' => 'admin']);

    expect($host->isVisible($admin))->toBeTrue()
        ->and($received)->toBe($admin)
        ->and($host->isVisible(createNadotaRequest(['role' => 'guest'])))->toBeFalse();
});

it('keeps only the last callback', function () {
    $host = visibleWhenHost()->canSee(fn () => false)->canSee(fn () => true);

    expect($host->isVisible(createNadotaRequest()))->toBeTrue();
});

it('accepts any callable, not only closures', function () {
    $gate = new class {
        public function __invoke($request): bool
        {
            return $request->has('allowed');
        }
    };

    expect(visibleWhenHost()->canSee($gate)->isVisible(createNadotaRequest(['allowed' => 1])))->toBeTrue();
});

it('coerces truthy callback results to bool', function () {
    expect(visibleWhenHost()->canSee(fn () => 1)->isVisible(createNadotaRequest()))->toBeTrue()
        ->and(visibleWhenHost()->canSee(fn () => 0)->isVisible(createNadotaRequest()))->toBeFalse();
});

it('treats a null callback result as hidden', function () {
    // e.g. canSee(fn ($request) => $request->user()?->isAdmin()) for a guest
    expect(visibleWhenHost()->canSee(fn () => null)->isVisible(createNadotaRequest()))->toBeFalse();
})->skip('ISSUE TRT-014: isVisible(): bool returns the raw callback result; null throws a TypeError');

it('is shared by resources, menu items and menu sections', function () {
    expect(class_uses(Resource::class))->toContain(VisibleWhen::class)
        ->and(class_uses(MenuItem::class))->toContain(VisibleWhen::class)
        ->and(class_uses(MenuSection::class))->toContain(VisibleWhen::class);
});

it('can be set on a resource instance', function () {
    $resource = (new StudentResource())->canSee(fn () => false);

    expect($resource->isVisible(createNadotaRequest()))->toBeFalse();
    // Whether anything reads it for resources is AUD-011.
});
