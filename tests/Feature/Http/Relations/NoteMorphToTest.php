<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelNoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

/**
 * NoteResource's MorphTo "notable" pointing at a Student or a Grade, through the
 * real routes: show, index, store, update.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi([...$this->fixtureResources, RelNoteResource::class]);
});

afterEach(function () {
    RelNoteResource::reset();
});

function noteMorphToField(array $payload): ?array
{
    return collect($payload['data']['attributes'])->firstWhere('key', 'notable_id');
}

// --- show ---------------------------------------------------------------

it('shows a note attached to a student', function () {
    $student = Student::create(['name' => 'Ana']);
    $note = $student->notes()->create(['body' => 'hello']);

    $field = noteMorphToField($this->getJson($this->apiUrl('note', (string) $note->id))->assertOk()->json());

    expect($field['type'])->toBe('morphTo')
        ->and($field['value'])->toBe([
            'id' => $student->id,
            'key' => $student->id,
            'label' => 'Ana',
            'resource' => 'student',
            'deletedAt' => null,
            'type' => 'student',
            'typeLabel' => 'Student',
            'optionsUrl' => '/nadota-api/note/resource/field/notable_id/morph-options/student',
        ])
        ->and(collect($field['props']['morphTypes'])->pluck('value')->all())->toBe(['student', 'grade'])
        ->and($field['props']['baseOptionsUrl'])->toBe('/nadota-api/note/resource/field/notable_id/morph-options');
});

it('shows a note attached to a grade', function () {
    $grade = Grade::create(['name' => '5th A']);
    $note = $grade->notes()->create(['body' => 'hello']);

    $value = noteMorphToField($this->getJson($this->apiUrl('note', (string) $note->id))->json())['value'];

    expect($value)->toMatchArray(['id' => $grade->id, 'label' => '5th A', 'resource' => 'grade', 'type' => 'grade', 'typeLabel' => 'Grade']);
});

it('shows a null target for a note without notable', function () {
    $note = Note::create(['body' => 'orphan']);

    expect(noteMorphToField($this->getJson($this->apiUrl('note', (string) $note->id))->json())['value'])->toBeNull();
});

it('lists notes of both morph types with one query per type', function () {
    foreach (range(1, 4) as $i) {
        Student::create(['name' => "S{$i}"])->notes()->create(['body' => "s{$i}"]);
        Grade::create(['name' => "G{$i}"])->notes()->create(['body' => "g{$i}"]);
    }

    DB::enableQueryLog();
    $rows = $this->getJson($this->apiUrl('note') . '?perPage=50')->assertOk()->json('data');
    $sql = collect(DB::getQueryLog())->pluck('query');

    $types = collect($rows)->map(fn ($row) => collect($row['attributes'])->firstWhere('key', 'notable_id')['value']['type'])->countBy();

    expect($types->all())->toEqualCanonicalizing(['student' => 4, 'grade' => 4])
        ->and($sql->filter(fn ($q) => str_contains($q, 'from "fx_students"')))->toHaveCount(1)
        ->and($sql->filter(fn ($q) => str_contains($q, 'from "fx_grades"')))->toHaveCount(1);
});

it('filters the note index by morph type and id when the MorphTo is filterable', function () {
    RelNoteResource::using(fn () => [
        Textarea::make('Body', 'body'),
        MorphTo::make('Notable', 'notable', ['student' => StudentResource::class, 'grade' => GradeResource::class])->filterable(),
    ]);
    $ana = Student::create(['name' => 'Ana']);
    $ana->notes()->create(['body' => 'ana']);
    Student::create(['name' => 'Beto'])->notes()->create(['body' => 'beto']);
    Grade::create(['name' => 'G'])->notes()->create(['body' => 'grade']);

    $rows = $this->getJson($this->apiUrl('rel-note') . "?filters[notable_type]=student&filters[notable_id]={$ana->id}")
        ->assertOk()
        ->json('data');

    expect(collect($rows)->map(fn ($row) => $row['attributes'][0]['value'])->all())->toBe(['ana']);
});

// --- store / update -----------------------------------------------------

it('stores a note for a student and for a grade from the morph alias', function () {
    $student = Student::create(['name' => 'Ana']);
    $grade = Grade::create(['name' => 'G']);

    $this->postJson($this->apiUrl('note'), ['body' => 'for student', 'notable_type' => 'student', 'notable_id' => $student->id])
        ->assertCreated();
    $this->postJson($this->apiUrl('note'), ['body' => 'for grade', 'notable_type' => 'grade', 'notable_id' => $grade->id])
        ->assertCreated();

    expect($student->notes()->pluck('body')->all())->toBe(['for student'])
        ->and($grade->notes()->pluck('body')->all())->toBe(['for grade'])
        ->and(Note::pluck('notable_type')->all())->toBe([Student::class, Grade::class]);
});

it('stores a note without notable when the morph columns are omitted', function () {
    $this->postJson($this->apiUrl('note'), ['body' => 'orphan'])->assertCreated();

    expect(Note::first()->only(['notable_type', 'notable_id']))->toBe(['notable_type' => null, 'notable_id' => null]);
});

it('validates the morph id when the MorphTo is required', function () {
    RelNoteResource::using(fn () => [
        Textarea::make('Body', 'body'),
        MorphTo::make('Notable', 'notable', ['student' => StudentResource::class])->required(),
    ]);

    $this->postJson($this->apiUrl('rel-note'), ['body' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['notable_id']);
});

it('rejects an unknown morph type with a validation error', function () {
    $this->postJson($this->apiUrl('note'), ['body' => 'x', 'notable_type' => 'teacher', 'notable_id' => 1])
        ->assertUnprocessable();

    expect(Note::count())->toBe(0);
})->skip('ISSUE REL-011: an unknown type hits ResourceManager::getResourceByKey() abort(404), caught by the store service as a 500');

it('moves a note from a student to a grade on update', function () {
    $student = Student::create(['name' => 'Ana']);
    $grade = Grade::create(['name' => 'G']);
    $note = $student->notes()->create(['body' => 'x']);

    $this->putJson($this->apiUrl('note', (string) $note->id), ['body' => 'x', 'notable_type' => 'grade', 'notable_id' => $grade->id])
        ->assertOk();

    expect($note->fresh()->only(['notable_type', 'notable_id']))->toBe(['notable_type' => Grade::class, 'notable_id' => $grade->id]);
});

it('keeps the notable when a partial update does not send it', function () {
    $student = Student::create(['name' => 'Ana']);
    $note = $student->notes()->create(['body' => 'x']);

    $this->patchJson($this->apiUrl('note', (string) $note->id), ['body' => 'changed'])->assertOk();

    expect($note->fresh()->only(['body', 'notable_type', 'notable_id']))
        ->toBe(['body' => 'changed', 'notable_type' => Student::class, 'notable_id' => $student->id]);
})->skip('ISSUE REL-002: MorphTo::fill() nulls notable_type/notable_id when the request omits them (data loss on PATCH)');

it('stores the morph-map alias so the parent relation still finds the note', function () {
    Relation::morphMap(['student' => Student::class, 'grade' => Grade::class]);

    try {
        $student = Student::create(['name' => 'Ana']);

        $this->postJson($this->apiUrl('note'), ['body' => 'mapped', 'notable_type' => 'student', 'notable_id' => $student->id])
            ->assertCreated();

        expect(Note::first()->notable_type)->toBe('student')
            ->and($student->notes()->pluck('body')->all())->toBe(['mapped']);
    } finally {
        Relation::morphMap([], false);
    }
})->skip('ISSUE REL-015: MorphTo::fill() stores the class name; with a morph map $student->notes() no longer finds the row');
