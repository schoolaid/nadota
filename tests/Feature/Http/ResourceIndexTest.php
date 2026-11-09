<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Models\StudentProfile;
use SchoolAid\Nadota\Tests\Fixtures\Resources\PagedStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\IndexScopedStudentResource;

/**
 * GET /{resourceKey}/resource — ResourceIndexService and its pipes
 * (BuildQuery → ApplySearch → ApplyFilters → ApplySorting → PaginateAndTransform).
 * Filters are covered by tests/Feature/Http/Filters.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi();
    ResourceManager::registerResourceClass(PagedStudentResource::class);
    ResourceManager::registerResourceClass(IndexScopedStudentResource::class);
});

/** Names of the rows in an index response, in order. */
function resourceIndexTestNames(TestResponse $response): array
{
    return collect($response->json('data'))
        ->map(fn ($row) => collect($row['attributes'])->firstWhere('key', 'name')['value'])
        ->all();
}

/**
 * A pagination meta value. Reads the first element while ISSUE HTTP-001
 * (every meta value duplicated into a two-element array) is open, and the
 * plain value once it is fixed.
 */
function resourceIndexTestMeta(TestResponse $response, string $key): mixed
{
    return Arr::first(Arr::wrap($response->json("meta.{$key}")));
}

describe('response shape', function () {
    it('wraps rows in data with pagination meta and links', function () {
        Student::create(['name' => 'Ana']);

        $this->getJson($this->apiUrl('student'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'attributes', 'deletedAt', 'permissions']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    });

    it('returns pagination meta and links as plain values, not as arrays', function () {
        Student::create(['name' => 'Ana']);
        Student::create(['name' => 'Bob']);

        $this->getJson($this->apiUrl('student') . '?perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 2)
            ->assertJsonPath('links.prev', null)
            ->assertJsonPath('links.next', null);
    })->skip('ISSUE HTTP-001: IndexResource meta/links are merged recursively with Laravel\'s own pagination block, so every value becomes a 2-element array');

    it('omits links on mobile unless include_links is requested', function () {
        Student::create(['name' => 'Ana']);

        $this->getJson($this->apiUrl('student') . '?mobile=1')->assertOk()->assertJsonMissingPath('links');
        $this->getJson($this->apiUrl('student') . '?mobile=1&include_links=1')->assertOk()->assertJsonPath('links.prev', null);
    })->skip('ISSUE HTTP-001: Laravel\'s PaginatedResourceResponse re-adds links (and meta.links/meta.path) on top of IndexResource');

    it('adds has_more and has_previous to meta for mobile clients', function () {
        foreach (['Ana', 'Bob', 'Cid'] as $name) {
            Student::create(['name' => $name]);
        }

        $response = $this->getJson($this->apiUrl('student') . '?mobile=1&perPage=10&page=1')->assertOk();

        expect($response->json('meta'))->toHaveKeys(['has_more', 'has_previous'])
            ->and($response->json('meta.has_more'))->toBeFalse()
            ->and($response->json('meta.has_previous'))->toBeFalse();
    });

    it('detects mobile clients from the User-Agent', function () {
        Student::create(['name' => 'Ana']);

        $response = $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)')
            ->getJson($this->apiUrl('student'))
            ->assertOk();

        expect($response->json('meta'))->toHaveKey('has_more');
    });

    it('leaves the id out of every row when the resource disables it', function () {
        Student::create(['name' => 'Ana']);

        $row = $this->getJson($this->apiUrl('paged-student'))->assertOk()->json('data.0');

        expect($row)->not->toHaveKey('id')
            ->and(array_keys($row))->toBe(['attributes', 'deletedAt', 'permissions']);
    });

    it('returns an empty data array when there are no records', function () {
        $response = $this->getJson($this->apiUrl('student'))->assertOk()->assertJsonPath('data', []);

        expect(resourceIndexTestMeta($response, 'total'))->toBe(0);
    });
});

describe('composite row on the kitchen-sink resource', function () {
    it('renders every index field of a fully populated student with its value', function () {
        $grade = Grade::create(['name' => 'First']);
        $student = Student::create([
            'name' => 'Ana', 'email' => 'ana@example.com', 'grade_id' => $grade->id, 'age' => 10,
            'balance' => 12.5, 'birth_date' => '2015-03-04', 'enrolled_at' => '2024-01-02 03:04:05',
            'is_active' => true, 'status' => 'enrolled', 'uuid' => 'u-1', 'password' => 'secret-hash',
            'bio' => 'Long bio', 'website' => 'https://ana.example',
        ]);
        $student->courses()->attach(Course::create(['name' => 'Math'])->id, ['score' => 9]);
        Note::create(['notable_type' => Student::class, 'notable_id' => $student->id, 'body' => 'hi']);

        $row = $this->getJson($this->apiUrl('student'))->assertOk()->json('data.0');
        $values = collect($row['attributes'])->mapWithKeys(fn ($field) => [$field['key'] => $field['value']]);

        expect($row['id'])->toBe($student->id)
            ->and($row['deletedAt'])->toBeNull()
            ->and($values->keys()->all())->toBe([
                'name', 'email', 'age', 'balance', 'birth_date', 'enrolled_at',
                'is_active', 'status', 'grade_id', 'courses_count', 'notes_exists',
            ])
            ->and($values['name'])->toBe('Ana')
            ->and($values['email'])->toBe('ana@example.com')
            ->and($values['age'])->toBe(10)
            ->and($values['balance'])->toBe('12.50')
            ->and($values['birth_date'])->toStartWith('2015-03-04')
            ->and($values['enrolled_at'])->toStartWith('2024-01-02')
            ->and($values['is_active'])->toBeTrue()
            ->and($values['status'])->toBe('enrolled')
            ->and($values['grade_id'])->toMatchArray(['id' => $grade->id, 'label' => 'First', 'resource' => 'grade'])
            ->and($values['courses_count'])->toBe(1)
            ->and($values['notes_exists'])->toBeTrue();
    });

    it('never sends hidden-from-index fields or the password', function () {
        Student::create(['name' => 'Ana', 'password' => 'secret-hash', 'bio' => 'Long bio', 'uuid' => 'u-1']);

        $response = $this->getJson($this->apiUrl('student'))->assertOk();
        $keys = collect($response->json('data.0.attributes'))->pluck('key');

        expect($keys)->not->toContain('password', 'bio', 'website', 'uuid', 'settings', 'profile')
            ->and($response->getContent())->not->toContain('secret-hash');
    });

    it('builds the per-row permission block', function () {
        Student::create(['name' => 'Ana']);

        $permissions = $this->getJson($this->apiUrl('student'))->assertOk()->json('data.0.permissions');

        expect($permissions)->toMatchArray([
            'view' => true, 'update' => true, 'delete' => true,
            'forceDelete' => true, 'restore' => false, 'attach' => true, 'detach' => true,
        ])->and(array_keys($permissions['fields']))->toBe(['courses', 'notes', 'labels']);
    });

    it('runs the same number of queries whatever the number of rows (no N+1)', function () {
        $grade = Grade::create(['name' => 'First']);
        $course = Course::create(['name' => 'Math']);
        $seed = function (int $count) use ($grade, $course) {
            foreach (range(1, $count) as $i) {
                $student = Student::create(['name' => "S{$i}", 'grade_id' => $grade->id]);
                $student->courses()->attach($course->id);
                Note::create(['notable_type' => Student::class, 'notable_id' => $student->id, 'body' => 'n']);
            }
        };
        $countQueries = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($this->apiUrl('student'))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $seed(1);
        $withOneRow = $countQueries();
        $seed(6);
        $withSevenRows = $countQueries();

        expect($withSevenRows)->toBe($withOneRow);
    });
    it('builds the field list a fixed number of times whatever the number of rows', function () {
        $built = new ArrayObject();
        $this->app->instance('paged-student.fields-built', $built);
        $countBuilds = function () use ($built) {
            $built->exchangeArray([]);
            $this->getJson($this->apiUrl('paged-student'))->assertOk();

            return count($built);
        };

        Student::create(['name' => 'Only']);
        $withOneRow = $countBuilds();
        foreach (range(1, 4) as $i) {
            Student::create(['name' => "S{$i}"]);
        }

        expect($countBuilds())->toBe($withOneRow);
    })->skip('ISSUE HTTP-016: every index row rebuilds fields() through getPermissionsForResource() -> getFieldPermissions() -> flattenFields()');
});

describe('pagination', function () {
    beforeEach(function () {
        foreach (range(1, 12) as $i) {
            Student::create(['name' => sprintf('Student %02d', $i)]);
        }
    });

    it('uses the resource perPage by default', function () {
        $response = $this->getJson($this->apiUrl('student'))->assertOk();

        expect($response->json('data'))->toHaveCount(12)
            ->and(resourceIndexTestMeta($response, 'per_page'))->toBe(20);

        $paged = $this->getJson($this->apiUrl('paged-student'))->assertOk();

        expect($paged->json('data'))->toHaveCount(5)
            ->and(resourceIndexTestMeta($paged, 'per_page'))->toBe(5)
            ->and(resourceIndexTestMeta($paged, 'last_page'))->toBe(3);
    });

    it('honours an allowed perPage and the page number', function () {
        $response = $this->getJson($this->apiUrl('student') . '?perPage=10&page=2')->assertOk();

        expect(resourceIndexTestNames($response))->toBe(['Student 11', 'Student 12'])
            ->and(resourceIndexTestMeta($response, 'current_page'))->toBe(2)
            ->and(resourceIndexTestMeta($response, 'total'))->toBe(12);
    });

    it('returns no rows for a page past the last one', function () {
        $this->getJson($this->apiUrl('student') . '?page=99')->assertOk()->assertJsonPath('data', []);
    });

    it('treats a non-numeric page as the first page', function () {
        $response = $this->getJson($this->apiUrl('student') . '?perPage=10&page=abc')->assertOk();

        expect(resourceIndexTestNames($response)[0])->toBe('Student 01');
    });

    it('falls back to the resource perPage when the requested size is not in allowedPerPage', function (string $perPage) {
        $response = $this->getJson($this->apiUrl('paged-student') . '?perPage=' . $perPage)->assertOk();

        expect(resourceIndexTestMeta($response, 'per_page'))->toBe(5)
            ->and($response->json('data'))->toHaveCount(5);
    })->with([
        'not in the list' => '7',
        'huge' => '100000',
        'zero' => '0',
    ])->skip('ISSUE HTTP-002: perPage is passed to paginate() unchecked; allowedPerPage is only advertised, never enforced');

    it('does not fail on a negative or non-numeric perPage', function (string $perPage) {
        $response = $this->getJson($this->apiUrl('student') . '?perPage=' . $perPage);

        expect($response->status())->toBeIn([200, 422]);
    })->with([
        'negative' => '-1',
        'non-numeric' => 'abc',
    ])->skip('ISSUE HTTP-002: perPage=-1 and perPage=abc reach paginate() and end in a 500');
});

describe('sorting', function () {
    beforeEach(function () {
        Student::create(['name' => 'Carla', 'age' => 9, 'status' => 'draft']);
        Student::create(['name' => 'Ana', 'age' => 12, 'status' => 'enrolled']);
        Student::create(['name' => 'Bob', 'age' => 10, 'status' => 'graduated']);
    });

    it('applies the resource default sort when none is requested', function () {
        expect(resourceIndexTestNames($this->getJson($this->apiUrl('student'))->assertOk()))
            ->toBe(['Ana', 'Bob', 'Carla']);
    });

    it('sorts by a sortable field in the requested direction', function () {
        expect(resourceIndexTestNames($this->getJson($this->apiUrl('student') . '?sortField=age&sortDirection=asc')))
            ->toBe(['Carla', 'Bob', 'Ana'])
            ->and(resourceIndexTestNames($this->getJson($this->apiUrl('student') . '?sortField=age&sortDirection=desc')))
            ->toBe(['Ana', 'Bob', 'Carla']);
    });

    it('accepts the direction in upper case', function () {
        expect(resourceIndexTestNames($this->getJson($this->apiUrl('student') . '?sortField=age&sortDirection=ASC')->assertOk()))
            ->toBe(['Carla', 'Bob', 'Ana']);
    });

    it('sorts descending when only the field is given', function () {
        expect(resourceIndexTestNames($this->getJson($this->apiUrl('student') . '?sortField=age')->assertOk()))
            ->toBe(['Ana', 'Bob', 'Carla']);
    });

    it('ignores a field that is not sortable and keeps the default sort', function () {
        expect(resourceIndexTestNames($this->getJson($this->apiUrl('student') . '?sortField=status&sortDirection=desc')->assertOk()))
            ->toBe(['Ana', 'Bob', 'Carla']);
    });

    it('ignores a sortField that is not a field key, so raw SQL never reaches ORDER BY', function (string $sortField) {
        $response = $this->getJson($this->apiUrl('student') . '?sortField=' . urlencode($sortField) . '&sortDirection=asc')->assertOk();

        expect(resourceIndexTestNames($response))->toBe(['Ana', 'Bob', 'Carla'])
            ->and(Schema::hasTable('fx_students'))->toBeTrue();
    })->with([
        'unknown column' => 'password',
        'sql' => 'name; DROP TABLE fx_students; --',
        'subquery' => '(select password from fx_students limit 1)',
        'case expression' => 'CASE WHEN 1=1 THEN name END',
    ]);

    it('never interpolates sortDirection into the SQL', function () {
        $this->getJson($this->apiUrl('student') . '?sortField=age&sortDirection=' . urlencode('asc; DROP TABLE fx_students; --'));

        expect(Schema::hasTable('fx_students'))->toBeTrue()
            ->and(Student::count())->toBe(3);
    });

    it('falls back to a valid direction when sortDirection is invalid', function () {
        $this->getJson($this->apiUrl('student') . '?sortField=age&sortDirection=sideways')->assertOk();
    })->skip('ISSUE HTTP-003: an invalid sortDirection reaches Builder::orderBy(), which throws, so the index answers 500');

    it('falls back to created_at desc when the resource has no default sort', function () {
        Student::query()->forceDelete();
        foreach (['First', 'Second', 'Third'] as $name) {
            Student::create(['name' => $name]);
            $this->travel(1)->seconds();
        }

        expect(resourceIndexTestNames($this->getJson($this->apiUrl('paged-student'))->assertOk()))
            ->toBe(['Third', 'Second', 'First']);
    });
});

describe('global search', function () {
    beforeEach(function () {
        $first = Grade::create(['name' => 'First Grade']);
        $second = Grade::create(['name' => 'Second Grade']);
        Student::create(['name' => 'Ana Lopez', 'email' => 'ana@school.test', 'grade_id' => $first->id, 'age' => 30]);
        Student::create(['name' => 'Bob Stone', 'email' => 'bob@mail.test', 'grade_id' => $second->id, 'age' => 31]);
        Student::create(['name' => 'Agent 0', 'email' => 'zero@mail.test', 'age' => 32]);
    });

    it('matches part of a searchable attribute under the globalSearch key', function () {
        expect(resourceIndexTestNames($this->getJson($this->apiUrl('student') . '?globalSearch=Lop')->assertOk()))
            ->toBe(['Ana Lopez'])
            ->and(resourceIndexTestNames($this->getJson($this->apiUrl('student') . '?globalSearch=mail.test')))
            ->toBe(['Agent 0', 'Bob Stone']);
    });

    it('matches a searchable relation path', function () {
        expect(resourceIndexTestNames($this->getJson($this->apiUrl('student') . '?globalSearch=Second')->assertOk()))
            ->toBe(['Bob Stone']);
    });

    it('reports the filtered total', function () {
        $response = $this->getJson($this->apiUrl('student') . '?globalSearch=mail.test')->assertOk();

        expect(resourceIndexTestMeta($response, 'total'))->toBe(2);
    });

    it('returns everything for an empty search term', function () {
        expect($this->getJson($this->apiUrl('student') . '?globalSearch=')->assertOk()->json('data'))->toHaveCount(3);
    });

    it('ignores a "search" parameter because the default key is globalSearch', function () {
        expect($this->getJson($this->apiUrl('student') . '?search=Lopez')->assertOk()->json('data'))->toHaveCount(3);
    });

    it('binds the search term instead of concatenating it', function () {
        expect($this->getJson($this->apiUrl('student') . '?globalSearch=' . urlencode("' OR '1'='1"))->assertOk()->json('data'))
            ->toBe([]);
    });

    it('uses the resource search key and its applySearch() hook', function () {
        Student::create(['name' => 'Numeric', 'age' => 77]);

        expect(resourceIndexTestNames($this->getJson($this->apiUrl('paged-student') . '?q=Stone')->assertOk()))
            ->toBe(['Bob Stone'])
            ->and(resourceIndexTestNames($this->getJson($this->apiUrl('paged-student') . '?q=77')))
            ->toBe(['Numeric'])
            ->and($this->getJson($this->apiUrl('paged-student') . '?globalSearch=Stone')->json('data'))
            ->toHaveCount(4);
    });

    it('searches for the term "0" instead of ignoring it', function () {
        expect(resourceIndexTestNames($this->getJson($this->apiUrl('student') . '?globalSearch=0')->assertOk()))
            ->toBe(['Agent 0']);
    })->skip('ISSUE HTTP-004: ApplySearchPipe uses empty($search), so the term "0" is treated as no search');

    it('treats LIKE wildcards in the term literally', function () {
        Student::create(['name' => '100% done']);

        expect(resourceIndexTestNames($this->getJson($this->apiUrl('student') . '?globalSearch=' . urlencode('%'))->assertOk()))
            ->toBe(['100% done']);
    })->skip('ISSUE HTTP-004: % and _ are not escaped, so "%" matches every row');

    it('does not fail when the search term is an array', function () {
        $response = $this->getJson($this->apiUrl('student') . '?globalSearch[]=Ana');

        expect($response->status())->toBeIn([200, 422]);
    })->skip('ISSUE HTTP-004: an array search term reaches string interpolation / a string-typed parameter and ends in a 500');
});

describe('soft deletes', function () {
    beforeEach(function () {
        Student::create(['name' => 'Active']);
        Student::create(['name' => 'Trashed'])->delete();
    });

    it('hides trashed records by default', function () {
        expect(resourceIndexTestNames($this->getJson($this->apiUrl('student'))->assertOk()))->toBe(['Active']);
    });

    it('lists only trashed records with their deletedAt and restore permission', function (string $value) {
        $row = $this->getJson($this->apiUrl('student') . '?withTrashed=' . $value)->assertOk()->json('data');

        expect($row)->toHaveCount(1)
            ->and($row[0]['deletedAt'])->not->toBeNull()
            ->and($row[0]['permissions']['restore'])->toBeTrue();
    })->with(['only', 'deleted', '1']);

    it('lists active and trashed records together', function (string $value) {
        expect(resourceIndexTestNames($this->getJson($this->apiUrl('student') . '?withTrashed=' . $value)->assertOk()))
            ->toBe(['Active', 'Trashed']);
    })->with(['with', 'all', '2', 'true']);

    it('keeps the default for "without" and unknown values', function (string $value) {
        expect(resourceIndexTestNames($this->getJson($this->apiUrl('student') . '?withTrashed=' . $value)->assertOk()))
            ->toBe(['Active']);
    })->with(['without', 'active', '0', 'nonsense']);

    it('ignores withTrashed on a resource without soft deletes', function () {
        Grade::create(['name' => 'Only grade']);

        expect($this->getJson($this->apiUrl('grade') . '?withTrashed=only')->assertOk()->json('data'))->toHaveCount(1);
    });
});

describe('query scoping', function () {
    it('applies both getQuery() and queryIndex() to the listing', function () {
        Student::create(['name' => 'Active enrolled', 'is_active' => true, 'status' => 'enrolled']);
        Student::create(['name' => 'Active draft', 'is_active' => true, 'status' => 'draft']);
        Student::create(['name' => 'Inactive enrolled', 'is_active' => false, 'status' => 'enrolled']);

        $response = $this->getJson($this->apiUrl('index-scoped-student'))->assertOk();

        expect(resourceIndexTestNames($response))->toBe(['Active enrolled'])
            ->and(resourceIndexTestMeta($response, 'total'))->toBe(1);
    });

    it('eager loads the relations listed in withOnIndex with a single query', function () {
        foreach (range(1, 3) as $i) {
            $student = Student::create(['name' => "S{$i}"]);
            StudentProfile::create(['student_id' => $student->id, 'nickname' => "n{$i}"]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($this->apiUrl('paged-student'))->assertOk();
        $profileQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'fx_student_profiles'));
        DB::disableQueryLog();

        expect($profileQueries)->toHaveCount(1)
            ->and($profileQueries->first()['query'])->toContain(' in (');
    });

    it('selects only the columns the index fields need, plus the key and deleted_at', function () {
        Student::create(['name' => 'Ana']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($this->apiUrl('paged-student'))->assertOk();
        $select = collect(DB::getQueryLog())->first(fn ($q) => str_starts_with($q['query'], 'select "name"'));
        DB::disableQueryLog();

        expect($select['query'])->toStartWith('select "name", "age", "email", "id", "deleted_at" from "fx_students"');
    });
});

it('lists a non-soft-deleting resource the same way', function () {
    Grade::create(['name' => 'Second', 'level' => 2]);
    Grade::create(['name' => 'First', 'level' => 1]);

    $response = $this->getJson($this->apiUrl('grade'))->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0.deletedAt'))->toBeNull()
        ->and($response->json('data.0.permissions.forceDelete'))->toBeFalse()
        ->and($response->json('data.0.permissions.restore'))->toBeFalse();
});
