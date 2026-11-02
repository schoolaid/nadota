<?php

use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\Tag;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * Inline (non-paginated) rendering of to-many relation fields.
 *
 * Since 4acdfd4 resolve() read the eager-loaded relation and the eager-load
 * constraint applied neither limit() nor orderBy(), so every related row was
 * returned in database order. An explicit limit() and orderBy() now apply to
 * the eager load (per parent) and to a lazy load alike. Without limit() every
 * row is still returned.
 */

function inlineLimitResource(array $fields): Resource
{
    return new class($fields) extends Resource
    {
        public string $model = TestModel::class;

        public function __construct(private array $inlineFields)
        {
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            return $this->inlineFields;
        }
    };
}

function eagerLoadWith(array $fields, $query)
{
    $request = createNadotaRequest();
    $resource = inlineLimitResource($fields);

    return $query->with($resource->getEagerLoadRelations($request, collect($fields)))->get();
}

function seedRelated(TestModel $parent, array $titles): void
{
    foreach ($titles as $title) {
        RelatedModel::create(['title' => $title, 'test_model_id' => $parent->id]);
    }
}

function labels(array $value): array
{
    return array_column($value['data'], 'label');
}

it('returns every related row when no limit is set', function () {
    $parent = TestModel::factory()->create();
    seedRelated($parent, range(1, 15));

    $value = HasMany::make('Related', 'relatedModels')->resolve(createNadotaRequest(), $parent, null);

    expect($value['data'])->toHaveCount(15)
        ->and($value['meta']['hasMore'])->toBeFalse();
});

it('applies limit and orderBy on a lazy load', function () {
    $parent = TestModel::factory()->create();
    seedRelated($parent, ['b', 'e', 'a', 'd', 'c']);

    $field = HasMany::make('Related', 'relatedModels')->limit(3)->orderBy('title', 'asc');
    $value = $field->resolve(createNadotaRequest(), $parent, null);

    expect(labels($value))->toBe(['a', 'b', 'c'])
        ->and($value['meta']['hasMore'])->toBeTrue();
});

it('does not report hasMore when exactly limit rows exist', function () {
    $parent = TestModel::factory()->create();
    seedRelated($parent, ['a', 'b', 'c']);

    $value = HasMany::make('Related', 'relatedModels')->limit(3)->resolve(createNadotaRequest(), $parent, null);

    expect($value['data'])->toHaveCount(3)
        ->and($value['meta']['hasMore'])->toBeFalse();
});

it('applies limit and orderBy per parent when eager loaded', function () {
    $first = TestModel::factory()->create();
    $second = TestModel::factory()->create();
    seedRelated($first, ['c', 'a', 'd', 'b']);
    seedRelated($second, ['z', 'x', 'y']);

    $field = HasMany::make('Related', 'relatedModels')->limit(2)->orderBy('title', 'desc');
    $models = eagerLoadWith([$field], TestModel::query()->orderBy('id'));

    // Loads limit + 1 rows per parent so hasMore is exact
    expect($models[0]->relatedModels)->toHaveCount(3)
        ->and($models[1]->relatedModels)->toHaveCount(3);

    $request = createNadotaRequest();
    $firstValue = $field->resolve($request, $models[0], null);
    $secondValue = $field->resolve($request, $models[1], null);

    expect(labels($firstValue))->toBe(['d', 'c'])
        ->and($firstValue['meta']['hasMore'])->toBeTrue()
        ->and(labels($secondValue))->toBe(['z', 'y'])
        ->and($secondValue['meta']['hasMore'])->toBeTrue();
});

it('trims a relation loaded elsewhere without the field constraints', function () {
    $parent = TestModel::factory()->create();
    seedRelated($parent, ['a', 'b', 'c', 'd']);
    $parent->load('relatedModels');

    $value = HasMany::make('Related', 'relatedModels')->limit(2)->resolve(createNadotaRequest(), $parent, null);

    expect($value['data'])->toHaveCount(2)
        ->and($value['meta']['hasMore'])->toBeTrue();
});

it('qualifies orderBy for pivot relations', function () {
    $parent = TestModel::factory()->create();
    $tags = collect(['b', 'a', 'c'])->map(fn ($name) => Tag::create(['name' => $name]));
    $parent->simpleTags()->attach($tags->pluck('id'));

    // created_at exists on both tags and the pivot; unqualified it would be ambiguous
    $field = BelongsToMany::make('Tags', 'simpleTags')->withTimestamps()->limit(2)->orderBy('created_at', 'asc');
    $models = eagerLoadWith([$field], TestModel::query());

    $value = $field->resolve(createNadotaRequest(), $models[0], null);

    expect($value['data'])->toHaveCount(2)
        ->and($value['meta']['hasMore'])->toBeTrue();
});
