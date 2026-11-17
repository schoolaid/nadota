<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelCourseResource;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelGradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelStudentResource;

/*
 * The records a relation field can attach come from the related resource's getQuery() first, the
 * place apps scope rows (tenant, ownership). A record that getQuery() hides is neither listed as
 * attachable nor accepted by attach. Root cause 1 of docs/testing/ISSUES.md (same family as OPS-008).
 */

uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi([...$this->fixtureResources, RelGradeResource::class, RelStudentResource::class, RelCourseResource::class]);
});

afterEach(function () {
    RelGradeResource::reset();
    RelStudentResource::reset();
    RelCourseResource::reset();
});

describe('HasMany (owned children)', function () {
    beforeEach(function () {
        RelGradeResource::using(fn () => [
            Input::make('Name', 'name'),
            HasMany::make('Students', 'students', RelStudentResource::class)->attachable(),
        ]);
        RelStudentResource::scopeQueryUsing(fn ($query) => $query->where('is_active', true));

        $this->grade = Grade::create(['name' => 'First']);
        $this->visible = Student::create(['name' => 'Visible', 'is_active' => true]);
        $this->hidden = Student::create(['name' => 'Hidden', 'is_active' => false]);
    });

    it('lists only the children the related resource getQuery() lets through', function () {
        $ids = collect($this->getJson($this->apiUrl('rel-grade', "{$this->grade->id}/attachable/students"))->assertOk()->json('data'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        expect($ids)->toBe([$this->visible->id]);
    });

    it('refuses to attach a child the related resource getQuery() hides and writes nothing', function () {
        $this->postJson($this->apiUrl('rel-grade', "{$this->grade->id}/attach/students"), ['items' => [$this->hidden->id]])
            ->assertUnprocessable();

        expect($this->hidden->fresh()->grade_id)->toBeNull();
    });
});

describe('BelongsToMany (pivot)', function () {
    beforeEach(function () {
        RelStudentResource::using(fn () => [
            Input::make('Name', 'name'),
            BelongsToMany::make('Courses', 'courses', RelCourseResource::class),
        ]);
        RelCourseResource::scopeQueryUsing(fn ($query) => $query->where('credits', '>', 0));

        $this->student = Student::create(['name' => 'Ana']);
        $this->open = Course::create(['name' => 'Open', 'code' => 'OPN', 'credits' => 3]);
        $this->closed = Course::create(['name' => 'Closed', 'code' => 'CLS', 'credits' => 0]);
    });

    it('lists only the records the related resource getQuery() lets through', function () {
        $ids = collect($this->getJson($this->apiUrl('rel-student', "{$this->student->id}/attachable/courses"))->assertOk()->json('data'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        expect($ids)->toBe([$this->open->id]);
    });

    it('refuses to attach a record the related resource getQuery() hides and writes nothing', function () {
        $this->postJson($this->apiUrl('rel-student', "{$this->student->id}/attach/courses"), ['items' => [$this->closed->id]])
            ->assertUnprocessable();

        expect($this->student->courses()->count())->toBe(0);
    });
});
