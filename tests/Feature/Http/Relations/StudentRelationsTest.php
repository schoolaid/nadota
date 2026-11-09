<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphToMany;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Label;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Models\StudentProfile;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelGradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\CourseResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\LabelResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\NoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

/**
 * How every relation of StudentResource renders through the real routes:
 * show, index, create, edit, and how the pivot relations persist on store/update.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi([...$this->fixtureResources, RelStudentResource::class, RelGradeResource::class]);
});

afterEach(function () {
    RelStudentResource::reset();
    RelGradeResource::reset();
});

/**
 * Ana in 5th A with a profile, two courses (scores 90 / 75), two notes and two labels.
 */
function studentRelationsSeed(int $extraRows = 0): Student
{
    $grade = Grade::create(['name' => '5th A', 'level' => 5]);
    $student = Student::create(['name' => 'Ana', 'grade_id' => $grade->id]);
    StudentProfile::create(['student_id' => $student->id, 'nickname' => 'Annie']);

    $math = Course::create(['name' => 'Math', 'code' => 'M1']);
    $art = Course::create(['name' => 'Art', 'code' => 'A1']);
    $student->courses()->attach([$math->id => ['score' => 90], $art->id => ['score' => 75]]);

    $student->notes()->create(['body' => 'first note']);
    $student->notes()->create(['body' => 'latest note']);

    $student->labels()->attach([Label::create(['name' => 'VIP'])->id, Label::create(['name' => 'New'])->id]);

    foreach (range(1, $extraRows) as $i) {
        if ($extraRows === 0) {
            break;
        }
        $student->courses()->attach(Course::create(['name' => "Extra {$i}"])->id, ['score' => $i]);
        $student->notes()->create(['body' => "extra {$i}"]);
        $student->labels()->attach(Label::create(['name' => "Extra {$i}"])->id);
    }

    return $student;
}

function studentRelationsField(array $payload, string $key): ?array
{
    return collect($payload['data']['attributes'])->firstWhere('key', $key);
}

// --- show ---------------------------------------------------------------

it('renders the BelongsTo grade as a compact object', function () {
    $student = studentRelationsSeed();

    $payload = $this->getJson($this->apiUrl('student', (string) $student->id))->assertOk()->json();

    expect(studentRelationsField($payload, 'grade_id'))
        ->toMatchArray(['type' => 'belongsTo', 'component' => 'FieldBelongsTo', 'filterable' => true])
        ->and(studentRelationsField($payload, 'grade_id')['value'])->toBe([
            'id' => $student->grade_id,
            'key' => $student->grade_id,
            'label' => '5th A',
            'resource' => 'grade',
            'deletedAt' => null,
        ]);
});

it('renders the HasOne profile with its createContext', function () {
    $student = studentRelationsSeed();

    $field = studentRelationsField($this->getJson($this->apiUrl('student', (string) $student->id))->json(), 'profile');

    expect($field['value'])->toMatchArray(['label' => (string) $student->profile->id, 'resource' => 'student-profile'])
        ->and($field['props']['createContext']['prefill'])->toBe(['student_id' => $student->id])
        ->and($field['props']['urls']['create'])->toBe('/nadota-api/student-profile/resource/create');
});

it('renders the BelongsToMany courses as a collection with attach urls', function () {
    $student = studentRelationsSeed();

    $field = studentRelationsField($this->getJson($this->apiUrl('student', (string) $student->id))->json(), 'courses');

    expect(collect($field['value']['data'])->pluck('label')->sort()->values()->all())->toBe(['Art', 'Math'])
        ->and($field['value']['meta'])->toMatchArray(['total' => 2, 'hasMore' => false, 'resource' => 'course'])
        ->and($field['props']['urls']['attach'])->toBe("/nadota-api/student/resource/{$student->id}/attach/courses")
        ->and($field['pivotFields'])->toBe([]);
});

it('renders pivot scores when the BelongsToMany declares withPivot', function () {
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        BelongsToMany::make('Courses', 'courses', CourseResource::class)
            ->withPivot(['score'])
            ->pivotFields([Number::make('Score', 'score')]),
    ]);
    $student = studentRelationsSeed();

    $field = studentRelationsField($this->getJson($this->apiUrl('rel-student', (string) $student->id))->assertOk()->json(), 'courses');

    expect(collect($field['value']['data'])->mapWithKeys(fn ($item) => [$item['label'] => $item['pivot']['score']])->all())
        ->toBe(['Math' => 90, 'Art' => 75])
        ->and($field['value']['meta']['pivotColumns'])->toBe(['score'])
        ->and($field['pivotFields'][0]['key'])->toBe('score');
});

it('renders the MorphMany notes and the MorphOne latest note', function () {
    $student = studentRelationsSeed();
    $latest = Note::where('body', 'latest note')->first();

    $payload = $this->getJson($this->apiUrl('student', (string) $student->id))->json();
    $notes = studentRelationsField($payload, 'notes');
    $latestNote = collect($payload['data']['attributes'])->firstWhere('component', 'FieldMorphOne');

    expect($notes['value']['meta'])->toMatchArray(['total' => 2, 'isPolymorphic' => true, 'resource' => 'note'])
        ->and($notes['props']['createContext']['prefill'])->toBe(['notable_type' => Student::class, 'notable_id' => $student->id])
        ->and($latestNote['value'])->toMatchArray(['id' => $latest->id, 'resource' => 'note', 'morphType' => Note::class])
        ->and($latestNote['label'])->toBe('Latest Note');
});

it('renders the MorphToMany labels as a polymorphic collection', function () {
    $student = studentRelationsSeed();

    $field = studentRelationsField($this->getJson($this->apiUrl('student', (string) $student->id))->json(), 'labels');

    expect($field['component'])->toBe('FieldMorphToMany')
        ->and(collect($field['value']['data'])->pluck('label')->sort()->values()->all())->toBe(['New', 'VIP'])
        ->and($field['value']['meta']['isPolymorphic'])->toBeTrue()
        ->and($field['props']['urls']['sync'])->toBe("/nadota-api/student/resource/{$student->id}/sync/labels");
});

it('gives every relation field of the show payload a distinct, non-empty key', function () {
    $student = studentRelationsSeed();

    $keys = collect($this->getJson($this->apiUrl('student', (string) $student->id))->json('data.attributes'))->pluck('key');

    expect($keys)->not->toContain('')
        ->and($keys->duplicates()->all())->toBe([]);
})->skip('ISSUE REL-001: the MorphOne latestNote field is serialized with key ""');

it('runs a bounded number of queries on show, whatever the number of related rows', function () {
    $small = studentRelationsSeed();
    DB::enableQueryLog();
    $this->getJson($this->apiUrl('student', (string) $small->id))->assertOk();
    $smallCount = count(DB::getQueryLog());

    DB::flushQueryLog();
    $large = studentRelationsSeed(extraRows: 6);
    DB::flushQueryLog();
    $this->getJson($this->apiUrl('student', (string) $large->id))->assertOk();
    $largeCount = count(DB::getQueryLog());

    expect($largeCount)->toBe($smallCount)
        ->and($smallCount)->toBeLessThanOrEqual(10);
});

it('does not eager load a paginated relation on show and exposes its pagination url instead', function () {
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        BelongsToMany::make('Courses', 'courses', CourseResource::class)->paginated(),
        MorphMany::make('Notes', 'notes', NoteResource::class),
    ]);
    $student = studentRelationsSeed();

    DB::enableQueryLog();
    $payload = $this->getJson($this->apiUrl('rel-student', (string) $student->id))->assertOk()->json();
    $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");

    expect(studentRelationsField($payload, 'courses')['value'])
        ->toBe(['data' => [], 'meta' => ['total' => 0, 'hasMore' => false, 'paginated' => true, 'pivotColumns' => []]])
        ->and(studentRelationsField($payload, 'courses')['props']['paginationUrl'])
        ->toBe("/nadota-api/rel-student/resource/{$student->id}/relation/courses")
        ->and($sql)->not->toContain('fx_course_student')
        ->and(studentRelationsField($payload, 'notes')['value']['meta']['total'])->toBe(2);
});

it('applies limit and orderBy to inline relations on show', function () {
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        BelongsToMany::make('Courses', 'courses', CourseResource::class)->orderBy('name', 'asc')->limit(1),
        MorphToMany::make('Labels', 'labels', LabelResource::class)->orderBy('name', 'desc')->limit(5),
    ]);
    $student = studentRelationsSeed();

    $payload = $this->getJson($this->apiUrl('rel-student', (string) $student->id))->assertOk()->json();

    expect(collect(studentRelationsField($payload, 'courses')['value']['data'])->pluck('label')->all())->toBe(['Art'])
        ->and(studentRelationsField($payload, 'courses')['value']['meta']['hasMore'])->toBeTrue()
        ->and(collect(studentRelationsField($payload, 'labels')['value']['data'])->pluck('label')->all())->toBe(['VIP', 'New'])
        ->and(studentRelationsField($payload, 'labels')['value']['meta']['hasMore'])->toBeFalse();
});

it('renders related fields as attributes with withFields() and skips exceptFields()', function () {
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        BelongsTo::make('Grade', 'grade', GradeResource::class)->withFields()->exceptFields(['school', 'level']),
    ]);
    $student = studentRelationsSeed();

    $value = studentRelationsField($this->getJson($this->apiUrl('rel-student', (string) $student->id))->json(), 'grade_id')['value'];

    expect(collect($value['attributes'])->pluck('value', 'key')->all())->toBe(['name' => '5th A']);
});

// --- index --------------------------------------------------------------

it('only renders the BelongsTo grade on the index', function () {
    studentRelationsSeed();

    $row = $this->getJson($this->apiUrl('student'))->assertOk()->json('data.0');
    $types = collect($row['attributes'])->pluck('type', 'key');

    expect($types->get('grade_id'))->toBe('belongsTo')
        ->and($types->keys())->not->toContain('courses', 'notes', 'labels', 'profile')
        ->and(collect($row['attributes'])->firstWhere('key', 'grade_id')['value']['label'])->toBe('5th A');
});

it('eager loads the grades of a whole index page with a single query', function () {
    foreach (range(1, 6) as $i) {
        Student::create(['name' => "S{$i}", 'grade_id' => Grade::create(['name' => "G{$i}"])->id]);
    }

    DB::enableQueryLog();
    $this->getJson($this->apiUrl('student'))->assertOk()->assertJsonCount(6, 'data');
    $gradeQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, 'from "fx_grades"'));

    expect($gradeQueries)->toHaveCount(1);
});

it('eager loads a to-many relation shown on the index instead of querying it per row', function () {
    RelGradeResource::using(fn () => [
        Input::make('Name', 'name'),
        HasMany::make('Students', 'students', StudentResource::class)->showOnIndex(),
    ]);
    foreach (range(1, 5) as $i) {
        $grade = Grade::create(['name' => "G{$i}"]);
        Student::create(['name' => "S{$i}", 'grade_id' => $grade->id]);
    }

    DB::enableQueryLog();
    $this->getJson($this->apiUrl('rel-grade'))->assertOk()->assertJsonCount(5, 'data');
    $studentQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, 'from "fx_students"'));

    expect($studentQueries)->toHaveCount(1);
})->skip('ISSUE REL-017: to-many fields default to applyInIndexQuery = false, so showOnIndex() loads them lazily per row (N+1)');

it('filters the index by the BelongsTo foreign key', function () {
    $student = studentRelationsSeed();
    Student::create(['name' => 'Other', 'grade_id' => Grade::create(['name' => 'Other grade'])->id]);

    $this->getJson($this->apiUrl('student') . '?filters[grade_id]=' . $student->grade_id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $student->id);
});

// --- create / edit ------------------------------------------------------

it('offers only the fillable relations in the create form, with options urls', function () {
    $payload = $this->getJson($this->apiUrl('student', 'create'))->assertOk()->json();
    $fields = collect($payload)->dot()->filter(fn ($value, $key) => str_ends_with($key, '.key'))->values();

    expect($fields)->toContain('grade_id', 'courses', 'labels')
        ->not->toContain('profile', 'notes')
        ->and(json_encode($payload))->toContain('\/nadota-api\/student\/resource\/field\/courses\/options"');
});

it('renders the current relation values in the edit form', function () {
    $student = studentRelationsSeed();

    $payload = $this->getJson($this->apiUrl('student', $student->id . '/edit'))->assertOk()->json();
    $keys = collect($payload['data']['attributes'])->pluck('key');

    expect($keys)->toContain('grade_id', 'courses', 'labels')
        ->not->toContain('profile', 'notes')
        ->and(studentRelationsField($payload, 'courses')['value']['meta']['total'])->toBe(2)
        ->and(studentRelationsField($payload, 'grade_id')['value']['label'])->toBe('5th A');
});

// --- store / update -----------------------------------------------------

it('stores the grade and syncs courses and labels on store', function () {
    $grade = Grade::create(['name' => '5th A']);
    $courses = [Course::create(['name' => 'Math'])->id, Course::create(['name' => 'Art'])->id];
    $label = Label::create(['name' => 'VIP']);

    $this->postJson($this->apiUrl('student'), [
        'name' => 'Ana',
        'password' => 'secret-password',
        'grade_id' => $grade->id,
        'courses' => $courses,
        'labels' => [$label->id],
    ])->assertCreated();

    $student = Student::firstWhere('name', 'Ana');

    expect($student->grade_id)->toBe($grade->id)
        ->and($student->courses()->pluck('fx_courses.id')->sort()->values()->all())->toBe($courses)
        ->and($student->labels()->pluck('fx_labels.id')->all())->toBe([$label->id])
        ->and(DB::table('fx_labelables')->value('labelable_type'))->toBe(Student::class);
});

it('stores pivot attributes sent as id-bearing entries', function () {
    $math = Course::create(['name' => 'Math']);

    $this->postJson($this->apiUrl('student'), [
        'name' => 'Ana',
        'password' => 'secret-password',
        'courses' => [['id' => $math->id, 'score' => 88]],
    ])->assertCreated();

    expect(Student::first()->courses()->first()->pivot->score)->toBe(88);
});

it('re-syncs the pivot relations on update and leaves them alone when omitted', function () {
    $student = studentRelationsSeed();
    $keep = $student->courses()->firstWhere('name', 'Math');

    $this->putJson($this->apiUrl('student', (string) $student->id), ['name' => 'Ana', 'courses' => [$keep->id]])->assertOk();
    $afterSync = $student->courses()->pluck('fx_courses.id')->all();

    $this->putJson($this->apiUrl('student', (string) $student->id), ['name' => 'Ana B.'])->assertOk();

    expect($afterSync)->toBe([$keep->id])
        ->and($student->courses()->pluck('fx_courses.id')->all())->toBe([$keep->id])
        ->and($student->labels()->count())->toBe(2)
        ->and($student->fresh()->grade_id)->not->toBeNull();
});

it('validates a required BelongsToMany against the field key', function () {
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        BelongsToMany::make('Courses', 'courses', CourseResource::class)->required(),
    ]);
    $math = Course::create(['name' => 'Math']);

    $this->postJson($this->apiUrl('rel-student'), ['name' => 'Ana', 'courses' => [$math->id]])->assertCreated();
    $this->postJson($this->apiUrl('rel-student'), ['name' => 'Beto'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['courses']);
})->skip('ISSUE REL-009: rules of empty-attribute relation fields are keyed by "" so required() can never pass');

it('rejects course ids that do not exist instead of attaching them', function () {
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        BelongsToMany::make('Courses', 'courses', CourseResource::class)->rules(['array']),
    ]);

    $this->postJson($this->apiUrl('rel-student'), ['name' => 'Ana', 'courses' => [999]])->assertUnprocessable();

    expect(DB::table('fx_course_student')->count())->toBe(0);
})->skip('ISSUE REL-009: pivot sync reads the raw request (no exists check) and field rules never reach the "courses" key');
