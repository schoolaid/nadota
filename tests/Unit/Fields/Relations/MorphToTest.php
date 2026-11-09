<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Models\OwnedNote;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\NoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

beforeEach(function () {
    FixtureSchema::create();
});

afterEach(function () {
    InteractsWithNadotaApi::resetResourceManager();
});

function morphToNotable(): MorphTo
{
    return MorphTo::make('Notable', 'notable', [
        'student' => StudentResource::class,
        'grade' => GradeResource::class,
    ]);
}

function morphToNoteFor($notable, string $body = 'note'): Note
{
    return Note::create(['body' => $body, 'notable_type' => get_class($notable), 'notable_id' => $notable->getKey()]);
}

// --- construction -------------------------------------------------------

it('infers the morph type and id attributes from the relation name', function () {
    $field = morphToNotable();

    expect($field)->toBeField()
        ->and($field->getAttribute())->toBe('notable_id')
        ->and($field->key())->toBe('notable_id')
        ->and($field->getRelation())->toBe('notable')
        ->and($field->getMorphTypeAttribute())->toBe('notable_type')
        ->and($field->getMorphIdAttribute())->toBe('notable_id')
        ->and($field->getType())->toBe('morphTo')
        ->and($field->getComponent())->toBe('FieldMorphTo')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->isMorphTo())->toBeTrue();
});

it('is visible everywhere and applied to both index and show queries by default', function () {
    $field = morphToNotable();
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeTrue()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeTrue()
        ->and($field->isShowOnUpdate($request, null))->toBeTrue()
        ->and($field->isAppliedInIndexQuery())->toBeTrue()
        ->and($field->isAppliedInShowQuery())->toBeTrue();
});

it('auto-detects the morph models from the resources map', function () {
    $field = morphToNotable();

    expect($field->getMorphResources())->toBe(['student' => StudentResource::class, 'grade' => GradeResource::class])
        ->and($field->getMorphModels())->toBe(['student' => Student::class, 'grade' => Grade::class]);
});

it('keeps explicit models and lets addMorphType() register more types', function () {
    $field = MorphTo::make('Notable', 'notable')
        ->models(['pupil' => Student::class])
        ->resources(['pupil' => StudentResource::class])
        ->addMorphType('grade', Grade::class, GradeResource::class);

    expect($field->getMorphModels())->toBe(['pupil' => Student::class, 'grade' => Grade::class])
        ->and($field->getMorphResources())->toBe(['pupil' => StudentResource::class, 'grade' => GradeResource::class]);
});

it('selects both morph columns resolved from the Eloquent relation', function () {
    expect(morphToNotable()->getColumnsForSelect(Note::class))->toBe(['notable_type', 'notable_id'])
        ->and(MorphTo::make('Owner', 'owner')->getColumnsForSelect(OwnedNote::class))->toBe(['notable_type', 'notable_id'])
        // Without the relation on the model the inferred attributes are used.
        ->and(MorphTo::make('Owner', 'owner')->getColumnsForSelect(Note::class))->toBe(['owner_type', 'owner_id']);
});

it('does not constrain the eager-load columns, since each morph type has its own table', function () {
    expect(morphToNotable()->getRelatedColumns(createNadotaRequest()))->toBeNull();
});

// --- morph types / props -------------------------------------------------

it('lists every morph type with its resource key and options url', function () {
    expect(morphToNotable()->getMorphTypes(new NoteResource()))->toBe([
        ['value' => 'student', 'label' => 'Student', 'resource' => 'student', 'optionsUrl' => '/nadota-api/note/resource/field/notable_id/morph-options/student'],
        ['value' => 'grade', 'label' => 'Grade', 'resource' => 'grade', 'optionsUrl' => '/nadota-api/note/resource/field/notable_id/morph-options/grade'],
    ])
        ->and(morphToNotable()->getMorphTypes()[0]['optionsUrl'])->toBeNull();
});

it('labels morph types by titleizing the alias', function () {
    $types = MorphTo::make('Notable', 'notable', ['study_group' => GradeResource::class])->getMorphTypes();

    expect($types[0]['label'])->toBe('Study Group');
});

it('serializes the morph configuration in props', function () {
    $props = morphToNotable()->toArray(createNadotaRequest(), null, new NoteResource())['props'];

    expect($props['morphTypes'])->toHaveCount(2)
        ->and($props['morphTypeAttribute'])->toBe('notable_type')
        ->and($props['morphIdAttribute'])->toBe('notable_id')
        ->and($props['isPolymorphic'])->toBeTrue()
        ->and($props['baseOptionsUrl'])->toBe('/nadota-api/note/resource/field/notable_id/morph-options')
        ->and(morphToNotable()->toArray(createNadotaRequest())['props']['baseOptionsUrl'])->toBeNull();
});

// --- resolve ------------------------------------------------------------

it('resolves a student target with its alias, type label and options url', function () {
    $student = Student::create(['name' => 'Ana']);
    $note = morphToNoteFor($student);

    $value = morphToNotable()->resolve(createNadotaRequest(), $note, new NoteResource());

    expect($value)->toBe([
        'id' => $student->id,
        'key' => $student->id,
        'label' => 'Ana',
        'resource' => 'student',
        'deletedAt' => null,
        'type' => 'student',
        'typeLabel' => 'Student',
        'optionsUrl' => '/nadota-api/note/resource/field/notable_id/morph-options/student',
    ]);
});

it('resolves a grade target with the grade resource', function () {
    $grade = Grade::create(['name' => '5th A']);
    $note = morphToNoteFor($grade);

    $value = morphToNotable()->resolve(createNadotaRequest(), $note, new NoteResource());

    expect($value)->toMatchArray(['id' => $grade->id, 'label' => '5th A', 'resource' => 'grade', 'type' => 'grade']);
});

it('includes rendered fields of the target only with withFields()', function () {
    $note = morphToNoteFor(Student::create(['name' => 'Ana']));

    $value = morphToNotable()->fields([Input::make('Name', 'name')])->withFields()
        ->resolve(createNadotaRequest(), $note, null);

    expect($value['attributes'][0]['value'])->toBe('Ana')
        ->and($value['optionsUrl'])->toBeNull()
        ->and($value)->not->toHaveKey('permissions');
});

it('formats basically, using the class basename as alias, for an unregistered morph type', function () {
    $student = Student::create(['name' => 'Ana']);
    $note = morphToNoteFor($student);

    $value = MorphTo::make('Notable', 'notable', ['grade' => GradeResource::class])->displayAttribute('name')
        ->resolve(createNadotaRequest(), $note, new NoteResource());

    expect($value)->toBe([
        'id' => $student->id,
        'label' => 'Ana',
        'resource' => null,
        'type' => 'Student',
        'typeLabel' => 'Student',
        'optionsUrl' => null,
        'deletedAt' => null,
    ]);
});

it('returns null, or the default, when the morph columns are empty', function () {
    $note = Note::create(['body' => 'orphan']);
    $request = createNadotaRequest();

    expect(morphToNotable()->resolve($request, $note, null))->toBeNull()
        ->and(morphToNotable()->default('none')->resolve($request, $note, null))->toBe('none');
});

it('returns null when the target row no longer exists', function () {
    $note = Note::create(['body' => 'dangling', 'notable_type' => Student::class, 'notable_id' => 999]);

    expect(morphToNotable()->resolve(createNadotaRequest(), $note, null))->toBeNull();
});

it('resolves a target stored under a morph-map alias', function () {
    Relation::morphMap(['student' => Student::class]);

    try {
        $student = Student::create(['name' => 'Ana']);
        $note = Note::create(['body' => 'x', 'notable_type' => 'student', 'notable_id' => $student->id]);

        expect(morphToNotable()->resolve(createNadotaRequest(), $note, null))
            ->toMatchArray(['id' => $student->id, 'label' => 'Ana', 'type' => 'student']);
    } finally {
        Relation::morphMap([], false);
    }
});

it('resolves through the relation even when its columns do not follow {relation}_type/_id', function () {
    $student = Student::create(['name' => 'Ana']);
    $note = OwnedNote::create(['body' => 'x', 'notable_type' => Student::class, 'notable_id' => $student->id]);

    $value = MorphTo::make('Owner', 'owner', ['student' => StudentResource::class])
        ->resolve(createNadotaRequest(), $note, null);

    expect($value)->toMatchArray(['id' => $student->id, 'label' => 'Ana', 'type' => 'student']);
})->skip('ISSUE REL-016: MorphTo reads {relation}_type/{relation}_id instead of the relation\'s real morph columns');

// --- fill ---------------------------------------------------------------

it('fills the morph class and id from an alias', function () {
    $note = new Note(['body' => 'x']);

    morphToNotable()->fill(createNadotaRequest(['notable_type' => 'grade', 'notable_id' => 5]), $note);

    expect($note->notable_type)->toBe(Grade::class)
        ->and($note->notable_id)->toBe(5);
});

it('fills from a resource key that differs from the alias', function () {
    $note = new Note(['body' => 'x']);

    MorphTo::make('Notable', 'notable', ['pupil' => StudentResource::class])
        ->fill(createNadotaRequest(['notable_type' => 'student', 'notable_id' => 3]), $note);

    expect($note->notable_type)->toBe(Student::class)
        ->and($note->notable_id)->toBe(3);
});

it('falls back to the ResourceManager for a resource key outside the field map', function () {
    ResourceManager::registerResourceClass(GradeResource::class);
    $note = new Note(['body' => 'x']);

    MorphTo::make('Notable', 'notable', ['student' => StudentResource::class])
        ->fill(createNadotaRequest(['notable_type' => 'grade', 'notable_id' => 8]), $note);

    expect($note->notable_type)->toBe(Grade::class)
        ->and($note->notable_id)->toBe(8);
});

it('clears both columns when the request carries an explicit empty type or id', function () {
    $note = morphToNoteFor(Student::create(['name' => 'Ana']));

    morphToNotable()->fill(createNadotaRequest(['notable_type' => null, 'notable_id' => null]), $note);

    expect($note->notable_type)->toBeNull()
        ->and($note->notable_id)->toBeNull();
});

it('leaves the association untouched when the request does not carry the morph columns', function () {
    $student = Student::create(['name' => 'Ana']);
    $note = morphToNoteFor($student);

    morphToNotable()->fill(createNadotaRequest(['body' => 'changed']), $note);

    expect($note->notable_type)->toBe(Student::class)
        ->and($note->notable_id)->toBe($student->id);
})->skip('ISSUE REL-002: MorphTo::fill() nulls both morph columns whenever the request omits them');

it('does not fill when readonly or disabled', function (string $state) {
    $note = new Note(['body' => 'x']);

    morphToNotable()->{$state}()->fill(createNadotaRequest(['notable_type' => 'grade', 'notable_id' => 5]), $note);

    expect($note->notable_type)->toBeNull();
})->with(['readonly', 'disabled'])
    ->skip('ISSUE REL-002: MorphTo::fill() ignores readonly()/disabled() (BelongsTo honours them)');

it('rejects an unknown morph type without throwing', function () {
    $note = new Note(['body' => 'x']);

    morphToNotable()->fill(createNadotaRequest(['notable_type' => 'teacher', 'notable_id' => 5]), $note);

    expect($note->notable_type)->toBeNull()
        ->and($note->notable_id)->toBeNull();
})->skip('ISSUE REL-011: an unknown type reaches ResourceManager::getResourceByKey(), which aborts with a 404 HttpException');

it('stores the morph-map alias instead of the class when a morph map is registered', function () {
    Relation::morphMap(['student' => Student::class, 'grade' => Grade::class]);

    try {
        $note = new Note(['body' => 'x']);
        morphToNotable()->fill(createNadotaRequest(['notable_type' => 'grade', 'notable_id' => 5]), $note);

        expect($note->notable_type)->toBe('grade');
    } finally {
        Relation::morphMap([], false);
    }
})->skip('ISSUE REL-015: MorphTo::fill() stores the class name, ignoring the morph map');

// --- sorting / filters / export ----------------------------------------

it('sorts by the morph type and then the morph id', function () {
    $note = new Note();

    $sorted = morphToNotable()->applySorting($note->newQuery(), 'asc', $note);

    expect($sorted->getQuery()->orders)->toBe([
        ['column' => 'fx_notes.notable_type', 'direction' => 'asc'],
        ['column' => 'fx_notes.notable_id', 'direction' => 'asc'],
    ]);
});

it('exposes a morph type filter and a morph entity filter when filterable', function () {
    $filters = morphToNotable()->filterable()->filters();

    expect($filters)->toHaveCount(2)
        ->and(collect($filters)->map(fn ($filter) => $filter->key())->all())->toBe(['notable_type', 'notable_id']);
});

it('exports the target as "[Type label] label"', function () {
    $note = morphToNoteFor(Grade::create(['name' => '5th A']));

    // The export passes the parent resource; its generic displayLabel() happens to read `name`.
    expect(morphToNotable()->resolveForExport(createNadotaRequest(), $note, new NoteResource()))->toBe('[Grade] 5th A')
        ->and(morphToNotable()->displayAttribute('name')->resolveForExport(createNadotaRequest(), $note, null))->toBe('[Grade] 5th A')
        ->and(morphToNotable()->resolveForExport(createNadotaRequest(), Note::create(['body' => 'orphan']), null))->toBeNull();
});

it('exports the label from the target resource, not the parent resource', function () {
    $note = morphToNoteFor(Student::create(['name' => 'Ana']));

    $parentResource = new class extends Resource {
        public string $model = Note::class;
        public function fields(NadotaRequest $request): array { return []; }
        public function displayLabel($model): string { return 'NOTE LABEL'; }
    };

    expect(morphToNotable()->resolveForExport(createNadotaRequest(), $note, $parentResource))->toBe('[Student] Ana');
})->skip('ISSUE REL-007: MorphTo::resolveForExport() passes the parent resource to resolveLabel()');
