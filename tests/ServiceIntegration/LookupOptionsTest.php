<?php

use SchoolAid\Nadota\Http\Fields\Lookup;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\DefaultOptionsStrategy;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\RelatedModelResource;

beforeEach(function () {
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

function fetchLookupOptions(Lookup $field, array $params = []): array
{
    return (new DefaultOptionsStrategy())->fetchOptions(
        createNadotaRequest(),
        createTestResource(),
        $field,
        $params
    );
}

it('serves lookup options from its resource without a dedicated strategy', function () {
    $field = Lookup::make('Item', 'item')->resource(RelatedModelResource::class);

    expect((new DefaultOptionsStrategy())->canHandle($field))->toBeTrue()
        ->and(fetchLookupOptions($field))->toHaveCount(3);
});

it('narrows a lookup with a scope, so lookups can cascade', function () {
    $field = Lookup::make('Item', 'item')
        ->resource(RelatedModelResource::class)
        ->scopedBy('owner', 'test_model_id');

    $options = fetchLookupOptions($field, ['scope' => ['owner' => 15]]);

    expect($options)->toHaveCount(1)
        ->and($options[0]['label'])->toBe('Item for owner 15');
});

it('returns nothing for a scoped lookup with no value', function () {
    $field = Lookup::make('Item', 'item')
        ->resource(RelatedModelResource::class)
        ->scopedBy('owner', 'test_model_id');

    expect(fetchLookupOptions($field))->toBe([]);
});
