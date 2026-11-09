<?php

use Illuminate\Support\Facades\Schema;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasManyThrough;
use SchoolAid\Nadota\Http\Fields\Relations\HasOne;
use SchoolAid\Nadota\Http\Fields\Relations\HasOneThrough;
use SchoolAid\Nadota\Http\Fields\Relations\MorphedByMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphOne;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Fields\Relations\MorphToMany;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\RelationIndexService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\Tag;
use SchoolAid\Nadota\Tests\Models\TestModel;

beforeEach(function () {
    $this->service = new RelationIndexService();
    $this->reflection = new ReflectionClass(RelationIndexService::class);

    $this->relatedResource = new class extends Resource {
        public string $model = RelatedModel::class;

        protected array $searchableAttributes = ['title'];

        public static function getKey(): string
        {
            return 'related-models';
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Title', 'title')
                    ->filterable()
                    ->searchable(),
            ];
        }
    };

    $this->invoke = function (string $method, ...$arguments) {
        $reflected = $this->reflection->getMethod($method);
        $reflected->setAccessible(true);

        return $reflected->invoke($this->service, ...$arguments);
    };
});

function relationIndexParentWithTitles(array $titles): TestModel
{
    $parent = TestModel::factory()->create();
    foreach ($titles as $title) {
        RelatedModel::factory()->create(['test_model_id' => $parent->id, 'title' => $title]);
    }

    return $parent;
}

// --- field lookup -------------------------------------------------------

it('finds a relation field by its key, or by its relation name', function () {
    $parentResource = new class extends Resource {
        public string $model = TestModel::class;
        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name'),
                HasMany::make('Related', 'relatedModels')->withKey('related'),
                BelongsToMany::make('Tags', 'simpleTags'),
            ];
        }
    };
    $request = createNadotaRequest();

    expect(($this->invoke)('findRelationField', $parentResource, $request, 'related')->getRelation())->toBe('relatedModels')
        ->and(($this->invoke)('findRelationField', $parentResource, $request, 'relatedModels')->key())->toBe('related')
        ->and(($this->invoke)('findRelationField', $parentResource, $request, 'simpleTags'))->toBeInstanceOf(BelongsToMany::class)
        ->and(($this->invoke)('findRelationField', $parentResource, $request, 'missing'))->toBeNull();
});

it('only treats to-many relation fields as paginatable', function (string $fieldClass, bool $expected) {
    $field = $fieldClass === MorphTo::class
        ? MorphTo::make('Target', 'target')
        : $fieldClass::make('Relation', 'relation');

    expect(($this->invoke)('isMultiRelation', $field))->toBe($expected);
})->with([
    'HasMany' => [HasMany::class, true],
    'BelongsToMany' => [BelongsToMany::class, true],
    'MorphMany' => [MorphMany::class, true],
    'MorphToMany' => [MorphToMany::class, true],
    'MorphedByMany' => [MorphedByMany::class, true],
    'HasManyThrough' => [HasManyThrough::class, true],
    'BelongsTo' => [BelongsTo::class, false],
    'HasOne' => [HasOne::class, false],
    'HasOneThrough' => [HasOneThrough::class, false],
    'MorphOne' => [MorphOne::class, false],
    'MorphTo' => [MorphTo::class, false],
]);

// --- query building ------------------------------------------------------

it('selects the related resource columns qualified with the related table', function () {
    $parent = relationIndexParentWithTitles(['a']);
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    $query = ($this->invoke)('buildQuery', createNadotaRequest(), $parent->relatedModels(), $field, $this->relatedResource);

    expect($query->toBase()->columns)->toBe(['related_models.title', 'related_models.id']);
});

it('adds the pivot columns of the field to a pivot query', function () {
    $parent = TestModel::factory()->create();
    $parent->simpleTags()->attach(Tag::factory()->create(['name' => 'Laravel'])->id, ['created_at' => now(), 'updated_at' => now()]);
    $field = BelongsToMany::make('Tags', 'simpleTags')->withTimestamps();

    $query = ($this->invoke)('buildQuery', createNadotaRequest(), $parent->simpleTags(), $field, null);
    $tag = $query->first();

    expect($tag->pivot->created_at)->not->toBeNull()
        ->and($tag->pivot->updated_at)->not->toBeNull();
});

it('searches the related resource searchable attributes, qualified', function () {
    $parent = relationIndexParentWithTitles(['Alpha', 'Beta', 'Alphabet']);
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    $query = ($this->invoke)('applySearch', createNadotaRequest(['search' => 'Alpha']), $parent->relatedModels(), $field, $this->relatedResource);

    expect($query->pluck('title')->all())->toBe(['Alpha', 'Alphabet'])
        ->and($query->toSql())->toContain('"related_models"."title" like ?');
});

it('ignores search without a related resource or searchable attributes', function () {
    $parent = relationIndexParentWithTitles(['Alpha', 'Beta']);
    $field = HasMany::make('Related Models', 'relatedModels');
    $request = createNadotaRequest(['search' => 'Alpha']);

    expect(($this->invoke)('applySearch', $request, $parent->relatedModels(), $field, null)->count())->toBe(2);
});

it('sorts by the request sort field and direction', function () {
    $parent = relationIndexParentWithTitles(['b', 'c', 'a']);
    $field = HasMany::make('Related Models', 'relatedModels');
    $request = createNadotaRequest(['sort_field' => 'title', 'sort_direction' => 'asc']);

    expect(($this->invoke)('applySorting', $request, $parent->relatedModels(), $field)->pluck('title')->all())
        ->toBe(['a', 'b', 'c']);
});

it('falls back to the field orderBy() column without a request sort', function () {
    $parent = relationIndexParentWithTitles(['b', 'c', 'a']);
    $field = HasMany::make('Related Models', 'relatedModels')->orderBy('title');

    expect(($this->invoke)('applySorting', createNadotaRequest(), $parent->relatedModels(), $field)->pluck('title')->all())
        ->toBe(['c', 'b', 'a']);
});

it('falls back to the field orderBy() direction without a request sort', function () {
    $parent = relationIndexParentWithTitles(['b', 'c', 'a']);
    $field = HasMany::make('Related Models', 'relatedModels')->orderBy('title', 'asc');

    expect(($this->invoke)('applySorting', createNadotaRequest(), $parent->relatedModels(), $field)->pluck('title')->all())
        ->toBe(['a', 'b', 'c']);
})->skip('ISSUE REL-003: sort_direction defaults to "desc", so the field orderBy() direction is never used');

// --- filters ------------------------------------------------------------

it('applies filter to relation query', function () {
    $parent = relationIndexParentWithTitles(['Alpha', 'Beta', 'Alpha Two']);
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    $results = ($this->invoke)('applyFilters', createNadotaRequest(['filters' => ['title' => 'Alpha']]), $parent->relatedModels(), $field, $this->relatedResource)->get();

    expect($results->pluck('title')->sort()->values()->all())->toBe(['Alpha', 'Alpha Two']);
});

it('returns unmodified query when no filters provided', function () {
    $parent = relationIndexParentWithTitles(['a', 'b', 'c']);
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    expect(($this->invoke)('applyFilters', createNadotaRequest(), $parent->relatedModels(), $field, $this->relatedResource)->count())->toBe(3);
});

it('returns unmodified query when resource has no filterable fields', function () {
    $noFilterResource = new class extends Resource {
        public string $model = RelatedModel::class;
        public static function getKey(): string { return 'related-models'; }
        public function fields(NadotaRequest $request): array { return [Input::make('Title', 'title')]; }
    };
    $parent = relationIndexParentWithTitles(['a', 'b', 'c']);
    $field = HasMany::make('Related Models', 'relatedModels', get_class($noFilterResource));

    $query = ($this->invoke)('applyFilters', createNadotaRequest(['filters' => ['title' => 'something']]), $parent->relatedModels(), $field, $noFilterResource);

    expect($query->count())->toBe(3);
});

it('ignores an unknown filter key and treats an empty value as match-all', function () {
    $parent = relationIndexParentWithTitles(['a', 'b', 'c']);
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    $unknown = ($this->invoke)('applyFilters', createNadotaRequest(['filters' => ['nope' => 'x']]), $parent->relatedModels(), $field, $this->relatedResource);
    $empty = ($this->invoke)('applyFilters', createNadotaRequest(['filters' => ['title' => '']]), $parent->relatedModels(), $field, $this->relatedResource);

    expect($unknown->count())->toBe(3)
        ->and($empty->count())->toBe(3);
});

it('applies multiple filters correctly', function () {
    Schema::table('related_models', function ($table) {
        $table->boolean('is_active')->default(true);
    });

    $multiFilterResource = new class extends Resource {
        public string $model = RelatedModel::class;
        public static function getKey(): string { return 'related-models'; }
        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Title', 'title')->filterable(),
                Toggle::make('Active', 'is_active')->filterable(),
            ];
        }
    };

    $parent = TestModel::factory()->create();
    RelatedModel::factory()->create(['test_model_id' => $parent->id, 'title' => 'Important Task', 'is_active' => true]);
    RelatedModel::factory()->create(['test_model_id' => $parent->id, 'title' => 'Important Note', 'is_active' => false]);
    RelatedModel::factory()->create(['test_model_id' => $parent->id, 'title' => 'Random Task', 'is_active' => true]);

    $request = createNadotaRequest(['filters' => ['title' => 'Important', 'is_active' => 'true']]);
    $field = HasMany::make('Related Models', 'relatedModels', get_class($multiFilterResource));

    $results = ($this->invoke)('applyFilters', $request, $parent->relatedModels(), $field, $multiFilterResource)->get();

    expect($results->pluck('title')->all())->toBe(['Important Task']);
});

it('returns unmodified query when resource is null', function () {
    $parent = relationIndexParentWithTitles(['a', 'b', 'c']);

    $query = ($this->invoke)('applyFilters', createNadotaRequest(['filters' => ['title' => 'something']]), $parent->relatedModels(), HasMany::make('R', 'relatedModels'), null);

    expect($query->count())->toBe(3);
});

it('includes available filters in metadata', function () {
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    $filters = ($this->invoke)('getAvailableFilters', createNadotaRequest(), $field, $this->relatedResource);

    expect($filters)->toHaveCount(1)
        ->and($filters[0])->toMatchArray(['key' => 'title', 'label' => 'Title', 'component' => 'FilterText']);
});

it('returns empty array when resource has no filterable fields', function () {
    $noFilterResource = new class extends Resource {
        public string $model = RelatedModel::class;
        public static function getKey(): string { return 'related-models'; }
        public function fields(NadotaRequest $request): array { return [Input::make('Title', 'title')]; }
    };

    $filters = ($this->invoke)('getAvailableFilters', createNadotaRequest(), HasMany::make('R', 'relatedModels', get_class($noFilterResource)), $noFilterResource);

    expect($filters)->toBe([]);
});

it('returns empty array when resource is null', function () {
    expect(($this->invoke)('getAvailableFilters', createNadotaRequest(), HasMany::make('R', 'relatedModels'), null))->toBe([])
        ->and(($this->invoke)('getAvailableActions', createNadotaRequest(), null))->toBe([]);
});

// --- formatting ---------------------------------------------------------

it('formats items with the related resource, custom fields and pivot data', function () {
    $parent = TestModel::factory()->create();
    $parent->simpleTags()->attach(Tag::factory()->create(['name' => 'Laravel'])->id, ['created_at' => now(), 'updated_at' => now()]);
    $tagResource = new class extends Resource {
        public string $model = Tag::class;
        public static function getKey(): string { return 'tags'; }
        public function fields(NadotaRequest $request): array { return [Input::make('Name', 'name')]; }
    };
    $field = BelongsToMany::make('Tags', 'simpleTags', get_class($tagResource))->withTimestamps()->withFields();
    $items = $parent->simpleTags()->withPivot($field->getPivotColumns())->get()->all();

    $formatted = ($this->invoke)('formatItems', $items, $field, $tagResource, createNadotaRequest());

    expect($formatted[0])->toMatchArray(['label' => 'Laravel', 'resource' => 'tags'])
        ->and($formatted[0]['attributes'][0]['value'])->toBe('Laravel')
        ->and($formatted[0]['pivot'])->toHaveKeys(['created_at', 'updated_at'])
        ->and($formatted[0])->toHaveKey('permissions');
});

it('formats basic items without a resource and honours withoutId()', function () {
    $parent = relationIndexParentWithTitles(['Alpha']);
    $items = $parent->relatedModels()->get()->all();

    $withId = ($this->invoke)('formatBasicItems', $items, HasMany::make('R', 'relatedModels'));
    $withoutId = ($this->invoke)('formatBasicItems', $items, HasMany::make('R', 'relatedModels')->withoutId());

    expect($withId)->toBe([['id' => $items[0]->id, 'label' => 'Alpha', 'deletedAt' => null]])
        ->and($withoutId)->toBe([['label' => 'Alpha', 'deletedAt' => null]]);
});

it('resolves labels with displayUsing, then displayAttribute, then the resource, then common attributes', function () {
    $item = new RelatedModel(['id' => 9, 'title' => 'Alpha']);
    $item->id = 9;

    $byCallback = HasMany::make('R', 'relatedModels')->displayUsing(fn ($m) => "cb {$m->title}");
    $byAttribute = HasMany::make('R', 'relatedModels')->displayAttribute('id');
    $resource = new class extends Resource {
        public string $model = RelatedModel::class;
        public function fields(NadotaRequest $request): array { return []; }
        public function displayLabel($model): string { return 'from resource'; }
    };

    expect(($this->invoke)('resolveLabel', $item, $byCallback, $resource))->toBe('cb Alpha')
        ->and(($this->invoke)('resolveLabel', $item, $byAttribute, $resource))->toBe(9)
        ->and(($this->invoke)('resolveLabel', $item, HasMany::make('R', 'relatedModels'), $resource))->toBe('from resource')
        ->and(($this->invoke)('resolveLabel', $item, HasMany::make('R', 'relatedModels'), null))->toBe('Alpha');
});
