<?php

use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Resources\RelatedModelResource;
use SchoolAid\Nadota\Tests\Resources\TestResource;

it('can be instantiated', function () {
    $field = HasMany::make('Related Models', 'relatedModels');

    // The relation name is the field key; the attribute stays empty so the
    // field never adds a column to the parent SELECT.
    expect($field)->toBeField()
        ->and($field->key())->toBe('relatedModels')
        ->and($field->getRelation())->toBe('relatedModels')
        ->and($field->getAttribute())->toBe('')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->fieldData->label)->toBe('Related Models');
});

it('returns hasMany relation type', function () {
    $field = HasMany::make('Related Models', 'relatedModels');

    $array = $field->toArray(createNadotaRequest());

    expect($field->getType())->toBe('hasMany')
        ->and($array['props'])->toHaveKey('relationType', 'hasMany');
});

it('can set related model and resource', function () {
    $field = HasMany::make('Related Models', 'relatedModels', RelatedModelResource::class)
        ->model(RelatedModel::class);

    expect($field->getResource())->toBe(RelatedModelResource::class)
        ->and($field->getModel())->toBe(RelatedModel::class);
});

it('can set display limit', function () {
    $field = HasMany::make('Related Models', 'relatedModels')
        ->limit(10);

    expect($field->limit)->toBe(10);
});

it('can set display attribute', function () {
    $field = HasMany::make('Related Models', 'relatedModels')
        ->displayAttribute('title');

    expect($field->getAttributeForDisplay())->toBe('title');
});

it('can define custom fields', function () {
    $field = HasMany::make('Related Models', 'relatedModels')
        ->fields([
            Input::make('Title', 'title'),
            Input::make('Description', 'description'),
        ]);

    expect($field->getCustomFields())->toHaveCount(2)
        ->and($field->getCustomFields()[0])->toBeInstanceOf(Input::class)
        ->and($field->getCustomFields()[1])->toBeInstanceOf(Input::class);
});

it('resolves multiple related models', function () {
    $testModel = TestModel::factory()->create(['name' => 'Parent Model']);
    $related1 = RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Related Item 1'
    ]);
    $related2 = RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Related Item 2'
    ]);

    $field = HasMany::make('Related Models', 'relatedModels')
        ->displayAttribute('title');

    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);

    expect($value['data'])->toBeArray()
        ->toHaveCount(2)
        ->and($value['data'][0])->toHaveKey('id', $related1->id)
        ->and($value['data'][0])->toHaveKey('label', 'Related Item 1')
        ->and($value['data'][1])->toHaveKey('id', $related2->id)
        ->and($value['data'][1])->toHaveKey('label', 'Related Item 2')
        ->and($value['meta'])->toHaveKey('total', 2);
});

it('respects display limit', function () {
    $testModel = TestModel::factory()->create();
    RelatedModel::factory()->count(10)->create(['test_model_id' => $testModel->id]);

    $field = HasMany::make('Related Models', 'relatedModels')
        ->limit(3);

    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);

    expect($value['data'])->toHaveCount(3)
        ->and($value['meta'])->toHaveKey('hasMore', true);
});

it('returns an empty collection when no relationships exist', function () {
    $testModel = TestModel::factory()->create();

    $field = HasMany::make('Related Models', 'relatedModels');

    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);

    expect($value['data'])->toBeArray()->toBeEmpty()
        ->and($value['meta'])->toHaveKey('total', 0)
        ->and($value['meta'])->toHaveKey('hasMore', false);
});

it('includes custom field data when configured', function () {
    $testModel = TestModel::factory()->create();
    $relatedModel = RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Test Item'
    ]);

    $field = HasMany::make('Related Models', 'relatedModels', RelatedModelResource::class)
        ->displayAttribute('title')
        ->fields([
            Input::make('Title', 'title'),
        ])
        ->withFields();

    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);

    $item = $value['data'][0];
    $attributes = collect($item['attributes'])->keyBy('key');

    expect($item)->toHaveKey('id', $relatedModel->id)
        ->toHaveKey('label', 'Test Item')
        ->and($attributes)->toHaveCount(1)
        ->and($attributes['title']['value'])->toBe('Test Item');
});

it('omits field data unless withFields is enabled', function () {
    $testModel = TestModel::factory()->create();
    RelatedModel::factory()->create(['test_model_id' => $testModel->id]);

    $field = HasMany::make('Related Models', 'relatedModels', RelatedModelResource::class)
        ->fields([
            Input::make('Title', 'title'),
        ]);

    $value = $field->resolve(createNadotaRequest(), $testModel, null);

    expect($value['data'][0])->not->toHaveKey('attributes');
});

it('can be made sortable', function () {
    $field = HasMany::make('Related Models', 'relatedModels')
        ->sortable();

    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = HasMany::make('Related Models', 'relatedModels')
        ->filterable();

    expect($field->isFilterable())->toBeTrue();
});

it('serializes to array correctly', function () {
    $testModel = TestModel::factory()->create();
    RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Test Item'
    ]);

    $field = HasMany::make('Related Models', 'relatedModels')
        ->displayAttribute('title')
        ->limit(10)
        ->sortable()
        ->filterable();

    $request = createNadotaRequest();
    $array = $field->toArray($request, $testModel, new TestResource());

    expect($array)
        ->toHaveKey('label', 'Related Models')
        ->toHaveKey('key', 'relatedModels')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->toHaveKey('value')
        ->toHaveKey('props');

    expect($array['props'])
        ->toHaveKey('relationType', 'hasMany')
        ->toHaveKey('limit', 10)
        ->and($array['props']['urls']['options'])
        ->toContain("/resource/field/relatedModels/options?resourceId={$testModel->id}")
        ->and($array['value']['data'][0])->toHaveKey('label', 'Test Item');
});

it('falls back to common attributes for the label when no display attribute is set', function () {
    $testModel = TestModel::factory()->create();
    RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Fallback Title'
    ]);

    $field = HasMany::make('Related Models', 'relatedModels');

    $value = $field->resolve(createNadotaRequest(), $testModel, null);

    // RelatedModel has no `name`, so the next common attribute (`title`) is used.
    expect($field->getAttributeForDisplay())->toBeNull()
        ->and($value['data'][0])->toHaveKey('label', 'Fallback Title');
});

it('has no default limit', function () {
    // Renders every related row unless limit() is set
    $field = HasMany::make('Related Models', 'relatedModels');

    expect($field->getLimit())->toBeNull();
});
