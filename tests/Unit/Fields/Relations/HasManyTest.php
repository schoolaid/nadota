<?php

use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Filters\RelationFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\SchoolResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;

beforeEach(function () {
    FixtureSchema::create();
});

function hasManyGradeWithStudents(array $names): Grade
{
    $grade = Grade::create(['name' => '5th A']);
    foreach ($names as $name) {
        Student::create(['name' => $name, 'grade_id' => $grade->id]);
    }

    return $grade;
}

function hasManyDenyAuthorization(): void
{
    app()->bind(ResourceAuthorizationInterface::class, fn () => new class implements ResourceAuthorizationInterface {
        public function setModel($model): self { return $this; }
        public function authorizedTo(NadotaRequest $request, string $action, array $context = []): bool { return false; }
    });
}

// --- construction -------------------------------------------------------

it('uses the relation name as key and leaves the attribute empty', function () {
    $field = HasMany::make('Students', 'students', StudentResource::class);

    expect($field)->toBeField()
        ->and($field->key())->toBe('students')
        ->and($field->getRelation())->toBe('students')
        ->and($field->getAttribute())->toBe('')
        ->and($field->getType())->toBe('hasMany')
        ->and($field->getComponent())->toBe('FieldHasMany')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->getResource())->toBe(StudentResource::class)
        ->and($field->getColumnsForSelect(Grade::class))->toBe([]);
});

it('reports its relation type like every other relation field', function () {
    expect(HasMany::make('Students', 'students')->relationType())->toBe('hasMany');
})->skip('ISSUE REL-021: HasMany is the only relation field without a relationType() method');

it('is only shown on detail and only applied to the show query by default', function () {
    $field = HasMany::make('Students', 'students');
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeFalse()
        ->and($field->isShowOnUpdate($request, null))->toBeFalse()
        ->and($field->isAppliedInIndexQuery())->toBeFalse()
        ->and($field->isAppliedInShowQuery())->toBeTrue();
});

it('has no limit, no ordering and no pagination by default', function () {
    $field = HasMany::make('Students', 'students');

    expect($field->getLimit())->toBeNull()
        ->and($field->getOrderBy())->toBeNull()
        ->and($field->getOrderDirection())->toBe('desc')
        ->and($field->isPaginated())->toBeFalse()
        ->and($field->shouldIncludeFields())->toBeFalse()
        ->and($field->hasPivotColumns())->toBeFalse()
        ->and($field->getPivotColumns())->toBe([]);
});

it('stores limit, ordering, pagination and custom fields', function () {
    $field = HasMany::make('Students', 'students')
        ->limit(10)
        ->orderBy('name', 'asc')
        ->paginated()
        ->fields([Input::make('Name', 'name'), Input::make('Email', 'email')])
        ->exceptFields(['grade']);

    expect($field->getLimit())->toBe(10)
        ->and($field->getOrderBy())->toBe('name')
        ->and($field->getOrderDirection())->toBe('asc')
        ->and($field->isPaginated())->toBeTrue()
        ->and($field->getCustomFields())->toHaveCount(2)
        ->and($field->getExceptFieldKeys())->toBe(['grade']);
});

// --- resolve ------------------------------------------------------------

it('resolves each related model with the related resource and collection meta', function () {
    $grade = hasManyGradeWithStudents(['Ana', 'Beto']);

    $value = HasMany::make('Students', 'students', StudentResource::class)
        ->resolve(createNadotaRequest(), $grade, new GradeResource());

    expect(collect($value['data'])->pluck('label')->all())->toBe(['Ana', 'Beto'])
        ->and($value['data'][0])->toHaveKeys(['id', 'key', 'label', 'resource', 'deletedAt', 'permissions'])
        ->and($value['data'][0]['resource'])->toBe('student')
        ->and($value['data'][0])->not->toHaveKey('attributes')
        ->and($value['meta']['total'])->toBe(2)
        ->and($value['meta']['hasMore'])->toBeFalse()
        ->and($value['meta']['resource'])->toBe('student')
        ->and(collect($value['meta']['fields'])->pluck('key'))->toContain('name', 'email');
});

it('resolves basic items with the display attribute when there is no resource', function () {
    $testModel = TestModel::factory()->create(['name' => 'Parent Model']);
    $first = RelatedModel::factory()->create(['test_model_id' => $testModel->id, 'title' => 'Related Item 1']);
    $second = RelatedModel::factory()->create(['test_model_id' => $testModel->id, 'title' => 'Related Item 2']);

    $value = HasMany::make('Related Models', 'relatedModels')->displayAttribute('title')
        ->resolve(createNadotaRequest(), $testModel, null);

    expect(collect($value['data'])->map(fn ($item) => [$item['id'], $item['label']])->all())
        ->toBe([[$first->id, 'Related Item 1'], [$second->id, 'Related Item 2']])
        ->and($value['meta'])->toBe(['total' => 2, 'hasMore' => false]);
});

it('falls back to common attributes and then "Item #id" for basic labels', function () {
    $testModel = TestModel::factory()->create();
    RelatedModel::factory()->create(['test_model_id' => $testModel->id, 'title' => 'Fallback Title']);
    $grade = Grade::create(['name' => 'G']);
    $note = $grade->notes()->create(['body' => 'no common label attribute']);

    $withTitle = HasMany::make('Related Models', 'relatedModels')->resolve(createNadotaRequest(), $testModel, null);
    $withoutLabel = HasMany::make('Notes', 'notes')->resolve(createNadotaRequest(), $grade, null);

    // RelatedModel has no `name`, so the next common attribute (`title`) is used.
    expect($withTitle['data'][0]['label'])->toBe('Fallback Title')
        ->and($withoutLabel['data'][0]['label'])->toBe("Item #{$note->id}");
});

it('includes rendered custom fields as attributes only with withFields()', function () {
    $grade = hasManyGradeWithStudents(['Ana']);
    $request = createNadotaRequest();

    $without = HasMany::make('Students', 'students', StudentResource::class)
        ->fields([Input::make('Name', 'name')])
        ->resolve($request, $grade, null);
    $with = HasMany::make('Students', 'students', StudentResource::class)
        ->fields([Input::make('Name', 'name')])
        ->withFields()
        ->resolve($request, $grade, null);

    expect($without['data'][0])->not->toHaveKey('attributes')
        ->and($with['data'][0]['attributes'])->toHaveCount(1)
        ->and($with['data'][0]['attributes'][0]['key'])->toBe('name')
        ->and($with['data'][0]['attributes'][0]['value'])->toBe('Ana');
});

it('omits the item id when withoutId() is set', function () {
    $grade = hasManyGradeWithStudents(['Ana']);

    $withResource = HasMany::make('Students', 'students', StudentResource::class)->withoutId()
        ->resolve(createNadotaRequest(), $grade, null);
    $basic = HasMany::make('Students', 'students')->withoutId()
        ->resolve(createNadotaRequest(), $grade, null);

    expect($withResource['data'][0])->not->toHaveKeys(['id', 'key'])
        ->and($basic['data'][0])->not->toHaveKey('id');
});

it('respects the display limit and reports hasMore', function () {
    $grade = hasManyGradeWithStudents(['a', 'b', 'c', 'd', 'e']);

    $value = HasMany::make('Students', 'students', StudentResource::class)->limit(3)
        ->resolve(createNadotaRequest(), $grade, null);

    expect($value['data'])->toHaveCount(3)
        ->and($value['meta']['total'])->toBe(3)
        ->and($value['meta']['hasMore'])->toBeTrue();
});

it('applies orderBy on a lazy load', function () {
    $grade = hasManyGradeWithStudents(['b', 'c', 'a']);

    $value = HasMany::make('Students', 'students', StudentResource::class)->orderBy('name', 'asc')
        ->resolve(createNadotaRequest(), $grade, null);

    expect(collect($value['data'])->pluck('label')->all())->toBe(['a', 'b', 'c']);
});

it('returns an empty paginated stub without querying when paginated', function () {
    $grade = hasManyGradeWithStudents(['Ana']);

    $value = HasMany::make('Students', 'students', StudentResource::class)->paginated()
        ->resolve(createNadotaRequest(), $grade, null);

    expect($value)->toBe(['data' => [], 'meta' => ['total' => 0, 'hasMore' => false, 'paginated' => true]])
        ->and($grade->relationLoaded('students'))->toBeFalse();
});

it('returns an empty collection when there are no related rows', function () {
    $grade = Grade::create(['name' => 'Empty']);

    $value = HasMany::make('Students', 'students', StudentResource::class)->resolve(createNadotaRequest(), $grade, null);

    expect($value['data'])->toBe([])
        ->and($value['meta']['total'])->toBe(0)
        ->and($value['meta']['hasMore'])->toBeFalse();
});

it('returns an empty array when the parent model does not define the relation method', function () {
    $grade = Grade::create(['name' => 'G']);

    expect(HasMany::make('Teachers', 'teachers')->resolve(createNadotaRequest(), $grade, null))->toBe([]);
});

// --- toArray / props ----------------------------------------------------

it('serializes the inline configuration in props', function () {
    $grade = hasManyGradeWithStudents(['Ana']);

    $array = HasMany::make('Students', 'students', StudentResource::class)
        ->attachable()
        ->limit(10)
        ->orderBy('name', 'asc')
        ->toArray(createNadotaRequest(), $grade, new GradeResource());

    expect($array)
        ->toHaveKey('key', 'students')
        ->toHaveKey('type', 'hasMany')
        ->and($array['props'])->toMatchArray([
            'limit' => 10,
            'paginated' => false,
            'orderBy' => 'name',
            'orderDirection' => 'asc',
            'relationType' => 'hasMany',
            'resource' => 'student',
            'canCreate' => false,
            'canAttach' => true,
            'canDetach' => true,
        ])
        ->and($array['value']['data'][0]['label'])->toBe('Ana');
});

it('offers no attach or detach until the field opts in with attachable()', function () {
    $grade = Grade::create(['name' => 'G']);

    $props = HasMany::make('Students', 'students', StudentResource::class)
        ->toArray(createNadotaRequest(), $grade, new GradeResource())['props'];

    expect($props['canAttach'])->toBeFalse()
        ->and($props['canDetach'])->toBeFalse()
        ->and($props)->not->toHaveKey('attachment')
        ->and(array_keys($props['urls']))->toBe(['options']);
});

it('builds options, attach and detach urls for an existing parent', function () {
    $grade = Grade::create(['name' => 'G']);

    $props = HasMany::make('Students', 'students', StudentResource::class)->attachable()
        ->toArray(createNadotaRequest(), $grade, new GradeResource())['props'];

    expect($props['urls'])->toBe([
        'options' => "/nadota-api/grade/resource/field/students/options?resourceId={$grade->id}",
        'attachable' => "/nadota-api/grade/resource/{$grade->id}/attachable/students",
        'attach' => "/nadota-api/grade/resource/{$grade->id}/attach/students",
        'detach' => "/nadota-api/grade/resource/{$grade->id}/detach/students",
    ])
        ->and($props)->not->toHaveKey('paginationUrl')
        ->and($props)->not->toHaveKey('createContext');
});

it('only has the options url without a parent model', function () {
    $props = HasMany::make('Students', 'students', StudentResource::class)
        ->toArray(createNadotaRequest(), null, new GradeResource())['props'];

    expect($props['urls'])->toBe(['options' => '/nadota-api/grade/resource/field/students/options']);
});

it('has no urls without a parent resource', function () {
    $props = HasMany::make('Students', 'students', StudentResource::class)->toArray(createNadotaRequest())['props'];

    expect($props)->not->toHaveKey('urls');
});

it('adds the pagination url when paginated', function () {
    $grade = Grade::create(['name' => 'G']);

    $props = HasMany::make('Students', 'students', StudentResource::class)->paginated()
        ->toArray(createNadotaRequest(), $grade, new GradeResource())['props'];

    expect($props['paginated'])->toBeTrue()
        ->and($props['paginationUrl'])->toBe("/nadota-api/grade/resource/{$grade->id}/relation/students");
});

it('drops attach urls and flags when attachable(false)', function () {
    $grade = Grade::create(['name' => 'G']);

    $props = HasMany::make('Students', 'students', StudentResource::class)->attachable(false)
        ->toArray(createNadotaRequest(), $grade, new GradeResource())['props'];

    expect($props['canAttach'])->toBeFalse()
        ->and($props['canDetach'])->toBeFalse()
        ->and($props)->not->toHaveKey('attachment')
        ->and(array_keys($props['urls']))->toBe(['options']);
});

it('drops attach urls when the parent policy denies attach and detach', function () {
    hasManyDenyAuthorization();
    $grade = Grade::create(['name' => 'G']);

    $props = HasMany::make('Students', 'students', StudentResource::class)->attachable()
        ->toArray(createNadotaRequest(), $grade, new GradeResource())['props'];

    expect($props['canAttach'])->toBeFalse()
        ->and($props['canDetach'])->toBeFalse()
        ->and($props['attachment'])->toMatchArray(['canAttach' => false, 'canDetach' => false])
        ->and(array_keys($props['urls']))->toBe(['options']);
});

it('emits a foreign-key createContext when canCreate() is enabled', function () {
    $grade = Grade::create(['name' => 'G']);

    $props = HasMany::make('Students', 'students', StudentResource::class)->canCreate()
        ->toArray(createNadotaRequest(), $grade, new GradeResource())['props'];

    expect($props['canCreate'])->toBeTrue()
        ->and($props['createContext'])->toBe([
            'parentResource' => 'grade',
            'parentId' => $grade->id,
            'relatedResource' => 'student',
            'foreignKey' => 'grade_id',
            'prefill' => ['grade_id' => $grade->id],
            'lock' => ['grade_id'],
            'returnUrl' => "/resources/grade/{$grade->id}",
            'createUrl' => '/nadota-api/student/resource/create',
            'storeUrl' => '/nadota-api/student/resource',
        ]);
});

it('hides canCreate when the related resource denies create', function () {
    hasManyDenyAuthorization();
    $grade = Grade::create(['name' => 'G']);

    $props = HasMany::make('Students', 'students', StudentResource::class)->canCreate()
        ->toArray(createNadotaRequest(), $grade, new GradeResource())['props'];

    expect($props['canCreate'])->toBeFalse()
        ->and($props)->not->toHaveKey('createContext');
});

it('adds a filtered export url to the related resource when exportable', function () {
    $grade = Grade::create(['name' => 'G']);

    $props = HasMany::make('Students', 'students', StudentResource::class)->exportable()
        ->toArray(createNadotaRequest(), $grade, new GradeResource())['props'];

    expect($props['urls']['export'])->toBe("/nadota-api/student/resource/export?filters[grade_id]={$grade->id}");
});

// --- eager loading ------------------------------------------------------

it('adds the foreign key to the eager-load columns', function () {
    $relatedResource = new class extends Resource {
        public string $model = Student::class;
        public function fields(NadotaRequest $request): array { return [Input::make('Name', 'name')]; }
    };
    $request = createNadotaRequest();
    $request->setResource(new GradeResource());

    $columns = HasMany::make('Students', 'students', get_class($relatedResource))->getRelatedColumns($request);

    expect($columns)->toEqualCanonicalizing(['name', 'id', 'grade_id']);
});

it('is eager loaded by its resource unless paginated', function () {
    $resource = new class extends Resource {
        public string $model = Grade::class;
        public bool $paginate = false;
        public function fields(NadotaRequest $request): array
        {
            return [HasMany::make('Students', 'students', StudentResource::class)->paginated($this->paginate)];
        }
    };
    $request = createNadotaRequest();

    $inline = $resource->getEagerLoadRelations($request);
    $resource->paginate = true;
    $paginated = $resource->getEagerLoadRelations($request);

    expect(array_keys($inline))->toBe(['students'])
        ->and($paginated)->toBe([]);
});

// --- fill / sorting / filters / export ---------------------------------

it('never fills the parent model', function () {
    $grade = new Grade(['name' => 'G']);

    HasMany::make('Students', 'students')->fill(createNadotaRequest(['students' => [1, 2]]), $grade);

    expect($grade->getDirty())->toBe(['name' => 'G'])
        ->and(HasMany::make('Students', 'students')->supportsAfterSave())->toBeFalse();
});

it('does not alter the parent query when sorting', function () {
    $grade = new Grade();
    $query = $grade->newQuery();

    $sorted = HasMany::make('Students', 'students')->sortable()->applySorting($query, 'asc', $grade);

    expect($sorted)->toBe($query)
        ->and($sorted->getQuery()->orders)->toBeNull();
});

it('exposes a multiple whereHas relation filter keyed by the relation when filterable', function () {
    $filters = HasMany::make('Students', 'students', StudentResource::class)->filterable()->filters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(RelationFilter::class)
        ->and($filters[0]->key())->toBe('students');
});

it('exports the related labels joined with the separator', function () {
    $grade = hasManyGradeWithStudents(['Ana', 'Beto', 'Caro']);
    $request = createNadotaRequest();

    expect(HasMany::make('Students', 'students')->resolveForExport($request, $grade, null))->toBe('Ana, Beto, Caro')
        ->and(HasMany::make('Students', 'students')->exportSeparator(' | ')->exportLimit(2)->resolveForExport($request, $grade, null))
        ->toBe('Ana | Beto');
});

it('exports the attribute given to exportUsing()', function () {
    $grade = Grade::create(['name' => 'G']);
    Student::create(['name' => 'Ana', 'email' => 'ana@example.test', 'grade_id' => $grade->id]);

    $field = HasMany::make('Students', 'students')->exportUsing('email');

    expect($field->isExportable())->toBeTrue()
        ->and($field->resolveForExport(createNadotaRequest(), $grade, null))->toBe('ana@example.test');
});

it('exports an empty string when there are no related rows', function () {
    $school = School::create(['name' => 'Empty']);

    expect(HasMany::make('Grades', 'grades', GradeResource::class)->resolveForExport(createNadotaRequest(), $school, new SchoolResource()))
        ->toBe('');
});
