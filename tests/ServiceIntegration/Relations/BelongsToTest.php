<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Count;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceEditService;
use SchoolAid\Nadota\Http\Services\ResourceIndexService;
use SchoolAid\Nadota\Http\Services\ResourceShowService;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;

/**
 * BelongsTo driven through the CRUD services (no HTTP layer): the foreign key
 * is written on store/update, validated with the exists rule from
 * relatedModel(), and the related row is eager loaded with only the related
 * resource's columns on show/edit/index.
 */
beforeEach(function () {
    FixtureSchema::create();
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name')->required(),
        BelongsTo::make('Grade', 'grade', GradeResource::class)->relatedModel(Grade::class)->sortable(),
    ]);
});

afterEach(function () {
    RelStudentResource::reset();
});

function belongsToServiceRequest(array $data = []): NadotaRequest
{
    $request = createNadotaRequest($data);
    $request->setResource(new RelStudentResource());

    return $request;
}

function belongsToServiceIndex(NadotaRequest $request): array
{
    return app(ResourceIndexService::class)->handle($request)->response($request)->getData(true);
}

function belongsToServiceAttribute(array $payload, string $key): ?array
{
    return collect($payload['data']['attributes'])->firstWhere('key', $key);
}

it('stores the foreign key from the request', function () {
    $grade = Grade::create(['name' => '5th A']);

    $response = app(ResourceStoreService::class)->handle(belongsToServiceRequest(['name' => 'Ana', 'grade_id' => $grade->id]));

    expect($response->getStatusCode())->toBe(201)
        ->and(Student::first()->grade_id)->toBe($grade->id);
});

it('rejects a foreign key that does not exist', function () {
    $response = app(ResourceStoreService::class)->handle(belongsToServiceRequest(['name' => 'Ana', 'grade_id' => 999]));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey('grade_id')
        ->and(Student::count())->toBe(0);
});

it('stores a null foreign key when none is sent', function () {
    app(ResourceStoreService::class)->handle(belongsToServiceRequest(['name' => 'Ana']));

    expect(Student::first()->grade_id)->toBeNull();
});

it('changes the foreign key on update and keeps it when the update omits it', function () {
    $first = Grade::create(['name' => 'First']);
    $second = Grade::create(['name' => 'Second']);
    $student = Student::create(['name' => 'Ana', 'grade_id' => $first->id]);
    $service = app(ResourceUpdateService::class);

    $service->handle(belongsToServiceRequest(['name' => 'Ana', 'grade_id' => $second->id]), $student->id);
    $afterChange = $student->fresh()->grade_id;
    $service->handle(belongsToServiceRequest(['name' => 'Ana B.']), $student->id);

    expect($afterChange)->toBe($second->id)
        ->and($student->fresh()->grade_id)->toBe($second->id)
        ->and($student->fresh()->name)->toBe('Ana B.');
});

it('eager loads the related row on show with only the related resource columns', function () {
    $grade = Grade::create(['name' => '5th A']);
    $student = Student::create(['name' => 'Ana', 'grade_id' => $grade->id]);

    DB::enableQueryLog();
    $payload = app(ResourceShowService::class)->handle(belongsToServiceRequest(), $student->id)->getData(true);
    $queries = collect(DB::getQueryLog())->pluck('query');

    expect(belongsToServiceAttribute($payload, 'grade_id')['value'])
        ->toBe(['id' => $grade->id, 'key' => $grade->id, 'label' => '5th A', 'resource' => 'grade', 'deletedAt' => null])
        ->and($queries)->toHaveCount(2)
        ->and($queries->last())->toContain('"fx_grades"."name"')
        ->not->toContain('"fx_grades".*');
});

it('renders the related row in the edit form', function () {
    $grade = Grade::create(['name' => '5th A']);
    $student = Student::create(['name' => 'Ana', 'grade_id' => $grade->id]);

    $payload = app(ResourceEditService::class)->handle(belongsToServiceRequest(), $student->id)->getData(true);

    expect(belongsToServiceAttribute($payload, 'grade_id')['value']['label'])->toBe('5th A')
        ->and(belongsToServiceAttribute($payload, 'grade_id')['rules'])->toContain('exists:fx_grades,id');
});

it('eager loads the related rows of a whole index page in one query', function () {
    foreach (range(1, 5) as $i) {
        Student::create(['name' => "S{$i}", 'grade_id' => Grade::create(['name' => "G{$i}"])->id]);
    }

    DB::enableQueryLog();
    $payload = belongsToServiceIndex(belongsToServiceRequest());
    $gradeQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, 'from "fx_grades"'));

    expect($payload['data'])->toHaveCount(5)
        ->and($gradeQueries)->toHaveCount(1);
});

it('sorts the index by the related display column', function () {
    $zeta = Grade::create(['name' => 'Zeta']);
    $alpha = Grade::create(['name' => 'Alpha']);
    Student::create(['name' => 'In Zeta', 'grade_id' => $zeta->id]);
    Student::create(['name' => 'In Alpha', 'grade_id' => $alpha->id]);
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        BelongsTo::make('Grade', 'grade', GradeResource::class)->displayAttribute('name')->sortable(),
    ]);

    $data = belongsToServiceIndex(belongsToServiceRequest(['sortField' => 'grade_id', 'sortDirection' => 'asc']))['data'];

    expect(collect($data)->map(fn ($row) => $row['attributes'][0]['value'])->all())->toBe(['In Alpha', 'In Zeta']);
});

it('keeps Count fields when the index is sorted by a BelongsTo', function () {
    $grade = Grade::create(['name' => 'G']);
    $student = Student::create(['name' => 'Ana', 'grade_id' => $grade->id]);
    $student->courses()->attach(Course::create(['name' => 'Math'])->id);
    RelStudentResource::using(fn () => [
        Input::make('Name', 'name'),
        BelongsTo::make('Grade', 'grade', GradeResource::class)->sortable(),
        Count::make('Courses', 'courses'),
    ]);

    $unsorted = belongsToServiceIndex(belongsToServiceRequest())['data'][0];
    $sorted = belongsToServiceIndex(belongsToServiceRequest(['sortField' => 'grade_id', 'sortDirection' => 'asc']))['data'][0];

    expect(collect($unsorted['attributes'])->firstWhere('key', 'courses_count')['value'])->toBe(1)
        ->and(collect($sorted['attributes'])->firstWhere('key', 'courses_count')['value'])->toBe(1);
})->skip('ISSUE REL-010: BelongsTo::applySorting() replaces the select list with "table.*", dropping withCount/withExists columns');
