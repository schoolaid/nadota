<?php

use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Label;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Models\StudentProfile;
use SchoolAid\Nadota\Tests\Fixtures\Resources\CustomResponseStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\IndexScopedStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\PagedStudentResource;

/**
 * GET /{resourceKey}/resource/{id}       — ResourceShowService
 * GET /{resourceKey}/resource/{id}/edit  — ResourceEditService
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi();
    ResourceManager::registerResourceClass(PagedStudentResource::class);
    ResourceManager::registerResourceClass(IndexScopedStudentResource::class);
    ResourceManager::registerResourceClass(CustomResponseStudentResource::class);

    $this->grade = Grade::create(['name' => 'First']);
    $this->student = Student::create([
        'name' => 'Ana', 'email' => 'ana@example.com', 'grade_id' => $this->grade->id, 'age' => 10,
        'bio' => 'Long bio', 'status' => 'enrolled', 'password' => 'stored-hash-value', 'uuid' => 'u-1',
        'interests' => ['art'], 'settings' => ['theme' => 'dark'],
    ]);
    $this->student->courses()->attach(Course::create(['name' => 'Math'])->id, ['score' => 9]);
    $this->student->labels()->attach(Label::create(['name' => 'VIP'])->id);
    Note::create(['notable_type' => Student::class, 'notable_id' => $this->student->id, 'body' => 'First note']);
    StudentProfile::create(['student_id' => $this->student->id, 'nickname' => 'Annie']);
});

describe('show', function () {
    it('returns the detail envelope', function () {
        $data = $this->getJson($this->apiUrl('student', $this->student->id))->assertOk()->json('data');

        expect(array_keys($data))->toBe([
            'id', 'key', 'attributes', 'permissions', 'title', 'deletedAt', 'detailCardWidth', 'tools', 'actionEventsUrl',
        ])
            ->and($data['id'])->toBe($this->student->id)
            ->and($data['key'])->toBe('student')
            ->and($data['deletedAt'])->toBeNull()
            ->and($data['tools'])->toBe([])
            ->and($data['actionEventsUrl'])->toBe("/nadota-api/student/resource/{$this->student->id}/action-events");
    });

    it('renders the detail fields, including the ones hidden from index, with their values', function () {
        $attributes = collect($this->getJson($this->apiUrl('student', $this->student->id))->assertOk()->json('data.attributes'));
        $value = fn (string $key, string $type) => $attributes->first(fn ($f) => $f['key'] === $key && $f['type'] === $type)['value'];

        expect($attributes->pluck('type')->all())->toContain('status', 'hasOne', 'belongsToMany', 'morphMany')
            ->and($value('name', 'text'))->toBe('Ana')
            ->and($value('bio', 'textarea'))->toBe('Long bio')
            ->and($value('status', 'status'))->toBe('enrolled')
            ->and($value('interests', 'checkbox_list'))->toBe(['art'])
            ->and($value('settings', 'keyValue'))->not->toBeEmpty()
            ->and($value('grade_id', 'belongsTo'))->toMatchArray(['id' => $this->grade->id, 'label' => 'First'])
            ->and($value('courses_count', 'number'))->toBe(1)
            ->and($value('notes_exists', 'boolean'))->toBeTrue();
    });

    it('loads to-one and to-many relations into the detail payload', function () {
        $attributes = collect($this->getJson($this->apiUrl('student', $this->student->id))->assertOk()->json('data.attributes'));

        expect($attributes->firstWhere('key', 'profile')['value'])
            ->toMatchArray(['id' => StudentProfile::first()->id, 'resource' => 'student-profile'])
            ->and(json_encode($attributes->firstWhere('key', 'courses')['value']))->toContain('Math')
            ->and($attributes->firstWhere('key', 'notes')['value']['data'][0]['id'])->toBe(Note::first()->id)
            ->and($attributes->firstWhere('key', 'notes')['value']['meta']['total'])->toBe(1)
            ->and(json_encode($attributes->firstWhere('key', 'labels')['value']))->toContain('VIP');
    });

    it('never exposes the password, hidden fields or the stored hash', function () {
        $response = $this->getJson($this->apiUrl('student', $this->student->id))->assertOk();
        $keys = collect($response->json('data.attributes'))->pluck('key');

        expect($keys)->not->toContain('password', 'uuid')
            ->and($response->getContent())->not->toContain('stored-hash-value');
    });

    it('returns 404 for a missing record', function () {
        $this->getJson($this->apiUrl('student', 999))->assertNotFound();
    });

    it('returns 404 for a non-numeric id on an integer-keyed resource', function () {
        $this->getJson($this->apiUrl('student', 'abc'))->assertNotFound();
    });

    it('returns 404 for a trashed record', function () {
        $this->student->delete();

        $this->getJson($this->apiUrl('student', $this->student->id))->assertNotFound();
    });

    it('can show a trashed record so its deletedAt and restore permission reach the detail view', function () {
        $this->student->delete();

        $this->getJson($this->apiUrl('student', $this->student->id))
            ->assertOk()
            ->assertJsonPath('data.permissions.restore', true);
    })->skip('ISSUE HTTP-012: show/edit/permissions load through getQuery() without withTrashed, so deletedAt is always null and permissions.restore always false there');

    it('honours getQuery() scoping', function () {
        $inactive = Student::create(['name' => 'Inactive', 'is_active' => false, 'status' => 'enrolled']);

        $this->getJson($this->apiUrl('index-scoped-student', $inactive->id))->assertNotFound();
    });

    it('shows a record that queryIndex() leaves out of the listing', function () {
        $draft = Student::create(['name' => 'Draft', 'is_active' => true, 'status' => 'draft']);

        $this->getJson($this->apiUrl('index-scoped-student'))->assertOk()->assertJsonPath('data', []);
        $this->getJson($this->apiUrl('index-scoped-student', $draft->id))->assertOk()->assertJsonPath('data.id', $draft->id);
    });

    it('leaves the id out and sends the detail card width when the resource configures them', function () {
        $data = $this->getJson($this->apiUrl('paged-student', $this->student->id))->assertOk()->json('data');

        expect($data)->not->toHaveKey('id')
            ->and($data['detailCardWidth'])->toBe('lg')
            ->and($data['title'])->toBe('Pupils');
    });

    it('switches to the update context with ?action=update', function () {
        $data = $this->getJson($this->apiUrl('student', $this->student->id) . '?action=update')->assertOk()->json('data');
        $keys = collect($data['attributes'])->pluck('key');

        expect($data)->not->toHaveKeys(['tools', 'actionEventsUrl'])
            ->and($keys)->toContain('password', 'uuid')
            ->and($keys)->not->toContain('courses_count', 'notes_exists', 'profile');
    });

    it('returns the custom show response resource instead of the envelope', function () {
        $this->getJson($this->apiUrl('custom-response-student', $this->student->id))
            ->assertOk()
            ->assertExactJson(['data' => ['summary' => 'Ana <ana@example.com>', 'context' => 'show']]);
    });
});

describe('edit', function () {
    it('returns the edit envelope', function () {
        $data = $this->getJson($this->apiUrl('student', $this->student->id . '/edit'))->assertOk()->json('data');

        expect(array_keys($data))->toBe(['id', 'key', 'attributes', 'permissions', 'title', 'deletedAt'])
            ->and($data['id'])->toBe($this->student->id)
            ->and($data['key'])->toBe('student');
    });

    it('renders the update fields with their current values', function () {
        $attributes = collect($this->getJson($this->apiUrl('student', $this->student->id . '/edit'))->assertOk()->json('data.attributes'));

        expect($attributes->pluck('key')->all())->toBe([
            'name', 'email', 'website', 'password', 'bio', 'rich_bio', 'html_notes', 'age', 'balance',
            'birth_date', 'enrolled_at', 'class_time', 'favorite_color', 'is_active', 'accept_terms', 'status',
            'gender', 'interests', 'settings', 'skills', 'meta', 'snippet', 'template', 'uuid', 'grade_id',
            'courses', 'labels',
        ])
            ->and($attributes->firstWhere('key', 'name')['value'])->toBe('Ana')
            ->and($attributes->firstWhere('key', 'bio')['value'])->toBe('Long bio')
            ->and($attributes->firstWhere('key', 'uuid')['value'])->toBe('u-1');
    });

    it('sends the password field empty, never the stored hash', function () {
        $response = $this->getJson($this->apiUrl('student', $this->student->id . '/edit'))->assertOk();

        expect(collect($response->json('data.attributes'))->firstWhere('key', 'password')['value'])->toBeNull()
            ->and($response->getContent())->not->toContain('stored-hash-value');
    });

    it('returns 404 for a missing or trashed record', function () {
        $this->getJson($this->apiUrl('student', '999/edit'))->assertNotFound();

        $this->student->delete();
        $this->getJson($this->apiUrl('student', $this->student->id . '/edit'))->assertNotFound();
    });

    it('honours getQuery() scoping', function () {
        $inactive = Student::create(['name' => 'Inactive', 'is_active' => false, 'status' => 'enrolled']);

        $this->getJson($this->apiUrl('index-scoped-student', $inactive->id . '/edit'))->assertNotFound();
    });

    it('returns the custom edit response resource instead of the envelope', function () {
        $this->getJson($this->apiUrl('custom-response-student', $this->student->id . '/edit'))
            ->assertOk()
            ->assertJsonPath('data.summary', 'Ana <ana@example.com>');
    });
});
