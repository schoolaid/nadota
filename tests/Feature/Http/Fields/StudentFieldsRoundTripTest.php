<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/*
 * Every scalar field of StudentResource, stored with POST, read back with GET
 * show / edit / index and changed with PUT, asserting both the raw database
 * value and what the API renders.
 */

uses(InteractsWithNadotaApi::class);

beforeEach(fn () => $this->bootNadotaApi());

/** The serialized field with $key (and $type, for keys used twice) from a show/edit/create response. */
function roundTripAttribute(TestResponse $response, string $key, ?string $type = null): ?array
{
    return collect($response->json('data.attributes'))
        ->first(fn (array $field) => $field['key'] === $key && ($type === null || $field['type'] === $type));
}

/** A valid store payload: StudentResource requires a name, and a password on creation. */
function roundTripStorePayload(array $extra = []): array
{
    return array_merge(['name' => 'Base Student', 'password' => 'secret-123'], $extra);
}

function roundTripRawColumn(int $id, string $column): mixed
{
    return DB::table('fx_students')->where('id', $id)->value($column);
}

/**
 * attribute => [submitted value, raw database value, value rendered by the API, view it is rendered on]
 * The view is "edit" for fields hidden from the detail view.
 */
dataset('student scalar fields', [
    'Input' => ['name', 'Ana López', 'Ana López', 'Ana López', 'show'],
    'Email' => ['email', 'ana@school.test', 'ana@school.test', 'ana@school.test', 'show'],
    'URL' => ['website', 'https://ana.school.test', 'https://ana.school.test', 'https://ana.school.test', 'show'],
    'Textarea' => ['bio', "Line one\nLine two", "Line one\nLine two", "Line one\nLine two", 'show'],
    'RichText' => ['rich_bio', '<p><strong>Rich</strong> bio</p>', '<p><strong>Rich</strong> bio</p>', '<p><strong>Rich</strong> bio</p>', 'show'],
    'Html' => ['html_notes', '<ul><li>note</li></ul>', '<ul><li>note</li></ul>', '<ul><li>note</li></ul>', 'show'],
    'Number' => ['age', 12, 12, 12, 'show'],
    'Currency' => ['balance', '1234.5', 1234.5, '1234.50', 'show'],
    'Date' => ['birth_date', '2014-03-05', '2014-03-05 00:00:00', '2014-03-05', 'show'],
    'DateTime (ISO from the frontend)' => ['enrolled_at', '2024-01-15T10:30:00.000Z', '2024-01-15 10:30:00', '2024-01-15 10:30:00', 'show'],
    'Time' => ['class_time', '08:30:00', '08:30:00', '08:30:00', 'show'],
    'Color' => ['favorite_color', '#ff8800', '#ff8800', '#ff8800', 'show'],
    'Toggle' => ['is_active', true, 1, true, 'show'],
    'Checkbox (string from a form)' => ['accept_terms', 'true', 1, true, 'show'],
    'Select' => ['status', 'enrolled', 'enrolled', 'enrolled', 'show'],
    'Radio' => ['gender', 'x', 'x', 'x', 'show'],
    'CheckboxList' => ['interests', ['art', 'music'], '["art","music"]', ['art', 'music'], 'show'],
    'KeyValue' => ['settings', ['theme' => 'dark', 'lang' => 'es'], '{"theme":"dark","lang":"es"}', ['theme' => 'dark', 'lang' => 'es'], 'show'],
    'ArrayField' => ['skills', ['php', 'sql'], '["php","sql"]', ['php', 'sql'], 'show'],
    'Json (array payload)' => ['meta', ['a' => 1, 'b' => [true]], '{"a":1,"b":[true]}', ['a' => 1, 'b' => [true]], 'show'],
    'Code' => ['snippet', "echo 'hi';", "echo 'hi';", "echo 'hi';", 'show'],
    'VariableText' => ['template', 'Hello $name', 'Hello $name', 'Hello $name', 'show'],
    'Hidden' => ['uuid', 'uuid-123', 'uuid-123', 'uuid-123', 'edit'],
]);

it('stores a field and renders it back', function (string $attribute, mixed $input, mixed $raw, mixed $rendered, string $view) {
    $response = $this->postJson($this->apiUrl('student'), roundTripStorePayload([$attribute => $input]))->assertCreated();
    $id = $response->json('data.id');

    expect(roundTripRawColumn($id, $attribute))->toBe($raw);

    $read = $this->getJson($this->apiUrl('student', $view === 'show' ? (string) $id : "{$id}/edit"))->assertOk();

    expect(roundTripAttribute($read, $attribute)['value'])->toBe($rendered);
})->with('student scalar fields');

it('updates a field and renders the new value on the edit form', function (string $attribute, mixed $input, mixed $raw, mixed $rendered) {
    $student = Student::create(['name' => 'Base Student']);

    $this->putJson($this->apiUrl('student', (string) $student->id), ['name' => $attribute === 'name' ? $input : 'Base Student', $attribute => $input])
        ->assertOk();

    expect(roundTripRawColumn($student->id, $attribute))->toBe($raw);

    $edit = $this->getJson($this->apiUrl('student', "{$student->id}/edit"))->assertOk();

    expect(roundTripAttribute($edit, $attribute)['value'])->toBe($rendered);
})->with('student scalar fields');

it('clears nullable fields with an explicit null on update', function (string $attribute, mixed $initial) {
    $student = Student::create(['name' => 'Ana', $attribute => $initial]);

    $this->putJson($this->apiUrl('student', (string) $student->id), ['name' => 'Ana', $attribute => null])->assertOk();

    expect(roundTripRawColumn($student->id, $attribute))->toBeNull();
})->with([
    'Email' => ['email', 'a@b.test'],
    'Number' => ['age', 5],
    'Date' => ['birth_date', '2014-01-01'],
    'DateTime' => ['enrolled_at', '2024-01-01 10:00:00'],
    'Textarea' => ['bio', 'text'],
    'CheckboxList' => ['interests', ['art']],
]);

it('hashes the password and never renders it', function () {
    $id = $this->postJson($this->apiUrl('student'), roundTripStorePayload(['name' => 'Ana']))
        ->assertCreated()
        ->assertJsonMissingPath('data.password')
        ->json('data.id');

    expect(Hash::check('secret-123', roundTripRawColumn($id, 'password')))->toBeTrue();

    $show = $this->getJson($this->apiUrl('student', (string) $id))->assertOk();
    $edit = $this->getJson($this->apiUrl('student', "{$id}/edit"))->assertOk();

    expect(roundTripAttribute($show, 'password'))->toBeNull()
        ->and(roundTripAttribute($edit, 'password')['value'])->toBeNull()
        ->and(roundTripAttribute($edit, 'password')['type'])->toBe('password');
});

it('keeps the password when an update leaves it empty and rehashes a new one', function () {
    $student = Student::create(['name' => 'Ana', 'password' => Hash::make('old-secret')]);
    $originalHash = roundTripRawColumn($student->id, 'password');

    $this->putJson($this->apiUrl('student', (string) $student->id), ['name' => 'Ana', 'password' => ''])->assertOk();
    $this->putJson($this->apiUrl('student', (string) $student->id), ['name' => 'Ana', 'password' => null])->assertOk();
    $this->putJson($this->apiUrl('student', (string) $student->id), ['name' => 'Ana'])->assertOk();

    expect(roundTripRawColumn($student->id, 'password'))->toBe($originalHash);

    $this->putJson($this->apiUrl('student', (string) $student->id), ['name' => 'Ana', 'password' => 'new-secret'])->assertOk();

    expect(Hash::check('new-secret', roundTripRawColumn($student->id, 'password')))->toBeTrue();
});

it('requires the password on store only', function () {
    $this->postJson($this->apiUrl('student'), ['name' => 'Ana'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $student = Student::create(['name' => 'Ana']);

    $this->putJson($this->apiUrl('student', (string) $student->id), ['name' => 'Ana'])->assertOk();
});

it('renders the Status badge field from the same column on the detail view only', function () {
    $student = Student::create(['name' => 'Ana', 'status' => 'graduated']);

    $show = $this->getJson($this->apiUrl('student', (string) $student->id))->assertOk();
    $edit = $this->getJson($this->apiUrl('student', "{$student->id}/edit"))->assertOk();

    expect(roundTripAttribute($show, 'status', 'status')['value'])->toBe('graduated')
        ->and(array_column(roundTripAttribute($show, 'status', 'status')['props']['statuses'], 'color'))->toBe(['gray', 'green', 'blue'])
        ->and(roundTripAttribute($show, 'status', 'select')['value'])->toBe('graduated')
        ->and(roundTripAttribute($edit, 'status', 'status'))->toBeNull();
});

it('renders the relation count and existence fields', function () {
    $student = Student::create(['name' => 'Ana']);
    $student->courses()->attach([Course::create(['name' => 'Math'])->id, Course::create(['name' => 'Art'])->id]);
    Note::create(['notable_type' => Student::class, 'notable_id' => $student->id, 'body' => 'hi']);
    $other = Student::create(['name' => 'Bea']);

    $show = $this->getJson($this->apiUrl('student', (string) $student->id))->assertOk();
    $otherShow = $this->getJson($this->apiUrl('student', (string) $other->id))->assertOk();

    expect(roundTripAttribute($show, 'courses_count')['value'])->toBe(2)
        ->and(roundTripAttribute($show, 'notes_exists')['value'])->toBeTrue()
        ->and(roundTripAttribute($otherShow, 'courses_count')['value'])->toBe(0)
        ->and(roundTripAttribute($otherShow, 'notes_exists')['value'])->toBeFalse();
});

it('ignores values sent for the computed Count and Exists fields', function () {
    $id = $this->postJson($this->apiUrl('student'), roundTripStorePayload(['courses_count' => 99, 'notes_exists' => true]))
        ->assertCreated()
        ->json('data.id');

    $show = $this->getJson($this->apiUrl('student', (string) $id))->assertOk();

    expect(roundTripAttribute($show, 'courses_count')['value'])->toBe(0)
        ->and(roundTripAttribute($show, 'notes_exists')['value'])->toBeFalse();
});

it('lists only the index fields with their rendered values', function () {
    Student::create([
        'name' => 'Ana', 'email' => 'ana@school.test', 'age' => 12, 'balance' => 10, 'birth_date' => '2014-03-05',
        'enrolled_at' => '2024-01-15 10:30:00', 'is_active' => true, 'status' => 'enrolled', 'bio' => 'hidden on index',
    ]);

    $row = $this->getJson($this->apiUrl('student'))->assertOk()->json('data.0.attributes');
    $values = collect($row)->mapWithKeys(fn ($field) => [$field['key'] => $field['value']])->all();

    expect(array_keys($values))->toBe([
        'name', 'email', 'age', 'balance', 'birth_date', 'enrolled_at', 'is_active', 'status',
        'grade_id', 'courses_count', 'notes_exists',
    ])->and($values)->toMatchArray([
        'name' => 'Ana',
        'email' => 'ana@school.test',
        'age' => 12,
        'balance' => '10.00',
        'birth_date' => '2014-03-05',
        'enrolled_at' => '2024-01-15 10:30:00',
        'is_active' => true,
        'status' => 'enrolled',
        'courses_count' => 0,
        'notes_exists' => false,
    ]);
});

it('describes every form field on the create endpoint', function () {
    $create = $this->getJson($this->apiUrl('student', 'create'))->assertOk();
    $fields = collect($create->json('data.attributes'));

    expect($fields->pluck('key')->all())->toBe([
        'name', 'email', 'website', 'password', 'bio', 'rich_bio', 'html_notes', 'age', 'balance', 'birth_date',
        'enrolled_at', 'class_time', 'favorite_color', 'is_active', 'accept_terms', 'status', 'gender', 'interests',
        'settings', 'skills', 'meta', 'snippet', 'template', 'uuid', 'grade_id', 'courses', 'labels',
    ])->and($fields->pluck('type', 'key')->only(['name', 'balance', 'is_active', 'interests', 'skills', 'uuid'])->all())->toBe([
        'name' => 'text', 'balance' => 'currency', 'is_active' => 'boolean', 'interests' => 'checkbox_list',
        'skills' => 'array', 'uuid' => 'hidden',
    ])->and($fields->firstWhere('key', 'name')['required'])->toBeTrue()
        ->and($fields->firstWhere('key', 'balance')['props']['symbol'])->toBe('$')
        ->and($fields->every(fn ($field) => ! array_key_exists('value', $field)))->toBeTrue();
});

it('rejects invalid values with field errors', function (string $attribute, mixed $value) {
    $this->postJson($this->apiUrl('student'), roundTripStorePayload([$attribute => $value]))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($attribute);
})->with([
    'Email' => ['email', 'not-an-email'],
    'URL' => ['website', 'not a url'],
    'Number below min' => ['age', -1],
    'Number not integer' => ['age', 'twelve'],
    'Currency not numeric' => ['balance', 'ten'],
    'ArrayField not an array' => ['skills', 'php'],
    'KeyValue not an object' => ['settings', 'dark'],
]);

it('requires the name', function () {
    $this->postJson($this->apiUrl('student'), roundTripStorePayload(['name' => null]))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// Behaviour that is wrong today (see docs/testing/findings/fields.md)
// ---------------------------------------------------------------------------

it('decodes a JSON string sent to a Json field instead of double encoding it', function () {
    $id = $this->postJson($this->apiUrl('student'), roundTripStorePayload(['meta' => '{"a":1}']))->assertCreated()->json('data.id');

    expect(roundTripRawColumn($id, 'meta'))->toBe('{"a":1}');
});

it('decodes a JSON string sent to a KeyValue field instead of double encoding it', function () {
    $student = Student::create(['name' => 'Ana']);

    $this->putJson($this->apiUrl('student', (string) $student->id), ['name' => 'Ana', 'settings' => '{"theme":"dark"}'])->assertOk();

    expect(roundTripRawColumn($student->id, 'settings'))->toBe('{"theme":"dark"}');
})->skip('ISSUE FLD-002: KeyValue::fill() never runs on store/update, the JSON string is double encoded by the array cast');

it('stores "false" sent by a form to a Toggle as false', function () {
    $student = Student::create(['name' => 'Ana', 'is_active' => true]);

    $this->putJson($this->apiUrl('student', (string) $student->id), ['name' => 'Ana', 'is_active' => 'false'])->assertOk();

    expect($student->fresh()->is_active)->toBeFalse();
})->skip('ISSUE FLD-010: Toggle stores the string "false" as is and the boolean cast reads it back as true');

it('drops empty items sent to an ArrayField', function () {
    $id = $this->postJson($this->apiUrl('student'), roundTripStorePayload(['skills' => ['php', '', 'sql']]))->assertCreated()->json('data.id');

    expect(roundTripRawColumn($id, 'skills'))->toBe('["php","sql"]');
})->skip('ISSUE FLD-004: ArrayField::fill() never runs on store/update, the empty item is stored as null');

it('answers an unparseable date with a validation error', function (string $attribute) {
    $this->postJson($this->apiUrl('student'), roundTripStorePayload([$attribute => 'not a date']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($attribute);
})->with(['Date' => ['birth_date'], 'DateTime' => ['enrolled_at']])
    ->skip('ISSUE FLD-013: Date/DateTime add no date rule, the Eloquent cast throws and the API answers 500');

it('strips script tags from sanitized html fields', function (string $attribute) {
    $id = $this->postJson($this->apiUrl('student'), roundTripStorePayload([$attribute => '<p>ok</p><script>alert(1)</script>']))
        ->assertCreated()->json('data.id');

    expect(roundTripRawColumn($id, $attribute))->not->toContain('<script');
})->with(['Html' => ['html_notes'], 'RichText' => ['rich_bio']])
    ->skip('ISSUE FLD-012: sanitize() defaults to true but nothing sanitizes on the backend');

it('rejects values outside the options of option fields', function (string $attribute, mixed $value) {
    $this->postJson($this->apiUrl('student'), roundTripStorePayload([$attribute => $value]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($attribute);
})->with([
    'Select' => ['status', 'expelled'],
    'Radio' => ['gender', 'zz'],
    'CheckboxList' => ['interests', ['cooking']],
])->skip('ISSUE FLD-023: Select, Radio, CheckboxList and Status add no in: rule');
