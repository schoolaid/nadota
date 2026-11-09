<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Label;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelSchoolResource;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\CourseResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\NoteResource;

/**
 * GET /{resource}/resource/{id}/relation/{field}: the paginated loader of the
 * to-many relation fields.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi([...$this->fixtureResources, RelStudentResource::class, RelSchoolResource::class]);
});

afterEach(function () {
    RelStudentResource::reset();
    RelSchoolResource::reset();
});

/**
 * North school, grade G1 with Ana (3 courses, 3 notes, 2 labels) and Beto; a
 * second student/grade/school pair whose rows must never leak into Ana's.
 *
 * @return array{school: School, grade: Grade, ana: Student, label: Label}
 */
function relationEndpointSeed(): array
{
    $school = School::create(['name' => 'North']);
    $grade = Grade::create(['name' => 'G1', 'school_id' => $school->id]);
    $ana = Student::create(['name' => 'Ana', 'grade_id' => $grade->id]);
    Student::create(['name' => 'Beto', 'grade_id' => $grade->id]);
    Grade::create(['name' => 'G0', 'school_id' => $school->id]);

    $courses = collect([['Math', 'M1', 3, 90], ['Art', 'A1', 2, 75], ['Biology', 'B1', 3, 60]])
        ->map(fn ($row) => [Course::create(['name' => $row[0], 'code' => $row[1], 'credits' => $row[2]]), $row[3]]);
    foreach ($courses as [$course, $score]) {
        $ana->courses()->attach($course->id, ['score' => $score]);
    }
    foreach (['n1', 'n2', 'n3'] as $body) {
        $ana->notes()->create(['body' => $body]);
    }
    $label = Label::create(['name' => 'VIP']);
    $ana->labels()->attach([$label->id, Label::create(['name' => 'New'])->id]);

    $otherSchool = School::create(['name' => 'South']);
    $other = Student::create(['name' => 'Zed', 'grade_id' => Grade::create(['name' => 'Z', 'school_id' => $otherSchool->id])->id]);
    $other->courses()->attach(Course::create(['name' => 'Other course'])->id);
    $other->notes()->create(['body' => 'other note']);
    $other->labels()->attach($label->id);

    return ['school' => $school, 'grade' => $grade, 'ana' => $ana, 'label' => $label];
}

function relationEndpointLabels(array $payload): array
{
    return collect($payload['data'])->pluck('label')->all();
}

// --- every to-many relation ---------------------------------------------

it('returns only the rows related to the parent', function (string $resource, string $parent, string $field, ?string $type, string $relatedKey, array $labels) {
    $seed = relationEndpointSeed();

    $payload = $this->getJson($this->apiUrl($resource, $seed[$parent]->id . "/relation/{$field}"))->assertOk()->json();

    expect(relationEndpointLabels($payload))->toEqualCanonicalizing($labels)
        ->and($payload['meta'])->toMatchArray([
            'current_page' => 1,
            'per_page' => 15,
            'total' => count($labels),
            'resource' => $relatedKey,
            'has_pivot' => false,
        ])
        ->and($payload['data'][0])->toHaveKeys(['id', 'key', 'label', 'resource', 'deletedAt', 'permissions']);

    // HasMany rows pass null: their relation_type is covered by the REL-021 test below.
    if ($type !== null) {
        expect($payload['meta']['relation_type'])->toBe($type);
    }
})->with([
    'BelongsToMany student.courses' => ['student', 'ana', 'courses', 'belongsToMany', 'course', ['Math', 'Art', 'Biology']],
    'MorphMany student.notes' => ['student', 'ana', 'notes', 'morphMany', 'note', ['1', '2', '3']],
    'MorphToMany student.labels' => ['student', 'ana', 'labels', 'morphToMany', 'label', ['VIP', 'New']],
    'HasMany grade.students' => ['grade', 'grade', 'students', null, 'student', ['Ana', 'Beto']],
    'HasMany school.grades' => ['school', 'school', 'grades', null, 'grade', ['G1', 'G0']],
    'HasManyThrough school.students' => ['school', 'school', 'students', 'hasManyThrough', 'student', ['Ana', 'Beto']],
    'MorphedByMany label.students' => ['label', 'label', 'students', 'morphedByMany', 'student', ['Ana', 'Zed']],
]);

it('reports relation_type "hasMany" for a HasMany field', function () {
    ['grade' => $grade] = relationEndpointSeed();

    $this->getJson($this->apiUrl('grade', "{$grade->id}/relation/students"))
        ->assertOk()
        ->assertJsonPath('meta.relation_type', 'hasMany');
})->skip('ISSUE REL-021: HasMany has no relationType() method, so meta.relation_type is null');

// --- pagination ---------------------------------------------------------

it('paginates with per_page and page and links the neighbour pages', function () {
    ['ana' => $ana] = relationEndpointSeed();
    $url = $this->apiUrl('student', "{$ana->id}/relation/notes");

    $first = $this->getJson("{$url}?per_page=2")->assertOk()->json();
    $second = $this->getJson("{$url}?per_page=2&page=2")->assertOk()->json();

    expect($first['data'])->toHaveCount(2)
        ->and($first['meta'])->toMatchArray(['current_page' => 1, 'last_page' => 2, 'per_page' => 2, 'total' => 3, 'from' => 1, 'to' => 2])
        ->and($first['links']['prev'])->toBeNull()
        ->and($first['links']['next'])->toContain('page=2')
        ->and($second['data'])->toHaveCount(1)
        ->and($second['meta'])->toMatchArray(['current_page' => 2, 'from' => 3, 'to' => 3])
        ->and($second['links']['next'])->toBeNull();
});

it('returns an empty page past the last one', function () {
    ['ana' => $ana] = relationEndpointSeed();

    $payload = $this->getJson($this->apiUrl('student', "{$ana->id}/relation/courses?page=9"))->assertOk()->json();

    expect($payload['data'])->toBe([])
        ->and($payload['meta'])->toMatchArray(['total' => 3, 'from' => null, 'to' => null]);
});

it('honours any per_page the client asks for', function () {
    ['ana' => $ana] = relationEndpointSeed();

    $payload = $this->getJson($this->apiUrl('student', "{$ana->id}/relation/courses?per_page=500"))->assertOk()->json();

    // REL-019: there is no upper bound (the options/paginated endpoint caps at 100).
    expect($payload['meta']['per_page'])->toBe(500);
});

it('rejects or clamps a non-positive per_page instead of failing', function () {
    ['ana' => $ana] = relationEndpointSeed();

    $status = $this->getJson($this->apiUrl('student', "{$ana->id}/relation/courses?per_page=-1"))->status();

    expect($status)->toBeIn([200, 422]);
})->skip('ISSUE REL-019: per_page is cast to int and handed to paginate() unchecked; -1 returns a 500');

// --- sorting ------------------------------------------------------------

it('sorts by sort_field and sort_direction', function () {
    ['ana' => $ana] = relationEndpointSeed();
    $url = $this->apiUrl('student', "{$ana->id}/relation/courses");

    expect(relationEndpointLabels($this->getJson("{$url}?sort_field=name&sort_direction=asc")->json()))->toBe(['Art', 'Biology', 'Math'])
        ->and(relationEndpointLabels($this->getJson("{$url}?sort_field=name")->json()))->toBe(['Math', 'Biology', 'Art']);
});

it('defaults to the orderBy() column of the field', function () {
    RelSchoolResource::using(fn () => [
        Input::make('Name', 'name'),
        HasMany::make('Grades', 'grades', GradeResource::class)->paginated()->orderBy('name'),
    ]);
    ['school' => $school] = relationEndpointSeed();

    $payload = $this->getJson($this->apiUrl('rel-school', "{$school->id}/relation/grades"))->assertOk()->json();

    expect(relationEndpointLabels($payload))->toBe(['G1', 'G0']);
});

it('defaults to the orderBy() direction of the field', function () {
    RelSchoolResource::using(fn () => [
        Input::make('Name', 'name'),
        HasMany::make('Grades', 'grades', GradeResource::class)->paginated()->orderBy('name', 'asc'),
    ]);
    ['school' => $school] = relationEndpointSeed();

    $payload = $this->getJson($this->apiUrl('rel-school', "{$school->id}/relation/grades"))->assertOk()->json();

    expect(relationEndpointLabels($payload))->toBe(['G0', 'G1']);
})->skip('ISSUE REL-003: sort_direction defaults to "desc", so orderBy(..., "asc") is ignored by the endpoint');

it('qualifies the sort column of a pivot relation', function () {
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        BelongsToMany::make('Courses', 'courses', CourseResource::class)->paginated()->withTimestamps()->orderBy('created_at', 'asc'),
    ]);
    ['ana' => $ana] = relationEndpointSeed();

    DB::enableQueryLog();
    $this->getJson($this->apiUrl('rel-student', "{$ana->id}/relation/courses"))->assertOk();
    $sql = collect(DB::getQueryLog())->pluck('query')->last();

    // created_at exists on fx_courses and on the pivot: MySQL rejects the unqualified column.
    expect($sql)->toContain('order by "fx_courses"."created_at" asc');
})->skip('ISSUE REL-004: the endpoint passes sort_field/orderBy() unqualified to orderBy() (the inline path qualifies it)');

it('rejects an invalid sort_direction instead of failing', function () {
    ['ana' => $ana] = relationEndpointSeed();

    $status = $this->getJson($this->apiUrl('student', "{$ana->id}/relation/courses?sort_field=name&sort_direction=sideways"))->status();

    expect($status)->toBeIn([200, 422]);
})->skip('ISSUE REL-004: an invalid sort_direction reaches Builder::orderBy() and returns a 500');

it('ignores a sort_field that is not a sortable field of the related resource', function () {
    ['ana' => $ana] = relationEndpointSeed();

    DB::enableQueryLog();
    $this->getJson($this->apiUrl('student', "{$ana->id}/relation/courses?sort_field=does_not_exist"))->assertOk();
    $sql = collect(DB::getQueryLog())->pluck('query')->last();

    // SQLite reads an unknown quoted identifier as a string literal; MySQL/Postgres fail with a 500.
    expect($sql)->not->toContain('does_not_exist');
})->skip('ISSUE REL-004: sort_field is passed to orderBy() without checking the related resource sortable fields');

// --- search / filters ---------------------------------------------------

it('searches the related resource searchable attributes', function () {
    ['ana' => $ana] = relationEndpointSeed();

    $payload = $this->getJson($this->apiUrl('student', "{$ana->id}/relation/courses?search=B1"))->assertOk()->json();

    expect(relationEndpointLabels($payload))->toBe(['Biology'])
        ->and($payload['meta']['total'])->toBe(1);
});

it('applies the related resource filterable fields and exposes them in meta', function () {
    ['ana' => $ana] = relationEndpointSeed();

    $payload = $this->getJson($this->apiUrl('student', "{$ana->id}/relation/courses?filters[credits]=3"))->assertOk()->json();

    expect(relationEndpointLabels($payload))->toEqualCanonicalizing(['Math', 'Biology'])
        ->and(collect($payload['meta']['filters'])->pluck('key')->all())->toBe(['credits']);
});

it('exposes the related resource index actions in meta', function () {
    ['grade' => $grade] = relationEndpointSeed();

    $payload = $this->getJson($this->apiUrl('grade', "{$grade->id}/relation/students"))->assertOk()->json();

    expect($payload['meta']['actions'])->not->toBeEmpty()
        ->and($payload['meta']['actions'][0])->toHaveKey('key');
});

// --- pivot / custom fields ---------------------------------------------

it('returns pivot data and has_pivot for a paginated pivot relation', function () {
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        BelongsToMany::make('Courses', 'courses', CourseResource::class)->paginated()->withPivot(['score']),
    ]);
    ['ana' => $ana] = relationEndpointSeed();

    $payload = $this->getJson($this->apiUrl('rel-student', "{$ana->id}/relation/courses?sort_field=name&sort_direction=asc"))
        ->assertOk()
        ->json();

    expect($payload['meta']['has_pivot'])->toBeTrue()
        ->and(collect($payload['data'])->map(fn ($item) => [$item['label'], $item['pivot']])->all())
        ->toBe([['Art', ['score' => 75]], ['Biology', ['score' => 60]], ['Math', ['score' => 90]]]);
});

it('renders the custom fields of the relation field as attributes with withFields()', function () {
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        MorphMany::make('Notes', 'notes', NoteResource::class)->paginated()->withFields()->fields([Input::make('Body', 'body')]),
    ]);
    ['ana' => $ana] = relationEndpointSeed();

    $payload = $this->getJson($this->apiUrl('rel-student', "{$ana->id}/relation/notes?sort_field=body&sort_direction=asc"))
        ->assertOk()
        ->json();

    expect(collect($payload['data'])->map(fn ($item) => $item['attributes'][0]['value'])->all())->toBe(['n1', 'n2', 'n3']);
});

it('omits the item ids when the field uses withoutId()', function () {
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        MorphMany::make('Notes', 'notes', NoteResource::class)->paginated()->withoutId(),
    ]);
    ['ana' => $ana] = relationEndpointSeed();

    $item = $this->getJson($this->apiUrl('rel-student', "{$ana->id}/relation/notes"))->assertOk()->json('data.0');

    expect($item)->not->toHaveKeys(['id', 'key'])
        ->toHaveKey('label');
});

// --- errors -------------------------------------------------------------

it('answers 404 for an unknown field', function () {
    ['ana' => $ana] = relationEndpointSeed();

    $this->getJson($this->apiUrl('student', "{$ana->id}/relation/teachers"))
        ->assertNotFound()
        ->assertExactJson(['error' => 'Field not found', 'field' => 'teachers']);
});

it('answers 422 for a single-record relation', function (string $field) {
    ['ana' => $ana] = relationEndpointSeed();

    $this->getJson($this->apiUrl('student', "{$ana->id}/relation/{$field}"))
        ->assertUnprocessable()
        ->assertExactJson(['error' => 'Field is not a multi-record relation']);
})->with(['BelongsTo' => 'grade_id', 'HasOne' => 'profile', 'BelongsTo by relation name' => 'grade']);

it('answers 404 for a parent that does not exist', function () {
    relationEndpointSeed();

    $this->getJson($this->apiUrl('student', '999/relation/courses'))->assertNotFound();
});

// --- authorization / scoping -------------------------------------------

it('denies the relation of a parent the user may not view', function () {
    ['ana' => $ana] = relationEndpointSeed();
    $this->useRealAuthorization();
    Gate::policy(Student::class, RelationEndpointDenyViewPolicy::class);

    $this->getJson($this->apiUrl('student', (string) $ana->id))->assertForbidden();
    $this->getJson($this->apiUrl('student', "{$ana->id}/relation/courses"))->assertForbidden();
})->skip('ISSUE REL-005: RelationController/RelationIndexService never authorize the parent (view) or the related resource (viewAny)');

it('does not serve a parent excluded by the resource query scope', function () {
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        BelongsToMany::make('Courses', 'courses', CourseResource::class)->paginated(),
    ]);
    RelStudentResource::scopeQueryUsing(fn ($query) => $query->where('is_active', true));
    ['ana' => $ana] = relationEndpointSeed();

    $this->getJson($this->apiUrl('rel-student', (string) $ana->id))->assertNotFound();
    $this->getJson($this->apiUrl('rel-student', "{$ana->id}/relation/courses"))->assertNotFound();
})->skip('ISSUE REL-005: the parent is loaded with $resource->model::findOrFail(), bypassing getQuery()');

it('does not serve a relation field hidden from the detail view', function () {
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        BelongsToMany::make('Courses', 'courses', CourseResource::class)->paginated()->showOnDetail(fn () => false),
    ]);
    ['ana' => $ana] = relationEndpointSeed();

    $show = $this->getJson($this->apiUrl('rel-student', (string) $ana->id))->assertOk()->json('data.attributes');

    expect(collect($show)->pluck('key'))->not->toContain('courses');
    $this->getJson($this->apiUrl('rel-student', "{$ana->id}/relation/courses"))->assertNotFound();
})->skip('ISSUE REL-005: findRelationField() searches flattenFields() and ignores field visibility');

class RelationEndpointDenyViewPolicy
{
    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, $model): bool
    {
        return false;
    }
}
