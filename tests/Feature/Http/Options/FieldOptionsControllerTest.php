<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Label;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Policies\OptionsAwareStudentPolicy;
use SchoolAid\Nadota\Tests\Fixtures\Policies\OptionsAwareStudentPolicyWithOptions;
use SchoolAid\Nadota\Tests\Fixtures\Resources\EnrollmentFormResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\ScopedStudentResource;

/**
 * GET /{resourceKey}/resource/field/{fieldName}/options
 * GET /{resourceKey}/resource/field/{fieldName}/options/paginated
 * GET /{resourceKey}/resource/field/{fieldName}/morph-options/{morphType}
 *
 * The strategies and the scope resolver have service-level tests
 * (tests/ServiceIntegration/Scoped*OptionsTest.php, MorphToScopedOptionsTest.php,
 * LookupOptionsTest.php); these tests drive the real routes and controller.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi(array_merge($this->fixtureResources, [
        ScopedStudentResource::class,
        EnrollmentFormResource::class,
    ]));
});

function fieldOptionsControllerValues($response, string $key = 'options'): array
{
    return collect($response->json($key))->pluck('value')->all();
}

describe('belongsTo options', function () {
    it('lists the related resource records as value/label pairs with meta', function () {
        Grade::create(['name' => 'First']);
        Grade::create(['name' => 'Second']);

        $this->getJson($this->apiUrl('student', 'field/grade/options'))
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'options' => [
                    ['value' => 1, 'label' => 'First'],
                    ['value' => 2, 'label' => 'Second'],
                ],
                'meta' => ['total' => 2, 'search' => '', 'limit' => 15, 'fieldType' => 'belongsTo'],
            ]);
    });

    it('finds the field by key, by attribute and by relation name', function (string $fieldName) {
        Grade::create(['name' => 'First']);

        $this->getJson($this->apiUrl('student', "field/{$fieldName}/options"))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('options.0.label', 'First');
    })->with(['grade_id', 'grade']);

    it('searches the related resource searchable attributes', function () {
        Grade::create(['name' => 'First']);
        Grade::create(['name' => 'Second']);

        $response = $this->getJson($this->apiUrl('student', 'field/grade/options?search=sec'))->assertOk();

        expect(collect($response->json('options'))->pluck('label')->all())->toBe(['Second'])
            ->and($response->json('meta.search'))->toBe('sec');
    });

    it('returns at most limit options', function () {
        foreach (range(1, 5) as $i) {
            Grade::create(['name' => "Grade {$i}"]);
        }

        $response = $this->getJson($this->apiUrl('student', 'field/grade/options?limit=2'))->assertOk();

        expect($response->json('options'))->toHaveCount(2);
    });

    it('caps a large limit at 100 options', function () {
        DB::table('fx_grades')->insert(array_map(fn ($i) => ['name' => "G{$i}", 'level' => 1], range(1, 105)));

        $response = $this->getJson($this->apiUrl('student', 'field/grade/options?limit=500'))->assertOk();

        expect($response->json('options'))->toHaveCount(100);
    });

    it('reports the limit it actually applied in meta', function () {
        DB::table('fx_grades')->insert(array_map(fn ($i) => ['name' => "G{$i}", 'level' => 1], range(1, 105)));

        $this->getJson($this->apiUrl('student', 'field/grade/options?limit=500'))
            ->assertOk()
            ->assertJsonPath('meta.limit', 100);
    })->skip('ISSUE OPS-012: meta.limit echoes the raw request value ("500") while 100 options are returned');

    it('never returns more than 100 options, whatever the limit', function (string $limit) {
        DB::table('fx_grades')->insert(array_map(fn ($i) => ['name' => "G{$i}", 'level' => 1], range(1, 105)));

        $response = $this->getJson($this->apiUrl('student', "field/grade/options?limit={$limit}"))->assertOk();

        expect(count($response->json('options')))->toBeLessThanOrEqual(100);
    })->with(['-1', ''])->skip('ISSUE OPS-011: limit=-1 or an empty limit skips the 100 cap and returns every row');

    it('excludes ids given as an array or as a comma separated string', function (string $query) {
        Grade::create(['name' => 'First']);
        Grade::create(['name' => 'Second']);
        Grade::create(['name' => 'Third']);

        $response = $this->getJson($this->apiUrl('student', "field/grade/options?{$query}"))->assertOk();

        expect(fieldOptionsControllerValues($response))->toBe([2]);
    })->with(['exclude[]=1&exclude[]=3', 'exclude=1,3']);

    it('orders by the requested column and direction', function () {
        Grade::create(['name' => 'Beta', 'level' => 1]);
        Grade::create(['name' => 'Alpha', 'level' => 3]);
        Grade::create(['name' => 'Gamma', 'level' => 2]);

        $response = $this->getJson($this->apiUrl('student', 'field/grade/options?orderBy=level&orderDirection=desc'))->assertOk();

        expect(collect($response->json('options'))->pluck('label')->all())->toBe(['Alpha', 'Gamma', 'Beta']);
    });

    it('answers 422, not 500, for an invalid order direction', function () {
        Grade::create(['name' => 'First']);

        $this->getJson($this->apiUrl('student', 'field/grade/options?orderBy=name&orderDirection=sideways'))
            ->assertStatus(422);
    })->skip('ISSUE OPS-013: an orderDirection other than asc/desc reaches orderBy() and throws (500)');
});

describe('to-many options', function () {
    it('lists BelongsToMany options and auto-excludes the ones attached to resourceId', function () {
        $student = Student::create(['name' => 'Ana']);
        $math = Course::create(['name' => 'Math']);
        $art = Course::create(['name' => 'Art']);
        $student->courses()->attach($math->id);

        $response = $this->getJson($this->apiUrl('student', "field/courses/options?resourceId={$student->id}"))
            ->assertOk()
            ->assertJsonPath('meta.fieldType', 'belongsToMany');

        expect(fieldOptionsControllerValues($response))->toBe([$art->id]);
    });

    it('lists every BelongsToMany option without resourceId', function () {
        $student = Student::create(['name' => 'Ana']);
        $student->courses()->attach(Course::create(['name' => 'Math'])->id);
        Course::create(['name' => 'Art']);

        $response = $this->getJson($this->apiUrl('student', 'field/courses/options'))->assertOk();

        expect(fieldOptionsControllerValues($response))->toBe([1, 2]);
    });

    it('lists MorphToMany options and auto-excludes the attached ones', function () {
        $student = Student::create(['name' => 'Ana']);
        $red = Label::create(['name' => 'Red']);
        $blue = Label::create(['name' => 'Blue']);
        $student->labels()->attach($red->id);

        $response = $this->getJson($this->apiUrl('student', "field/labels/options?resourceId={$student->id}"))->assertOk();

        expect(collect($response->json('options'))->pluck('label')->all())->toBe(['Blue']);
    });

    it('lists MorphedByMany options from the inverse side', function () {
        $label = Label::create(['name' => 'Red']);
        $ana = Student::create(['name' => 'Ana']);
        $ben = Student::create(['name' => 'Ben']);
        $label->students()->attach($ana->id);

        $response = $this->getJson($this->apiUrl('label', "field/students/options?resourceId={$label->id}"))->assertOk();

        expect(fieldOptionsControllerValues($response))->toBe([$ben->id]);
    });
});

describe('morph-options', function () {
    it('lists the records of the requested morph type', function (string $morphType, array $labels) {
        Student::create(['name' => 'Ana']);
        Grade::create(['name' => 'First']);

        $response = $this->getJson($this->apiUrl('note', "field/notable/morph-options/{$morphType}"))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.fieldType', 'morphTo');

        expect(collect($response->json('options'))->pluck('label')->all())->toBe($labels);
    })->with([
        'student' => ['student', ['Ana']],
        'grade' => ['grade', ['First']],
    ]);

    it('searches inside the morph type resource', function () {
        Student::create(['name' => 'Ana']);
        Student::create(['name' => 'Ben']);

        $response = $this->getJson($this->apiUrl('note', 'field/notable/morph-options/student?search=be'))->assertOk();

        expect(collect($response->json('options'))->pluck('label')->all())->toBe(['Ben']);
    });

    it('answers an empty success for a morph type the field does not declare', function () {
        Student::create(['name' => 'Ana']);

        $this->getJson($this->apiUrl('note', 'field/notable/morph-options/school'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('options', []);
    });

    it('answers an empty success when the plain options route is used for a MorphTo field', function () {
        Student::create(['name' => 'Ana']);

        $this->getJson($this->apiUrl('note', 'field/notable/options'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('options', []);
    });
});

describe('fields without options', function () {
    it('answers success false for an unknown field', function (string $path, string $key) {
        $this->getJson($this->apiUrl('student', $path))
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Field not found')
            ->assertJsonPath($key, []);
    })->with([
        'options' => ['field/nope/options', 'options'],
        'paginated' => ['field/nope/options/paginated', 'data'],
    ]);

    it('answers 404 for an unknown field, like the attachment endpoints', function () {
        $this->getJson($this->apiUrl('student', 'field/nope/options'))->assertNotFound();
    })->skip('ISSUE OPS-014: field options answer 200 {success:false} for an unknown field, attachments answer 404');

    it('answers success false for a field that has no related resource', function (string $fieldName) {
        $this->getJson($this->apiUrl('student', "field/{$fieldName}/options"))
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No strategy found for field type');
    })->with(['name', 'status']);

    it('answers success false on the paginated route for a field that has no related resource', function () {
        $this->getJson($this->apiUrl('student', 'field/name/options/paginated'))
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Field has no associated resource');
    });

    it('answers 404 for an unknown resource', function () {
        $this->getJson($this->apiUrl('nope', 'field/grade/options'))->assertNotFound();
    });
});

describe('paginated options', function () {
    it('pages the options and reports pagination meta', function () {
        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            Grade::create(['name' => $name]);
        }

        $this->getJson($this->apiUrl('student', 'field/grade/options/paginated?per_page=2&page=2'))
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => [['value' => 3, 'label' => 'Gamma']],
                'meta' => [
                    'current_page' => 2,
                    'per_page' => 2,
                    'total' => 3,
                    'last_page' => 2,
                    'from' => 3,
                    'to' => 3,
                    'search' => '',
                    'fieldType' => 'belongsTo',
                ],
            ]);
    });

    it('applies search and exclude', function () {
        Course::create(['name' => 'Math']);
        $music = Course::create(['name' => 'Music']);
        Course::create(['name' => 'Art']);
        $mechanics = Course::create(['name' => 'Mechanics']);

        $response = $this->getJson($this->apiUrl(
            'student',
            "field/courses/options/paginated?search=mu&exclude={$mechanics->id}"
        ))->assertOk();

        expect(collect($response->json('data'))->pluck('value')->all())->toBe([$music->id])
            ->and($response->json('meta.total'))->toBe(1)
            ->and($response->json('meta.search'))->toBe('mu');

        $response = $this->getJson($this->apiUrl('student', "field/courses/options/paginated?search=m&exclude={$mechanics->id}"))->assertOk();

        expect(collect($response->json('data'))->pluck('label')->all())->toBe(['Math', 'Music']);
    });

    it('auto-excludes the records attached to resourceId', function () {
        $student = Student::create(['name' => 'Ana']);
        $math = Course::create(['name' => 'Math']);
        $art = Course::create(['name' => 'Art']);
        $student->courses()->attach($math->id);

        $response = $this->getJson($this->apiUrl('student', "field/courses/options/paginated?resourceId={$student->id}"))->assertOk();

        expect(collect($response->json('data'))->pluck('value')->all())->toBe([$art->id]);
    });

    it('caps per_page at 100', function () {
        DB::table('fx_grades')->insert(array_map(fn ($i) => ['name' => "G{$i}", 'level' => 1], range(1, 105)));

        $response = $this->getJson($this->apiUrl('student', 'field/grade/options/paginated?per_page=500'))->assertOk();

        expect($response->json('data'))->toHaveCount(100)
            ->and($response->json('meta.per_page'))->toBe(100);
    });

    it('treats a negative per_page as the default instead of failing', function () {
        Grade::create(['name' => 'First']);

        $this->getJson($this->apiUrl('student', 'field/grade/options/paginated?per_page=-1'))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15);
    })->skip('ISSUE OPS-011: per_page=-1 is not clamped and the paginated options endpoint answers 500');

    it('reads the page size from per_page, not from the perPage documented in docs/api/routes.md', function () {
        foreach (range(1, 20) as $i) {
            Grade::create(['name' => "Grade {$i}"]);
        }

        $this->getJson($this->apiUrl('student', 'field/grade/options/paginated?perPage=5'))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15);

        $this->getJson($this->apiUrl('student', 'field/grade/options/paginated?per_page=5'))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5);
    });
});

describe('scoped options', function () {
    beforeEach(function () {
        $this->first = Grade::create(['name' => 'First']);
        $this->second = Grade::create(['name' => 'Second']);
        $this->ana = Student::create(['name' => 'Ana', 'grade_id' => $this->first->id]);
        $this->ben = Student::create(['name' => 'Ben', 'grade_id' => $this->second->id]);
    });

    it('narrows the options to the scope value sent as scope[field]', function (string $path, string $key) {
        $response = $this->getJson($this->apiUrl('enrollment-form', "{$path}?scope[grade]={$this->second->id}"))->assertOk();

        expect(collect($response->json($key))->pluck('value')->all())->toBe([$this->ben->id]);
    })->with([
        'options' => ['field/student/options', 'options'],
        'paginated' => ['field/student/options/paginated', 'data'],
    ]);

    it('accepts several scope values as an IN list', function () {
        $response = $this->getJson($this->apiUrl(
            'enrollment-form',
            "field/student/options?scope[grade][]={$this->first->id}&scope[grade][]={$this->second->id}"
        ))->assertOk();

        expect(fieldOptionsControllerValues($response))->toBe([$this->ana->id, $this->ben->id]);
    });

    it('lists every option when the optional scope is not sent', function () {
        $response = $this->getJson($this->apiUrl('enrollment-form', 'field/student/options'))->assertOk();

        expect(fieldOptionsControllerValues($response))->toBe([$this->ana->id, $this->ben->id]);
    });

    it('serves the options of a Lookup field from its resource', function () {
        $response = $this->getJson($this->apiUrl('enrollment-form', 'field/grade/options'))->assertOk();

        expect(collect($response->json('options'))->pluck('label')->all())->toBe(['First', 'Second']);
    });
});

describe('data isolation', function () {
    it('leaves out the records the target resource getQuery() hides', function (string $path, string $key) {
        $visible = Student::create(['name' => 'Ana']);
        Student::create(['name' => 'Archived Ben', 'status' => 'archived']);

        $response = $this->getJson($this->apiUrl('enrollment-form', $path))->assertOk();

        expect(collect($response->json($key))->pluck('value')->all())->toBe([$visible->id]);
    })->with([
        'options' => ['field/student/options', 'options'],
        'paginated' => ['field/student/options/paginated', 'data'],
    ]);

    it('ignores filters on columns the target resource does not expose', function (string $path, string $key) {
        Student::create(['name' => 'Ana', 'password' => 'hash-ana']);
        Student::create(['name' => 'Ben', 'password' => 'hash-ben']);

        // A LIKE on the hidden password column is an oracle for its value.
        $response = $this->getJson($this->apiUrl(
            'enrollment-form',
            "{$path}?filters[password][operator]=like&filters[password][value]=hash-a%25"
        ))->assertOk();

        expect(collect($response->json($key))->pluck('value')->all())->toBe([1, 2]);
    })->with([
        'options' => ['field/student/options', 'options'],
        'paginated' => ['field/student/options/paginated', 'data'],
    ]);
});

describe('authorization', function () {
    beforeEach(function () {
        Grade::create(['name' => 'First']);
        $this->useRealAuthorization();
    });

    it('answers 403 when the policy denies both viewAny and viewOptions', function (string $path) {
        Gate::policy(Student::class, OptionsAwareStudentPolicyWithOptions::class);
        config(['nadota-tests.ops_denied' => ['viewAny', 'viewOptions']]);

        $this->getJson($this->apiUrl('student', $path))->assertForbidden();
    })->with(['field/grade/options', 'field/grade/options/paginated']);

    it('serves options to a user denied viewAny when the policy grants viewOptions', function () {
        Gate::policy(Student::class, OptionsAwareStudentPolicyWithOptions::class);
        config(['nadota-tests.ops_denied' => ['viewAny']]);

        $this->getJson($this->apiUrl('student', 'field/grade/options'))
            ->assertOk()
            ->assertJsonPath('options.0.label', 'First');
    });

    it('answers 403 on morph-options when both abilities are denied', function () {
        Gate::policy(\SchoolAid\Nadota\Tests\Fixtures\Models\Note::class, OptionsAwareStudentPolicyWithOptions::class);
        config(['nadota-tests.ops_denied' => ['viewAny', 'viewOptions']]);

        $this->getJson($this->apiUrl('note', 'field/notable/morph-options/grade'))->assertForbidden();
    });

    it('denies options when viewAny is denied and the policy says nothing about viewOptions', function () {
        Gate::policy(Student::class, OptionsAwareStudentPolicy::class);
        config(['nadota-tests.ops_denied' => ['viewAny']]);

        $this->getJson($this->apiUrl('student', 'field/grade/options'))->assertForbidden();
    })->skip('ISSUE OPS-009: viewOptions is granted whenever the policy lacks the method, so denying viewAny never protects options');

    it('authorizes against the resource that owns the field, not the related one', function () {
        // Grades are not listable by this user, but student/field/grade still lists them.
        Gate::policy(Grade::class, OptionsAwareStudentPolicyWithOptions::class);
        config(['nadota-tests.ops_denied' => ['viewAny', 'viewOptions']]);

        $this->getJson($this->apiUrl('grade', 'options'))->assertForbidden();
        $this->getJson($this->apiUrl('student', 'field/grade/options'))
            ->assertOk()
            ->assertJsonPath('options.0.label', 'First');
    });
});
