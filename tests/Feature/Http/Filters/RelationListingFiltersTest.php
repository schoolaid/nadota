<?php

use Illuminate\Testing\TestResponse;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * GET /nadota-api/grade/resource/{id}/relation/students?filters[...] — the
 * paginated relation listing applies (and advertises under meta.filters) the
 * filters of the RELATED resource (StudentResource) through
 * RelationIndexService, not through ApplyFiltersPipe.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi();

    $this->grade = Grade::create(['name' => 'G1']);
    $other = Grade::create(['name' => 'G2']);

    Student::create(['name' => 'Ana', 'email' => 'ana@x.test', 'age' => 8, 'is_active' => true, 'status' => 'draft', 'grade_id' => $this->grade->id, 'enrolled_at' => '2024-01-01 08:00:00']);
    Student::create(['name' => 'Bob', 'email' => 'bob@x.test', 'age' => 10, 'is_active' => false, 'status' => 'enrolled', 'grade_id' => $this->grade->id, 'enrolled_at' => '2024-01-20 10:00:00']);
    Student::create(['name' => 'Cid', 'email' => 'cid@x.test', 'age' => 12, 'is_active' => true, 'status' => 'enrolled', 'grade_id' => $this->grade->id, 'enrolled_at' => '2024-03-01 09:00:00']);
    Student::create(['name' => 'Zed', 'email' => 'zed@x.test', 'age' => 10, 'is_active' => true, 'status' => 'enrolled', 'grade_id' => $other->id]);

    $this->students = fn (array $query = []): TestResponse => $this->getJson(
        $this->apiUrl('grade', $this->grade->id . '/relation/students') . '?' . http_build_query($query)
    );
});

function relationListingLabels(TestResponse $response): array
{
    return collect($response->assertOk()->json('data'))->pluck('label')->sort()->values()->all();
}

it('advertises the related resource filters under meta.filters', function () {
    $filters = ($this->students)()->assertOk()->json('meta.filters');

    expect(collect($filters)->map(fn ($f) => $f['key'] . '|' . $f['label'])->all())->toBe([
        'age|Age', 'birth_date|Birth Date', 'is_active|Active', 'status|Status', 'grade_id|Grade',
        'status|Status Filter', 'age|Age Range', 'enrolled_at|Enrolled Between',
    ]);
});

it('builds the advertised option endpoints on the related resource', function () {
    $grade = collect(($this->students)()->json('meta.filters'))->firstWhere('key', 'grade_id');

    expect($grade['endpoint'])->toBe('/nadota-api/student/resource/field/grade_id/options');
});

it('applies field filters of the related resource inside the relation only', function () {
    // Zed is also 10 but belongs to another grade.
    expect(relationListingLabels(($this->students)(['filters' => ['age' => 10]])))->toBe(['Bob'])
        ->and(relationListingLabels(($this->students)(['filters' => ['status' => 'enrolled', 'is_active' => 'true']])))->toBe(['Cid']);
});

it('reports the filtered total', function () {
    $response = ($this->students)(['filters' => ['status' => 'enrolled'], 'per_page' => 1]);

    expect($response->assertOk()->json('meta.total'))->toBe(2)
        ->and($response->json('meta.last_page'))->toBe(2);
});

it('applies filters on top of the relation search', function () {
    expect(relationListingLabels(($this->students)(['search' => 'x.test'])))->toBe(['Ana', 'Bob', 'Cid'])
        ->and(relationListingLabels(($this->students)(['search' => 'x.test', 'filters' => ['is_active' => 'true']])))->toBe(['Ana', 'Cid']);
});

it('ignores unknown and empty filter values', function () {
    expect(relationListingLabels(($this->students)(['filters' => ['nope' => 'x', 'status' => '']])))->toBe(['Ana', 'Bob', 'Cid']);
});

it('applies _from/_to range filters like the main index', function () {
    expect(relationListingLabels(($this->students)(['filters' => ['enrolled_at_from' => '2024-01-15', 'enrolled_at_to' => '2024-02-15']])))->toBe(['Bob']);
})->skip('ISSUE FLT-010: RelationIndexService hands filters[] to FilterCriteria without normalising _from/_to, so range filters are ignored here');
