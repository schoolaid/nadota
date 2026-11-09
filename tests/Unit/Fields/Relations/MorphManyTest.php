<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Filters\RelationFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\NoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

beforeEach(function () {
    FixtureSchema::create();
});

function morphManyStudentWithNotes(array $bodies): Student
{
    $student = Student::create(['name' => 'Ana']);
    foreach ($bodies as $body) {
        $student->notes()->create(['body' => $body]);
    }

    return $student;
}

function morphManyRequest(Resource $parent): NadotaRequest
{
    $request = createNadotaRequest();
    $request->setResource($parent);

    return $request;
}

// --- construction -------------------------------------------------------

it('uses the relation name as key and leaves the attribute empty', function () {
    $field = MorphMany::make('Notes', 'notes', NoteResource::class);

    expect($field)->toBeField()
        ->and($field->key())->toBe('notes')
        ->and($field->getRelation())->toBe('notes')
        ->and($field->getAttribute())->toBe('')
        ->and($field->getType())->toBe('morphMany')
        ->and($field->getComponent())->toBe('FieldMorphMany')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->relationType())->toBe('morphMany')
        ->and($field->getColumnsForSelect(Student::class))->toBe([]);
});

it('is only shown on detail and only applied to the show query by default', function () {
    $field = MorphMany::make('Notes', 'notes');
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeFalse()
        ->and($field->isShowOnUpdate($request, null))->toBeFalse()
        ->and($field->isAppliedInIndexQuery())->toBeFalse()
        ->and($field->isAppliedInShowQuery())->toBeTrue()
        ->and($field->getLimit())->toBeNull()
        ->and($field->getOrderDirection())->toBe('desc')
        ->and($field->hasPivotColumns())->toBeFalse();
});

// --- resolve ------------------------------------------------------------

it('resolves only the notes of the given parent and morph type', function () {
    $student = morphManyStudentWithNotes(['first', 'second']);
    // Same id, other morph type: must not leak into the student's notes.
    $grade = Grade::create(['name' => 'G']);
    $grade->notes()->create(['body' => 'grade note']);

    $value = MorphMany::make('Notes', 'notes', NoteResource::class)->displayAttribute('body')
        ->resolve(createNadotaRequest(), $student, new StudentResource());

    expect($student->id)->toBe($grade->id)
        ->and(collect($value['data'])->pluck('label')->all())->toBe(['first', 'second'])
        ->and($value['data'][0])->toHaveKeys(['id', 'key', 'label', 'resource', 'deletedAt', 'permissions'])
        ->and($value['data'][0]['resource'])->toBe('note')
        ->and($value['meta'])->toMatchArray(['total' => 2, 'hasMore' => false, 'isPolymorphic' => true, 'resource' => 'note']);
});

it('resolves basic items flagged as polymorphic without a resource', function () {
    $student = morphManyStudentWithNotes(['first']);
    $note = Note::first();

    $value = MorphMany::make('Notes', 'notes')->displayAttribute('body')->resolve(createNadotaRequest(), $student, null);

    expect($value)->toBe([
        'data' => [['id' => $note->id, 'label' => 'first', 'deletedAt' => null]],
        'meta' => ['total' => 1, 'hasMore' => false, 'isPolymorphic' => true],
    ]);
});

it('falls back to the primary key for the label when no common attribute exists', function () {
    $student = morphManyStudentWithNotes(['first']);

    $value = MorphMany::make('Notes', 'notes')->resolve(createNadotaRequest(), $student, null);

    expect($value['data'][0]['label'])->toBe(Note::first()->id);
});

it('includes rendered fields as attributes only with withFields()', function () {
    $student = morphManyStudentWithNotes(['first']);

    $value = MorphMany::make('Notes', 'notes', NoteResource::class)
        ->fields([Input::make('Body', 'body')])
        ->withFields()
        ->resolve(createNadotaRequest(), $student, null);

    expect($value['data'][0]['attributes'])->toHaveCount(1)
        ->and($value['data'][0]['attributes'][0]['value'])->toBe('first');
});

it('applies limit and orderBy to the inline items', function () {
    $student = morphManyStudentWithNotes(['b', 'c', 'a']);

    $value = MorphMany::make('Notes', 'notes', NoteResource::class)->displayAttribute('body')
        ->orderBy('body', 'asc')->limit(2)
        ->resolve(createNadotaRequest(), $student, null);

    expect(collect($value['data'])->pluck('label')->all())->toBe(['a', 'b'])
        ->and($value['meta']['hasMore'])->toBeTrue();
});

it('returns an empty paginated stub when paginated', function () {
    $student = morphManyStudentWithNotes(['first']);

    $value = MorphMany::make('Notes', 'notes', NoteResource::class)->paginated()->resolve(createNadotaRequest(), $student, null);

    expect($value)->toBe(['data' => [], 'meta' => ['total' => 0, 'hasMore' => false, 'paginated' => true]]);
});

// --- props --------------------------------------------------------------

it('serializes inline configuration, urls and a polymorphic createContext', function () {
    $student = Student::create(['name' => 'Ana']);

    $props = MorphMany::make('Notes', 'notes', NoteResource::class)->limit(5)->orderBy('created_at')
        ->toArray(createNadotaRequest(), $student, new StudentResource())['props'];

    expect($props)->toMatchArray([
        'limit' => 5,
        'paginated' => false,
        'orderBy' => 'created_at',
        'orderDirection' => 'desc',
        'relationType' => 'morphMany',
        'resource' => 'note',
        'isPolymorphic' => true,
    ])
        ->and($props['urls'])->toBe([
            'options' => "/nadota-api/student/resource/field/notes/options?resourceId={$student->id}",
            'attachable' => "/nadota-api/student/resource/{$student->id}/attachable/notes",
            'attach' => "/nadota-api/student/resource/{$student->id}/attach/notes",
            'detach' => "/nadota-api/student/resource/{$student->id}/detach/notes",
        ])
        ->and($props['createContext'])->toBe([
            'parentResource' => 'student',
            'parentId' => $student->id,
            'relatedResource' => 'note',
            'morphType' => 'notable_type',
            'morphId' => 'notable_id',
            'morphClass' => Student::class,
            'prefill' => ['notable_type' => Student::class, 'notable_id' => $student->id],
            'lock' => ['notable_type', 'notable_id'],
            'returnUrl' => "/resources/student/{$student->id}",
            'createUrl' => '/nadota-api/note/resource/create',
            'storeUrl' => '/nadota-api/note/resource',
            'isPolymorphic' => true,
        ]);
});

it('only has the options url without a parent model', function () {
    $props = MorphMany::make('Notes', 'notes', NoteResource::class)
        ->toArray(createNadotaRequest(), null, new StudentResource())['props'];

    expect($props['urls'])->toBe(['options' => '/nadota-api/student/resource/field/notes/options'])
        ->and($props)->not->toHaveKey('createContext');
});

it('adds the pagination url when paginated', function () {
    $grade = Grade::create(['name' => 'G']);

    $props = MorphMany::make('Notes', 'notes', NoteResource::class)->paginated()
        ->toArray(createNadotaRequest(), $grade, new GradeResource())['props'];

    expect($props['paginationUrl'])->toBe("/nadota-api/grade/resource/{$grade->id}/relation/notes")
        ->and($props['createContext']['morphClass'])->toBe(Grade::class);
});

it('drops attach urls when attachable(false), like HasMany does', function () {
    $student = Student::create(['name' => 'Ana']);

    $props = MorphMany::make('Notes', 'notes', NoteResource::class)->attachable(false)
        ->toArray(createNadotaRequest(), $student, new StudentResource())['props'];

    expect(array_keys($props['urls']))->toBe(['options'])
        ->and($props)->toHaveKeys(['canAttach', 'canDetach', 'canCreate']);
})->skip('ISSUE REL-012: MorphMany always emits attachable/attach/detach urls and has no canAttach/canDetach/canCreate props');

it('prefills the morph alias when a morph map is registered', function () {
    Relation::morphMap(['student' => Student::class, 'grade' => Grade::class]);

    try {
        $student = Student::create(['name' => 'Ana']);
        $props = MorphMany::make('Notes', 'notes', NoteResource::class)
            ->toArray(createNadotaRequest(), $student, new StudentResource())['props'];

        expect($props['createContext']['prefill']['notable_type'])->toBe('student');
    } finally {
        Relation::morphMap([], false);
    }
})->skip('ISSUE REL-015: createContext uses get_class() instead of getMorphClass(), ignoring morph maps');

// --- eager loading ------------------------------------------------------

it('adds the morph id and type columns to the eager-load columns', function () {
    $noteResource = new class extends Resource {
        public string $model = Note::class;
        public function fields(NadotaRequest $request): array { return [Input::make('Body', 'body')]; }
    };

    $columns = MorphMany::make('Notes', 'notes', get_class($noteResource))
        ->getRelatedColumns(morphManyRequest(new StudentResource()));

    expect($columns)->toEqualCanonicalizing(['body', 'id', 'notable_id', 'notable_type']);
});

it('eager loads the notes with the constrained columns', function () {
    $student = morphManyStudentWithNotes(['first', 'second']);
    $resource = new StudentResource();
    $relations = $resource->getEagerLoadRelations(morphManyRequest($resource));

    $loaded = Student::with(['notes' => $relations['notes']])->find($student->id);

    expect($loaded->notes->pluck('body')->all())->toBe(['first', 'second']);
});

// --- fill / sorting / filters / export ---------------------------------

it('never fills the parent model', function () {
    $student = new Student(['name' => 'Ana']);

    MorphMany::make('Notes', 'notes')->fill(createNadotaRequest(['notes' => [1]]), $student);

    expect($student->getDirty())->toBe(['name' => 'Ana']);
});

it('does not alter the parent query when sorting', function () {
    $student = new Student();
    $query = $student->newQuery();

    expect(MorphMany::make('Notes', 'notes')->applySorting($query, 'asc', $student))->toBe($query)
        ->and($query->getQuery()->orders)->toBeNull();
});

it('exposes a whereHas relation filter keyed by the relation when filterable', function () {
    $filters = MorphMany::make('Notes', 'notes', NoteResource::class)->filterable()->filters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(RelationFilter::class)
        ->and($filters[0]->key())->toBe('notes');
});

it('exports the export attribute of every note', function () {
    $student = morphManyStudentWithNotes(['first', 'second']);

    expect(MorphMany::make('Notes', 'notes')->exportUsing('body')->resolveForExport(createNadotaRequest(), $student, null))
        ->toBe('first, second')
        ->and(MorphMany::make('Notes', 'notes')->resolveForExport(createNadotaRequest(), Student::create(['name' => 'B']), null))
        ->toBe('');
});
