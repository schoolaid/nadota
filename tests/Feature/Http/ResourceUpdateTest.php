<?php

use Illuminate\Support\Facades\Hash;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\IndexScopedStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\LifecycleStudentResource;

/**
 * PUT|PATCH /{resourceKey}/resource/{id} — ResourceUpdateService (AbstractResourcePersistService)
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi();
    ResourceManager::registerResourceClass(LifecycleStudentResource::class);
    ResourceManager::registerResourceClass(IndexScopedStudentResource::class);

    $this->student = Student::create([
        'name' => 'Ana', 'email' => 'ana@example.com', 'age' => 10, 'is_active' => true,
        'status' => 'enrolled', 'bio' => 'Original bio', 'password' => Hash::make('old-secret'),
    ]);
});

it('updates the record with PUT and PATCH alike', function (string $method) {
    $this->json($method, $this->apiUrl('student', $this->student->id), ['name' => 'Ana Maria', 'age' => 11])
        ->assertOk()
        ->assertJsonPath('message', 'Resource updated successfully')
        ->assertJsonPath('data.id', $this->student->id)
        ->assertJsonPath('data.name', 'Ana Maria');

    expect($this->student->fresh()->only(['name', 'age']))->toBe(['name' => 'Ana Maria', 'age' => 11]);
})->with(['PUT', 'PATCH']);

it('leaves the attributes that are not sent untouched', function () {
    $this->putJson($this->apiUrl('student', $this->student->id), ['name' => 'Renamed'])->assertOk();

    expect($this->student->fresh()->only(['email', 'age', 'is_active', 'status', 'bio']))->toBe([
        'email' => 'ana@example.com', 'age' => 10, 'is_active' => true, 'status' => 'enrolled', 'bio' => 'Original bio',
    ]);
});

it('clears a nullable attribute sent as null', function () {
    $this->putJson($this->apiUrl('student', $this->student->id), ['name' => 'Ana', 'email' => null, 'bio' => null])->assertOk();

    expect($this->student->fresh()->only(['email', 'bio']))->toBe(['email' => null, 'bio' => null]);
});

it('syncs a many-to-many field', function () {
    $math = Course::create(['name' => 'Math']);
    $art = Course::create(['name' => 'Art']);
    $this->student->courses()->attach($math->id);

    $this->putJson($this->apiUrl('student', $this->student->id), ['name' => 'Ana', 'courses' => [$art->id]])->assertOk();

    expect($this->student->courses()->pluck('fx_courses.id')->all())->toBe([$art->id]);
});

it('keeps the password when it is sent empty and hashes a new one', function () {
    $hash = $this->student->getAttributes()['password'];

    $this->putJson($this->apiUrl('student', $this->student->id), ['name' => 'Ana', 'password' => ''])->assertOk();
    expect($this->student->fresh()->getAttributes()['password'])->toBe($hash);

    $this->putJson($this->apiUrl('student', $this->student->id), ['name' => 'Ana', 'password' => 'new-secret'])->assertOk();
    expect(Hash::check('new-secret', $this->student->fresh()->getAttributes()['password']))->toBeTrue();
});

it('answers 422 and leaves the record unchanged when validation fails', function () {
    $this->putJson($this->apiUrl('student', $this->student->id), ['name' => '', 'email' => 'bad'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonValidationErrors(['name', 'email']);

    expect($this->student->fresh()->only(['name', 'email']))->toBe(['name' => 'Ana', 'email' => 'ana@example.com']);
});

it('requires the required fields on PUT', function () {
    $this->putJson($this->apiUrl('student', $this->student->id), ['age' => 12])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('validates only the attributes sent on PATCH', function () {
    $this->patchJson($this->apiUrl('student', $this->student->id), ['age' => 12])->assertOk();

    expect($this->student->fresh()->age)->toBe(12);
})->skip('ISSUE HTTP-010: PATCH is routed to the same handler as PUT, so required fields must be resent and a partial update is impossible');

it('replaces the :id placeholder so a unique rule ignores the record itself', function () {
    Student::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    $this->putJson($this->apiUrl('lifecycle-student', $this->student->id), ['name' => 'Ana', 'email' => 'ana@example.com'])
        ->assertOk();
    $this->putJson($this->apiUrl('lifecycle-student', $this->student->id), ['name' => 'Ana', 'email' => 'bob@example.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('ignores attributes that are not fields, including the primary key and deleted_at', function () {
    $other = Student::create(['name' => 'Other']);

    $this->putJson($this->apiUrl('student', $this->student->id), [
        'name' => 'Ana', 'id' => $other->id, 'deleted_at' => '2020-01-01 00:00:00', 'avatar' => 'injected.png',
    ])->assertOk();

    $fresh = Student::withTrashed()->find($this->student->id);

    expect($fresh->trashed())->toBeFalse()
        ->and($fresh->avatar)->toBeNull()
        ->and(Student::find($other->id)->name)->toBe('Other');
});

it('never writes a readonly field', function () {
    $this->putJson($this->apiUrl('lifecycle-student', $this->student->id), ['name' => 'Ana', 'website' => 'https://evil.example'])
        ->assertOk();

    expect($this->student->fresh()->website)->toBeNull();
});

it('never writes a disabled field', function () {
    $this->putJson($this->apiUrl('lifecycle-student', $this->student->id), ['name' => 'Ana', 'favorite_color' => 'red'])
        ->assertOk();

    expect($this->student->fresh()->favorite_color)->toBeNull();
})->skip('ISSUE HTTP-008: ProcessesFields filters readonly and computed fields but not disabled ones, so a disabled field is written');

it('returns 404 for a missing or trashed record', function () {
    $this->putJson($this->apiUrl('student', 999), ['name' => 'X'])->assertNotFound();

    $this->student->delete();
    $this->putJson($this->apiUrl('student', $this->student->id), ['name' => 'X'])->assertNotFound();
    expect(Student::withTrashed()->find($this->student->id)->name)->toBe('Ana');
});

it('honours getQuery() scoping', function () {
    $inactive = Student::create(['name' => 'Inactive', 'is_active' => false, 'status' => 'enrolled']);

    $this->putJson($this->apiUrl('index-scoped-student', $inactive->id), ['name' => 'Changed'])->assertNotFound();

    expect($inactive->fresh()->name)->toBe('Inactive');
});

it('updates a record it can show and edit even when queryIndex() leaves it out of the listing', function () {
    $draft = Student::create(['name' => 'Draft', 'is_active' => true, 'status' => 'draft']);

    $this->getJson($this->apiUrl('index-scoped-student', $draft->id . '/edit'))->assertOk();
    $this->putJson($this->apiUrl('index-scoped-student', $draft->id), ['name' => 'Changed'])->assertOk();
})->skip('ISSUE HTTP-011: update loads the record through getQuery() + queryIndex() while show/edit/destroy use getQuery() only');

it('records an update action event with the original and changed values', function () {
    $this->putJson($this->apiUrl('student', $this->student->id), ['name' => 'Renamed'])->assertOk();

    $event = ActionEvent::query()->where('model_id', $this->student->id)->where('name', 'update')->sole();

    expect($event->original['name'])->toBe('Ana')
        ->and($event->changes['name'])->toBe('Renamed');
});
