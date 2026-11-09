<?php

use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\FilterLabNoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\FilterLabStudentResource;

/**
 * Index requests with every filter type StudentResource does not declare:
 * FilterLabStudentResource (field filters of each kind + one resource filter
 * per Filter class) and FilterLabNoteResource (MorphTo pair, Toggle).
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi([...$this->fixtureResources, FilterLabStudentResource::class, FilterLabNoteResource::class]);

    $this->north = School::create(['name' => 'North']);
    $this->south = School::create(['name' => 'South']);
    $this->g1 = Grade::create(['name' => 'G1', 'school_id' => $this->north->id]);
    $this->g2 = Grade::create(['name' => 'G2', 'school_id' => $this->south->id]);
    $math = Course::create(['name' => 'Math']);
    $this->art = Course::create(['name' => 'Art']);

    $this->ana = Student::create(['name' => 'Ana', 'email' => 'ana@school.test', 'website' => 'ana.dev', 'age' => 8, 'balance' => 0,
        'enrolled_at' => '2024-01-01 08:00:00', 'class_time' => '08:30', 'accept_terms' => true, 'gender' => 'f', 'interests' => ['art'],
        'grade_id' => $this->g1->id, 'is_active' => true, 'status' => 'draft', 'favorite_color' => 'red']);
    $this->bob = Student::create(['name' => 'Bob', 'email' => 'bob@school.test', 'website' => 'bob.dev', 'age' => 10, 'balance' => 50.5,
        'enrolled_at' => '2024-01-31 10:00:00', 'class_time' => '10:00', 'accept_terms' => false, 'gender' => 'm', 'interests' => ['math'],
        'grade_id' => $this->g1->id, 'is_active' => false, 'status' => 'enrolled', 'favorite_color' => 'blue']);
    $this->cid = Student::create(['name' => 'Cid', 'email' => 'cid@mail.test', 'age' => 12, 'balance' => 100,
        'enrolled_at' => '2024-02-01 00:00:00', 'class_time' => '12:00', 'accept_terms' => true, 'gender' => 'm', 'interests' => ['art', 'math'],
        'grade_id' => $this->g2->id, 'is_active' => true, 'status' => 'enrolled', 'favorite_color' => 'dark red']);
    Student::create(['name' => 'Dee', 'age' => 15, 'is_active' => true, 'status' => 'graduated']);

    $this->profile = $this->ana->profile()->create(['nickname' => 'annie']);
    $this->ana->courses()->attach([$math->id, $this->art->id]);
    $this->bob->courses()->attach([$this->art->id]);
    $this->anaNote = $this->ana->notes()->create(['body' => 'ana pinned', 'pinned' => true]);
    $this->bobNote = $this->bob->notes()->create(['body' => 'bob plain', 'pinned' => false]);
    $this->gradeNote = $this->g1->notes()->create(['body' => 'grade pinned', 'pinned' => true]);

    $this->students = fn (array|string $query): TestResponse => $this->getJson(
        $this->apiUrl('filter-lab-student') . '?' . (is_string($query) ? $query : http_build_query($query))
    );
    $this->notes = fn (array $query): TestResponse => $this->getJson($this->apiUrl('filter-lab-note') . '?' . http_build_query($query));
});

/** Value of one attribute for every row of an index response. */
function filterLabColumn(TestResponse $response, string $key): array
{
    return collect($response->assertOk()->json('data'))
        ->map(fn ($row) => collect($row['attributes'])->firstWhere('key', $key)['value'])
        ->sort()->values()->all();
}

function filterLabNames(TestResponse $response): array
{
    return filterLabColumn($response, 'name');
}

describe('text filters', function () {
    it('matches part of an Email field', function () {
        expect(filterLabNames(($this->students)(['filters' => ['email' => 'school']])))->toBe(['Ana', 'Bob']);
    });

    it('matches part of a resource DefaultFilter column', function () {
        expect(filterLabNames(($this->students)(['filters' => ['favorite_color' => 'red']])))->toBe(['Ana', 'Cid']);
    });

    it('answers 200 for an array sent to a text filter', function () {
        ($this->students)('filters[email][]=school')->assertOk();
    })->skip('ISSUE FLT-009: DefaultFilter concatenates the array into the LIKE pattern ("Array to string conversion") and answers 500');
});

describe('range filters', function () {
    it('applies a Number field made filterableRange()', function () {
        expect(filterLabNames(($this->students)(['filters' => ['age_from' => 10, 'age_to' => 12]])))->toBe(['Bob', 'Cid'])
            ->and(filterLabNames(($this->students)(['filters' => ['age_from' => 12]])))->toBe(['Cid', 'Dee'])
            ->and(filterLabNames(($this->students)(['filters' => ['age_to' => 8]])))->toBe(['Ana'])
            ->and(filterLabNames(($this->students)(['filters' => ['age_from' => 0, 'age_to' => 8]])))->toBe(['Ana']);
    });

    it('applies a DateTime field made filterableRange()', function () {
        expect(filterLabNames(($this->students)(['filters' => ['enrolled_at_from' => '2024-01-15']])))->toBe(['Bob', 'Cid']);
    });

    it('applies a resource RangeFilter', function () {
        expect(filterLabNames(($this->students)(['filters' => ['balance_from' => 50, 'balance_to' => 100]])))->toBe(['Bob', 'Cid'])
            ->and(filterLabNames(($this->students)(['filters' => ['balance_to' => 60]])))->toBe(['Ana', 'Bob']);
    });

    it('treats 0 as a real RangeFilter bound', function () {
        expect(filterLabNames(($this->students)(['filters' => ['balance_from' => 0, 'balance_to' => 0]])))->toBe(['Ana']);
    })->skip('ISSUE FLT-006: RangeFilter tests bounds for truthiness, so "0" is dropped and every row comes back');
});

describe('boolean and existence filters', function () {
    it('filters a Checkbox field', function () {
        expect(filterLabNames(($this->students)(['filters' => ['accept_terms' => 'true']])))->toBe(['Ana', 'Cid'])
            ->and(filterLabNames(($this->students)(['filters' => ['accept_terms' => 'false']])))->toBe(['Bob', 'Dee']);
    });

    it('filters a resource BooleanFilter', function () {
        expect(filterLabNames(($this->students)(['filters' => ['is_active' => 'false']])))->toBe(['Bob']);
    });

    it('filters an Exists field by the existence of the relation', function () {
        expect(filterLabNames(($this->students)(['filters' => ['notes_exists' => 'true']])))->toBe(['Ana', 'Bob'])
            ->and(filterLabNames(($this->students)(['filters' => ['notes_exists' => 'false']])))->toBe(['Cid', 'Dee']);
    });

    it('applies the constraint of a resource ExistsFilter on both branches', function () {
        expect(filterLabNames(($this->students)(['filters' => ['pinned_notes_exists' => '1']])))->toBe(['Ana'])
            ->and(filterLabNames(($this->students)(['filters' => ['pinned_notes_exists' => '0']])))->toBe(['Bob', 'Cid', 'Dee']);
    });

    it('ignores an Exists value that is neither true nor false', function () {
        expect(filterLabNames(($this->students)(['filters' => ['notes_exists' => 'banana']])))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
    })->skip('ISSUE FLT-007: unknown values are coerced to false, so "banana" means "has no notes"');
});

describe('select filters', function () {
    it('filters a resource SelectFilter by one value or several', function () {
        expect(filterLabNames(($this->students)(['filters' => ['status' => 'enrolled']])))->toBe(['Bob', 'Cid'])
            ->and(filterLabNames(($this->students)(['filters' => ['status' => ['draft', 'graduated']]])))->toBe(['Ana', 'Dee']);
    });

    it('filters a Radio field by the stored value', function () {
        expect(filterLabNames(($this->students)(['filters' => ['gender' => 'm']])))->toBe(['Bob', 'Cid']);
    });

    it('filters a Radio field by the value /filters advertises for an option', function () {
        $gender = collect($this->getJson($this->apiUrl('filter-lab-student', 'filters'))->json('data'))->firstWhere('key', 'gender');
        $male = collect($gender['options'])->firstWhere('label', 'Male');

        expect($male)->not->toBeNull()
            ->and(filterLabNames(($this->students)(['filters' => ['gender' => $male['value']]])))->toBe(['Bob', 'Cid']);
    })->skip('ISSUE FLT-027: the advertised options are swapped ({label: "m", value: "Male"}), so no option carries the stored value');

    it('filters a CheckboxList field by one of its stored values', function () {
        expect(filterLabNames(($this->students)(['filters' => ['interests' => 'art']])))->toBe(['Ana', 'Cid']);
    })->skip('ISSUE FLT-013: SelectFilter compares the JSON column with "=", so ["art"] never equals "art"');
});

describe('relation filters', function () {
    it('filters a BelongsTo field', function () {
        expect(filterLabNames(($this->students)(['filters' => ['grade_id' => $this->g2->id]])))->toBe(['Cid']);
    });

    it('filters through a dotted relation with a resource DynamicSelectFilter', function () {
        expect(filterLabNames(($this->students)(['filters' => ['school_id' => $this->north->id]])))->toBe(['Ana', 'Bob'])
            ->and(filterLabNames(($this->students)(['filters' => ['school_id' => $this->south->id]])))->toBe(['Cid']);
    });

    it('filters a HasOne field shown on the index', function () {
        expect(filterLabNames(($this->students)(['filters' => ['profile' => $this->profile->id]])))->toBe(['Ana']);
    });

    it('filters a BelongsToMany field (hidden from the index by default)', function () {
        expect(filterLabNames(($this->students)(['filters' => ['courses' => [$this->art->id]]])))->toBe(['Ana', 'Bob']);
    })->skip('ISSUE FLT-004: the filter is listed by /filters but BelongsToMany is hidden from the index, so the pipe never sees it (and FLT-025 waits behind it)');

    it('filters a MorphMany field (hidden from the index by default)', function () {
        expect(filterLabNames(($this->students)(['filters' => ['notes' => [$this->bobNote->id]]])))->toBe(['Bob']);
    })->skip('ISSUE FLT-004: the filter is listed by /filters but MorphMany is hidden from the index, so the pipe never sees it');
});

describe('fields whose filter is listed but does not work', function () {
    it('applies the filter of a field hidden from the index', function () {
        expect(filterLabNames(($this->students)(['filters' => ['website' => 'ana']])))->toBe(['Ana']);
    })->skip('ISSUE FLT-004: /filters uses flattenFields(), ApplyFiltersPipe uses fieldsForIndex(), so hideFromIndex() fields are listed but ignored');

    it('filters a Time field by its time', function () {
        expect(filterLabNames(($this->students)(['filters' => ['class_time' => '10:00']])))->toBe(['Bob']);
    })->skip('ISSUE FLT-012: Time maps to DateFilter, which runs whereDate() on a time value and never matches');

    it('filters a Count field by the number of related rows', function () {
        expect(filterLabNames(($this->students)(['filters' => ['courses_count' => 0]])))->toBe(['Cid', 'Dee'])
            ->and(filterLabNames(($this->students)(['filters' => ['courses_count' => 2]])))->toBe(['Ana']);
    })->skip('ISSUE FLT-011: NumberFilter puts the withCount alias in WHERE; SQLite reads "courses_count" as a string literal (no rows), MySQL/PostgreSQL fail');
});

describe('FilterLabNoteResource', function () {
    it('filters a Toggle field', function () {
        expect(filterLabColumn(($this->notes)(['filters' => ['pinned' => 'true']]), 'body'))->toBe(['ana pinned', 'grade pinned']);
    });

    it('filters by morph type alias', function () {
        expect(filterLabColumn(($this->notes)(['filters' => ['notable_type' => 'student']]), 'body'))->toBe(['ana pinned', 'bob plain'])
            ->and(filterLabColumn(($this->notes)(['filters' => ['notable_type' => 'grade']]), 'body'))->toBe(['grade pinned']);
    });

    it('filters by morph type and entity together', function () {
        $response = ($this->notes)(['filters' => ['notable_type' => 'student', 'notable_id' => $this->ana->id]]);

        expect(filterLabColumn($response, 'body'))->toBe(['ana pinned']);
    });

    it('matches the entity id across every morph type when the type is not sent', function () {
        // Grade #1 and Student #1 (Ana) share the id; the frontend is expected to send both halves.
        expect(filterLabColumn(($this->notes)(['filters' => ['notable_id' => $this->ana->id]]), 'body'))->toBe(['ana pinned', 'grade pinned']);
    });

    it('ignores an unknown morph type alias', function () {
        expect(filterLabColumn(($this->notes)(['filters' => ['notable_type' => 'teacher']]), 'body'))->toBe(['ana pinned', 'bob plain', 'grade pinned']);
    });

    it('combines the morph pair with another filter', function () {
        $response = ($this->notes)(['filters' => ['notable_type' => 'student', 'pinned' => 'false']]);

        expect(filterLabColumn($response, 'body'))->toBe(['bob plain'])
            ->and(Arr::first(Arr::wrap($response->json('meta.total'))))->toBe(1);
    });
});
