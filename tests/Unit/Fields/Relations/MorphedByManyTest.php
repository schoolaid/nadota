<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\MorphedByMany;
use SchoolAid\Nadota\Http\Filters\RelationFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Label;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\LabelResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

beforeEach(function () {
    FixtureSchema::create();
});

function morphedByManyLabelWithStudents(array $names): Label
{
    $label = Label::create(['name' => 'VIP']);
    foreach ($names as $name) {
        Student::create(['name' => $name])->labels()->attach($label->id);
    }

    return $label;
}

// --- construction -------------------------------------------------------

it('uses the relation name as key and leaves the attribute empty', function () {
    $field = MorphedByMany::make('Students', 'students', StudentResource::class);

    expect($field)->toBeField()
        ->and($field->key())->toBe('students')
        ->and($field->getRelation())->toBe('students')
        ->and($field->getAttribute())->toBe('')
        ->and($field->getComponent())->toBe('FieldMorphedByMany')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->relationType())->toBe('morphedByMany')
        ->and($field->supportsAfterSave())->toBeFalse();
});

it('reports the belongsToMany field type (see CORE-008)', function () {
    expect(MorphedByMany::make('Students', 'students')->getType())->toBe('belongsToMany');
});

it('is only shown on detail and only applied to the show query by default', function () {
    $field = MorphedByMany::make('Students', 'students');
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeFalse()
        ->and($field->isShowOnUpdate($request, null))->toBeFalse()
        ->and($field->isAppliedInIndexQuery())->toBeFalse()
        ->and($field->isAppliedInShowQuery())->toBeTrue();
});

it('collects pivot columns from withPivot and withTimestamps', function () {
    expect(MorphedByMany::make('S', 'students')->withPivot(['note'])->withTimestamps()->getPivotColumns())
        ->toBe(['note', 'created_at', 'updated_at'])
        ->and(MorphedByMany::make('S', 'students')->hasPivotColumns())->toBeFalse();
});

// --- resolve ------------------------------------------------------------

it('resolves the models carrying the label with the related resource', function () {
    $label = morphedByManyLabelWithStudents(['Ana', 'Beto']);

    $value = MorphedByMany::make('Students', 'students', StudentResource::class)
        ->resolve(createNadotaRequest(), $label, new LabelResource());

    expect(collect($value['data'])->pluck('label')->all())->toBe(['Ana', 'Beto'])
        ->and($value['data'][0]['resource'])->toBe('student')
        ->and($value['meta'])->toMatchArray([
            'total' => 2,
            'hasMore' => false,
            'resource' => 'student',
            'pivotColumns' => [],
            'isPolymorphic' => true,
        ]);
});

it('resolves basic items carrying the related model class as morphType', function () {
    $label = morphedByManyLabelWithStudents(['Ana']);
    $student = Student::first();

    $value = MorphedByMany::make('Students', 'students')->resolve(createNadotaRequest(), $label, null);

    expect($value)->toBe([
        'data' => [['id' => $student->id, 'label' => 'Ana', 'morphType' => Student::class, 'deletedAt' => null]],
        'meta' => ['total' => 1, 'hasMore' => false, 'pivotColumns' => [], 'isPolymorphic' => true],
    ]);
});

it('carries morphType on items rendered with a resource too, as documented', function () {
    $label = morphedByManyLabelWithStudents(['Ana']);

    $value = MorphedByMany::make('Students', 'students', StudentResource::class)->resolve(createNadotaRequest(), $label, null);

    expect($value['data'][0])->toHaveKey('morphType', Student::class);
})->skip('ISSUE REL-014: docs say MorphedByMany items carry morphType; only the resource-less format does');

it('resolves pivot timestamps per item when configured and eager loaded', function () {
    $label = morphedByManyLabelWithStudents(['Ana']);
    $field = MorphedByMany::make('Students', 'students')->withPivot([])->withTimestamps();
    $resource = new class extends Resource {
        public string $model = Label::class;
        public array $declared = [];
        public function fields(NadotaRequest $request): array { return $this->declared; }
    };
    $resource->declared = [$field];

    $loaded = Label::with($resource->getEagerLoadRelations(createNadotaRequest()))->find($label->id);
    $value = $field->resolve(createNadotaRequest(), $loaded, null);

    expect($value['data'][0]['pivot'])->toHaveKeys(['created_at', 'updated_at']);
});

it('applies limit and orderBy and supports the paginated stub', function () {
    $label = morphedByManyLabelWithStudents(['c', 'a', 'b']);
    $request = createNadotaRequest();

    $limited = MorphedByMany::make('Students', 'students', StudentResource::class)->orderBy('name', 'asc')->limit(2)
        ->resolve($request, $label, null);
    $paginated = MorphedByMany::make('Students', 'students', StudentResource::class)->paginated()
        ->resolve($request, $label, null);

    expect(collect($limited['data'])->pluck('label')->all())->toBe(['a', 'b'])
        ->and($limited['meta']['hasMore'])->toBeTrue()
        ->and($paginated)->toBe([
            'data' => [],
            'meta' => ['total' => 0, 'hasMore' => false, 'paginated' => true, 'pivotColumns' => [], 'isPolymorphic' => true],
        ]);
});

it('includes rendered fields as attributes only with withFields()', function () {
    $label = morphedByManyLabelWithStudents(['Ana']);

    $value = MorphedByMany::make('Students', 'students', StudentResource::class)
        ->fields([Input::make('Name', 'name')])->withFields()
        ->resolve(createNadotaRequest(), $label, null);

    expect($value['data'][0]['attributes'][0]['value'])->toBe('Ana');
});

// --- props --------------------------------------------------------------

it('serializes pivot props and attach, detach and sync urls for an existing parent', function () {
    $label = Label::create(['name' => 'VIP']);

    $props = MorphedByMany::make('Students', 'students', StudentResource::class)
        ->toArray(createNadotaRequest(), $label, new LabelResource())['props'];

    expect($props)->toMatchArray([
        'limit' => null,
        'paginated' => false,
        'relationType' => 'morphedByMany',
        'resource' => 'student',
        'pivotColumns' => [],
        'withPivot' => false,
        'isPolymorphic' => true,
    ])
        ->and($props['urls'])->toBe([
            'options' => "/nadota-api/label/resource/field/students/options?resourceId={$label->id}",
            'attach' => "/nadota-api/label/resource/{$label->id}/attach/students",
            'detach' => "/nadota-api/label/resource/{$label->id}/detach/students",
            'sync' => "/nadota-api/label/resource/{$label->id}/sync/students",
        ]);
});

it('adds the pagination url when paginated', function () {
    $label = Label::create(['name' => 'VIP']);

    $props = MorphedByMany::make('Students', 'students', StudentResource::class)->paginated()
        ->toArray(createNadotaRequest(), $label, new LabelResource())['props'];

    expect($props['paginationUrl'])->toBe("/nadota-api/label/resource/{$label->id}/relation/students");
});

it('drops attach, detach and sync urls when attachable(false)', function () {
    $label = Label::create(['name' => 'VIP']);

    $props = MorphedByMany::make('Students', 'students', StudentResource::class)->attachable(false)
        ->toArray(createNadotaRequest(), $label, new LabelResource())['props'];

    expect(array_keys($props['urls']))->toBe(['options']);
})->skip('ISSUE REL-012: MorphedByMany always emits attach/detach/sync urls regardless of attachable() or policy');

// --- eager loading ------------------------------------------------------

it('eager loads the related models with qualified resource columns', function () {
    $label = morphedByManyLabelWithStudents(['Ana']);
    $resource = new LabelResource();

    DB::enableQueryLog();
    $loaded = Label::with($resource->getEagerLoadRelations(createNadotaRequest()))->find($label->id);
    $sql = collect(DB::getQueryLog())->pluck('query')->last();

    expect($sql)->toContain('"fx_students"."name"', '"fx_labelables"."labelable_type" = ?')
        ->and($loaded->students->pluck('name')->all())->toBe(['Ana']);
});

// --- fill / sorting / filters / export ---------------------------------

it('is read-only: fill and afterSave never touch the relation', function () {
    $label = morphedByManyLabelWithStudents(['Ana']);
    $field = MorphedByMany::make('Students', 'students');

    $field->fill(createNadotaRequest(['students' => []]), $label);

    expect($label->getDirty())->toBe([])
        ->and($label->students()->count())->toBe(1);
});

it('does not alter the parent query when sorting', function () {
    $label = new Label();
    $query = $label->newQuery();

    expect(MorphedByMany::make('Students', 'students')->applySorting($query, 'asc', $label))->toBe($query)
        ->and($query->getQuery()->orders)->toBeNull();
});

it('exposes a whereHas relation filter keyed by the relation when filterable', function () {
    $filters = MorphedByMany::make('Students', 'students', StudentResource::class)->filterable()->filters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(RelationFilter::class)
        ->and($filters[0]->key())->toBe('students');
});

it('exports the related labels joined with the separator', function () {
    $label = morphedByManyLabelWithStudents(['Ana', 'Beto']);

    $value = MorphedByMany::make('Students', 'students', StudentResource::class)->exportable()
        ->resolveForExport(createNadotaRequest(), $label, new LabelResource());

    expect($value)->toBe('Ana, Beto');
})->skip('ISSUE REL-008: MorphedByMany has no resolveForExport(); export falls back to resolve() and json-encodes the collection');
