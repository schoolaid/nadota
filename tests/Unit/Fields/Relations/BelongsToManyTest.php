<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\BelongsToManyOptionsStrategy;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Models\LabeledTag;
use SchoolAid\Nadota\Tests\Models\Tag;
use SchoolAid\Nadota\Tests\Resources\TestResource;

it('can be instantiated', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags');

    // The relation name is the field key; the attribute stays empty so the
    // field never adds a column to the parent SELECT.
    expect($field)->toBeField()
        ->and($field->key())->toBe('simpleTags')
        ->and($field->getRelation())->toBe('simpleTags')
        ->and($field->getAttribute())->toBe('')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->fieldData->label)->toBe('Tags');
});

it('returns belongsToMany relation type', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags');

    expect($field->relationType())->toBe('belongsToMany');
});

it('can set related model', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->model(Tag::class);

    expect($field->getModel())->toBe(Tag::class);
});

it('can be made searchable', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->searchable();

    expect($field->searchable)->toBeTrue();
});

it('can be made not searchable', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->searchable()
        ->notSearchable();

    expect($field->isSearchable())->toBeFalse();
});

it('can set display attribute', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->displayAttribute('title');

    expect($field->getAttributeForDisplay())->toBe('title');
});

it('can define pivot columns', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->withPivot(['created_at', 'updated_at']);

    expect($field->getPivotColumns())->toBe(['created_at', 'updated_at'])
        ->and($field->hasPivotColumns())->toBeTrue();
});

it('resolves multiple related models without pivot', function () {
    $testModel = TestModel::factory()->create();
    $tag1 = Tag::factory()->create(['name' => 'Laravel']);
    $tag2 = Tag::factory()->create(['name' => 'PHP']);

    $testModel->simpleTags()->attach([$tag1->id, $tag2->id]);

    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->displayAttribute('name');

    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);

    expect($value['data'])->toBeArray()
        ->toHaveCount(2)
        ->and($value['data'][0])->toHaveKey('id', $tag1->id)
        ->and($value['data'][0])->toHaveKey('label', 'Laravel')
        ->and($value['data'][0])->not->toHaveKey('pivot')
        ->and($value['data'][1])->toHaveKey('id', $tag2->id)
        ->and($value['data'][1])->toHaveKey('label', 'PHP')
        ->and($value['meta'])->toHaveKey('total', 2);
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
        ->displayAttribute('name')
        ->withPivot(['created_at', 'updated_at']);

    // Eager load the way ManagesRelationLoading does, applying the field's pivot columns.
    $testModel->load([
        'simpleTags' => fn ($query) => $query->withPivot($field->getPivotColumns()),
    ]);

    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);

    expect($value['data'][0])->toHaveKey('pivot')
        ->and($value['data'][0]['pivot']['created_at'])->not->toBeNull()
        ->and($value['data'][0]['pivot']['updated_at'])->not->toBeNull()
        ->and($value['meta'])->toHaveKey('pivotColumns', ['created_at', 'updated_at']);
});

it('returns an empty collection when no relationships exist', function () {
    $testModel = TestModel::factory()->create();

    $field = BelongsToMany::make('Tags', 'simpleTags');

    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);

    expect($value['data'])->toBeArray()->toBeEmpty()
        ->and($value['meta'])->toHaveKey('total', 0)
        ->and($value['meta'])->toHaveKey('hasMore', false);
});

it('generates options for selection', function () {
    $tagResource = new class extends Resource {
        public string $model = Tag::class;
        public static function getKey(): string { return 'tags'; }
        public function fields(NadotaRequest $request): array
        {
            return [Input::make('Name', 'name')];
        }
    };

    $tags = Tag::factory()->count(3)->create();

    $field = BelongsToMany::make('Tags', 'simpleTags', get_class($tagResource))
        ->displayAttribute('name');

    $strategy = new BelongsToManyOptionsStrategy();
    $options = $strategy->fetchOptions(createNadotaRequest(), new TestResource(), $field);

    expect($strategy->canHandle($field))->toBeTrue()
        ->and($options)->toHaveCount(3)
        ->and(collect($options)->pluck('label', 'value')->all())
        ->toEqual($tags->pluck('name', 'id')->all());
});

it('labels options with the display attribute even when the related resource has no field for it', function () {
    $tagResource = new class extends Resource {
        public string $model = Tag::class;
        public static function getKey(): string { return 'tags'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $tags = Tag::factory()->count(2)->create();

    $field = BelongsToMany::make('Tags', 'simpleTags', get_class($tagResource))
        ->displayAttribute('name');

    $options = (new BelongsToManyOptionsStrategy())
        ->fetchOptions(createNadotaRequest(), new TestResource(), $field);

    expect(collect($options)->pluck('label', 'value')->all())
        ->toEqual($tags->pluck('name', 'id')->all());
});

it('does not select a display attribute that is an accessor', function () {
    $tagResource = new class extends Resource {
        public string $model = LabeledTag::class;
        public static function getKey(): string { return 'labeled-tags'; }
        public function fields(NadotaRequest $request): array
        {
            return [Input::make('Name', 'name')];
        }
    };

    $tags = Tag::factory()->count(2)->create();

    $field = BelongsToMany::make('Tags', 'simpleTags', get_class($tagResource))
        ->displayAttribute('label_text');

    DB::enableQueryLog();

    $options = (new BelongsToManyOptionsStrategy())
        ->fetchOptions(createNadotaRequest(), new TestResource(), $field);

    // MySQL rejects an unknown column; SQLite reads it as a string literal, so the
    // SQL itself is checked. The accessor is computed from the selected columns.
    expect(collect(DB::getQueryLog())->pluck('query')->implode(' '))
        ->not->toContain('label_text')
        ->and(collect($options)->pluck('label', 'value')->all())
        ->toEqual($tags->mapWithKeys(fn ($tag) => [$tag->id => "#{$tag->id} {$tag->name}"])->all());
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

it('serializes to array correctly', function () {
    $testModel = TestModel::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Laravel']);
    $testModel->simpleTags()->attach($tag->id);

    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->displayAttribute('name')
        ->searchable()
        ->withPivot(['created_at'])
        ->sortable()
        ->filterable();

    $request = createNadotaRequest();
    $array = $field->toArray($request, $testModel, new TestResource());

    expect($array)
        ->toHaveKey('label', 'Tags')
        ->toHaveKey('key', 'simpleTags')
        ->toHaveKey('searchable', true)
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->toHaveKey('pivotFields', [])
        ->toHaveKey('value')
        ->toHaveKey('props');

    expect($array['props'])
        ->toHaveKey('relationType', 'belongsToMany')
        ->toHaveKey('withPivot', true)
        ->toHaveKey('pivotColumns', ['created_at'])
        ->and($array['props']['urls']['options'])
        ->toContain("/resource/field/simpleTags/options?resourceId={$testModel->id}")
        ->and($array['value']['data'][0])->toHaveKey('label', 'Laravel');
});

it('falls back to the name attribute for the label when no display attribute is set', function () {
    $testModel = TestModel::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Laravel']);
    $testModel->simpleTags()->attach($tag->id);

    $field = BelongsToMany::make('Tags', 'simpleTags');

    $value = $field->resolve(createNadotaRequest(), $testModel, null);

    expect($field->getAttributeForDisplay())->toBeNull()
        ->and($value['data'][0])->toHaveKey('label', 'Laravel');
});

it('is not searchable by default', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags');

    expect($field->searchable)->toBeFalse();
});

it('has no pivot fields by default', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags');

    expect($field->pivotFields)->toBeEmpty();
});

it('handles pivot field configurations', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->withPivot(['created_at'])
        ->pivotFields([
            Input::make('Order', 'order'),
            Input::make('Notes', 'notes'),
        ]);

    $array = $field->toArray(createNadotaRequest());

    expect($field->pivotFields)->toHaveCount(2)
        ->and($field->getPivotColumns())->toBe(['created_at', 'order', 'notes'])
        ->and($array['pivotFields'])->toHaveCount(2)
        ->and(collect($array['pivotFields'])->pluck('key')->all())->toBe(['order', 'notes']);
});
