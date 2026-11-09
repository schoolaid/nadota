<?php

use SchoolAid\Nadota\Http\Filters\DynamicSelectFilter;
use SchoolAid\Nadota\Http\Filters\RelationFilter;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

function relationFilterNames(RelationFilter $filter, mixed $value): array
{
    return $filter->apply(createNadotaRequest(), Student::query(), $value)
        ->orderBy('name')->pluck('name')->all();
}

function seedRelationFilterStudents(): array
{
    FixtureSchema::create();
    $math = Course::create(['name' => 'Math']);
    $art = Course::create(['name' => 'Art']);
    $music = Course::create(['name' => 'Music']);

    $ana = Student::create(['name' => 'Ana']);
    $bob = Student::create(['name' => 'Bob']);
    $cid = Student::create(['name' => 'Cid']);
    Student::create(['name' => 'Dee']);

    $ana->courses()->attach([$math->id, $art->id]);
    $bob->courses()->attach([$art->id]);
    $cid->courses()->attach([$music->id]);

    $profile = $bob->profile()->create(['nickname' => 'bobby']);
    $anaNote = $ana->notes()->create(['body' => 'first']);
    $cidNote = $cid->notes()->create(['body' => 'hello']);

    return compact('math', 'art', 'music', 'profile', 'anaNote', 'cidNote');
}

it('extends DynamicSelectFilter with a "relation" type and the dynamic-select component', function () {
    $filter = new RelationFilter('Courses', 'courses');

    expect($filter)->toBeInstanceOf(DynamicSelectFilter::class)
        ->and($filter->type)->toBe('relation')
        ->and($filter->component())->toBe('FilterDynamicSelect')
        ->and($filter->key())->toBe('courses');
});

it('adds the relation type (hasMany by default) to the dynamic-select props', function () {
    expect((new RelationFilter('Courses', 'courses'))->props())->toBe([
        'valueField' => 'id',
        'labelField' => 'name',
        'multiple' => false,
        'searchable' => true,
        'applyToQuery' => true,
        'relationType' => 'hasMany',
    ])->and(RelationFilter::make('Courses', 'courses')->relationType('belongsToMany')->props()['relationType'])->toBe('belongsToMany');
});

it('builds its options endpoint from the current resource and field', function () {
    $request = createNadotaRequest();
    $request->setResource(new StudentResource());

    expect(RelationFilter::make('Courses', 'courses')->toArray($request)['endpoint'])
        ->toBe('/nadota-api/student/resource/field/courses/options');
});

it('ignores resourceKey() when building the endpoint (unlike DynamicSelectFilter)', function () {
    expect(RelationFilter::make('Courses', 'courses')->resourceKey('student')->toArray(createNadotaRequest()))
        ->not->toHaveKey('endpoint');
});

it('prefers an explicit endpoint', function () {
    expect(RelationFilter::make('Courses', 'courses')->endpoint('/x')->toArray(createNadotaRequest())['endpoint'])->toBe('/x');
});

it('falls back to the field as relation name', function () {
    ['cidNote' => $note] = seedRelationFilterStudents();

    expect(relationFilterNames(new RelationFilter('Notes', 'notes'), $note->id))->toBe(['Cid']);
});

it('uses the explicit relation over the field', function () {
    ['profile' => $profile] = seedRelationFilterStudents();
    $filter = RelationFilter::make('Profile', 'profile_id')->relation('profile')->relationType('hasOne');

    expect(relationFilterNames($filter, $profile->id))->toBe(['Bob']);
});

it('matches any of several related keys when multiple', function () {
    ['anaNote' => $anaNote, 'cidNote' => $cidNote] = seedRelationFilterStudents();
    $filter = RelationFilter::make('Notes', 'notes')->relationType('morphMany')->multiple();

    expect(relationFilterNames($filter, [$anaNote->id, $cidNote->id]))->toBe(['Ana', 'Cid']);
});

it('filters a belongs-to-many relation whose pivot table has its own id', function () {
    ['art' => $art] = seedRelationFilterStudents();

    expect(relationFilterNames(new RelationFilter('Courses', 'courses'), $art->id))->toBe(['Ana', 'Bob']);
})->skip('ISSUE FLT-025: whereHas() compares the unqualified getKeyName() "id", which is ambiguous once the pivot is joined');

it('filters a belongs-to-many relation with several keys', function () {
    ['math' => $math, 'music' => $music] = seedRelationFilterStudents();
    $filter = RelationFilter::make('Courses', 'courses')->relationType('belongsToMany')->multiple();

    expect(relationFilterNames($filter, [$math->id, $music->id]))->toBe(['Ana', 'Cid']);
})->skip('ISSUE FLT-025: whereHas() compares the unqualified getKeyName() "id", which is ambiguous once the pivot is joined');

it('ignores empty values', function ($value) {
    seedRelationFilterStudents();

    expect(relationFilterNames(new RelationFilter('Courses', 'courses'), $value))->toBe(['Ana', 'Bob', 'Cid', 'Dee']);
})->with(['null' => null, 'empty string' => '', 'empty array' => [[]]]);

it('does not honour applyToQuery(false), unlike its parent', function () {
    ['cidNote' => $note] = seedRelationFilterStudents();
    $filter = RelationFilter::make('Notes', 'notes')->applyToQuery(false);

    // DynamicSelectFilter would return the query untouched here (see FLT-018).
    expect(relationFilterNames($filter, $note->id))->toBe(['Cid']);
});

it('does not reduce an array to its first element when multiple is off', function () {
    ['anaNote' => $anaNote, 'cidNote' => $cidNote] = seedRelationFilterStudents();

    expect(relationFilterNames(new RelationFilter('Notes', 'notes'), [$anaNote->id, $cidNote->id]))->not->toBe(['Ana']);
})->skip('ISSUE FLT-020: a non-multiple filter passes the array to where(), which keeps only the first element');
