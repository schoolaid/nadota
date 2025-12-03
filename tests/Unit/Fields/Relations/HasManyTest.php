<?php

use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Models\RelatedModel;

it('can be instantiated', function () {
    $field = HasMany::make('Related Models', 'relatedModels');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('relatedModels')
        ->and($field->fieldData->label)->toBe('Related Models');
});

it('returns hasMany relation type', function () {
    $field = HasMany::make('Related Models', 'relatedModels');
    
    expect($field->relationType())->toBe('hasMany');
});

it('can set related model', function () {
    $field = HasMany::make('Related Models', 'relatedModels')
        ->relatedModel(RelatedModel::class);
    
    expect($field->relatedModelClass)->toBe(RelatedModel::class);
});

it('can set display limit', function () {
    $field = HasMany::make('Related Models', 'relatedModels')
        ->limit(10);
    
    expect($field->limit)->toBe(10);
});

it('can be made collapsible', function () {
    $field = HasMany::make('Related Models', 'relatedModels')
        ->collapsible();
    
    expect($field->collapsible)->toBeTrue();
});

it('can be made not collapsible', function () {
    $field = HasMany::make('Related Models', 'relatedModels')
        ->collapsible(false);
    
    expect($field->collapsible)->toBeFalse();
});

it('can set relation attribute', function () {
    $field = HasMany::make('Related Models', 'relatedModels')
        ->relationAttribute('title');
    
    expect($field->getAttributeForDisplay())->toBe('title');
});

it('can define nested fields', function () {
    $field = HasMany::make('Related Models', 'relatedModels')
        ->fields(function () {
            return [
                Input::make('Title', 'title'),
                Input::make('Description', 'description'),
            ];
        });
    
    expect($field->nestedFields)->toHaveCount(2)
        ->and($field->nestedFields[0])->toBeInstanceOf(Input::class)
        ->and($field->nestedFields[1])->toBeInstanceOf(Input::class);
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
        ->relatedModel(RelatedModel::class)
        ->relationAttribute('title');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);
    
    expect($value)->toBeArray()
        ->toHaveCount(2)
        ->and($value[0])->toHaveKey('key', $related1->id)
        ->and($value[0])->toHaveKey('label', 'Related Item 1')
        ->and($value[1])->toHaveKey('key', $related2->id)
        ->and($value[1])->toHaveKey('label', 'Related Item 2');
});

it('respects display limit', function () {
    $testModel = TestModel::factory()->create();
    RelatedModel::factory()->count(10)->create(['test_model_id' => $testModel->id]);
    
    $field = HasMany::make('Related Models', 'relatedModels')
        ->relatedModel(RelatedModel::class)
        ->limit(3);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);
    
    expect($value)->toHaveCount(3);
});

it('returns empty array when no relationships exist', function () {
    $testModel = TestModel::factory()->create();
    
    $field = HasMany::make('Related Models', 'relatedModels')
        ->relatedModel(RelatedModel::class);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);
    
    expect($value)->toBeArray()->toBeEmpty();
});

it('includes nested field data when configured', function () {
    $testModel = TestModel::factory()->create();
    $relatedModel = RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Test Item'
    ]);
    
    $field = HasMany::make('Related Models', 'relatedModels')
        ->relatedModel(RelatedModel::class)
        ->relationAttribute('title')
        ->fields(function () {
            return [
                Input::make('Title', 'title'),
            ];
        });
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);
    
    expect($value[0])->toHaveKey('data')
        ->and($value[0]['data'])->toHaveKey('title', 'Test Item');
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
    $relatedModel = RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Test Item'
    ]);
    
    $field = HasMany::make('Related Models', 'relatedModels')
        ->relatedModel(RelatedModel::class)
        ->relationAttribute('title')
        ->limit(10)
        ->collapsible()
        ->sortable()
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $testModel, null);
    
    expect($array)
        ->toHaveKey('label', 'Related Models')
        ->toHaveKey('attribute', 'relatedModels')
        ->toHaveKey('relationType', 'hasMany')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->toHaveKey('value')
        ->toHaveKey('props');
        
    expect($array['props'])
        ->toHaveKey('limit', 10)
        ->toHaveKey('collapsible', true);
});

it('uses name as default display attribute', function () {
    $field = HasMany::make('Related Models', 'relatedModels');
    
    expect($field->getAttributeForDisplay())->toBe('name');
});

it('has default limit of 5', function () {
    $field = HasMany::make('Related Models', 'relatedModels');
    
    expect($field->limit)->toBe(5);
});

it('is not collapsible by default', function () {
    $field = HasMany::make('Related Models', 'relatedModels');
    
    expect($field->collapsible)->toBeFalse();
});