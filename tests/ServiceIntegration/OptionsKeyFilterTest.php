<?php

use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\BelongsToOptionsStrategy;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\RelatedModelResource;

/**
 * The frontend resolves the label of a saved BelongsTo with `filters[id]=<id>`. A plain filter is a
 * LIKE '%value%', so id 5 also matched 15 and 51 and, past the options limit, the saved option could
 * be missing. Keys and foreign keys must match exactly; text columns keep LIKE.
 */
function seedKeyFilterOptions(): void
{
    foreach ([5, 15, 51] as $id) {
        $owner = new TestModel(['name' => "Owner {$id}"]);
        $owner->id = $id;
        $owner->save();

        $item = new RelatedModel(['title' => "Item {$id}", 'test_model_id' => $id]);
        $item->id = $id;
        $item->save();
    }
}

function fetchKeyFilterOptions(array $filters): array
{
    return (new BelongsToOptionsStrategy())->fetchOptions(
        createNadotaRequest(),
        createTestResource(),
        BelongsTo::make('Item', 'item', RelatedModelResource::class),
        ['filters' => $filters]
    );
}

it('matches the model key exactly', function () {
    seedKeyFilterOptions();

    $options = fetchKeyFilterOptions(['id' => '5']);

    expect($options)->toHaveCount(1)
        ->and($options[0]['value'])->toBe(5);
});

it('matches a foreign key column exactly', function () {
    seedKeyFilterOptions();

    expect(fetchKeyFilterOptions(['test_model_id' => '5']))->toHaveCount(1);
});

it('keeps LIKE for text columns', function () {
    seedKeyFilterOptions();

    // "Item 5" and "Item 51"
    expect(fetchKeyFilterOptions(['title' => 'Item 5']))->toHaveCount(2);
});
