<?php

use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Models\Tag;

it('can be instantiated', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('simpleTags')
        ->and($field->fieldData->name)->toBe('Tags');
});

it('returns belongsToMany relation type', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags');
    
    expect($field->relationType())->toBe('belongsToMany');
});

it('can set related model', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->relatedModel(Tag::class);
    
    expect($field->relatedModelClass)->toBe(Tag::class);
});

it('can be made searchable', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->searchable();
    
    expect($field->searchable)->toBeTrue();
});

it('can be made not searchable', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->searchable(false);
    
    expect($field->searchable)->toBeFalse();
});

it('can set relation attribute', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->relationAttribute('title');
    
    expect($field->getAttributeForDisplay())->toBe('title');
});

it('can define pivot fields', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->withPivot(['created_at', 'updated_at']);
    
    expect($field->pivotFields)->toBe(['created_at', 'updated_at']);
});

it('resolves multiple related models without pivot', function () {
    $testModel = TestModel::factory()->create();
    $tag1 = Tag::factory()->create(['name' => 'Laravel']);
    $tag2 = Tag::factory()->create(['name' => 'PHP']);
    
    $testModel->simpleTags()->attach([$tag1->id, $tag2->id]);
    
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->relatedModel(Tag::class)
        ->relationAttribute('name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);
    
    expect($value)->toBeArray()
        ->toHaveCount(2)
        ->and($value[0])->toHaveKey('key', $tag1->id)
        ->and($value[0])->toHaveKey('label', 'Laravel')
        ->and($value[1])->toHaveKey('key', $tag2->id)
        ->and($value[1])->toHaveKey('label', 'PHP');
});

it('resolves related models with pivot data', function () {
    $testModel = TestModel::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Laravel']);
    
    // Attach with pivot data
    $testModel->simpleTags()->attach($tag->id, [
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->relatedModel(Tag::class)
        ->relationAttribute('name')
        ->withPivot(['created_at', 'updated_at']);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);
    
    expect($value[0])->toHaveKey('pivot')
        ->and($value[0]['pivot'])->toHaveKey('created_at')
        ->and($value[0]['pivot'])->toHaveKey('updated_at');
});

it('returns empty array when no relationships exist', function () {
    $testModel = TestModel::factory()->create();
    
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->relatedModel(Tag::class);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);
    
    expect($value)->toBeArray()->toBeEmpty();
});

it('generates options for selection', function () {
    Tag::factory()->count(3)->create();
    
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->relatedModel(Tag::class)
        ->relationAttribute('name');
    
    $options = $field->getOptions();
    
    expect($options)->toHaveCount(3)
        ->and($options[0])->toHaveKey('value')
        ->and($options[0])->toHaveKey('label')
        ->and($options[0])->toHaveKey('searchable', false);
});

it('includes searchable flag in options when searchable', function () {
    Tag::factory()->create(['name' => 'Laravel']);
    
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->relatedModel(Tag::class)
        ->relationAttribute('name')
        ->searchable();
    
    $options = $field->getOptions();
    
    expect($options[0])->toHaveKey('searchable', true);
});

it('can be made sortable', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('adds validation rules for related model existence', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->relatedModel(Tag::class);
    
    $rules = $field->getRules();
    
    expect($rules)->toContain('exists:tags,id');
});

it('serializes to array correctly', function () {
    $testModel = TestModel::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Laravel']);
    $testModel->simpleTags()->attach($tag->id);
    
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->relatedModel(Tag::class)
        ->relationAttribute('name')
        ->searchable()
        ->withPivot(['created_at'])
        ->sortable()
        ->filterable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $testModel, null);
    
    expect($array)
        ->toHaveKey('name', 'Tags')
        ->toHaveKey('attribute', 'simpleTags')
        ->toHaveKey('relationType', 'belongsToMany')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->toHaveKey('value')
        ->toHaveKey('props');
        
    expect($array['props'])
        ->toHaveKey('searchable', true)
        ->toHaveKey('pivotFields', ['created_at'])
        ->toHaveKey('options');
});

it('uses name as default display attribute', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags');
    
    expect($field->getAttributeForDisplay())->toBe('name');
});

it('is not searchable by default', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags');
    
    expect($field->searchable)->toBeFalse();
});

it('has no pivot fields by default', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags');
    
    expect($field->pivotFields)->toBeEmpty();
});

it('handles complex pivot field configurations', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->withPivot([
            'created_at',
            ['name' => 'order', 'type' => 'integer'],
            'notes'
        ]);
    
    expect($field->pivotFields)->toHaveCount(3);
});