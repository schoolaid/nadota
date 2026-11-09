<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\MorphOne;
use SchoolAid\Nadota\Http\Filters\RelationFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\NoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

beforeEach(function () {
    FixtureSchema::create();
});

function morphOneStudentWithNotes(array $bodies): Student
{
    $student = Student::create(['name' => 'Ana']);
    foreach ($bodies as $body) {
        $student->notes()->create(['body' => $body]);
    }

    return $student;
}

function morphOneRequest(Resource $parent): NadotaRequest
{
    $request = createNadotaRequest();
    $request->setResource($parent);

    return $request;
}

// --- construction -------------------------------------------------------

it('keeps the relation and an empty attribute', function () {
    $field = MorphOne::make('Latest Note', 'latestNote', NoteResource::class);

    expect($field)->toBeField()
        ->and($field->getRelation())->toBe('latestNote')
        ->and($field->getAttribute())->toBe('')
        ->and($field->getType())->toBe('morphOne')
        ->and($field->getComponent())->toBe('FieldMorphOne')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->relationType())->toBe('morphOne')
        ->and($field->getColumnsForSelect(Student::class))->toBe([]);
});

it('uses the relation name as the field key like every other relation field', function () {
    $field = MorphOne::make('Latest Note', 'latestNote', NoteResource::class);

    expect($field->key())->toBe('latestNote')
        ->and($field->toArray(createNadotaRequest(), null, new StudentResource())['key'])->toBe('latestNote');
})->skip('ISSUE REL-001: MorphOne never sets the key to the relation name, so key() is ""');

it('is only shown on detail and only applied to the show query by default', function () {
    $field = MorphOne::make('Latest Note', 'latestNote');
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeFalse()
        ->and($field->isShowOnUpdate($request, null))->toBeFalse()
        ->and($field->isAppliedInIndexQuery())->toBeFalse()
        ->and($field->isAppliedInShowQuery())->toBeTrue();
});

// --- resolve ------------------------------------------------------------

it('resolves the single related model with its class as morphType', function () {
    $student = morphOneStudentWithNotes(['old', 'new']);
    $latest = Note::where('body', 'new')->first();

    $value = MorphOne::make('Latest Note', 'latestNote', NoteResource::class)->displayAttribute('body')
        ->resolve(createNadotaRequest(), $student, new StudentResource());

    expect($value)->toBe([
        'id' => $latest->id,
        'key' => $latest->id,
        'label' => 'new',
        'resource' => 'note',
        'deletedAt' => null,
        'morphType' => Note::class,
    ]);
});

it('formats without resource as id, label, null resource, morphType and deletedAt', function () {
    $student = morphOneStudentWithNotes(['only']);
    $note = Note::first();

    $value = MorphOne::make('Latest Note', 'latestNote')->displayUsing(fn ($n) => strtoupper($n->body))
        ->resolve(createNadotaRequest(), $student, null);

    expect($value)->toBe([
        'id' => $note->id,
        'label' => 'ONLY',
        'resource' => null,
        'morphType' => Note::class,
        'deletedAt' => null,
    ]);
});

it('includes rendered fields as attributes only with withFields()', function () {
    $student = morphOneStudentWithNotes(['only']);

    $value = MorphOne::make('Latest Note', 'latestNote', NoteResource::class)
        ->fields([Input::make('Body', 'body')])
        ->withFields()
        ->resolve(createNadotaRequest(), $student, null);

    expect($value['attributes'][0]['value'])->toBe('only')
        ->and($value)->not->toHaveKey('permissions');
});

it('returns null, or the default, when there is no related model', function () {
    $student = Student::create(['name' => 'Ana']);
    $request = createNadotaRequest();

    expect(MorphOne::make('L', 'latestNote')->resolve($request, $student, null))->toBeNull()
        ->and(MorphOne::make('L', 'latestNote')->default('none')->resolve($request, $student, null))->toBe('none')
        ->and(MorphOne::make('L', 'missingRelation')->resolve($request, $student, null))->toBeNull();
});

// --- props --------------------------------------------------------------

it('adds create/show urls and a polymorphic createContext with a model and related resource', function () {
    $student = Student::create(['name' => 'Ana']);

    $props = MorphOne::make('Latest Note', 'latestNote', NoteResource::class)
        ->toArray(createNadotaRequest(), $student, new StudentResource())['props'];

    expect($props['relationType'])->toBe('morphOne')
        ->and($props['resource'])->toBe('note')
        ->and($props['isPolymorphic'])->toBeTrue()
        ->and($props['urls'])->toBe([
            'create' => '/nadota-api/note/resource/create',
            'show' => '/nadota-api/note/resource',
        ])
        ->and($props['createContext'])->toMatchArray([
            'parentResource' => 'student',
            'parentId' => $student->id,
            'relatedResource' => 'note',
            'morphType' => 'notable_type',
            'morphId' => 'notable_id',
            'morphClass' => Student::class,
            'prefill' => ['notable_type' => Student::class, 'notable_id' => $student->id],
            'lock' => ['notable_type', 'notable_id'],
            'isPolymorphic' => true,
        ]);
});

it('omits urls and createContext without a model', function () {
    $props = MorphOne::make('Latest Note', 'latestNote', NoteResource::class)
        ->toArray(createNadotaRequest(), null, new StudentResource())['props'];

    expect($props)->toBe(['relationType' => 'morphOne', 'resource' => 'note', 'isPolymorphic' => true]);
});

// --- eager loading ------------------------------------------------------

it('adds the morph id and type columns to the eager-load columns', function () {
    $noteResource = new class extends Resource {
        public string $model = Note::class;
        public function fields(NadotaRequest $request): array { return [Input::make('Body', 'body')]; }
    };

    $columns = MorphOne::make('Latest Note', 'latestNote', get_class($noteResource))
        ->getRelatedColumns(morphOneRequest(new StudentResource()));

    expect($columns)->toEqualCanonicalizing(['body', 'id', 'notable_id', 'notable_type']);
});

it('eager loads a latestOfMany relation with the constrained columns', function () {
    $first = morphOneStudentWithNotes(['a-old', 'a-new']);
    $second = morphOneStudentWithNotes(['b-only']);
    $resource = new StudentResource();
    $relations = $resource->getEagerLoadRelations(morphOneRequest($resource));

    DB::enableQueryLog();
    $loaded = Student::with(['latestNote' => $relations['latestNote']])->orderBy('id')->get();

    expect($loaded->map(fn ($s) => $s->latestNote->body)->all())->toBe(['a-new', 'b-only'])
        ->and(DB::getQueryLog())->toHaveCount(2);
});

// --- fill / sorting / filters / export ---------------------------------

it('never fills the parent model', function () {
    $student = new Student(['name' => 'Ana']);

    MorphOne::make('L', 'latestNote')->fill(createNadotaRequest(['latestNote' => 1, '' => 2]), $student);

    expect($student->getDirty())->toBe(['name' => 'Ana']);
});

it('does not alter the parent query when sorting', function () {
    $student = new Student();
    $query = $student->newQuery();

    expect(MorphOne::make('L', 'latestNote')->applySorting($query, 'asc', $student))->toBe($query)
        ->and($query->getQuery()->orders)->toBeNull();
});

it('exposes a whereHas relation filter keyed by the relation when filterable', function () {
    $filters = MorphOne::make('L', 'latestNote', NoteResource::class)->filterable()->filters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(RelationFilter::class)
        ->and($filters[0]->key())->toBe('latestNote');
})->skip('ISSUE REL-001: the filter is keyed by the empty field key ""');

it('exports the related label like HasOne does', function () {
    $student = morphOneStudentWithNotes(['only']);

    $value = MorphOne::make('L', 'latestNote', NoteResource::class)->displayAttribute('body')
        ->resolveForExport(createNadotaRequest(), $student, new StudentResource());

    expect($value)->toBe('only');
})->skip('ISSUE REL-008: MorphOne has no resolveForExport(); export falls back to resolve() and json-encodes the item');

it('is exported as a single value when shown on the index, like HasOne', function () {
    $resource = new class extends Resource {
        public string $model = Student::class;
        public function fields(NadotaRequest $request): array
        {
            return [MorphOne::make('Related', 'latestNote', NoteResource::class)->showOnIndex()];
        }
    };

    expect($resource->getExportableFields(createNadotaRequest()))->toHaveCount(1);
})->skip('ISSUE REL-008: ResourceExportable::isFieldExportable() only whitelists BelongsTo, MorphTo and HasOne single-value relations');
