<?php

use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\BelongsToOptionsStrategy;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\RelatedModelResource;

/**
 * Owners 5, 15 and 51 exist so an exact match on 5 can be told apart from a
 * LIKE '%5%', which the generic filters[] channel would produce.
 */
function seedScopedOptions(): void
{
    foreach ([5, 15, 51] as $ownerId) {
        $owner = new TestModel(['name' => "Owner {$ownerId}"]);
        $owner->id = $ownerId;
        $owner->save();

        RelatedModel::query()->create([
            'title' => "Item for owner {$ownerId}",
            'test_model_id' => $ownerId,
        ]);
    }
}

function fetchScopedOptions(BelongsTo $field, array $params = []): array
{
    return (new BelongsToOptionsStrategy())->fetchOptions(
        createNadotaRequest(),
        createTestResource(),
        $field,
        $params
    );
}

function scopedItemField(): BelongsTo
{
    return BelongsTo::make('Item', 'item', RelatedModelResource::class)
        ->scopedBy('owner', 'test_model_id');
}

it('returns every option when the field declares no scopes', function () {
    seedScopedOptions();

    $options = fetchScopedOptions(
        BelongsTo::make('Item', 'item', RelatedModelResource::class)
    );

    expect($options)->toHaveCount(3);
});

it('filters options by the exact scope value', function () {
    seedScopedOptions();

    $options = fetchScopedOptions(scopedItemField(), ['scope' => ['owner' => 5]]);

    expect($options)->toHaveCount(1)
        ->and($options[0]['label'])->toBe('Item for owner 5');
});

it('returns no options when a strict scope has no value', function () {
    seedScopedOptions();

    $options = fetchScopedOptions(scopedItemField());

    expect($options)->toBe([]);
});

it('returns every option when an optional scope has no value', function () {
    seedScopedOptions();

    $field = BelongsTo::make('Item', 'item', RelatedModelResource::class)
        ->scopedBy('owner', 'test_model_id', optional: true);

    expect(fetchScopedOptions($field))->toHaveCount(3);
});

it('ignores scope keys the field did not declare', function () {
    seedScopedOptions();

    $options = fetchScopedOptions(
        BelongsTo::make('Item', 'item', RelatedModelResource::class),
        ['scope' => ['title' => 'Item for owner 5']]
    );

    expect($options)->toHaveCount(3);
});

it('combines a scope with a search term', function () {
    seedScopedOptions();

    RelatedModel::query()->create([
        'title' => 'Another item for owner 5',
        'test_model_id' => 5,
    ]);

    $options = fetchScopedOptions(scopedItemField(), [
        'scope' => ['owner' => 5],
        'search' => 'Another',
    ]);

    expect($options)->toHaveCount(1)
        ->and($options[0]['label'])->toBe('Another item for owner 5');
});

it('reads the scope from the request when it is not passed in params', function () {
    seedScopedOptions();

    $request = createNadotaRequest(['scope' => ['owner' => 51]]);

    $options = (new BelongsToOptionsStrategy())->fetchOptions(
        $request,
        createTestResource(),
        scopedItemField()
    );

    expect($options)->toHaveCount(1)
        ->and($options[0]['label'])->toBe('Item for owner 51');
});
