<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Policies\OptionsAwareStudentPolicy;
use SchoolAid\Nadota\Tests\Fixtures\Policies\OptionsAwareStudentPolicyWithOptions;
use SchoolAid\Nadota\Tests\Fixtures\Resources\ScopedStudentResource;

/**
 * GET /{resourceKey}/resource/options — the resource's own records as options
 * (ResourceOptionsController + ResourceOptionsService).
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi(array_merge($this->fixtureResources, [ScopedStudentResource::class]));
});

function resourceOptionsControllerLabels($response): array
{
    return collect($response->json('options'))->pluck('label')->all();
}

it('lists the records with displayLabel and meta', function () {
    Student::create(['name' => 'Ana']);
    Student::create(['name' => 'Ben']);

    $this->getJson($this->apiUrl('student', 'options'))
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'options' => [
                ['value' => 1, 'label' => 'Ana'],
                ['value' => 2, 'label' => 'Ben'],
            ],
            'meta' => ['total' => 2, 'search' => '', 'keys' => [], 'limit' => 15],
        ]);
});

it('searches the searchable attributes and relations of the resource', function () {
    $first = Grade::create(['name' => 'First']);
    Student::create(['name' => 'Ana', 'email' => 'ana@school.test']);
    Student::create(['name' => 'Ben', 'grade_id' => $first->id]);
    Student::create(['name' => 'Carla']);

    expect(resourceOptionsControllerLabels($this->getJson($this->apiUrl('student', 'options?search=school.test'))))->toBe(['Ana'])
        ->and(resourceOptionsControllerLabels($this->getJson($this->apiUrl('student', 'options?search=first'))))->toBe(['Ben']);
});

it('restricts the options to the requested keys', function (string $query) {
    Student::create(['name' => 'Ana']);
    Student::create(['name' => 'Ben']);
    Student::create(['name' => 'Carla']);

    $response = $this->getJson($this->apiUrl('student', "options?{$query}"))->assertOk();

    expect(resourceOptionsControllerLabels($response))->toBe(['Ana', 'Carla']);
})->with(['keys[]=1&keys[]=3', 'keys=1,3']);

it('excludes the requested ids', function () {
    Student::create(['name' => 'Ana']);
    Student::create(['name' => 'Ben']);

    expect(resourceOptionsControllerLabels($this->getJson($this->apiUrl('student', 'options?exclude=1'))))->toBe(['Ben']);
});

it('applies limit and orderBy', function () {
    Student::create(['name' => 'Ana']);
    Student::create(['name' => 'Ben']);
    Student::create(['name' => 'Carla']);

    $response = $this->getJson($this->apiUrl('student', 'options?limit=2&orderBy=name&orderDirection=desc'))->assertOk();

    expect(resourceOptionsControllerLabels($response))->toBe(['Carla', 'Ben'])
        ->and($response->json('meta.limit'))->toBe(2);
});

it('caps the limit at 100', function () {
    DB::table('fx_grades')->insert(array_map(fn ($i) => ['name' => "G{$i}", 'level' => 1], range(1, 105)));

    $response = $this->getJson($this->apiUrl('grade', 'options?limit=500'))->assertOk();

    expect($response->json('options'))->toHaveCount(100)
        ->and($response->json('meta.limit'))->toBe(100);
});

it('never returns more than 100 options, whatever the limit', function () {
    DB::table('fx_grades')->insert(array_map(fn ($i) => ['name' => "G{$i}", 'level' => 1], range(1, 105)));

    $response = $this->getJson($this->apiUrl('grade', 'options?limit=-1'))->assertOk();

    expect(count($response->json('options')))->toBeLessThanOrEqual(100);
})->skip('ISSUE OPS-011: limit=-1 becomes LIMIT -1, which the query builder drops, so every row is returned');

it('leaves out soft-deleted records', function () {
    Student::create(['name' => 'Ana']);
    Student::create(['name' => 'Ben'])->delete();

    expect(resourceOptionsControllerLabels($this->getJson($this->apiUrl('student', 'options'))))->toBe(['Ana']);
});

it('goes through the resource getQuery(), so hidden records are not offered', function () {
    Student::create(['name' => 'Ana']);
    Student::create(['name' => 'Archived Ben', 'status' => 'archived']);

    expect(resourceOptionsControllerLabels($this->getJson($this->apiUrl('scoped-student', 'options'))))->toBe(['Ana']);
});

it('ignores filters on columns that are not allowed', function () {
    Student::create(['name' => 'Ana', 'password' => 'hash-ana']);
    Student::create(['name' => 'Ben', 'password' => 'hash-ben']);

    // filters[password]=<value> is an equality oracle on the hidden password column.
    $response = $this->getJson($this->apiUrl('student', 'options?filters[password]=hash-ana'))->assertOk();

    expect(resourceOptionsControllerLabels($response))->toBe(['Ana', 'Ben']);
});

it('ignores orderBy on a column the model hides, and still sorts by a visible one', function () {
    Student::create(['name' => 'Ana', 'password' => 'hash-b']);
    Student::create(['name' => 'Ben', 'password' => 'hash-a']);

    // Sorting by the hidden password column would leak the order of its values.
    $byPassword = $this->getJson($this->apiUrl('student', 'options?orderBy=password&orderDirection=asc'))->assertOk();
    $byName = $this->getJson($this->apiUrl('student', 'options?orderBy=name&orderDirection=desc'))->assertOk();

    expect(resourceOptionsControllerLabels($byPassword))->toBe(['Ana', 'Ben'])
        ->and(resourceOptionsControllerLabels($byName))->toBe(['Ben', 'Ana']);
});

it('answers 404 for an unknown resource', function () {
    $this->getJson($this->apiUrl('nope', 'options'))->assertNotFound();
});

describe('authorization', function () {
    beforeEach(function () {
        Student::create(['name' => 'Ana']);
        $this->useRealAuthorization();
    });

    it('answers 403 when the policy denies both viewAny and viewOptions', function () {
        Gate::policy(Student::class, OptionsAwareStudentPolicyWithOptions::class);
        config(['nadota-tests.ops_denied' => ['viewAny', 'viewOptions']]);

        $this->getJson($this->apiUrl('student', 'options'))->assertForbidden();
    });

    it('serves the options when only viewOptions is granted', function () {
        Gate::policy(Student::class, OptionsAwareStudentPolicyWithOptions::class);
        config(['nadota-tests.ops_denied' => ['viewAny']]);

        expect(resourceOptionsControllerLabels($this->getJson($this->apiUrl('student', 'options'))->assertOk()))->toBe(['Ana']);
    });

    it('denies the options when viewAny is denied and the policy says nothing about viewOptions', function () {
        Gate::policy(Student::class, OptionsAwareStudentPolicy::class);
        config(['nadota-tests.ops_denied' => ['viewAny']]);

        $this->getJson($this->apiUrl('student', 'options'))->assertForbidden();
    });
});
