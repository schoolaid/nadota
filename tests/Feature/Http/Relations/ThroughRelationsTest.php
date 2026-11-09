<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\HasManyThrough;
use SchoolAid\Nadota\Http\Fields\Relations\HasOneThrough;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Models\StudentProfile;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Models\CustomKeysGrade;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Models\CustomKeysSchool;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelCustomKeysGradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelCustomKeysSchoolResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentProfileResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

/**
 * Grade show (BelongsTo school, HasMany students, HasOneThrough profile, MorphMany notes)
 * and School show (Count grades, HasMany grades, HasManyThrough students).
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi([...$this->fixtureResources, RelCustomKeysSchoolResource::class, RelCustomKeysGradeResource::class]);
});

afterEach(function () {
    RelCustomKeysSchoolResource::reset();
    RelCustomKeysGradeResource::reset();
});

/**
 * North school with grades G1 (Ana + profile "Annie", Beto) and G2 (Caro); South with G3 (Dani).
 *
 * @return array{school: School, g1: Grade, g2: Grade}
 */
function throughRelationsSeed(): array
{
    $school = School::create(['name' => 'North', 'city' => 'Lima']);
    $g1 = Grade::create(['name' => 'G1', 'school_id' => $school->id]);
    $g2 = Grade::create(['name' => 'G2', 'school_id' => $school->id]);
    $ana = Student::create(['name' => 'Ana', 'grade_id' => $g1->id]);
    StudentProfile::create(['student_id' => $ana->id, 'nickname' => 'Annie']);
    Student::create(['name' => 'Beto', 'grade_id' => $g1->id]);
    Student::create(['name' => 'Caro', 'grade_id' => $g2->id]);

    $south = School::create(['name' => 'South']);
    Student::create(['name' => 'Dani', 'grade_id' => Grade::create(['name' => 'G3', 'school_id' => $south->id])->id]);

    $g1->notes()->create(['body' => 'grade note']);

    return ['school' => $school, 'g1' => $g1, 'g2' => $g2];
}

function throughRelationsAttributes(array $payload)
{
    return collect($payload['data']['attributes']);
}

// --- grade --------------------------------------------------------------

it('renders the grade school, students, first profile and notes', function () {
    ['school' => $school, 'g1' => $g1] = throughRelationsSeed();

    $attributes = throughRelationsAttributes($this->getJson($this->apiUrl('grade', (string) $g1->id))->assertOk()->json());

    expect($attributes->firstWhere('key', 'school_id')['value'])->toMatchArray(['id' => $school->id, 'label' => 'North', 'resource' => 'school'])
        ->and(collect($attributes->firstWhere('key', 'students')['value']['data'])->pluck('label')->all())->toBe(['Ana', 'Beto'])
        ->and($attributes->firstWhere('component', 'FieldHasOneThrough')['value'])
        ->toMatchArray(['id' => StudentProfile::first()->id, 'resource' => 'student-profile'])
        ->and($attributes->firstWhere('component', 'FieldHasOneThrough')['props'])
        ->toBe(['relationType' => 'hasOneThrough', 'resource' => 'student-profile'])
        ->and($attributes->firstWhere('key', 'notes')['value']['meta'])->toMatchArray(['total' => 1, 'isPolymorphic' => true])
        ->and($attributes->firstWhere('key', 'notes')['props']['createContext']['prefill'])
        ->toBe(['notable_type' => Grade::class, 'notable_id' => $g1->id]);
});

it('renders a null HasOneThrough when the grade has no student profile', function () {
    ['g2' => $g2] = throughRelationsSeed();

    $attributes = throughRelationsAttributes($this->getJson($this->apiUrl('grade', (string) $g2->id))->json());

    expect($attributes->firstWhere('component', 'FieldHasOneThrough')['value'])->toBeNull()
        ->and($attributes->firstWhere('key', 'notes')['value']['data'])->toBe([]);
});

it('keys the HasOneThrough field by its relation name', function () {
    ['g1' => $g1] = throughRelationsSeed();

    $attributes = throughRelationsAttributes($this->getJson($this->apiUrl('grade', (string) $g1->id))->json());

    expect($attributes->firstWhere('component', 'FieldHasOneThrough')['key'])->toBe('firstStudentProfile');
})->skip('ISSUE REL-001: HasOneThrough is serialized with key ""');

// --- school -------------------------------------------------------------

it('renders the school grades count, grades and students through grades', function () {
    ['school' => $school] = throughRelationsSeed();

    $attributes = throughRelationsAttributes($this->getJson($this->apiUrl('school', (string) $school->id))->assertOk()->json());
    $students = $attributes->firstWhere('component', 'FieldHasManyThrough');

    expect($attributes->firstWhere('key', 'grades_count')['value'])->toBe(2)
        ->and(collect($attributes->firstWhere('key', 'grades')['value']['data'])->pluck('label')->all())->toBe(['G1', 'G2'])
        ->and($students['key'])->toBe('students')
        ->and($students['type'])->toBe('hasMany')
        ->and(collect($students['value']['data'])->pluck('label')->sort()->values()->all())->toBe(['Ana', 'Beto', 'Caro'])
        ->and($students['value']['meta'])->toMatchArray(['total' => 3, 'resource' => 'student'])
        ->and($students['props'])->toMatchArray(['relationType' => 'hasManyThrough', 'paginated' => false]);
});

it('keeps the school show query count independent of the number of students', function () {
    ['school' => $school, 'g2' => $g2] = throughRelationsSeed();

    DB::enableQueryLog();
    $this->getJson($this->apiUrl('school', (string) $school->id))->assertOk();
    $before = count(DB::getQueryLog());

    foreach (range(1, 5) as $i) {
        Student::create(['name' => "Extra {$i}", 'grade_id' => $g2->id]);
    }
    DB::flushQueryLog();
    $this->getJson($this->apiUrl('school', (string) $school->id))->assertOk();

    expect(count(DB::getQueryLog()))->toBe($before);
});

it('lists the schools with the grades count and no to-many payload on the index', function () {
    throughRelationsSeed();

    $rows = $this->getJson($this->apiUrl('school'))->assertOk()->json('data');
    $north = collect($rows)->first(fn ($row) => collect($row['attributes'])->firstWhere('key', 'name')['value'] === 'North');

    expect(collect($north['attributes'])->pluck('key')->all())->toBe(['name', 'city', 'grades_count'])
        ->and(collect($north['attributes'])->firstWhere('key', 'grades_count')['value'])->toBe(2);
});

// --- custom through keys ------------------------------------------------

it('shows a HasManyThrough whose second local key is not the primary key', function () {
    RelCustomKeysSchoolResource::using(fn () => [
        Input::make('Name', 'name'),
        HasManyThrough::make('By level', 'studentsByLevel', StudentResource::class),
    ]);
    $school = CustomKeysSchool::create(['name' => 'North']);
    Grade::create(['name' => 'G7', 'school_id' => $school->id, 'level' => 7]);
    Student::create(['name' => 'Seven', 'age' => 7]);

    $value = throughRelationsAttributes($this->getJson($this->apiUrl('rel-custom-keys-school', (string) $school->id))->assertOk()->json())
        ->firstWhere('key', 'studentsByLevel')['value'];

    expect(collect($value['data'])->pluck('label')->all())->toBe(['Seven']);
})->skip('ISSUE REL-006: the eager load selects fx_students.level (the intermediate second local key) and fails with a 500');

it('shows a HasOneThrough whose second local key is not the primary key', function () {
    RelCustomKeysGradeResource::using(fn () => [
        Input::make('Name', 'name'),
        HasOneThrough::make('By age', 'profileByAge', StudentProfileResource::class)->displayAttribute('nickname'),
    ]);
    $grade = CustomKeysGrade::create(['name' => 'G']);
    Student::create(['name' => 'Seven', 'age' => 7, 'grade_id' => $grade->id]);
    StudentProfile::create(['student_id' => 7, 'nickname' => 'seven']);

    $payload = $this->getJson($this->apiUrl('rel-custom-keys-grade', (string) $grade->id))->assertOk()->json();

    expect(throughRelationsAttributes($payload)->firstWhere('component', 'FieldHasOneThrough')['value']['label'])->toBe('seven');
})->skip('ISSUE REL-006: the eager load selects fx_student_profiles.age (the intermediate second local key) and fails with a 500');
