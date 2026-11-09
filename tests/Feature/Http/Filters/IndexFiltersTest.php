<?php

use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * GET /nadota-api/student/resource?filters[...] — StudentResource's field
 * filters (age, birth_date, is_active, status, grade_id) and resource filters
 * (Status Filter, Age Range, Enrolled Between), alone and combined with
 * search, sorting and pagination.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi();

    $this->g1 = Grade::create(['name' => 'G1']);
    $this->g2 = Grade::create(['name' => 'G2']);

    Student::create(['name' => 'Ana', 'email' => 'ana@school.test', 'age' => 8, 'birth_date' => '2016-01-10', 'is_active' => true,
        'status' => 'draft', 'grade_id' => $this->g1->id, 'enrolled_at' => '2024-01-01 08:00:00']);
    Student::create(['name' => 'Bob', 'email' => 'bob@school.test', 'age' => 10, 'birth_date' => '2014-05-05', 'is_active' => false,
        'status' => 'enrolled', 'grade_id' => $this->g1->id, 'enrolled_at' => '2024-01-31 10:00:00']);
    Student::create(['name' => 'Cid', 'email' => 'cid@mail.test', 'age' => 12, 'birth_date' => '2012-07-07', 'is_active' => true,
        'status' => 'enrolled', 'grade_id' => $this->g2->id, 'enrolled_at' => '2024-02-01 00:00:00']);
    Student::create(['name' => 'Dee', 'email' => 'dee@mail.test', 'age' => 15, 'birth_date' => '2009-03-03', 'is_active' => true,
        'status' => 'graduated']);
    Student::create(['name' => 'Eve', 'email' => 'eve@school.test', 'age' => 10, 'is_active' => true,
        'status' => 'enrolled', 'grade_id' => $this->g2->id])->delete();

    $this->index = fn (array|string $query = []): TestResponse => $this->getJson(
        $this->apiUrl('student') . '?' . (is_string($query) ? $query : http_build_query($query))
    );
});

/** Names of the rows in an index response, in order. */
function indexFiltersNames(TestResponse $response): array
{
    return collect($response->assertOk()->json('data'))
        ->map(fn ($row) => collect($row['attributes'])->firstWhere('key', 'name')['value'])
        ->all();
}

/** A pagination meta value; tolerates ISSUE HTTP-001 (meta values duplicated into arrays). */
function indexFiltersMeta(TestResponse $response, string $key): mixed
{
    return Arr::first(Arr::wrap($response->json("meta.{$key}")));
}

describe('field filters', function () {
    it('filters by an exact number', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['age' => 10]])))->toBe(['Bob']);
    });

    it('filters by a single date', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['birth_date' => '2014-05-05']])))->toBe(['Bob']);
    });

    it('filters a Toggle by true and false in every query-string spelling', function () {
        foreach (['true', '1'] as $value) {
            expect(indexFiltersNames(($this->index)(['filters' => ['is_active' => $value]])))->toBe(['Ana', 'Cid', 'Dee']);
        }
        foreach (['false', '0'] as $value) {
            expect(indexFiltersNames(($this->index)(['filters' => ['is_active' => $value]])))->toBe(['Bob']);
        }
    });

    it('filters a Toggle with the boolean option values /filters advertises', function () {
        // http_build_query(true) === "1", http_build_query(false) === "0".
        expect(indexFiltersNames(($this->index)(['filters' => ['is_active' => true]])))->toBe(['Ana', 'Cid', 'Dee'])
            ->and(indexFiltersNames(($this->index)(['filters' => ['is_active' => false]])))->toBe(['Bob']);
    });

    it('ignores a Toggle value it does not understand', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['is_active' => 'yes']])))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
    });

    it('filters a Select by one value or by several', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['status' => 'enrolled']])))->toBe(['Bob', 'Cid'])
            ->and(indexFiltersNames(($this->index)(['filters' => ['status' => ['draft', 'graduated']]])))->toBe(['Ana', 'Dee']);
    });

    it('filters a BelongsTo by the related id', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['grade_id' => $this->g1->id]])))->toBe(['Ana', 'Bob'])
            ->and(indexFiltersNames(($this->index)(['filters' => ['grade_id' => 999]])))->toBe([]);
    });

    it('never brings back soft-deleted rows', function () {
        // Eve is 10, enrolled and in G2, but trashed.
        expect(indexFiltersNames(($this->index)(['filters' => ['age' => 10]])))->toBe(['Bob'])
            ->and(indexFiltersNames(($this->index)(['filters' => ['grade_id' => $this->g2->id]])))->toBe(['Cid']);
    });

    it('matches any of several exact numbers, or none, but not just the first', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['age' => [8, 12]]])))->not->toBe(['Ana']);
    })->skip('ISSUE FLT-020: the array reaches where("age", [...]) and only its first element is bound');
});

describe('resource filters', function () {
    it('applies the datetime range from enrolled_at_from / enrolled_at_to', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['enrolled_at_from' => '2024-01-15']])))->toBe(['Bob', 'Cid'])
            ->and(indexFiltersNames(($this->index)(['filters' => ['enrolled_at_to' => '2024-01-15']])))->toBe(['Ana'])
            ->and(indexFiltersNames(($this->index)(['filters' => ['enrolled_at_from' => '2024-01-01', 'enrolled_at_to' => '2024-02-02']])))->toBe(['Ana', 'Bob', 'Cid']);
    });

    it('includes the whole "to" day of the datetime range', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['enrolled_at_from' => '2024-01-01', 'enrolled_at_to' => '2024-01-31']])))->toBe(['Ana', 'Bob']);
    })->skip('ISSUE FLT-003: "2024-01-31 10:00:00" is compared with "2024-01-31", so Bob (enrolled that day) is left out');

    it('applies the Age Range resource filter', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['age_from' => 10, 'age_to' => 12]])))->toBe(['Bob', 'Cid'])
            ->and(indexFiltersNames(($this->index)(['filters' => ['age_from' => 12]])))->toBe(['Cid', 'Dee']);
    })->skip('ISSUE FLT-001: the Age field filter shares the "age" key and receives the {start,end} array as an exact value (age = 10)');

    it('accepts the nested filters[enrolled_at][from|to] format the API docs show', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['enrolled_at' => ['from' => '2024-01-15', 'to' => '2024-02-02']]])))->toBe(['Bob', 'Cid']);
    })->skip('ISSUE FLT-002: only filters[enrolled_at_from|_to] are read; the documented nested form is silently ignored');
});

describe('combinations', function () {
    it('AND-combines several filters', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['status' => 'enrolled', 'grade_id' => $this->g1->id]])))->toBe(['Bob'])
            ->and(indexFiltersNames(($this->index)(['filters' => ['is_active' => 'true', 'age' => 12]])))->toBe(['Cid'])
            ->and(indexFiltersNames(($this->index)(['filters' => ['is_active' => 'true', 'enrolled_at_from' => '2024-01-15']])))->toBe(['Cid']);
    });

    it('applies filters on top of the search term', function () {
        expect(indexFiltersNames(($this->index)(['globalSearch' => 'school.test', 'filters' => ['is_active' => 'true']])))->toBe(['Ana'])
            // grade.name is a searchable relation
            ->and(indexFiltersNames(($this->index)(['globalSearch' => 'G1', 'filters' => ['status' => 'enrolled']])))->toBe(['Bob']);
    });

    it('sorts the filtered rows', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['status' => 'enrolled'], 'sortField' => 'age', 'sortDirection' => 'desc'])))->toBe(['Cid', 'Bob']);
    });

    it('paginates the filtered rows and reports the filtered total', function () {
        $first = ($this->index)(['filters' => ['is_active' => 'true'], 'perPage' => 2]);
        $second = ($this->index)(['filters' => ['is_active' => 'true'], 'perPage' => 2, 'page' => 2]);

        expect(indexFiltersNames($first))->toBe(['Ana', 'Cid'])
            ->and(indexFiltersMeta($first, 'total'))->toBe(3)
            ->and(indexFiltersMeta($first, 'last_page'))->toBe(2)
            ->and(indexFiltersNames($second))->toBe(['Dee'])
            ->and(indexFiltersMeta($second, 'total'))->toBe(3);
    });

    it('keeps search, filter, sort and pagination consistent together', function () {
        $query = ['globalSearch' => 'mail.test', 'filters' => ['is_active' => 'true'], 'sortField' => 'age', 'sortDirection' => 'desc', 'perPage' => 1];

        $first = ($this->index)($query);
        $second = ($this->index)([...$query, 'page' => 2]);

        expect(indexFiltersNames($first))->toBe(['Dee'])
            ->and(indexFiltersMeta($first, 'total'))->toBe(2)
            ->and(indexFiltersMeta($first, 'last_page'))->toBe(2)
            ->and(indexFiltersNames($second))->toBe(['Cid']);
    });

    it('reports a total of 0 when the filters match nothing', function () {
        $response = ($this->index)(['filters' => ['status' => 'enrolled', 'age' => 15]]);

        expect(indexFiltersNames($response))->toBe([])
            ->and(indexFiltersMeta($response, 'total'))->toBe(0);
    });
});

describe('ignored input', function () {
    it('ignores keys no filter declares', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['nope' => 'x', 'grade' => 1]])))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
    });

    it('ignores values for fields that are not filterable', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['name' => 'Ana', 'email' => 'bob@school.test']])))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
    });

    it('ignores empty values (the empty string reaches the pipe as null)', function () {
        expect(indexFiltersNames(($this->index)('filters[status]=&filters[age]=&filters[is_active]=&filters[enrolled_at_from]=')))
            ->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
    });

    it('ignores a plain value sent to a range-only key', function () {
        expect(indexFiltersNames(($this->index)(['filters' => ['enrolled_at' => '2024-01-31']])))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
    });

    it('answers 200 and ignores a malformed filters parameter', function (string $query) {
        expect(indexFiltersNames(($this->index)($query)))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
    })->with([
        'string' => 'filters=abc',
        'empty' => 'filters=',
    ])->skip('ISSUE FLT-008: a non-array "filters" (string, or null once ConvertEmptyStringsToNull ran) hits normalizeRangeFilters(array) and answers 500');
});
