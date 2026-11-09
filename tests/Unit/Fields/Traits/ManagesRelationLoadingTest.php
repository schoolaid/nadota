<?php

use SchoolAid\Nadota\Http\Fields\Count;
use SchoolAid\Nadota\Http\Fields\Exists;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Fields\Section;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\CourseResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\NoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

beforeEach(fn () => FixtureSchema::create());

function relationLoadingStudent(): Student
{
    $grade = Grade::create(['name' => 'First', 'level' => 1]);
    $student = Student::create(['name' => 'Ana', 'grade_id' => $grade->id]);
    $student->courses()->attach(Course::create(['name' => 'Math', 'credits' => 3])->id, ['score' => 90]);
    $student->notes()->create(['body' => 'pinned note', 'pinned' => true]);
    $student->notes()->create(['body' => 'plain note', 'pinned' => false]);

    return $student;
}

describe('getEagerLoadRelations()', function () {
    it('keys a constraint per relation field by its relation name', function () {
        $relations = (new StudentResource())->getEagerLoadRelations(createNadotaRequest());

        expect(array_keys($relations))->toBe(['grade', 'profile', 'courses', 'notes', 'latestNote', 'labels'])
            ->and($relations)->each->toBeInstanceOf(Closure::class);
    });

    it('includes relation fields declared inside sections', function () {
        $resource = AdHocResource::withFields([
            Section::make('School', [BelongsTo::make('Grade', 'grade', GradeResource::class)]),
        ]);

        expect(array_keys($resource->getEagerLoadRelations(createNadotaRequest())))->toBe(['grade']);
    });

    it('leaves paginated relation fields to the pagination endpoint', function () {
        $resource = AdHocResource::withFields([
            MorphMany::make('Notes', 'notes', NoteResource::class)->paginated(),
            BelongsToMany::make('Courses', 'courses', CourseResource::class),
        ]);

        expect(array_keys($resource->getEagerLoadRelations(createNadotaRequest())))->toBe(['courses']);
    });

    it('leaves out relation fields not applied in the show query when it builds the list itself', function () {
        $notInShow = new class('Grade', 'grade', GradeResource::class) extends BelongsTo {
            protected bool $applyInShowQuery = false;
        };

        $resource = AdHocResource::withFields([$notInShow]);

        expect($resource->getEagerLoadRelations(createNadotaRequest()))->toBe([])
            ->and(array_keys($resource->getEagerLoadRelations(createNadotaRequest(), collect([$notInShow]))))->toBe(['grade']);
    });

    it('selects only the related resource columns, qualified with the related table', function () {
        $student = relationLoadingStudent();
        $relations = AdHocResource::withFields([BelongsTo::make('Grade', 'grade', GradeResource::class)])
            ->getEagerLoadRelations(createNadotaRequest());

        $loaded = Student::with($relations)->find($student->id);

        expect(array_keys($loaded->grade->getAttributes()))->toEqualCanonicalizing(['name', 'level', 'school_id', 'id']);
    });

    it('selects every column when the relation field has no resource', function () {
        $student = relationLoadingStudent();
        $relations = AdHocResource::withFields([BelongsTo::make('Grade', 'grade')])
            ->getEagerLoadRelations(createNadotaRequest());

        $loaded = Student::with($relations)->find($student->id);

        expect($loaded->grade->getAttributes())->toHaveKey('created_at');
    });

    it('loads the configured pivot columns of a pivot relation', function () {
        $student = relationLoadingStudent();
        $relations = AdHocResource::withFields([
            BelongsToMany::make('Courses', 'courses', CourseResource::class)->withPivot(['score']),
        ])->getEagerLoadRelations(createNadotaRequest());

        $course = Student::with($relations)->find($student->id)->courses->first();

        expect($course->pivot->score)->toBe(90)
            // id is qualified as fx_courses.id, so the pivot's own id does not clash
            ->and($course->id)->toBe(Course::first()->id);
    });

    it('applies a to-many field limit and order to the eager load', function () {
        $student = relationLoadingStudent();
        $relations = AdHocResource::withFields([
            MorphMany::make('Notes', 'notes', NoteResource::class)->limit(1)->orderBy('body', 'asc'),
        ])->getEagerLoadRelations(createNadotaRequest());

        $notes = Student::with($relations)->find($student->id)->notes;

        // limit + 1 so the field can tell "exactly limit rows" from "more"
        expect($notes->pluck('body')->all())->toBe(['pinned note', 'plain note']);
    });
});

describe('getWithCountRelations() / getWithExistsRelations()', function () {
    it('lists the relations of Count and Exists fields', function () {
        $resource = new StudentResource();
        $request = createNadotaRequest();

        expect($resource->getWithCountRelations($request))->toBe(['courses'])
            ->and($resource->getWithExistsRelations($request))->toBe(['notes']);
    });

    it('lists nothing for resources without Count or Exists fields', function () {
        $resource = AdHocResource::withFields([Input::make('Name', 'name')]);

        expect($resource->getWithCountRelations(createNadotaRequest()))->toBe([])
            ->and($resource->getWithExistsRelations(createNadotaRequest()))->toBe([]);
    });

    it('produces a list Eloquent can count and check with', function () {
        $student = relationLoadingStudent();
        $resource = new StudentResource();
        $request = createNadotaRequest();

        $loaded = Student::query()
            ->withCount($resource->getWithCountRelations($request))
            ->withExists($resource->getWithExistsRelations($request))
            ->find($student->id);

        expect($loaded->courses_count)->toBe(1)
            ->and((bool) $loaded->notes_exists)->toBeTrue();
    });

    it('produces a constrained count Eloquent accepts', function () {
        $student = relationLoadingStudent();
        $resource = AdHocResource::withFields([
            Count::make('Pinned notes', 'notes')->constraint(fn ($query) => $query->where('pinned', true)),
        ]);

        $loaded = Student::query()
            ->withCount($resource->getWithCountRelations(createNadotaRequest()))
            ->find($student->id);

        expect($loaded->notes_count)->toBe(1);
    })->skip('ISSUE TRT-002: constrained Count is returned as [[relation => closure]], withCount/withExists throw "Method name must be a string"');

    it('produces a constrained exists check Eloquent accepts', function () {
        $student = relationLoadingStudent();
        $resource = AdHocResource::withFields([
            Exists::make('Has pinned notes', 'notes')->constraint(fn ($query) => $query->where('pinned', true)),
        ]);

        $loaded = Student::query()
            ->withExists($resource->getWithExistsRelations(createNadotaRequest()))
            ->find($student->id);

        expect((bool) $loaded->notes_exists)->toBeTrue();
    })->skip('ISSUE TRT-002: constrained Exists is returned as [[relation => closure]], withCount/withExists throw "Method name must be a string"');
});
