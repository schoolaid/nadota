<?php

use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\CourseResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\NoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

/**
 * Query-level behaviour of applyInlineConstraints(). End-to-end rendering
 * (hasMore, eager vs lazy, trimming) lives in
 * tests/ServiceIntegration/RelationInlineLimitTest.php.
 */

beforeEach(fn () => FixtureSchema::create());

function inlineConstrainedSql($field, $relation): string
{
    $field->applyInlineConstraints($relation);

    return $relation->toSql();
}

it('leaves the query alone without limit or orderBy', function () {
    $student = new Student();
    $plain = $student->notes()->toSql();

    expect(inlineConstrainedSql(MorphMany::make('Notes', 'notes', NoteResource::class), $student->notes()))->toBe($plain);
});

it('asks for one row past the limit', function () {
    $sql = inlineConstrainedSql(MorphMany::make('Notes', 'notes', NoteResource::class)->limit(3), (new Student())->notes());

    // On a relation query Laravel turns limit() into a per-parent window
    expect($sql)->toContain('"laravel_row" <= 4');
});

it('qualifies a bare orderBy column with the related table', function () {
    $sql = inlineConstrainedSql(
        BelongsToMany::make('Courses', 'courses', CourseResource::class)->orderBy('created_at', 'asc'),
        (new Student())->courses(),
    );

    expect($sql)->toContain('order by "fx_courses"."created_at" asc');
});

it('keeps an already qualified orderBy column as given', function () {
    $sql = inlineConstrainedSql(
        BelongsToMany::make('Courses', 'courses', CourseResource::class)->orderBy('fx_course_student.score'),
        (new Student())->courses(),
    );

    expect($sql)->toContain('order by "fx_course_student"."score" desc');
});

it('defaults the order direction to desc', function () {
    $sql = inlineConstrainedSql(
        HasMany::make('Notes', 'notes', NoteResource::class)->orderBy('body'),
        (new Student())->notes(),
    );

    expect($sql)->toContain('order by "fx_notes"."body" desc');
});

it('does nothing for a paginated field', function () {
    $student = new Student();
    $plain = $student->courses()->toSql();

    $field = BelongsToMany::make('Courses', 'courses', CourseResource::class)->limit(2)->orderBy('name')->paginated();

    expect(inlineConstrainedSql($field, $student->courses()))->toBe($plain);
});

it('orders the inline items of a pivot relation by a pivot column', function () {
    $student = Student::create(['name' => 'Ana']);
    foreach (['Math' => 70, 'Art' => 95, 'Music' => 80] as $name => $score) {
        $student->courses()->attach(Course::create(['name' => $name])->id, ['score' => $score]);
    }

    $value = BelongsToMany::make('Courses', 'courses', CourseResource::class)
        ->orderBy('fx_course_student.score', 'desc')
        ->limit(2)
        ->resolve(createNadotaRequest(), $student, null);

    expect(array_column($value['data'], 'label'))->toBe(['Art', 'Music'])
        ->and($value['meta']['hasMore'])->toBeTrue();
});
