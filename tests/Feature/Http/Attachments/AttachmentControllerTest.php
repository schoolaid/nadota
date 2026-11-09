<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Label;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Policies\OptionsAwareStudentPolicy;
use SchoolAid\Nadota\Tests\Fixtures\Resources\ScopedStudentResource;

/**
 * GET  /{resourceKey}/resource/{id}/attachable/{field}
 * POST /{resourceKey}/resource/{id}/attach/{field}
 * POST /{resourceKey}/resource/{id}/detach/{field}
 * POST /{resourceKey}/resource/{id}/sync/{field}
 *
 * Service-level coverage lives in tests/ServiceIntegration/Attachment*Test.php
 * (events, hooks) and tests/Feature/AttachmentAuthorizationTest.php (policy
 * fallback through the controller). These drive the real routes.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi(array_merge($this->fixtureResources, [ScopedStudentResource::class]));

    $this->ana = Student::create(['name' => 'Ana']);
    $this->ben = Student::create(['name' => 'Ben']);
    $this->math = Course::create(['name' => 'Math', 'code' => 'MAT']);
    $this->art = Course::create(['name' => 'Art', 'code' => 'ART']);
    $this->music = Course::create(['name' => 'Music', 'code' => 'MUS']);
});

function attachmentControllerCourseIds(Student $student): array
{
    return $student->courses()->orderBy('fx_courses.id')->pluck('fx_courses.id')->all();
}

describe('attachable', function () {
    it('lists the BelongsToMany items not attached yet, with pagination meta', function () {
        $this->ana->courses()->attach($this->math->id);

        $this->getJson($this->apiUrl('student', "{$this->ana->id}/attachable/courses"))
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => [
                    ['id' => $this->art->id, 'label' => 'Art'],
                    ['id' => $this->music->id, 'label' => 'Music'],
                ],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 2, 'attached_count' => 1],
            ]);
    });

    it('searches the related resource searchable attributes and paginates', function () {
        $response = $this->getJson($this->apiUrl('student', "{$this->ana->id}/attachable/courses?search=mu&per_page=1"))
            ->assertOk();

        expect(collect($response->json('data'))->pluck('label')->all())->toBe(['Music'])
            ->and($response->json('meta.per_page'))->toBe(1);

        $page2 = $this->getJson($this->apiUrl('student', "{$this->ana->id}/attachable/courses?per_page=2&page=2"))->assertOk();

        expect(collect($page2->json('data'))->pluck('label')->all())->toBe(['Music'])
            ->and($page2->json('meta.last_page'))->toBe(2);
    });

    it('lists MorphToMany and MorphedByMany items not attached yet', function () {
        $red = Label::create(['name' => 'Red']);
        $blue = Label::create(['name' => 'Blue']);
        $this->ana->labels()->attach($red->id);

        $fromStudent = $this->getJson($this->apiUrl('student', "{$this->ana->id}/attachable/labels"))->assertOk();
        $fromLabel = $this->getJson($this->apiUrl('label', "{$red->id}/attachable/students"))->assertOk();

        expect(collect($fromStudent->json('data'))->pluck('id')->all())->toBe([$blue->id])
            ->and(collect($fromLabel->json('data'))->pluck('id')->all())->toBe([$this->ben->id]);
    });

    it('lists HasMany children of other parents or of none, with attachable_limit meta', function () {
        $first = Grade::create(['name' => 'First']);
        $second = Grade::create(['name' => 'Second']);
        $this->ana->update(['grade_id' => $first->id]);
        $this->ben->update(['grade_id' => $second->id]);
        $carla = Student::create(['name' => 'Carla']);

        $response = $this->getJson($this->apiUrl('grade', "{$first->id}/attachable/students"))
            ->assertOk()
            ->assertJsonPath('meta.attachable_limit', null);

        expect(collect($response->json('data'))->pluck('id')->all())->toBe([$this->ben->id, $carla->id])
            ->and($response->json('data.0'))->toBe(['id' => $this->ben->id, 'label' => 'Ben', 'meta' => []]);
    });

    it('lists MorphMany items not attached to this parent', function () {
        $mine = Note::create(['body' => 'Mine', 'notable_type' => Student::class, 'notable_id' => $this->ana->id]);
        $loose = Note::create(['body' => 'Loose']);

        $response = $this->getJson($this->apiUrl('student', "{$this->ana->id}/attachable/notes"))->assertOk();

        expect(collect($response->json('data'))->pluck('id')->all())->toBe([$loose->id]);
    })->skip('ISSUE OPS-001: AttachmentController::getServiceForField() references FieldType::MORPH_TO_MANY, which does not exist; every MorphMany call is a 500');

    it('falls back to the default page size for a per_page below one', function () {
        $this->getJson($this->apiUrl('student', "{$this->ana->id}/attachable/courses?per_page=-1"))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25);
    })->skip('ISSUE OPS-011: per_page=-1 is not clamped and attachable answers 500');
});

describe('attach', function () {
    it('attaches BelongsToMany items with the same pivot data for all', function () {
        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/courses"), [
            'items' => [$this->math->id, $this->art->id],
            'pivot' => ['score' => 9],
        ])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Items attached successfully',
                'attached' => [$this->math->id, $this->art->id],
                'count' => 2,
            ]);

        expect(DB::table('fx_course_student')->where('student_id', $this->ana->id)->pluck('score', 'course_id')->all())
            ->toBe([$this->math->id => 9, $this->art->id => 9]);
    });

    it('attaches BelongsToMany items with pivot data keyed by item id', function () {
        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/courses"), [
            'items' => [$this->math->id, $this->art->id],
            'pivot' => [$this->math->id => ['score' => 7], $this->art->id => ['score' => 3]],
        ])->assertOk()->assertJsonPath('count', 2);

        expect(DB::table('fx_course_student')->where('student_id', $this->ana->id)->pluck('score', 'course_id')->all())
            ->toBe([$this->math->id => 7, $this->art->id => 3]);
    });

    it('skips the items that are already attached', function () {
        $this->ana->courses()->attach($this->math->id);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/courses"), ['items' => [$this->math->id]])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'All items are already attached',
                'attached' => [],
                'already_attached' => 1,
            ]);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/courses"), ['items' => [$this->math->id, $this->art->id]])
            ->assertOk()
            ->assertJsonPath('attached', [$this->art->id]);

        expect(attachmentControllerCourseIds($this->ana))->toBe([$this->math->id, $this->art->id]);
    });

    it('answers 422 when items are missing, empty or not a list', function (array $payload) {
        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/courses"), $payload)
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'No items to attach']);
    })->with([
        'missing' => [[]],
        'empty' => [['items' => []]],
        'string' => [['items' => 'abc']],
    ]);

    it('refuses ids that do not belong to any related record', function () {
        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/courses"), ['items' => [999]])
            ->assertStatus(422);

        expect(DB::table('fx_course_student')->count())->toBe(0);
    })->skip('ISSUE OPS-005: attach inserts a pivot row for a related id that does not exist (dangling row, 200 count=1)');

    it('writes the pivot row for the parent of the URL, whatever the pivot payload says', function (array $pivot) {
        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/courses"), [
            'items' => [$this->math->id],
            'pivot' => $pivot,
        ])->assertOk();

        expect(attachmentControllerCourseIds($this->ana))->toBe([$this->math->id])
            ->and(attachmentControllerCourseIds($this->ben))->toBe([]);
    })->with([
        'flat' => [['student_id' => 2]],
        'keyed by id' => [[1 => ['student_id' => 2, 'score' => 1]]],
    ])->skip('ISSUE OPS-003: pivot data can override the pivot foreign key, attaching the item to another parent (Ben) under the check made for Ana');

    it('attaches MorphToMany and MorphedByMany items with the morph type', function () {
        $red = Label::create(['name' => 'Red']);
        $blue = Label::create(['name' => 'Blue']);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/labels"), ['items' => [$red->id]])
            ->assertOk()->assertJsonPath('attached', [$red->id]);
        $this->postJson($this->apiUrl('label', "{$blue->id}/attach/students"), ['items' => [$this->ben->id]])
            ->assertOk()->assertJsonPath('attached', [$this->ben->id]);

        expect(DB::table('fx_labelables')->orderBy('id')->get(['label_id', 'labelable_type', 'labelable_id'])->map(fn ($row) => (array) $row)->all())
            ->toBe([
                ['label_id' => $red->id, 'labelable_type' => Student::class, 'labelable_id' => $this->ana->id],
                ['label_id' => $blue->id, 'labelable_type' => Student::class, 'labelable_id' => $this->ben->id],
            ]);
    });

    it('attaches HasMany children by setting their foreign key, also moving them from another parent', function () {
        $first = Grade::create(['name' => 'First']);
        $second = Grade::create(['name' => 'Second']);
        $this->ben->update(['grade_id' => $second->id]);

        $this->postJson($this->apiUrl('grade', "{$first->id}/attach/students"), ['items' => [$this->ana->id, $this->ben->id]])
            ->assertOk()
            ->assertJsonPath('attached', [$this->ana->id, $this->ben->id]);

        expect($this->ana->fresh()->grade_id)->toBe($first->id)
            ->and($this->ben->fresh()->grade_id)->toBe($first->id);
    });

    it('attaches MorphMany children by setting the morph columns', function () {
        $note = Note::create(['body' => 'Loose']);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/notes"), ['items' => [$note->id]])
            ->assertOk()
            ->assertJsonPath('attached', [$note->id]);

        expect($note->fresh()->only('notable_type', 'notable_id'))->toBe(['notable_type' => Student::class, 'notable_id' => $this->ana->id]);
    })->skip('ISSUE OPS-001: AttachmentController::getServiceForField() references FieldType::MORPH_TO_MANY, which does not exist; every MorphMany call is a 500');

    it('enforces the attachable limit', function () {
        $this->ana->courses()->attach($this->math->id);

        $this->postJson($this->apiUrl('scoped-student', "{$this->ana->id}/attach/courses"), ['items' => [$this->art->id, $this->music->id]])
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'Attachment limit exceeded. Maximum allowed: 2',
                'current' => 1,
                'limit' => 2,
                'attempting' => 2,
            ]);

        expect(attachmentControllerCourseIds($this->ana))->toBe([$this->math->id]);
    });

    it('records an attach action event on the parent', function () {
        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/courses"), ['items' => [$this->math->id]])->assertOk();

        $event = ActionEvent::query()->sole();

        expect($event->only('name', 'model_type', 'model_id', 'user_id'))
            ->toBe(['name' => 'attach', 'model_type' => Student::class, 'model_id' => $this->ana->id, 'user_id' => 1])
            ->and($event->fields)->toBe(['relation' => 'courses'])
            ->and($event->changes)->toBe(['attached' => [$this->math->id]]);
    });
});

describe('detach', function () {
    it('detaches the BelongsToMany items and reports the affected rows', function () {
        $this->ana->courses()->attach([$this->math->id, $this->art->id]);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/detach/courses"), ['items' => [$this->math->id, $this->music->id]])
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Items detached successfully', 'detached' => 1]);

        expect(attachmentControllerCourseIds($this->ana))->toBe([$this->art->id]);
    });

    it('detaches MorphToMany items', function () {
        $red = Label::create(['name' => 'Red']);
        $this->ana->labels()->attach($red->id);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/detach/labels"), ['items' => [$red->id]])
            ->assertOk()
            ->assertJsonPath('detached', 1);

        expect($this->ana->labels()->count())->toBe(0);
    });

    it('nulls the foreign key of HasMany children of this parent only', function () {
        $first = Grade::create(['name' => 'First']);
        $second = Grade::create(['name' => 'Second']);
        $this->ana->update(['grade_id' => $first->id]);
        $this->ben->update(['grade_id' => $second->id]);

        $this->postJson($this->apiUrl('grade', "{$first->id}/detach/students"), ['items' => [$this->ana->id, $this->ben->id]])
            ->assertOk()
            ->assertJsonPath('detached', 1);

        expect($this->ana->fresh()->grade_id)->toBeNull()
            ->and($this->ben->fresh()->grade_id)->toBe($second->id);
    });

    it('answers 422 when there is nothing to detach', function () {
        $this->postJson($this->apiUrl('student', "{$this->ana->id}/detach/courses"), ['items' => []])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'No items to detach']);
    });

    it('records a detach action event with only the ids that were attached', function () {
        $this->ana->courses()->attach($this->math->id);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/detach/courses"), ['items' => [$this->math->id, $this->art->id]])->assertOk();

        expect(ActionEvent::query()->sole()->only('name', 'changes'))
            ->toBe(['name' => 'detach', 'changes' => ['detached' => [$this->math->id]]]);
    });
});

describe('sync', function () {
    it('replaces the BelongsToMany items and reports what changed', function () {
        $this->ana->courses()->attach([$this->math->id => ['score' => 1], $this->art->id => ['score' => 2]]);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/sync/courses"), [
            'items' => [$this->art->id, $this->music->id],
            'pivot' => [$this->art->id => ['score' => 8], $this->music->id => ['score' => 5]],
        ])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Items synced successfully',
                'attached' => [$this->music->id],
                'detached' => [$this->math->id],
                'updated' => [$this->art->id],
            ]);

        expect(DB::table('fx_course_student')->where('student_id', $this->ana->id)->pluck('score', 'course_id')->all())
            ->toBe([$this->art->id => 8, $this->music->id => 5]);
    });

    it('keeps the current items when detaching is false', function () {
        $this->ana->courses()->attach($this->math->id);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/sync/courses"), ['items' => [$this->art->id], 'detaching' => false])
            ->assertOk()
            ->assertJsonPath('detached', []);

        expect(attachmentControllerCourseIds($this->ana))->toBe([$this->math->id, $this->art->id]);
    });

    it('detaches everything for an empty items list', function () {
        $this->ana->courses()->attach($this->math->id);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/sync/courses"), ['items' => []])
            ->assertOk()
            ->assertJsonPath('detached', [$this->math->id]);

        expect(attachmentControllerCourseIds($this->ana))->toBe([]);
    });

    it('syncs MorphToMany items', function () {
        $red = Label::create(['name' => 'Red']);
        $blue = Label::create(['name' => 'Blue']);
        $this->ana->labels()->attach($red->id);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/sync/labels"), ['items' => [$blue->id]])
            ->assertOk()
            ->assertJsonPath('attached', [$blue->id])
            ->assertJsonPath('detached', [$red->id]);
    });

    it('answers 422 for items that are not a list', function () {
        $this->postJson($this->apiUrl('student', "{$this->ana->id}/sync/courses"), ['items' => 'abc'])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'Invalid items format']);
    });

    it('answers 422 for relation types without sync', function () {
        $grade = Grade::create(['name' => 'First']);

        $this->postJson($this->apiUrl('grade', "{$grade->id}/sync/students"), ['items' => [$this->ana->id]])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'Sync operation not supported for this relation type']);
    });

    it('enforces the attachable limit on the synced set', function () {
        $this->postJson($this->apiUrl('scoped-student', "{$this->ana->id}/sync/courses"), [
            'items' => [$this->math->id, $this->art->id, $this->music->id],
        ])
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'Attachment limit exceeded. Maximum allowed: 2',
                'limit' => 2,
                'attempting' => 3,
            ]);
    });

    it('cannot write pivot rows for another parent through the pivot payload', function () {
        $this->postJson($this->apiUrl('student', "{$this->ana->id}/sync/courses"), [
            'items' => [$this->math->id],
            'pivot' => [$this->math->id => ['student_id' => $this->ben->id]],
        ])->assertOk();

        expect(attachmentControllerCourseIds($this->ana))->toBe([$this->math->id])
            ->and(attachmentControllerCourseIds($this->ben))->toBe([]);
    })->skip('ISSUE OPS-003: sync passes the pivot payload unfiltered, so it can override the pivot foreign key');

    it('records a sync action event with the changes and the ids attached before', function () {
        $this->ana->courses()->attach($this->math->id);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/sync/courses"), ['items' => [$this->art->id]])->assertOk();

        $event = ActionEvent::query()->sole();

        expect($event->name)->toBe('sync')
            ->and($event->original)->toBe(['attached_before' => [$this->math->id]])
            ->and($event->changes)->toBe(['attached' => [$this->art->id], 'detached' => [$this->math->id], 'updated' => []]);
    });
});

describe('errors', function () {
    it('answers 404 for an unknown field', function (string $method, string $operation) {
        $this->json($method, $this->apiUrl('student', "{$this->ana->id}/{$operation}/nope"), ['items' => [1]])
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Field not found']);
    })->with([
        ['GET', 'attachable'],
        ['POST', 'attach'],
        ['POST', 'detach'],
        ['POST', 'sync'],
    ]);

    it('answers 404 for an unknown parent record', function (string $method, string $operation) {
        $this->json($method, $this->apiUrl('student', "999/{$operation}/courses"), ['items' => [1]])->assertNotFound();
    })->with([
        ['GET', 'attachable'],
        ['POST', 'attach'],
        ['POST', 'detach'],
        ['POST', 'sync'],
    ]);

    it('answers 404 for a parent the resource getQuery() hides', function (string $method, string $operation) {
        $archived = Student::create(['name' => 'Archived', 'status' => 'archived']);

        $this->json($method, $this->apiUrl('scoped-student', "{$archived->id}/{$operation}/courses"), ['items' => [$this->math->id]])
            ->assertNotFound();

        expect(attachmentControllerCourseIds($archived))->toBe([]);
    })->with([
        ['GET', 'attachable'],
        ['POST', 'attach'],
        ['POST', 'sync'],
    ]);

    it('answers 422 for a relation field marked as not attachable', function () {
        $this->postJson($this->apiUrl('scoped-student', "{$this->ana->id}/attach/notes"), ['items' => [1]])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'Field is not attachable']);
    });

    it('answers 422, not 500, for a field that cannot be attached at all', function (string $field) {
        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/{$field}"), ['items' => [1]])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    })->with(['grade', 'profile', 'name'])
        ->skip('ISSUE OPS-002: BelongsTo, HasOne and scalar fields have no isAttachable(), so the controller throws (500)');
});

describe('authorization', function () {
    beforeEach(function () {
        $this->useRealAuthorization();
        Gate::policy(Student::class, OptionsAwareStudentPolicy::class);
    });

    it('answers 403 and attaches nothing when the policy denies attach', function (string $method, string $operation) {
        config(['nadota-tests.ops_denied' => ['attach']]);

        $this->json($method, $this->apiUrl('student', "{$this->ana->id}/{$operation}/courses"), ['items' => [$this->math->id]])
            ->assertForbidden()
            ->assertExactJson(['success' => false, 'message' => 'Unauthorized']);

        expect(attachmentControllerCourseIds($this->ana))->toBe([]);
    })->with([
        ['GET', 'attachable'],
        ['POST', 'attach'],
        ['POST', 'sync'],
    ]);

    it('answers 403 and keeps the items when the policy denies detach', function () {
        $this->ana->courses()->attach($this->math->id);
        config(['nadota-tests.ops_denied' => ['detach']]);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/detach/courses"), ['items' => [$this->math->id]])
            ->assertForbidden();

        expect(attachmentControllerCourseIds($this->ana))->toBe([$this->math->id]);
    });

    it('lets the policy allow attach while denying detach', function () {
        config(['nadota-tests.ops_denied' => ['detach']]);

        $this->postJson($this->apiUrl('student', "{$this->ana->id}/attach/courses"), ['items' => [$this->math->id]])->assertOk();

        expect(attachmentControllerCourseIds($this->ana))->toBe([$this->math->id]);
    });
});
