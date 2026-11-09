<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Filters\RelationFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\BelongsToManyOptionsStrategy;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\CourseResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;
use SchoolAid\Nadota\Tests\Models\LabeledTag;
use SchoolAid\Nadota\Tests\Models\Tag;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;

beforeEach(function () {
    FixtureSchema::create();
});

/** @return array{0: Student, 1: Course, 2: Course} */
function belongsToManyEnrolledStudent(): array
{
    $student = Student::create(['name' => 'Ana']);
    $math = Course::create(['name' => 'Math']);
    $art = Course::create(['name' => 'Art']);
    $student->courses()->attach([$math->id => ['score' => 90], $art->id => ['score' => 75]]);

    return [$student, $math, $art];
}

function belongsToManyEagerLoad(BelongsToMany $field, Student $student): Student
{
    $resource = new class extends Resource {
        public string $model = Student::class;
        public array $declared = [];
        public function fields(NadotaRequest $request): array { return $this->declared; }
    };
    $resource->declared = [$field];

    return Student::with($resource->getEagerLoadRelations(createNadotaRequest()))->find($student->id);
}

// --- construction -------------------------------------------------------

it('uses the relation name as key and leaves the attribute empty', function () {
    $field = BelongsToMany::make('Courses', 'courses', CourseResource::class);

    expect($field)->toBeField()
        ->and($field->key())->toBe('courses')
        ->and($field->getRelation())->toBe('courses')
        ->and($field->getAttribute())->toBe('')
        ->and($field->getType())->toBe('belongsToMany')
        ->and($field->getComponent())->toBe('FieldBelongsToMany')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->relationType())->toBe('belongsToMany')
        ->and($field->getResource())->toBe(CourseResource::class);
});

it('is shown on detail and forms but not on index by default', function () {
    $field = BelongsToMany::make('Courses', 'courses');
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeTrue()
        ->and($field->isShowOnUpdate($request, null))->toBeTrue()
        ->and($field->isAppliedInIndexQuery())->toBeFalse()
        ->and($field->isAppliedInShowQuery())->toBeTrue()
        ->and($field->supportsAfterSave())->toBeTrue();
});

it('has no pivot configuration, limit or pagination by default', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags');

    expect($field->pivotFields)->toBeEmpty()
        ->and($field->getPivotColumns())->toBe([])
        ->and($field->hasPivotColumns())->toBeFalse()
        ->and($field->hasPivotDefaults())->toBeFalse()
        ->and($field->getLimit())->toBeNull()
        ->and($field->isPaginated())->toBeFalse()
        ->and($field->searchable)->toBeFalse();
});

it('can be made searchable and not searchable again', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')->searchable();

    expect($field->isSearchable())->toBeTrue()
        ->and($field->notSearchable()->isSearchable())->toBeFalse();
});

it('collects pivot columns from withPivot, pivotFields and withTimestamps', function () {
    $field = BelongsToMany::make('Tags', 'simpleTags')
        ->withPivot(['created_at'])
        ->pivotFields([Input::make('Order', 'order'), Input::make('Notes', 'notes')]);

    expect($field->getPivotColumns())->toBe(['created_at', 'order', 'notes'])
        ->and($field->hasPivotColumns())->toBeTrue()
        ->and(BelongsToMany::make('T', 't')->withTimestamps()->getPivotColumns())->toBe(['created_at', 'updated_at'])
        ->and(BelongsToMany::make('T', 't')->withTimestamps()->hasPivotColumns())->toBeTrue();
});

it('serializes pivot fields at the top level of the field array', function () {
    $array = BelongsToMany::make('Courses', 'courses')
        ->pivotFields([Number::make('Score', 'score')])
        ->toArray(createNadotaRequest());

    expect($array['pivotFields'])->toHaveCount(1)
        ->and($array['pivotFields'][0]['key'])->toBe('score')
        ->and($array['props']['pivotColumns'])->toBe(['score'])
        ->and($array['props']['withPivot'])->toBeTrue();
});

it('resolves pivot defaults from the static map first, then the callback', function () {
    $field = BelongsToMany::make('Courses', 'courses')->pivotDefaults([
        1 => ['score' => 100],
        '*' => fn ($course) => ['score' => strlen($course->name)],
    ]);

    expect($field->hasPivotDefaults())->toBeTrue()
        ->and($field->getPivotDefaults())->toBe([1 => ['score' => 100]])
        ->and($field->resolvePivotDefaults(1))->toBe(['score' => 100])
        ->and($field->resolvePivotDefaults(new Course(['id' => 2, 'name' => 'Math'])))->toBe(['score' => 4])
        ->and($field->resolvePivotDefaults(2))->toBeNull()
        ->and($field->getResolvedPivotDefaults())->toBe(['static' => [1 => ['score' => 100]], 'hasDynamicResolver' => true]);
});

// --- resolve ------------------------------------------------------------

it('resolves each related model with the related resource and collection meta', function () {
    [$student] = belongsToManyEnrolledStudent();

    $value = BelongsToMany::make('Courses', 'courses', CourseResource::class)
        ->resolve(createNadotaRequest(), $student, new StudentResource());

    expect(collect($value['data'])->pluck('label')->sort()->values()->all())->toBe(['Art', 'Math'])
        ->and($value['data'][0])->toHaveKeys(['id', 'key', 'label', 'resource', 'deletedAt', 'permissions'])
        ->and($value['data'][0])->not->toHaveKey('pivot')
        ->and($value['meta'])->toMatchArray(['total' => 2, 'hasMore' => false, 'resource' => 'course', 'pivotColumns' => []]);
});

it('resolves pivot data per item when withPivot is configured and eager loaded', function () {
    [$student, $math, $art] = belongsToManyEnrolledStudent();
    $field = BelongsToMany::make('Courses', 'courses', CourseResource::class)->withPivot(['score']);

    $value = $field->resolve(createNadotaRequest(), belongsToManyEagerLoad($field, $student), null);

    expect(collect($value['data'])->mapWithKeys(fn ($item) => [$item['label'] => $item['pivot']])->all())
        ->toBe(['Math' => ['score' => 90], 'Art' => ['score' => 75]])
        ->and($value['meta']['pivotColumns'])->toBe(['score']);
});

it('includes pivot timestamps in basic items when withTimestamps is set', function () {
    $testModel = TestModel::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Laravel']);
    $testModel->simpleTags()->attach($tag->id, ['created_at' => now(), 'updated_at' => now()]);

    $field = BelongsToMany::make('Tags', 'simpleTags')->displayAttribute('name')->withPivot([])->withTimestamps();
    $testModel->load(['simpleTags' => fn ($query) => $query->withPivot($field->getPivotColumns())]);

    $value = $field->resolve(createNadotaRequest(), $testModel, null);

    expect($value['data'][0]['label'])->toBe('Laravel')
        ->and($value['data'][0]['pivot'])->toHaveKeys(['created_at', 'updated_at'])
        ->and($value['data'][0]['pivot']['created_at'])->not->toBeNull();
});

it('resolves basic items without pivot when withPivot is not set', function () {
    $testModel = TestModel::factory()->create();
    $tag1 = Tag::factory()->create(['name' => 'Laravel']);
    $tag2 = Tag::factory()->create(['name' => 'PHP']);
    $testModel->simpleTags()->attach([$tag1->id, $tag2->id]);

    $value = BelongsToMany::make('Tags', 'simpleTags')->displayAttribute('name')
        ->resolve(createNadotaRequest(), $testModel, null);

    expect($value['data'])->toBe([
        ['id' => $tag1->id, 'label' => 'Laravel', 'deletedAt' => null],
        ['id' => $tag2->id, 'label' => 'PHP', 'deletedAt' => null],
    ])
        ->and($value['meta'])->toBe(['total' => 2, 'hasMore' => false, 'pivotColumns' => []]);
});

it('falls back to the name attribute for the basic label', function () {
    $testModel = TestModel::factory()->create();
    $testModel->simpleTags()->attach(Tag::factory()->create(['name' => 'Laravel'])->id);

    $value = BelongsToMany::make('Tags', 'simpleTags')->resolve(createNadotaRequest(), $testModel, null);

    expect($value['data'][0]['label'])->toBe('Laravel');
});

it('applies limit and orderBy to the inline items', function () {
    [$student] = belongsToManyEnrolledStudent();
    $student->courses()->attach(Course::create(['name' => 'Biology'])->id);

    $value = BelongsToMany::make('Courses', 'courses', CourseResource::class)->orderBy('name', 'asc')->limit(2)
        ->resolve(createNadotaRequest(), $student, null);

    expect(collect($value['data'])->pluck('label')->all())->toBe(['Art', 'Biology'])
        ->and($value['meta']['hasMore'])->toBeTrue();
});

it('returns an empty paginated stub carrying the pivot columns when paginated', function () {
    [$student] = belongsToManyEnrolledStudent();

    $value = BelongsToMany::make('Courses', 'courses', CourseResource::class)->paginated()->withPivot(['score'])
        ->resolve(createNadotaRequest(), $student, null);

    expect($value)->toBe([
        'data' => [],
        'meta' => ['total' => 0, 'hasMore' => false, 'paginated' => true, 'pivotColumns' => ['score']],
    ]);
});

it('returns an empty collection when no relationships exist', function () {
    $testModel = TestModel::factory()->create();

    $value = BelongsToMany::make('Tags', 'simpleTags')->resolve(createNadotaRequest(), $testModel, null);

    expect($value['data'])->toBe([])
        ->and($value['meta']['total'])->toBe(0)
        ->and($value['meta']['hasMore'])->toBeFalse();
});

it('omits the item id when withoutId() is set', function () {
    [$student] = belongsToManyEnrolledStudent();

    $value = BelongsToMany::make('Courses', 'courses', CourseResource::class)->withoutId()
        ->resolve(createNadotaRequest(), $student, null);

    expect($value['data'][0])->not->toHaveKeys(['id', 'key']);
})->skip('ISSUE REL-013: withoutId() is only honoured by HasMany and the relation endpoint');

// --- toArray / props ----------------------------------------------------

it('serializes metadata, pivot props and the resolved value', function () {
    $testModel = TestModel::factory()->create();
    $testModel->simpleTags()->attach(Tag::factory()->create(['name' => 'Laravel'])->id);

    $array = BelongsToMany::make('Tags', 'simpleTags')
        ->displayAttribute('name')
        ->searchable()
        ->withPivot(['created_at'])
        ->sortable()
        ->filterable()
        ->toArray(createNadotaRequest(), $testModel, new TestResource());

    expect($array)
        ->toHaveKey('label', 'Tags')
        ->toHaveKey('key', 'simpleTags')
        ->toHaveKey('searchable', true)
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->toHaveKey('pivotFields', [])
        ->and($array['props'])->toMatchArray([
            'relationType' => 'belongsToMany',
            'withPivot' => true,
            'pivotColumns' => ['created_at'],
            'withTimestamps' => false,
            'pivotDefaults' => null,
            'canCreate' => false,
            'canAttach' => true,
            'canDetach' => true,
            'autoAttach' => true,
            'resource' => null,
        ])
        ->and($array['value']['data'][0]['label'])->toBe('Laravel');
});

it('builds options, attach, sync and detach urls for an existing parent', function () {
    $student = Student::create(['name' => 'Ana']);

    $props = BelongsToMany::make('Courses', 'courses', CourseResource::class)
        ->toArray(createNadotaRequest(), $student, new StudentResource())['props'];

    expect($props['urls'])->toBe([
        'options' => "/nadota-api/student/resource/field/courses/options?resourceId={$student->id}",
        'attach' => "/nadota-api/student/resource/{$student->id}/attach/courses",
        'sync' => "/nadota-api/student/resource/{$student->id}/sync/courses",
        'detach' => "/nadota-api/student/resource/{$student->id}/detach/courses",
    ])
        ->and($props)->not->toHaveKey('paginationUrl');
});

it('only has the options url in a create form', function () {
    $props = BelongsToMany::make('Courses', 'courses', CourseResource::class)
        ->toArray(createNadotaRequest(), null, new StudentResource())['props'];

    expect($props['urls'])->toBe(['options' => '/nadota-api/student/resource/field/courses/options']);
});

it('drops attach, sync and detach urls when attachable(false)', function () {
    $student = Student::create(['name' => 'Ana']);

    $props = BelongsToMany::make('Courses', 'courses', CourseResource::class)->attachable(false)
        ->toArray(createNadotaRequest(), $student, new StudentResource())['props'];

    expect(array_keys($props['urls']))->toBe(['options'])
        ->and($props['canAttach'])->toBeFalse()
        ->and($props)->not->toHaveKey('attachment');
});

it('adds the pagination url when paginated', function () {
    $student = Student::create(['name' => 'Ana']);

    $props = BelongsToMany::make('Courses', 'courses', CourseResource::class)->paginated()
        ->toArray(createNadotaRequest(), $student, new StudentResource())['props'];

    expect($props['paginationUrl'])->toBe("/nadota-api/student/resource/{$student->id}/relation/courses");
});

// --- eager loading ------------------------------------------------------

it('selects the related resource columns qualified and applies withPivot when eager loading', function () {
    [$student] = belongsToManyEnrolledStudent();
    $field = BelongsToMany::make('Courses', 'courses', CourseResource::class)->withPivot(['score']);

    DB::enableQueryLog();
    $loaded = belongsToManyEagerLoad($field, $student);
    $sql = collect(DB::getQueryLog())->pluck('query')->last();

    expect($sql)->toContain('"fx_courses"."name"', '"fx_course_student"."score" as "pivot_score"')
        ->and($loaded->courses->first()->pivot->score)->not->toBeNull();
});

it('is not eager loaded when paginated', function () {
    $resource = new class extends Resource {
        public string $model = Student::class;
        public function fields(NadotaRequest $request): array
        {
            return [BelongsToMany::make('Courses', 'courses', CourseResource::class)->paginated()];
        }
    };

    expect($resource->getEagerLoadRelations(createNadotaRequest()))->toBe([]);
});

// --- fill / afterSave ---------------------------------------------------

it('does not fill the parent model before it is saved', function () {
    $student = new Student(['name' => 'Ana']);

    BelongsToMany::make('Courses', 'courses')->fill(createNadotaRequest(['courses' => [1]]), $student);

    expect($student->getDirty())->toBe(['name' => 'Ana']);
});

it('syncs a flat list of ids after save', function () {
    [$student, $math, $art] = belongsToManyEnrolledStudent();
    $bio = Course::create(['name' => 'Biology']);

    BelongsToMany::make('Courses', 'courses')->afterSave(createNadotaRequest(['courses' => [$math->id, $bio->id]]), $student);

    expect($student->courses()->pluck('fx_courses.id')->sort()->values()->all())->toBe([$math->id, $bio->id]);
});

it('syncs pivot attributes from id-bearing entries after save', function () {
    [$student, $math, $art] = belongsToManyEnrolledStudent();

    BelongsToMany::make('Courses', 'courses')->afterSave(
        createNadotaRequest(['courses' => [['id' => $math->id, 'score' => 99], ['id' => $art->id, 'score' => 10]]]),
        $student
    );

    expect($student->courses()->pluck('score', 'fx_courses.id')->all())->toBe([$math->id => 99, $art->id => 10]);
});

it('detaches everything when an empty list is sent', function () {
    [$student] = belongsToManyEnrolledStudent();

    BelongsToMany::make('Courses', 'courses')->afterSave(createNadotaRequest(['courses' => []]), $student);

    expect($student->courses()->count())->toBe(0);
});

it('leaves the relation untouched when the request does not carry the key', function () {
    [$student] = belongsToManyEnrolledStudent();

    BelongsToMany::make('Courses', 'courses')->afterSave(createNadotaRequest(['name' => 'x']), $student);

    expect($student->courses()->count())->toBe(2);
});

// --- options ------------------------------------------------------------

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

// --- sorting / filters / export ----------------------------------------

it('does not alter the parent query when sorting', function () {
    $student = new Student();
    $query = $student->newQuery();

    expect(BelongsToMany::make('Courses', 'courses')->sortable()->applySorting($query, 'asc', $student))->toBe($query)
        ->and($query->getQuery()->orders)->toBeNull();
});

it('exposes a multiple whereHas relation filter keyed by the relation when filterable', function () {
    $filters = BelongsToMany::make('Courses', 'courses', CourseResource::class)->filterable()->filters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(RelationFilter::class)
        ->and($filters[0]->key())->toBe('courses');
});

it('exports the related labels joined with the separator', function () {
    [$student] = belongsToManyEnrolledStudent();

    $value = BelongsToMany::make('Courses', 'courses')->resolveForExport(createNadotaRequest(), $student, null);

    expect(explode(', ', $value))->toEqualCanonicalizing(['Math', 'Art']);
});

it('exports an empty string when nothing is attached', function () {
    $student = Student::create(['name' => 'Ana']);

    expect(BelongsToMany::make('Courses', 'courses')->resolveForExport(createNadotaRequest(), $student, null))->toBe('');
});
