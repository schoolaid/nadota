<?php

use Illuminate\Support\Facades\Hash;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\LifecycleStudentResource;

/**
 * GET  /{resourceKey}/resource/create — ResourceCreateService
 * POST /{resourceKey}/resource        — ResourceStoreService (AbstractResourcePersistService)
 *
 * Per-field round trips live with the field tests; this file covers the
 * store flow itself: validation, attribute filtering, defaults, response.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi();
    ResourceManager::registerResourceClass(LifecycleStudentResource::class);
});

describe('create form', function () {
    it('returns the create envelope with the creation fields and no values', function () {
        $data = $this->getJson($this->apiUrl('student', 'create'))->assertOk()->json('data');

        expect(array_keys($data))->toBe(['key', 'attributes', 'title'])
            ->and($data['key'])->toBe('student')
            ->and(collect($data['attributes'])->pluck('key')->all())->toBe([
                'name', 'email', 'website', 'password', 'bio', 'rich_bio', 'html_notes', 'age', 'balance',
                'birth_date', 'enrolled_at', 'class_time', 'favorite_color', 'is_active', 'accept_terms', 'status',
                'gender', 'interests', 'settings', 'skills', 'meta', 'snippet', 'template', 'uuid', 'grade_id',
                'courses', 'labels',
            ])
            ->and(collect($data['attributes'])->every(fn ($field) => ! array_key_exists('value', $field)))->toBeTrue();
    });

    it('leaves detail-only, computed and to-one inverse relation fields out of the form', function () {
        $keys = collect($this->getJson($this->apiUrl('student', 'create'))->assertOk()->json('data.attributes'))
            ->map(fn ($field) => $field['key'] . ':' . $field['type']);

        expect($keys)->not->toContain('status:status', 'courses_count:number', 'notes_exists:boolean', 'profile:hasOne', 'notes:morphMany');
    });

    it('sends each field default and its creation rules', function () {
        $attributes = collect($this->getJson($this->apiUrl('lifecycle-student', 'create'))->assertOk()->json('data.attributes'));

        expect($attributes->firstWhere('key', 'age')['default'])->toBe(7)
            ->and($attributes->firstWhere('key', 'status')['default'])->toBe('draft')
            ->and($attributes->firstWhere('key', 'name')['required'])->toBeTrue()
            ->and($attributes->firstWhere('key', 'website')['readonly'])->toBeTrue()
            ->and($attributes->firstWhere('key', 'favorite_color')['disabled'])->toBeTrue();
    });
});

describe('store', function () {
    it('creates the record and answers 201 with the saved model', function () {
        $response = $this->postJson($this->apiUrl('student'), ['name' => 'Ana', 'password' => 'secret'])
            ->assertCreated()
            ->assertJsonPath('message', 'Resource created successfully')
            ->assertJsonPath('data.name', 'Ana');

        expect(Student::find($response->json('data.id'))->name)->toBe('Ana');
    });

    it('persists a payload spanning many field types and relations', function () {
        $grade = Grade::create(['name' => 'First']);
        $math = Course::create(['name' => 'Math']);
        $art = Course::create(['name' => 'Art']);

        $id = $this->postJson($this->apiUrl('student'), [
            'name' => 'Ana', 'email' => 'ana@example.com', 'website' => 'https://ana.example', 'password' => 'secret',
            'bio' => 'Bio', 'age' => 10, 'balance' => '12.50', 'birth_date' => '2015-03-04',
            'enrolled_at' => '2024-01-02 03:04:05', 'is_active' => true, 'accept_terms' => true,
            'status' => 'enrolled', 'gender' => 'f', 'interests' => ['art', 'math'], 'skills' => ['php'],
            'grade_id' => $grade->id, 'courses' => [$math->id, $art->id],
        ])->assertCreated()->json('data.id');

        $student = Student::find($id);

        expect($student->only(['name', 'email', 'website', 'bio', 'age', 'status', 'gender', 'grade_id']))->toBe([
            'name' => 'Ana', 'email' => 'ana@example.com', 'website' => 'https://ana.example', 'bio' => 'Bio',
            'age' => 10, 'status' => 'enrolled', 'gender' => 'f', 'grade_id' => $grade->id,
        ])
            ->and($student->balance)->toBe('12.50')
            ->and($student->birth_date->toDateString())->toBe('2015-03-04')
            ->and($student->is_active)->toBeTrue()
            ->and($student->accept_terms)->toBeTrue()
            ->and($student->interests)->toBe(['art', 'math'])
            ->and($student->courses()->pluck('fx_courses.id')->sort()->values()->all())->toBe([$math->id, $art->id]);
    });

    it('stores the password hashed and never returns it', function () {
        $response = $this->postJson($this->apiUrl('student'), ['name' => 'Ana', 'password' => 'secret'])->assertCreated();

        $stored = Student::find($response->json('data.id'))->getAttributes()['password'];

        expect(Hash::check('secret', $stored))->toBeTrue()
            ->and($response->json('data'))->not->toHaveKey('password')
            ->and($response->getContent())->not->toContain($stored);
    });

    it('answers 422 with the message of every failing rule', function () {
        $this->postJson($this->apiUrl('student'), ['email' => 'not-an-email', 'age' => -1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('errors.name.0', 'The name field is required.')
            ->assertJsonPath('errors.email.0', 'The email field must be a valid email address.')
            ->assertJsonPath('errors.age.0', 'The age field must be at least 0.')
            ->assertJsonPath('errors.password.0', 'The password field is required.');

        expect(Student::count())->toBe(0);
    });

    it('applies the type rules a field adds on its own', function () {
        $this->postJson($this->apiUrl('student'), ['name' => 'Ana', 'password' => 'x', 'age' => 'ten'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['age']);
    });

    it('ignores attributes that are not fields of the resource', function () {
        $existing = Student::create(['name' => 'Existing']);

        $response = $this->postJson($this->apiUrl('student'), [
            'name' => 'Ana', 'password' => 'secret',
            'id' => $existing->id, 'deleted_at' => '2020-01-01 00:00:00', 'created_at' => '2000-01-01 00:00:00',
            'avatar' => 'injected.png', 'signature' => 'injected', 'column_that_does_not_exist' => 'x',
        ])->assertCreated();

        $student = Student::withTrashed()->find($response->json('data.id'));

        expect($student->id)->not->toBe($existing->id)
            ->and($student->deleted_at)->toBeNull()
            ->and($student->created_at->year)->not->toBe(2000)
            ->and($student->avatar)->toBeNull()
            ->and($student->signature)->toBeNull()
            ->and(Student::find($existing->id)->name)->toBe('Existing');
    });

    it('never writes a readonly field', function () {
        $id = $this->postJson($this->apiUrl('lifecycle-student'), ['name' => 'Ana', 'website' => 'https://evil.example'])
            ->assertCreated()->json('data.id');

        expect(Student::find($id)->website)->toBeNull();
    });

    it('never writes a disabled field', function () {
        $id = $this->postJson($this->apiUrl('lifecycle-student'), ['name' => 'Ana', 'favorite_color' => 'red'])
            ->assertCreated()->json('data.id');

        expect(Student::find($id)->favorite_color)->toBeNull();
    })->skip('ISSUE HTTP-008: ProcessesFields filters readonly and computed fields but not disabled ones, so a disabled field is written');

    it('applies the field default when the attribute is sent as null', function () {
        $id = $this->postJson($this->apiUrl('lifecycle-student'), ['name' => 'Ana', 'status' => null])
            ->assertCreated()->json('data.id');

        expect(Student::find($id)->status)->toBe('draft');
    });

    it('applies the field default when the attribute is left out', function () {
        $id = $this->postJson($this->apiUrl('lifecycle-student'), ['name' => 'Ana'])->assertCreated()->json('data.id');

        expect(Student::find($id)->only(['status', 'age']))->toBe(['status' => 'draft', 'age' => 7]);
    })->skip('ISSUE HTTP-009: defaults are only resolved for attributes present in the request; DefaultValueHandler, which would cover omitted ones, is never called');

    it('keeps an explicit falsy value instead of replacing it with the default', function () {
        $id = $this->postJson($this->apiUrl('lifecycle-student'), ['name' => 'Ana', 'age' => 0])->assertCreated()->json('data.id');

        expect(Student::find($id)->age)->toBe(0);
    })->skip('ISSUE HTTP-009: resolveForStore() tests $value == null, so 0, "0", "" and false are replaced by the default');

    it('validates a unique rule against the existing rows', function () {
        Student::create(['name' => 'Taken', 'email' => 'taken@example.com']);

        $this->postJson($this->apiUrl('lifecycle-student'), ['name' => 'Ana', 'email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('records a create action event for the new record', function () {
        $id = $this->postJson($this->apiUrl('student'), ['name' => 'Ana', 'password' => 'secret'])->assertCreated()->json('data.id');

        $event = ActionEvent::query()->where('model_type', Student::class)->where('model_id', $id)->sole();

        expect($event->name)->toBe('create')
            ->and($event->status)->toBe('finished')
            ->and(json_encode($event->fields))->not->toContain('secret');
    });

    it('does not record an action event when validation fails', function () {
        $this->postJson($this->apiUrl('student'), [])->assertStatus(422);

        expect(ActionEvent::count())->toBe(0);
    });

    it('answers with the saved fields serialized like show, not the raw model', function () {
        $this->postJson($this->apiUrl('student'), ['name' => 'Ana', 'password' => 'secret'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'key', 'attributes']]);
    })->skip('ISSUE HTTP-013: store/update/restore return the raw Eloquent model (every non-$hidden column) instead of the Nadota envelope');
});
