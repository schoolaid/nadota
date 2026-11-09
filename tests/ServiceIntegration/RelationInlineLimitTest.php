<?php

use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasManyThrough;
use SchoolAid\Nadota\Http\Fields\Relations\MorphedByMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphToMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Label;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
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

function inlineLimitResource(string $model, array $fields): Resource
{
    $resource = new class extends Resource
    {
        public string $model = TestModel::class;

        public array $inlineFields = [];

        public function fields(NadotaRequest $request): array
        {
            return $this->inlineFields;
        }
    };
    $resource->model = $model;
    $resource->inlineFields = $fields;

    return $resource;
}

function inlineLimitEagerLoad(string $model, array $fields, $query)
{
    $request = createNadotaRequest();
    $resource = inlineLimitResource($model, $fields);
    $request->setResource($resource);

    return $query->with($resource->getEagerLoadRelations($request, collect($fields)))->get();
}

function inlineLimitSeedRelated(TestModel $parent, array $titles): void
{
    foreach ($titles as $title) {
        RelatedModel::create(['title' => $title, 'test_model_id' => $parent->id]);
    }
}

function inlineLimitLabels(array $value): array
{
    return array_column($value['data'], 'label');
}

it('returns every related row when no limit is set', function () {
    $parent = TestModel::factory()->create();
    inlineLimitSeedRelated($parent, range(1, 15));

    $value = HasMany::make('Related', 'relatedModels')->resolve(createNadotaRequest(), $parent, null);

    expect($value['data'])->toHaveCount(15)
        ->and($value['meta']['hasMore'])->toBeFalse();
});

it('applies limit and orderBy on a lazy load', function () {
    $parent = TestModel::factory()->create();
    inlineLimitSeedRelated($parent, ['b', 'e', 'a', 'd', 'c']);

    $field = HasMany::make('Related', 'relatedModels')->limit(3)->orderBy('title', 'asc');
    $value = $field->resolve(createNadotaRequest(), $parent, null);

    expect(inlineLimitLabels($value))->toBe(['a', 'b', 'c'])
        ->and($value['meta']['hasMore'])->toBeTrue();
});

it('does not report hasMore when exactly limit rows exist', function () {
    $parent = TestModel::factory()->create();
    inlineLimitSeedRelated($parent, ['a', 'b', 'c']);

    $value = HasMany::make('Related', 'relatedModels')->limit(3)->resolve(createNadotaRequest(), $parent, null);

    expect($value['data'])->toHaveCount(3)
        ->and($value['meta']['hasMore'])->toBeFalse();
});

it('applies limit and orderBy per parent when eager loaded', function () {
    $first = TestModel::factory()->create();
    $second = TestModel::factory()->create();
    inlineLimitSeedRelated($first, ['c', 'a', 'd', 'b']);
    inlineLimitSeedRelated($second, ['z', 'x', 'y']);

    $field = HasMany::make('Related', 'relatedModels')->limit(2)->orderBy('title', 'desc');
    $models = inlineLimitEagerLoad(TestModel::class, [$field], TestModel::query()->orderBy('id'));

    // Loads limit + 1 rows per parent so hasMore is exact
    expect($models[0]->relatedModels)->toHaveCount(3)
        ->and($models[1]->relatedModels)->toHaveCount(3);

    $request = createNadotaRequest();
    $firstValue = $field->resolve($request, $models[0], null);
    $secondValue = $field->resolve($request, $models[1], null);

    expect(inlineLimitLabels($firstValue))->toBe(['d', 'c'])
        ->and($firstValue['meta']['hasMore'])->toBeTrue()
        ->and(inlineLimitLabels($secondValue))->toBe(['z', 'y'])
        ->and($secondValue['meta']['hasMore'])->toBeTrue();
});

it('trims a relation loaded elsewhere without the field constraints', function () {
    $parent = TestModel::factory()->create();
    inlineLimitSeedRelated($parent, ['a', 'b', 'c', 'd']);
    $parent->load('relatedModels');

    $value = HasMany::make('Related', 'relatedModels')->limit(2)->resolve(createNadotaRequest(), $parent, null);

    expect($value['data'])->toHaveCount(2)
        ->and($value['meta']['hasMore'])->toBeTrue();
});

it('ignores limit and orderBy in the eager load of a paginated field', function () {
    $parent = TestModel::factory()->create();
    inlineLimitSeedRelated($parent, ['a', 'b', 'c']);
    $query = $parent->relatedModels();

    HasMany::make('Related', 'relatedModels')->limit(1)->orderBy('title')->paginated()->applyInlineConstraints($query);

    expect($query->toBase()->limit)->toBeNull()
        ->and($query->toBase()->orders)->toBeNull();
});

it('keeps an already-qualified orderBy column as given', function () {
    $parent = TestModel::factory()->create();
    $query = $parent->relatedModels();

    HasMany::make('Related', 'relatedModels')->orderBy('related_models.title', 'asc')->applyInlineConstraints($query);

    expect($query->toBase()->orders)->toBe([['column' => 'related_models.title', 'direction' => 'asc']]);
});

it('qualifies orderBy for pivot relations', function () {
    $parent = TestModel::factory()->create();
    $tags = collect(['b', 'a', 'c'])->map(fn ($name) => Tag::create(['name' => $name]));
    $parent->simpleTags()->attach($tags->pluck('id'));

    // created_at exists on both tags and the pivot; unqualified it would be ambiguous
    $field = BelongsToMany::make('Tags', 'simpleTags')->withTimestamps()->limit(2)->orderBy('created_at', 'asc');
    $models = inlineLimitEagerLoad(TestModel::class, [$field], TestModel::query());

    $value = $field->resolve(createNadotaRequest(), $models[0], null);

    expect($value['data'])->toHaveCount(2)
        ->and($value['meta']['hasMore'])->toBeTrue();
});

describe('on the fixture domain', function () {
    beforeEach(fn () => FixtureSchema::create());

    it('limits a BelongsToMany per parent when eager loaded', function () {
        $courses = collect(['d', 'a', 'c', 'b'])->map(fn ($name) => Course::create(['name' => $name]));
        $ana = Student::create(['name' => 'Ana']);
        $beto = Student::create(['name' => 'Beto']);
        $ana->courses()->attach($courses->pluck('id'));
        $beto->courses()->attach($courses->take(2)->pluck('id'));

        $field = BelongsToMany::make('Courses', 'courses')->limit(2)->orderBy('name', 'asc');
        $students = inlineLimitEagerLoad(Student::class, [$field], Student::query()->orderBy('id'));
        $request = createNadotaRequest();

        expect(inlineLimitLabels($field->resolve($request, $students[0], null)))->toBe(['a', 'b'])
            ->and($field->resolve($request, $students[0], null)['meta']['hasMore'])->toBeTrue()
            ->and(inlineLimitLabels($field->resolve($request, $students[1], null)))->toBe(['a', 'd'])
            ->and($field->resolve($request, $students[1], null)['meta']['hasMore'])->toBeFalse();
    });

    it('limits a MorphMany per parent when eager loaded', function () {
        $ana = Student::create(['name' => 'Ana']);
        $beto = Student::create(['name' => 'Beto']);
        foreach (['n1', 'n2', 'n3'] as $body) {
            $ana->notes()->create(['body' => $body]);
        }
        $beto->notes()->create(['body' => 'only']);

        $field = MorphMany::make('Notes', 'notes')->displayAttribute('body')->limit(2)->orderBy('body', 'desc');
        $students = inlineLimitEagerLoad(Student::class, [$field], Student::query()->orderBy('id'));
        $request = createNadotaRequest();

        expect(inlineLimitLabels($field->resolve($request, $students[0], null)))->toBe(['n3', 'n2'])
            ->and(inlineLimitLabels($field->resolve($request, $students[1], null)))->toBe(['only']);
    });

    it('limits a MorphToMany and a MorphedByMany per parent when eager loaded', function () {
        $labels = collect(['c', 'a', 'b'])->map(fn ($name) => Label::create(['name' => $name]));
        $ana = Student::create(['name' => 'Ana']);
        $beto = Student::create(['name' => 'Beto']);
        $ana->labels()->attach($labels->pluck('id'));
        $beto->labels()->attach($labels->first()->id);

        $toMany = MorphToMany::make('Labels', 'labels')->limit(1)->orderBy('name', 'asc');
        $students = inlineLimitEagerLoad(Student::class, [$toMany], Student::query()->orderBy('id'));

        $byMany = MorphedByMany::make('Students', 'students')->limit(1)->orderBy('name', 'desc');
        $loadedLabels = inlineLimitEagerLoad(Label::class, [$byMany], Label::query()->orderBy('id'));
        $request = createNadotaRequest();

        expect(inlineLimitLabels($toMany->resolve($request, $students[0], null)))->toBe(['a'])
            ->and(inlineLimitLabels($toMany->resolve($request, $students[1], null)))->toBe(['c'])
            ->and(inlineLimitLabels($byMany->resolve($request, $loadedLabels[0], null)))->toBe(['Beto'])
            ->and($byMany->resolve($request, $loadedLabels[0], null)['meta']['hasMore'])->toBeTrue()
            ->and(inlineLimitLabels($byMany->resolve($request, $loadedLabels[1], null)))->toBe(['Ana']);
    });

    it('limits a HasManyThrough per parent when eager loaded', function () {
        foreach (['North' => ['d', 'a', 'c'], 'South' => ['z', 'y']] as $schoolName => $names) {
            $school = School::create(['name' => $schoolName]);
            $grade = Grade::create(['name' => "{$schoolName} G", 'school_id' => $school->id]);
            foreach ($names as $name) {
                Student::create(['name' => $name, 'grade_id' => $grade->id]);
            }
        }

        $field = HasManyThrough::make('Students', 'students')->limit(2)->orderBy('name', 'asc');
        $schools = inlineLimitEagerLoad(School::class, [$field], School::query()->orderBy('id'));
        $request = createNadotaRequest();

        expect(inlineLimitLabels($field->resolve($request, $schools[0], null)))->toBe(['a', 'c'])
            ->and($field->resolve($request, $schools[0], null)['meta']['hasMore'])->toBeTrue()
            ->and(inlineLimitLabels($field->resolve($request, $schools[1], null)))->toBe(['y', 'z'])
            ->and($field->resolve($request, $schools[1], null)['meta']['hasMore'])->toBeFalse();
    });
});
