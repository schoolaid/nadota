<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Count;
use SchoolAid\Nadota\Http\Fields\CustomComponent;
use SchoolAid\Nadota\Http\Fields\Exists;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\HasOne;
use SchoolAid\Nadota\Http\Fields\Section;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceIndexService;
use SchoolAid\Nadota\Http\Services\ResourceShowService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentProfileResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

/**
 * BuildsSelectColumns::getSelectColumns() decides the SELECT list of the index,
 * show and edit queries. Every column it returns must exist on the table.
 */

beforeEach(fn () => FixtureSchema::create());

it('selects each column once and always appends the primary key', function () {
    $columns = (new StudentResource())->getSelectColumns(createNadotaRequest());

    expect($columns)->toContain('name', 'email', 'status', 'uuid', 'id')
        ->and(array_count_values($columns)['status'])->toBe(1)   // Select + Status share it
        ->and(array_count_values($columns)['id'])->toBe(1)
        ->and($columns)->not->toContain('');
});

it('resolves a BelongsTo to its foreign key, not to the relation name', function () {
    $resource = AdHocResource::withFields([BelongsTo::make('Grade', 'grade', GradeResource::class)]);

    expect($resource->getSelectColumns(createNadotaRequest()))->toBe(['grade_id', 'id']);
});

it('adds no column for to-one/to-many relations, Count, Exists or custom components', function () {
    $resource = AdHocResource::withFields([
        HasOne::make('Profile', 'profile', StudentProfileResource::class),
        Count::make('Courses', 'courses'),
        Exists::make('Has Notes', 'notes'),
        CustomComponent::make('Widget', '@/Widget.vue'),
    ]);

    expect($resource->getSelectColumns(createNadotaRequest()))->toBe(['id']);
});

it('leaves out virtual fields', function () {
    $resource = AdHocResource::withFields([
        Input::make('Name', 'name'),
        Input::make('Send Welcome', 'send_welcome')->virtual(),
    ]);

    expect($resource->getSelectColumns(createNadotaRequest()))->toBe(['name', 'id']);
});

it('includes the columns of fields declared inside sections', function () {
    $resource = AdHocResource::withFields([
        Input::make('Name', 'name'),
        Section::make('Contact', [Input::make('Email', 'email'), Input::make('Website', 'website')]),
    ]);

    expect($resource->getSelectColumns(createNadotaRequest()))->toBe(['name', 'email', 'website', 'id']);
});

it('includes fields hidden from every view when it builds the list itself', function () {
    // Show/Edit pass flattenFields() on purpose so visibility callbacks are not
    // evaluated before the model is loaded; the default path does the same.
    $resource = AdHocResource::withFields([
        Input::make('Name', 'name')->hideFromIndex()->hideFromDetail(),
    ]);

    expect($resource->getSelectColumns(createNadotaRequest()))->toBe(['name', 'id']);
});

it('only uses the given field collection when one is passed', function () {
    $resource = new StudentResource();

    expect($resource->getSelectColumns(createNadotaRequest(), collect([Input::make('Email', 'email')])))
        ->toBe(['email', 'id']);
});

it('uses the resource attributeKey as primary key', function () {
    $resource = new class extends Resource {
        public string $model = Student::class;
        public static string $attributeKey = 'uuid';

        public function fields(NadotaRequest $request): array
        {
            return [Input::make('Name', 'name')];
        }
    };

    expect($resource->getSelectColumns(createNadotaRequest()))->toBe(['name', 'uuid']);
});

it('produces a SELECT that runs against the real table', function () {
    Student::create(['name' => 'Ana', 'email' => 'ana@example.com']);
    $resource = new StudentResource();

    $row = Student::query()->select($resource->getSelectColumns(createNadotaRequest()))->first();

    expect($row->name)->toBe('Ana')
        ->and($row->getAttributes())->not->toHaveKey('created_at');
});

it('does not select the attribute of a computed (displayUsing) field', function () {
    $resource = AdHocResource::withFields([
        Input::make('Name', 'name'),
        Input::make('Shout', 'shout')->displayUsing(fn ($model) => strtoupper($model->name)),
    ]);

    expect($resource->getSelectColumns(createNadotaRequest()))->toBe(['name', 'id']);
})->skip('ISSUE TRT-001: computed fields still put their (non-column) attribute in SELECT');

// SQLite reads an unknown double-quoted identifier as a string literal, so
// `select "shout"` silently returns 'shout' here; MySQL/PostgreSQL fail with
// "unknown column". The tests therefore assert on the SQL that was sent.
function computedColumnResource(): AdHocResource
{
    return AdHocResource::withFields([
        Input::make('Name', 'name'),
        Input::make('Shout', 'shout')->displayUsing(fn ($model) => strtoupper($model->name)),
    ]);
}

function selectedFromStudents(callable $run): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();

    return collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn ($sql) => str_contains($sql, 'from "fx_students"'))
        ->values()
        ->all();
}

it('does not send the computed attribute to the database on show', function () {
    $student = Student::create(['name' => 'Ana']);
    $request = createNadotaRequest();
    $request->setResource(computedColumnResource());

    $queries = selectedFromStudents(fn () => (new ResourceShowService())->handle($request, $student->id));

    expect($queries)->not->toBeEmpty()
        ->and(implode(' ', $queries))->not->toContain('"shout"');
})->skip('ISSUE TRT-001: computed field attribute is selected as a column (fails on MySQL/PostgreSQL)');

it('does not send the computed attribute to the database on index', function () {
    Student::create(['name' => 'Ana']);
    $request = createNadotaRequest();
    $request->setResource(computedColumnResource());

    $queries = selectedFromStudents(fn () => (new ResourceIndexService())->handle($request)->response());

    expect($queries)->not->toBeEmpty()
        ->and(implode(' ', $queries))->not->toContain('"shout"');
})->skip('ISSUE TRT-001: computed field attribute is selected as a column (fails on MySQL/PostgreSQL)');
