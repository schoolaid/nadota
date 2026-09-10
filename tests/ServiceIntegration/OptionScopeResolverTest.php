<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Services\FieldOptions\OptionScopeResolver;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * Owners 5, 15 and 51 exist so that an exact match on 5 can be told apart
 * from a LIKE '%5%', which would also match 15 and 51.
 */
function seedOwnersAndItems(): void
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

function scopedField(string|Closure|null $target = 'test_model_id', bool $optional = false)
{
    return Input::make('Item', 'item_id')->scopedBy('owner', $target, $optional);
}

it('returns the query untouched when no scopes are declared', function () {
    seedOwnersAndItems();

    $query = RelatedModel::query();

    $result = (new OptionScopeResolver())->apply($query, Input::make('Item', 'item_id'), []);

    expect($result)->not->toBeNull()
        ->and($result->count())->toBe(3);
});

it('filters by an exact column match, not a partial one', function () {
    seedOwnersAndItems();

    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField(),
        ['owner' => 5]
    );

    expect($result->pluck('title')->all())->toBe(['Item for owner 5']);
});

it('uses whereIn for array values', function () {
    seedOwnersAndItems();

    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField(),
        ['owner' => [5, 51]]
    );

    // No orderBy is applied by the resolver, so sort before comparing instead
    // of relying on the database's incidental row order.
    expect($result->pluck('title')->sort()->values()->all())
        ->toBe(['Item for owner 5', 'Item for owner 51']);
});

it('returns null when a strict scope has no value', function () {
    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField(),
        []
    );

    expect($result)->toBeNull();
});

it('treats empty string, empty array and the string null as no value', function ($value) {
    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField(),
        ['owner' => $value]
    );

    expect($result)->toBeNull();
})->with([[''], [[]], ['null'], [null]]);

it('skips an optional scope with no value', function () {
    seedOwnersAndItems();

    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField('test_model_id', optional: true),
        []
    );

    expect($result)->not->toBeNull()
        ->and($result->count())->toBe(3);
});

it('applies a closure scope', function () {
    seedOwnersAndItems();

    $field = scopedField(
        fn ($query, $value) => $query->whereHas(
            'testModel',
            fn ($owner) => $owner->where('name', "Owner {$value}")
        )
    );

    $result = (new OptionScopeResolver())->apply(RelatedModel::query(), $field, ['owner' => 15]);

    expect($result->pluck('title')->all())->toBe(['Item for owner 15']);
});

it('ignores request keys that were not declared as scopes', function () {
    seedOwnersAndItems();

    $field = Input::make('Item', 'item_id')->scopedBy('owner', 'test_model_id');

    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        $field,
        ['owner' => 5, 'title' => 'Item for owner 51']
    );

    expect($result->pluck('title')->all())->toBe(['Item for owner 5']);
});

it('applies several scopes as AND', function () {
    seedOwnersAndItems();

    $field = Input::make('Item', 'item_id')
        ->scopedBy('owner', 'test_model_id')
        ->scopedBy('name', 'title');

    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        $field,
        ['owner' => 5, 'name' => 'Item for owner 51']
    );

    expect($result->count())->toBe(0);
});

it('coerces a non-array scope value to empty instead of throwing', function () {
    seedOwnersAndItems();

    // A plain `?scope=abc` query string arrives here as a string, not an
    // array. Every options endpoint calls apply() unconditionally, so this
    // must not TypeError for fields with no declared scopes...
    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        Input::make('Item', 'item_id'),
        'abc'
    );

    expect($result)->not->toBeNull()
        ->and($result->count())->toBe(3);
});

it('treats a non-array scope value as missing for a strict scope', function () {
    // ...nor for fields that do declare scopes: a strict scope with an
    // unusable value must still fall back to the null/unsatisfiable contract.
    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField(),
        'abc'
    );

    expect($result)->toBeNull();
});

it('drops non-scalar members of an array scope value before whereIn', function () {
    seedOwnersAndItems();

    // scope[owner][a][b]=1 style input reaches here as an array containing
    // arrays. whereIn() would fail at the driver if given those as-is, so
    // non-scalar members are dropped; the remaining scalar members still
    // narrow the query.
    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField(),
        ['owner' => [5, ['a' => ['b' => 1]], 51]]
    );

    expect($result->pluck('title')->sort()->values()->all())
        ->toBe(['Item for owner 5', 'Item for owner 51']);
});

it('treats an array scope value of only non-scalars as missing for a strict scope', function () {
    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField(),
        ['owner' => [['a' => 1], ['b' => 2]]]
    );

    expect($result)->toBeNull();
});

it('skips an optional scope whose array value is only non-scalars', function () {
    seedOwnersAndItems();

    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField('test_model_id', optional: true),
        ['owner' => [['a' => 1]]]
    );

    expect($result)->not->toBeNull()
        ->and($result->count())->toBe(3);
});
