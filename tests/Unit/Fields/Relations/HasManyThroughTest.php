<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\HasManyThrough;
use SchoolAid\Nadota\Http\Filters\RelationFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Models\CustomKeysSchool;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelCustomKeysSchoolResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\SchoolResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

beforeEach(function () {
    FixtureSchema::create();
});

afterEach(function () {
    RelCustomKeysSchoolResource::reset();
});

/** School "North" with two grades holding the given students (alternating). */
function hasManyThroughSchool(array $names): School
{
    $school = School::create(['name' => 'North']);
    $grades = [
        Grade::create(['name' => 'G1', 'school_id' => $school->id]),
        Grade::create(['name' => 'G2', 'school_id' => $school->id]),
    ];

    foreach ($names as $i => $name) {
        Student::create(['name' => $name, 'grade_id' => $grades[$i % 2]->id]);
    }

    return $school;
}

function hasManyThroughRequest(Resource $parent): NadotaRequest
{
    $request = createNadotaRequest();
    $request->setResource($parent);

    return $request;
}

// --- construction -------------------------------------------------------

it('uses the relation name as key and leaves the attribute empty', function () {
    $field = HasManyThrough::make('Students', 'students', StudentResource::class);

    expect($field)->toBeField()
        ->and($field->key())->toBe('students')
        ->and($field->getRelation())->toBe('students')
        ->and($field->getAttribute())->toBe('')
        ->and($field->getComponent())->toBe('FieldHasManyThrough')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->relationType())->toBe('hasManyThrough')
        ->and($field->getResource())->toBe(StudentResource::class)
        ->and($field->getColumnsForSelect(School::class))->toBe([]);
});

it('reports the hasMany field type (see CORE-008)', function () {
    expect(HasManyThrough::make('Students', 'students')->getType())->toBe('hasMany');
});

it('is only shown on detail and only applied to the show query by default', function () {
    $field = HasManyThrough::make('Students', 'students');
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeFalse()
        ->and($field->isShowOnUpdate($request, null))->toBeFalse()
        ->and($field->isAppliedInIndexQuery())->toBeFalse()
        ->and($field->isAppliedInShowQuery())->toBeTrue()
        ->and($field->hasPivotColumns())->toBeFalse()
        ->and($field->getPivotColumns())->toBe([]);
});

it('stores limit, ordering, pagination and custom fields', function () {
    $field = HasManyThrough::make('Students', 'students')
        ->limit(5)
        ->orderBy('name', 'asc')
        ->paginated()
        ->fields([Input::make('Name', 'name')])
        ->withFields();

    expect($field->getLimit())->toBe(5)
        ->and($field->getOrderBy())->toBe('name')
        ->and($field->getOrderDirection())->toBe('asc')
        ->and($field->isPaginated())->toBeTrue()
        ->and($field->getCustomFields())->toHaveCount(1)
        ->and($field->shouldIncludeFields())->toBeTrue()
        ->and(HasManyThrough::make('S', 's')->getOrderDirection())->toBe('desc');
});

// --- resolve ------------------------------------------------------------

it('resolves the far related models through the intermediate model', function () {
    $school = hasManyThroughSchool(['Ana', 'Beto', 'Caro']);
    hasManyThroughSchool(['Other school student']);

    $value = HasManyThrough::make('Students', 'students', StudentResource::class)
        ->resolve(createNadotaRequest(), $school, new SchoolResource());

    expect(collect($value['data'])->pluck('label')->sort()->values()->all())->toBe(['Ana', 'Beto', 'Caro'])
        ->and($value['data'][0])->toHaveKeys(['id', 'key', 'label', 'resource', 'deletedAt', 'permissions'])
        ->and($value['data'][0]['resource'])->toBe('student')
        ->and($value['meta'])->toMatchArray(['total' => 3, 'hasMore' => false, 'resource' => 'student']);
});

it('resolves basic items with id, label and deletedAt without a resource', function () {
    $school = hasManyThroughSchool(['Ana']);
    $student = Student::first();

    $value = HasManyThrough::make('Students', 'students')->resolve(createNadotaRequest(), $school, null);

    expect($value)->toBe([
        'data' => [['id' => $student->id, 'label' => 'Ana', 'deletedAt' => null]],
        'meta' => ['total' => 1, 'hasMore' => false],
    ]);
});

it('uses the display attribute and the displayUsing callback for labels', function () {
    $school = hasManyThroughSchool(['Ana']);
    Student::query()->update(['email' => 'ana@example.test']);
    $request = createNadotaRequest();

    $byAttribute = HasManyThrough::make('Students', 'students', StudentResource::class)->displayAttribute('email')
        ->resolve($request, $school, null);
    $byCallback = HasManyThrough::make('Students', 'students', StudentResource::class)
        ->displayUsing(fn ($student) => "#{$student->id} {$student->name}")
        ->resolve($request, $school, null);

    expect($byAttribute['data'][0]['label'])->toBe('ana@example.test')
        ->and($byCallback['data'][0]['label'])->toBe('#1 Ana');
});

it('includes rendered fields as attributes only with withFields()', function () {
    $school = hasManyThroughSchool(['Ana']);

    $value = HasManyThrough::make('Students', 'students', StudentResource::class)
        ->fields([Input::make('Name', 'name')])
        ->withFields()
        ->resolve(createNadotaRequest(), $school, null);

    expect($value['data'][0]['attributes'])->toHaveCount(1)
        ->and($value['data'][0]['attributes'][0]['value'])->toBe('Ana');
});

it('applies limit and orderBy to the inline items', function () {
    $school = hasManyThroughSchool(['d', 'b', 'a', 'c']);

    $value = HasManyThrough::make('Students', 'students', StudentResource::class)->orderBy('name', 'asc')->limit(2)
        ->resolve(createNadotaRequest(), $school, null);

    expect(collect($value['data'])->pluck('label')->all())->toBe(['a', 'b'])
        ->and($value['meta']['hasMore'])->toBeTrue();
});

it('returns an empty paginated stub when paginated', function () {
    $school = hasManyThroughSchool(['Ana']);

    $value = HasManyThrough::make('Students', 'students', StudentResource::class)->paginated()
        ->resolve(createNadotaRequest(), $school, null);

    expect($value)->toBe(['data' => [], 'meta' => ['total' => 0, 'hasMore' => false, 'paginated' => true]]);
});

it('returns an empty collection without intermediate rows and [] without the relation method', function () {
    $school = School::create(['name' => 'Empty']);
    $request = createNadotaRequest();

    expect(HasManyThrough::make('Students', 'students')->resolve($request, $school, null)['data'])->toBe([])
        ->and(HasManyThrough::make('Teachers', 'teachers')->resolve($request, $school, null))->toBe([]);
});

// --- props --------------------------------------------------------------

it('serializes the inline configuration in props without any url', function () {
    $school = School::create(['name' => 'North']);

    $props = HasManyThrough::make('Students', 'students', StudentResource::class)->limit(10)->orderBy('name', 'asc')
        ->toArray(createNadotaRequest(), $school, new SchoolResource())['props'];

    expect($props)->toBe([
        'limit' => 10,
        'paginated' => false,
        'orderBy' => 'name',
        'orderDirection' => 'asc',
        'relationType' => 'hasManyThrough',
        'resource' => 'student',
    ]);
});

it('adds only the pagination url when paginated, and never a createContext', function () {
    $school = School::create(['name' => 'North']);

    $props = HasManyThrough::make('Students', 'students', StudentResource::class)->paginated()
        ->toArray(createNadotaRequest(), $school, new SchoolResource())['props'];

    expect($props['paginationUrl'])->toBe("/nadota-api/school/resource/{$school->id}/relation/students")
        ->and($props)->not->toHaveKeys(['urls', 'createContext', 'canCreate']);
});

// --- eager loading ------------------------------------------------------

it('eager loads with the related resource columns, qualified, plus the through key', function () {
    $school = hasManyThroughSchool(['Ana', 'Beto']);
    $resource = new SchoolResource();
    $request = hasManyThroughRequest($resource);
    $relations = $resource->getEagerLoadRelations($request);

    DB::enableQueryLog();
    $loaded = School::with(['students' => $relations['students']])->find($school->id);
    $sql = collect(DB::getQueryLog())->pluck('query')->last();

    expect($sql)->toContain('"fx_students"."name"', 'as "laravel_through_key"')
        ->not->toContain('"fx_students".*')
        ->and($loaded->students)->toHaveCount(2);
});

it('does not add intermediate-table columns to the far table select', function () {
    RelCustomKeysSchoolResource::using(fn () => [
        HasManyThrough::make('By level', 'studentsByLevel', StudentResource::class),
    ]);
    $school = CustomKeysSchool::create(['name' => 'North']);
    Grade::create(['name' => 'G7', 'school_id' => $school->id, 'level' => 7]);
    Student::create(['name' => 'Seven', 'age' => 7]);

    $field = HasManyThrough::make('By level', 'studentsByLevel', StudentResource::class);
    $columns = $field->getRelatedColumns(hasManyThroughRequest(new RelCustomKeysSchoolResource()));

    // "level" is the second local key on fx_grades; selecting fx_students.level fails.
    expect($columns)->not->toContain('level');

    $loaded = CustomKeysSchool::with((new RelCustomKeysSchoolResource())->getEagerLoadRelations(
        hasManyThroughRequest(new RelCustomKeysSchoolResource())
    ))->find($school->id);

    expect($loaded->studentsByLevel->pluck('name')->all())->toBe(['Seven']);
})->skip('ISSUE REL-006: getRelatedColumns() adds getSecondLocalKeyName() (an intermediate-table column) to the far table select');

// --- fill / sorting / filters / export ---------------------------------

it('is read-only: fill never touches the parent model', function () {
    $school = new School(['name' => 'North']);

    HasManyThrough::make('Students', 'students')->fill(createNadotaRequest(['students' => [1]]), $school);

    expect($school->getDirty())->toBe(['name' => 'North'])
        ->and(HasManyThrough::make('Students', 'students')->supportsAfterSave())->toBeFalse();
});

it('does not alter the parent query when sorting', function () {
    $school = new School();
    $query = $school->newQuery();

    expect(HasManyThrough::make('Students', 'students')->applySorting($query, 'asc', $school))->toBe($query)
        ->and($query->getQuery()->orders)->toBeNull();
});

it('exposes a whereHas relation filter keyed by the relation when filterable', function () {
    $filters = HasManyThrough::make('Students', 'students', StudentResource::class)->filterable()->filters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(RelationFilter::class)
        ->and($filters[0]->key())->toBe('students');
});

it('exports the far related labels joined with the separator', function () {
    $school = hasManyThroughSchool(['Ana', 'Beto']);

    $value = HasManyThrough::make('Students', 'students', StudentResource::class)->exportable()
        ->resolveForExport(createNadotaRequest(), $school, new SchoolResource());

    expect($value)->toBeString()
        ->and(explode(', ', $value))->toEqualCanonicalizing(['Ana', 'Beto']);
})->skip('ISSUE REL-008: HasManyThrough has no resolveForExport(); export falls back to resolve() and json-encodes the collection');
