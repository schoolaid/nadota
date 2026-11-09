<?php

use Illuminate\Support\Facades\Hash;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Composite: one Student driven through every CRUD route of the API, in the
 * order a frontend would call them, checking that each step sees what the
 * previous one left.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(fn () => $this->bootNadotaApi());

it('takes a student through create, store, index, show, edit, update, destroy, restore and force delete', function () {
    $grade = Grade::create(['name' => 'First Grade']);
    $math = Course::create(['name' => 'Math']);
    $value = fn (array $attributes, string $key) => collect($attributes)->firstWhere('key', $key)['value'] ?? null;

    // 1. The create form describes the fields to fill.
    $createKeys = collect($this->getJson($this->apiUrl('student', 'create'))->assertOk()->json('data.attributes'))->pluck('key');
    expect($createKeys)->toContain('name', 'email', 'password', 'grade_id', 'courses');

    // 2. Store.
    $id = $this->postJson($this->apiUrl('student'), [
        'name' => 'Ana Lopez', 'email' => 'ana@example.com', 'password' => 'secret', 'age' => 10,
        'status' => 'enrolled', 'is_active' => true, 'grade_id' => $grade->id, 'courses' => [$math->id],
    ])->assertCreated()->json('data.id');

    // 3. The index lists it, and search finds it through the relation path.
    $index = $this->getJson($this->apiUrl('student'))->assertOk();
    expect(collect($index->json('data'))->pluck('id')->all())->toBe([$id]);
    $this->getJson($this->apiUrl('student') . '?globalSearch=First')->assertOk()->assertJsonPath('data.0.id', $id);

    // 4. Show renders the stored values and the relations.
    $show = $this->getJson($this->apiUrl('student', $id))->assertOk()->json('data');
    expect($value($show['attributes'], 'name'))->toBe('Ana Lopez')
        ->and($value($show['attributes'], 'grade_id'))->toMatchArray(['id' => $grade->id, 'label' => 'First Grade'])
        ->and(json_encode($value($show['attributes'], 'courses')))->toContain('Math')
        ->and($value($show['attributes'], 'courses_count'))->toBe(1)
        ->and($show['permissions']['delete'])->toBeTrue();

    // 5. Edit pre-fills the form, never with the password.
    $edit = $this->getJson($this->apiUrl('student', $id . '/edit'))->assertOk()->json('data.attributes');
    expect($value($edit, 'email'))->toBe('ana@example.com')
        ->and($value($edit, 'password'))->toBeNull();

    // 6. Update.
    $this->putJson($this->apiUrl('student', $id), ['name' => 'Ana Maria Lopez', 'age' => 11, 'courses' => []])->assertOk();
    $student = Student::find($id);
    expect($student->only(['name', 'age', 'email']))->toBe(['name' => 'Ana Maria Lopez', 'age' => 11, 'email' => 'ana@example.com'])
        ->and(Hash::check('secret', $student->getAttributes()['password']))->toBeTrue()
        ->and($student->courses()->count())->toBe(0);

    // 7. Destroy (soft): gone from the default index, present in the trashed view.
    $this->deleteJson($this->apiUrl('student', $id))->assertOk();
    $this->getJson($this->apiUrl('student'))->assertOk()->assertJsonPath('data', []);
    $trashed = $this->getJson($this->apiUrl('student') . '?withTrashed=only')->assertOk()->json('data.0');
    expect($trashed['id'])->toBe($id)
        ->and($trashed['deletedAt'])->not->toBeNull()
        ->and($trashed['permissions']['restore'])->toBeTrue();
    $this->getJson($this->apiUrl('student', $id))->assertNotFound();

    // 8. Restore.
    $this->postJson($this->apiUrl('student', $id . '/restore'))->assertOk();
    $this->getJson($this->apiUrl('student', $id))->assertOk()->assertJsonPath('data.deletedAt', null);

    // 9. Force delete.
    $this->deleteJson($this->apiUrl('student', $id . '/force'))->assertOk();
    $this->getJson($this->apiUrl('student') . '?withTrashed=with')->assertOk()->assertJsonPath('data', []);
    expect(Student::withTrashed()->find($id))->toBeNull();

    // Every write left an audit entry, in order.
    expect(ActionEvent::query()->where('model_type', Student::class)->where('model_id', $id)->orderBy('id')->pluck('name')->all())
        ->toBe(['create', 'update', 'delete', 'restore', 'forceDelete']);
});
