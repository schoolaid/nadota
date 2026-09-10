<?php

use SchoolAid\Nadota\Http\Services\FieldOptionsService;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\ScopedOptionsResource;

beforeEach(function () {
    // ResourceManager discovers resources from the filesystem, which does not work
    // for the test namespace, so the registry is seeded directly.
    $property = new ReflectionProperty(ResourceManager::class, 'resources');
    $property->setAccessible(true);

    // phpunit.xml runs tests in random order, so leaving the static registry
    // seeded here would leak into whichever test runs next in the process.
    $this->originalResources = $property->getValue();

    $property->setValue(null, collect([
        'scoped-options' => [
            'class' => ScopedOptionsResource::class,
            'model' => TestModel::class,
        ],
    ]));

    foreach ([5, 15, 51] as $ownerId) {
        $owner = new TestModel(['name' => "Owner {$ownerId}"]);
        $owner->id = $ownerId;
        $owner->save();

        RelatedModel::query()->create([
            'title' => "Item for owner {$ownerId}",
            'test_model_id' => $ownerId,
        ]);
    }
});

afterEach(function () {
    // Restore the static registry seeded in beforeEach() so it does not leak
    // into whichever test phpunit.xml's random order runs next.
    $property = new ReflectionProperty(ResourceManager::class, 'resources');
    $property->setAccessible(true);
    $property->setValue(null, $this->originalResources);
});

function paginatedOptions(array $query = []): array
{
    return (new FieldOptionsService(new ResourceManager()))->getPaginatedOptions(
        createNadotaRequest($query),
        'scoped-options',
        'item'
    );
}

it('paginates only the options matching the scope', function () {
    $response = paginatedOptions(['scope' => ['owner' => 5]]);

    expect($response['success'])->toBeTrue()
        ->and($response['data'])->toHaveCount(1)
        ->and($response['data'][0]['label'])->toBe('Item for owner 5')
        ->and($response['meta']['total'])->toBe(1);
});

it('returns an empty page with full meta when a strict scope has no value', function () {
    $response = paginatedOptions();

    expect($response['success'])->toBeTrue()
        ->and($response['data'])->toBe([])
        ->and($response['meta']['total'])->toBe(0)
        ->and($response['meta']['last_page'])->toBe(1)
        ->and($response['meta'])->toHaveKeys([
            'current_page', 'per_page', 'total', 'last_page', 'from', 'to',
        ]);
});

it('matches the scope value exactly', function () {
    $response = paginatedOptions(['scope' => ['owner' => 5]]);

    expect(collect($response['data'])->pluck('label')->all())
        ->toBe(['Item for owner 5']);
});

it('does not throw when scope arrives as a non-array string, e.g. a plain ?scope=abc', function () {
    // Before the fix, OptionScopeResolver::apply() declared `array $values` while
    // the request value is untyped; a plain query string like `?scope=abc` made
    // this an uncaught TypeError for every options endpoint, scoped or not.
    $response = paginatedOptions(['scope' => 'abc']);

    expect($response['success'])->toBeTrue()
        ->and($response['data'])->toBe([])
        ->and($response['meta']['total'])->toBe(0);
});
