<?php

use SchoolAid\Nadota\Http\Filters\DynamicSelectFilter;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

function dynamicSelectFilterNames(DynamicSelectFilter $filter, mixed $value): array
{
    return $filter->apply(createNadotaRequest(), Student::query(), $value)
        ->orderBy('name')->pluck('name')->all();
}

function seedDynamicSelectFilterStudents(): array
{
    FixtureSchema::create();
    $north = School::create(['name' => 'North']);
    $south = School::create(['name' => 'South']);
    $g1 = Grade::create(['name' => 'G1', 'school_id' => $north->id]);
    $g2 = Grade::create(['name' => 'G2', 'school_id' => $north->id]);
    $g3 = Grade::create(['name' => 'G3', 'school_id' => $south->id]);
    Student::create(['name' => 'Ana', 'grade_id' => $g1->id]);
    Student::create(['name' => 'Bob', 'grade_id' => $g2->id]);
    Student::create(['name' => 'Cid', 'grade_id' => $g3->id]);
    Student::create(['name' => 'Dee', 'grade_id' => null]);

    return compact('north', 'south', 'g1', 'g2', 'g3');
}

// --- constructor defaults & props ---------------------------------------------------------

it('is a FilterDynamicSelect "dynamicSelect" filter keyed by its field', function () {
    $filter = new DynamicSelectFilter('Grade', 'grade_id');

    expect($filter->type)->toBe('dynamicSelect')
        ->and($filter->component())->toBe('FilterDynamicSelect')
        ->and($filter->key())->toBe('grade_id')
        ->and($filter->isRange())->toBeFalse()
        ->and($filter->getFilterKeys())->toBe(['value' => 'grade_id']);
});

it('has searchable single-select props by default', function () {
    expect((new DynamicSelectFilter('Grade', 'grade_id'))->props())->toBe([
        'valueField' => 'id',
        'labelField' => 'name',
        'multiple' => false,
        'searchable' => true,
        'applyToQuery' => true,
    ]);
});

it('adds every configured option to props', function () {
    $filter = DynamicSelectFilter::make('Grade', 'grade_id')
        ->valueField('uuid')
        ->labelField('title')
        ->multiple()
        ->searchable(false)
        ->dependsOn(['school_id'])
        ->softDependsOn(['level'])
        ->filtersToSend(['school_id'])
        ->relation('grade')
        ->endpoint('/custom/options');

    expect($filter->props())->toBe([
        'valueField' => 'uuid',
        'labelField' => 'title',
        'multiple' => true,
        'searchable' => false,
        'applyToQuery' => true,
        'endpoint' => '/custom/options',
        'dependsOn' => ['school_id'],
        'softDependsOn' => ['level'],
        'filtersToSend' => ['school_id'],
        'relation' => 'grade',
    ]);
});

it('flags an endpoint with a {morphType} placeholder as a morph endpoint', function () {
    $props = DynamicSelectFilter::make('Notable', 'notable_id')
        ->endpoint('/nadota-api/note/resource/field/notable/morph-options/{morphType}')
        ->props();

    expect($props['isMorphEndpoint'])->toBeTrue()
        ->and($props['endpointTemplate'])->toBe('/nadota-api/note/resource/field/notable/morph-options/{morphType}');
});

it('flags asMorphFilter() filters as morph endpoints even before serialisation', function () {
    expect(DynamicSelectFilter::make('Notable', 'notable_id')->asMorphFilter('notable')->props())
        ->toHaveKey('isMorphEndpoint', true);
});

it('reports applyToQuery=false when the filter will not touch the query', function () {
    $filter = DynamicSelectFilter::make('Grade', 'grade_id')->relation('grade')->applyToQuery(false);

    expect($filter->props()['applyToQuery'])->toBeFalse();
})->skip('ISSUE FLT-018: props() reports applyToQuery || relation !== null, but apply() returns early whenever applyToQuery is false');

// --- endpoint resolution in toArray() -----------------------------------------------------

it('serialises an explicit endpoint at the top level and in props', function () {
    $array = DynamicSelectFilter::make('Grade', 'grade_id')->endpoint('/custom/options')->toArray(createNadotaRequest());

    expect($array['endpoint'])->toBe('/custom/options')
        ->and($array['props']['endpoint'])->toBe('/custom/options');
});

it('builds the endpoint from resourceKey() and the field', function () {
    $array = DynamicSelectFilter::make('Grade', 'grade_id')->resourceKey('student')->toArray(createNadotaRequest());

    expect($array['endpoint'])->toBe('/nadota-api/student/resource/field/grade_id/options');
});

it('builds the endpoint from the resource of the current request', function () {
    $request = createNadotaRequest();
    $request->setResource(new StudentResource());

    expect(DynamicSelectFilter::make('Grade', 'grade_id')->toArray($request)['endpoint'])
        ->toBe('/nadota-api/student/resource/field/grade_id/options');
});

it('builds a morph-options endpoint template for asMorphFilter() filters', function () {
    $request = createNadotaRequest();
    $request->setResource(new StudentResource());

    $array = DynamicSelectFilter::make('Notable', 'notable_id')->asMorphFilter('notable')->toArray($request);

    expect($array['endpoint'])->toBe('/nadota-api/student/resource/field/notable/morph-options/{morphType}')
        ->and($array['props']['isMorphEndpoint'])->toBeTrue()
        ->and($array['props']['endpointTemplate'])->toBe($array['endpoint']);
});

it('omits the endpoint when there is no way to build one', function () {
    // No explicit endpoint, no resourceKey, and the bare request has no resource (404 swallowed).
    $array = DynamicSelectFilter::make('Grade', 'grade_id')->toArray(createNadotaRequest());

    expect($array)->not->toHaveKey('endpoint')
        ->and($array['props'])->not->toHaveKey('endpoint');
});

// --- options & defaults -------------------------------------------------------------------

it('returns static options from resources() and none when they are fetched remotely', function () {
    expect(DynamicSelectFilter::make('Grade', 'grade_id')->options(['G1' => 1])->resources(createNadotaRequest()))->toBe(['G1' => 1])
        ->and(DynamicSelectFilter::make('Grade', 'grade_id')->resources(createNadotaRequest()))->toBe([]);
});

it('calls an options closure with the current request filters', function () {
    $received = null;
    $filter = DynamicSelectFilter::make('Grade', 'grade_id')->options(function (array $filters) use (&$received) {
        $received = $filters;

        return ['G1' => 1];
    });

    $options = $filter->resources(createNadotaRequest(['filters' => ['school_id' => 3]]));

    expect($received)->toBe(['school_id' => 3])
        ->and($options)->toBe(['G1' => 1]);
});

it('serialises static [label => value] options as {label, value}', function () {
    $array = DynamicSelectFilter::make('Grade', 'grade_id')->options(['G1' => 1, 'G2' => 2])->toArray(createNadotaRequest());

    expect($array['options'])->toBe([
        ['label' => 'G1', 'value' => 1],
        ['label' => 'G2', 'value' => 2],
    ]);
});

it('keeps the label of static options already shaped as {value, label}', function () {
    $array = DynamicSelectFilter::make('Grade', 'grade_id')
        ->options([['value' => 1, 'label' => 'G1'], ['value' => 2, 'label' => 'G2']])
        ->toArray(createNadotaRequest());

    expect($array['options'])->toBe([
        ['value' => 1, 'label' => 'G1'],
        ['value' => 2, 'label' => 'G2'],
    ]);
})->skip('ISSUE FLT-005: toArray() re-maps static options and put("label", $index) overwrites the real label with 0, 1, …');

it('serialises closure options through the base normaliser', function () {
    $array = DynamicSelectFilter::make('Grade', 'grade_id')
        ->options(fn () => [['value' => 1, 'label' => 'G1']])
        ->toArray(createNadotaRequest());

    expect($array['options'])->toBe([['value' => 1, 'label' => 'G1']]);
});

it('serialises scalar and array defaults', function () {
    expect(DynamicSelectFilter::make('Grade', 'grade_id')->default())->toBe('')
        ->and(DynamicSelectFilter::make('Grade', 'grade_id')->withDefault(5)->default())->toBe('5')
        ->and(DynamicSelectFilter::make('Grade', 'grade_id')->withDefault([1, 2])->default())->toBe('[1,2]')
        ->and(DynamicSelectFilter::make('Grade', 'grade_id')->withDefault(5)->toArray(createNadotaRequest())['value'])->toBe('5');
});

// --- apply() ------------------------------------------------------------------------------

it('filters the column directly when no relation is set', function () {
    ['g2' => $g2] = seedDynamicSelectFilterStudents();

    expect(dynamicSelectFilterNames(new DynamicSelectFilter('Grade', 'grade_id'), $g2->id))->toBe(['Bob'])
        ->and(dynamicSelectFilterNames(new DynamicSelectFilter('Grade', 'grade_id'), (string) $g2->id))->toBe(['Bob']);
});

it('uses whereIn for multiple selections on the column', function () {
    ['g1' => $g1, 'g3' => $g3] = seedDynamicSelectFilterStudents();

    expect(dynamicSelectFilterNames(DynamicSelectFilter::make('Grade', 'grade_id')->multiple(), [$g1->id, $g3->id]))->toBe(['Ana', 'Cid']);
});

it('uses whereHas on the related key when a relation is set', function () {
    ['g1' => $g1] = seedDynamicSelectFilterStudents();
    $filter = DynamicSelectFilter::make('Grade', 'grade_id')->relation('grade');

    $query = $filter->apply(createNadotaRequest(), Student::query(), $g1->id);

    expect($query->toSql())->toContain('exists (select * from "fx_grades"')
        ->and($query->pluck('name')->all())->toBe(['Ana']);
});

it('follows a dotted relation path with whereHas', function () {
    ['north' => $north] = seedDynamicSelectFilterStudents();
    $filter = DynamicSelectFilter::make('School', 'school_id')->relation('grade.school');

    expect(dynamicSelectFilterNames($filter, $north->id))->toBe(['Ana', 'Bob']);
});

it('uses whereIn inside whereHas for multiple selections', function () {
    ['g2' => $g2, 'g3' => $g3] = seedDynamicSelectFilterStudents();
    $filter = DynamicSelectFilter::make('Grade', 'grade_id')->relation('grade')->multiple();

    expect(dynamicSelectFilterNames($filter, [$g2->id, $g3->id]))->toBe(['Bob', 'Cid']);
});

it('leaves the query untouched when applyToQuery is false', function () {
    ['g1' => $g1] = seedDynamicSelectFilterStudents();
    $filter = DynamicSelectFilter::make('School', 'school_id')->applyToQuery(false);

    expect(dynamicSelectFilterNames($filter, $g1->id))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
});

it('ignores empty values', function ($value) {
    seedDynamicSelectFilterStudents();

    expect(dynamicSelectFilterNames(new DynamicSelectFilter('Grade', 'grade_id'), $value))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
})->with(['null' => null, 'empty string' => '', 'empty array' => [[]]]);

it('filters by a key whose value is 0', function () {
    seedDynamicSelectFilterStudents();
    Student::create(['name' => 'Zed', 'grade_id' => 0]);

    expect(dynamicSelectFilterNames(new DynamicSelectFilter('Grade', 'grade_id'), '0'))->toBe(['Zed']);
})->skip('ISSUE FLT-006: apply() bails out on empty($value), so 0 / "0" can never be selected');

it('does not reduce an array to its first element when multiple is off', function () {
    ['g1' => $g1, 'g2' => $g2] = seedDynamicSelectFilterStudents();

    expect(dynamicSelectFilterNames(new DynamicSelectFilter('Grade', 'grade_id'), [$g1->id, $g2->id]))->not->toBe(['Ana']);
})->skip('ISSUE FLT-020: a non-multiple filter passes the array to where(), which keeps only the first element');
