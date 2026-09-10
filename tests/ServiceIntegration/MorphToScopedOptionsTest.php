<?php

use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\MorphToOptionsStrategy;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\RelatedModelResource;

/**
 * MorphToOptionsStrategy builds its own query instead of extending
 * AbstractOptionsStrategy, so it is the one strategy that can silently ignore
 * a declared scopedBy() and fail open (return everything instead of nothing).
 * These tests guard the same strict-empty contract the other strategies get
 * for free from AbstractOptionsStrategy::buildAndExecuteQuery().
 *
 * Owners 5, 15 and 51 exist so an exact match on 5 can be told apart from a
 * LIKE '%5%', which the generic filters[] channel would produce.
 */
function seedMorphOwnersAndItems(): void
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

function scopedMorphField(): MorphTo
{
    return MorphTo::make('Item', 'itemable', ['related' => RelatedModelResource::class])
        ->scopedBy('owner', 'test_model_id');
}

function fetchScopedMorphOptions(MorphTo $field, array $params = []): array
{
    return (new MorphToOptionsStrategy())->fetchOptions(
        createNadotaRequest(),
        createTestResource(),
        $field,
        array_merge(['morphType' => 'related'], $params)
    );
}

it('returns every option for a morph type when the field declares no scopes', function () {
    seedMorphOwnersAndItems();

    $field = MorphTo::make('Item', 'itemable', ['related' => RelatedModelResource::class]);

    expect(fetchScopedMorphOptions($field))->toHaveCount(3);
});

it('narrows morph options by the exact scope value', function () {
    seedMorphOwnersAndItems();

    $options = fetchScopedMorphOptions(scopedMorphField(), ['scope' => ['owner' => 5]]);

    expect($options)->toHaveCount(1)
        ->and($options[0]['label'])->toBe('Item for owner 5');
});

it('returns no morph options when a strict scope has no value', function () {
    seedMorphOwnersAndItems();

    $options = fetchScopedMorphOptions(scopedMorphField());

    expect($options)->toBe([]);
});
