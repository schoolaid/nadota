<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentShowcaseResource;

/*
 * Sections and the composite fields (Lookup, Hidden, DynamicField, VariableText,
 * Signature in base64 mode, KeyValue with a schema, ArrayField, CustomComponent)
 * through real requests against StudentShowcaseResource ("student-showcase").
 */

uses(InteractsWithNadotaApi::class);

beforeEach(fn () => $this->bootNadotaApi([StudentShowcaseResource::class, GradeResource::class]));

function showcaseUrl(string $path = ''): string
{
    return '/nadota-api/student-showcase/resource' . ($path === '' ? '' : '/' . $path);
}

/** A valid store payload: name is required and the template must mention {{name}}. */
function showcasePayload(array $extra = []): array
{
    return array_merge(['name' => 'Ana', 'template' => 'Hello {{name}}'], $extra);
}

function showcaseColumn(int $id, string $column): mixed
{
    return DB::table('fx_students')->where('id', $id)->value($column);
}

function showcaseField(TestResponse $response, string $key): ?array
{
    return collect($response->json('data.attributes'))->firstWhere('key', $key);
}

// ---------------------------------------------------------------------------
// Sections
// ---------------------------------------------------------------------------

it('describes the section layout of every view in the config endpoint', function () {
    $sections = $this->getJson(showcaseUrl('config'))->assertOk()->json('sections');

    expect(array_map(fn (array $section) => $section['title'] ?? null, $sections['detail']))->toBe(['Identity', 'Answers', 'Preferences', 'Admin', null])
        ->and($sections['detail'][0])->toMatchArray([
            'type' => 'section', 'icon' => 'user', 'description' => 'Who the student is',
            'collapsible' => false, 'collapsed' => false, 'fieldKeys' => ['name', 'status', 'age'],
        ])
        ->and($sections['detail'][1]['collapsible'])->toBeTrue()
        ->and($sections['detail'][2])->toMatchArray(['collapsible' => true, 'collapsed' => true, 'fieldKeys' => ['settings', 'skills']])
        ->and($sections['detail'][4])->toBe(['type' => 'default', 'fieldKeys' => ['custom_component_progress_chart']])
        ->and(array_column($sections['create'], 'title'))->toBe(['Identity', 'Answers', 'Preferences'])
        ->and($sections['create'][0]['fieldKeys'])->toBe(['name', 'status', 'age', 'uuid', 'grade_filter'])
        ->and(array_column($sections['update'], 'title'))->toBe(['Identity', 'Answers', 'Preferences', 'Admin']);
});

it('validates and stores the fields of every visible section', function () {
    $id = $this->postJson(showcaseUrl(), showcasePayload(['status' => 'draft', 'age' => 10]))->assertCreated()->json('data.id');

    expect(showcaseColumn($id, 'name'))->toBe('Ana')
        ->and(showcaseColumn($id, 'status'))->toBe('draft')
        ->and(showcaseColumn($id, 'age'))->toBe(10)
        ->and(showcaseColumn($id, 'template'))->toBe('Hello {{name}}');
});

it('lets the update form change the fields of a section hidden from creation', function () {
    $student = Student::create(['name' => 'Ana', 'template' => 'Hello {{name}}']);

    $this->putJson(showcaseUrl((string) $student->id), showcasePayload(['website' => 'https://ana.test']))->assertOk();

    expect(showcaseColumn($student->id, 'website'))->toBe('https://ana.test');
});

it('ignores the fields of a section hidden from creation on store', function () {
    $id = $this->postJson(showcaseUrl(), showcasePayload(['website' => 'https://ana.test']))->assertCreated()->json('data.id');

    expect(showcaseColumn($id, 'website'))->toBeNull();
})->skip('ISSUE FLD-011: flattenFields() ignores Section visibility, the website of the "Admin" section is stored');

it('leaves the fields of a section hidden from creation out of the create endpoint', function () {
    $keys = collect($this->getJson(showcaseUrl('create'))->assertOk()->json('data.attributes'))->pluck('key');

    expect($keys)->not->toContain('website');
})->skip('ISSUE FLD-011: /create lists the website field of the "Admin" section hidden from creation');

// ---------------------------------------------------------------------------
// Hidden, Lookup and defaults
// ---------------------------------------------------------------------------

it('stores a Hidden field and shows it on the edit form only', function () {
    $id = $this->postJson(showcaseUrl(), showcasePayload(['uuid' => 'uuid-42']))->assertCreated()->json('data.id');

    $show = $this->getJson(showcaseUrl((string) $id))->assertOk();
    $edit = $this->getJson(showcaseUrl("{$id}/edit"))->assertOk();

    expect(showcaseColumn($id, 'uuid'))->toBe('uuid-42')
        ->and(showcaseField($show, 'uuid'))->toBeNull()
        ->and(showcaseField($edit, 'uuid'))->toMatchArray(['type' => 'hidden', 'component' => 'FieldHidden', 'value' => 'uuid-42']);
});

it('validates a Lookup value but never persists it', function () {
    // fx_students has no grade_filter column: persisting it would make the insert fail.
    $id = $this->postJson(showcaseUrl(), showcasePayload(['grade_filter' => 3]))->assertCreated()->json('data.id');

    $edit = $this->getJson(showcaseUrl("{$id}/edit"))->assertOk();

    expect(Student::find($id)->getAttributes())->not->toHaveKey('grade_filter')
        ->and(showcaseField($edit, 'grade_filter'))->toMatchArray(['type' => 'lookup', 'value' => null])
        ->and(showcaseField($edit, 'grade_filter')['optionsUrl'])->toContain('/field/grade_filter/options');
});

// The detail view shows the default for a null column although nothing is stored: by design of
// FieldResolveTrait::resolve(), questioned in FLD-015 (doubt).
it('offers the default on the create form and renders it for an empty column', function () {
    $create = $this->getJson(showcaseUrl('create'))->assertOk();
    $student = Student::create(['name' => 'Ana']);
    $show = $this->getJson(showcaseUrl((string) $student->id))->assertOk();

    expect(showcaseField($create, 'age')['default'])->toBe(18)
        ->and(showcaseField($show, 'age')['value'])->toBe(18)
        ->and(showcaseColumn($student->id, 'age'))->toBeNull();
});

it('applies the default when the attribute is left out on store', function () {
    $id = $this->postJson(showcaseUrl(), showcasePayload())->assertCreated()->json('data.id');

    expect(showcaseColumn($id, 'age'))->toBe(18);
})->skip('ISSUE HTTP-009: defaults are only applied to keys present in the payload; DefaultValueHandler is never used');

// ---------------------------------------------------------------------------
// VariableText
// ---------------------------------------------------------------------------

it('refuses a template without its required variables', function () {
    $this->postJson(showcaseUrl(), showcasePayload(['template' => 'Hello there']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.template.0', 'The template must contain the following variables: {{name}}');
});

it('describes the template variables on the create endpoint', function () {
    $props = showcaseField($this->getJson(showcaseUrl('create'))->assertOk(), 'template')['props'];

    expect($props['variablePrefix'])->toBe('{{')
        ->and($props['variableSuffix'])->toBe('}}')
        ->and(array_keys($props['variables']))->toBe(['name', 'grade'])
        ->and($props['variables']['name'])->toMatchArray(['formatted' => '{{name}}', 'required' => true])
        ->and($props['variables']['grade']['required'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// DynamicField
// ---------------------------------------------------------------------------

it('renders the dynamic field with the field matched by the stored type', function (?string $status, mixed $stored, string $type, mixed $value) {
    $student = Student::create(['name' => 'Ana', 'status' => $status, 'snippet' => $stored]);

    $field = showcaseField($this->getJson(showcaseUrl((string) $student->id))->assertOk(), 'snippet');

    expect($field['type'])->toBe('dynamic')
        ->and($field['props']['typeField'])->toBe('status')
        ->and($field['props']['matchedField']['type'])->toBe($type)
        ->and($field['props']['defaultField']['type'])->toBe('textarea')
        ->and($field['value'])->toBe($value);
})->with([
    'enrolled -> checkbox' => ['enrolled', 'no', 'checkbox', false],
    'draft -> number' => ['draft', '42', 'number', '42'],
    'no type -> default textarea' => [null, 'free text', 'textarea', 'free text'],
]);

it('stores the dynamic value for the type chosen in the same request', function () {
    $id = $this->postJson(showcaseUrl(), showcasePayload(['status' => 'draft', 'snippet' => '42']))->assertCreated()->json('data.id');

    expect(showcaseColumn($id, 'snippet'))->toBe('42');
});

it('stores the dynamic value converted by the matched field', function () {
    $id = $this->postJson(showcaseUrl(), showcasePayload(['status' => 'enrolled', 'snippet' => true]))->assertCreated()->json('data.id');

    expect(showcaseColumn($id, 'snippet'))->toBe('yes');
})->skip('ISSUE FLD-007: the matched Checkbox never converts the value, "1" is stored instead of "yes"');

it('validates the dynamic value with the rules of the matched field', function () {
    $this->postJson(showcaseUrl(), showcasePayload(['status' => 'draft', 'snippet' => 'not a number']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('snippet');
})->skip('ISSUE FLD-006: the Number rules of the matched type never reach the validator');

// ---------------------------------------------------------------------------
// Signature (base64 mode)
// ---------------------------------------------------------------------------

it('keeps a base64 signature in the column and renders it as a data url', function () {
    $dataUrl = 'data:image/png;base64,' . base64_encode('png-bytes');

    $id = $this->postJson(showcaseUrl(), showcasePayload(['signature' => $dataUrl]))->assertCreated()->json('data.id');

    expect(showcaseColumn($id, 'signature'))->toBe($dataUrl)
        ->and(showcaseField($this->getJson(showcaseUrl((string) $id))->assertOk(), 'signature')['value'])
        ->toBe(['type' => 'base64', 'data' => $dataUrl, 'dataUrl' => $dataUrl]);
});

it('clears a base64 signature with the clear keyword', function () {
    $student = Student::create(['name' => 'Ana', 'template' => 'Hello {{name}}', 'signature' => 'data:image/png;base64,AAAA']);

    $this->putJson(showcaseUrl((string) $student->id), showcasePayload(['signature' => 'clear']))->assertOk();

    expect(showcaseColumn($student->id, 'signature'))->toBeNull();
})->skip('ISSUE FLD-001: Signature::fill() never runs, the literal "clear" is stored as the signature');

// ---------------------------------------------------------------------------
// KeyValue with a schema and ArrayField
// ---------------------------------------------------------------------------

it('renders key-value pairs with defaults and without hidden keys', function () {
    $student = Student::create(['name' => 'Ana', 'settings' => ['locale' => 'es', 'token' => 'secret']]);

    $field = showcaseField($this->getJson(showcaseUrl((string) $student->id))->assertOk(), 'settings');

    expect($field['value'])->toBe(['locale' => 'es', 'theme' => 'light'])
        ->and($field['props']['readonlyKeys'])->toBe(['locale'])
        ->and($field['props']['hiddenKeys'])->toBe(['token']);
});

it('protects readonly and hidden keys and drops unknown keys on update', function () {
    $student = Student::create(['name' => 'Ana', 'template' => 'Hello {{name}}', 'settings' => ['locale' => 'es', 'token' => 'secret']]);

    $this->putJson(showcaseUrl((string) $student->id), showcasePayload([
        'settings' => ['theme' => 'dark', 'locale' => 'en', 'token' => 'stolen', 'admin' => true],
    ]))->assertOk();

    expect($student->fresh()->settings)->toBe(['theme' => 'dark', 'locale' => 'es', 'token' => 'secret']);
});

it('stores the array items and enforces the item count', function () {
    $id = $this->postJson(showcaseUrl(), showcasePayload(['skills' => ['a@school.test', 'b@school.test']]))->assertCreated()->json('data.id');

    expect(Student::find($id)->skills)->toBe(['a@school.test', 'b@school.test']);

    $this->postJson(showcaseUrl(), showcasePayload(['skills' => ['1@s.test', '2@s.test', '3@s.test', '4@s.test']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('skills');
});

it('validates every array item and refuses duplicates', function (array $skills) {
    $this->postJson(showcaseUrl(), showcasePayload(['skills' => $skills]))
        ->assertUnprocessable();
})->with([
    'invalid e-mail' => [['a@school.test', 'not-an-email']],
    'duplicated e-mail' => [['a@school.test', 'a@school.test']],
])->skip('ISSUE FLD-005: ArrayField item rules and unique() are not enforced by the backend');

// ---------------------------------------------------------------------------
// CustomComponent
// ---------------------------------------------------------------------------

it('renders the custom component on the detail view with its props and data', function () {
    $student = Student::create(['name' => 'Ana', 'age' => 11]);

    $show = $this->getJson(showcaseUrl((string) $student->id))->assertOk();
    $edit = $this->getJson(showcaseUrl("{$student->id}/edit"))->assertOk();
    $field = showcaseField($show, 'custom_component_progress_chart');

    expect($field)->toMatchArray([
        'type' => 'customComponent',
        'component' => StudentShowcaseResource::CHART_COMPONENT,
        'value' => ['age' => 11],
    ])->and($field['props'])->toMatchArray([
        'componentPath' => StudentShowcaseResource::CHART_COMPONENT,
        'position' => 'below',
        'componentProps' => ['color' => 'green'],
        'componentData' => ['age' => 11],
    ])->and(showcaseField($edit, 'custom_component_progress_chart'))->toBeNull();
});

it('ignores a value sent for the custom component', function () {
    // There is no custom_component_progress_chart column: filling it would break the insert.
    $this->postJson(showcaseUrl(), showcasePayload(['custom_component_progress_chart' => 'x']))->assertCreated();
});
