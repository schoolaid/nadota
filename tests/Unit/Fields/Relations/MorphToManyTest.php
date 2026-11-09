<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\MorphToMany;
use SchoolAid\Nadota\Http\Filters\RelationFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Label;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\LabelResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

beforeEach(function () {
    FixtureSchema::create();
});

/** @return array{0: Student, 1: Label, 2: Label} */
function morphToManyLabelledStudent(): array
{
    $student = Student::create(['name' => 'Ana']);
    $vip = Label::create(['name' => 'VIP']);
    $new = Label::create(['name' => 'New']);
    $student->labels()->attach([$vip->id, $new->id]);

    return [$student, $vip, $new];
}

function morphToManyEagerLoad(MorphToMany $field, Student $student): Student
{
    $resource = new class extends Resource {
        public string $model = Student::class;
        public array $declared = [];
        public function fields(NadotaRequest $request): array { return $this->declared; }
    };
    $resource->declared = [$field];

    return Student::with($resource->getEagerLoadRelations(createNadotaRequest()))->find($student->id);
}

// --- construction -------------------------------------------------------

it('uses the relation name as key and leaves the attribute empty', function () {
    $field = MorphToMany::make('Labels', 'labels', LabelResource::class);

    expect($field)->toBeField()
        ->and($field->key())->toBe('labels')
        ->and($field->getRelation())->toBe('labels')
        ->and($field->getAttribute())->toBe('')
        ->and($field->getComponent())->toBe('FieldMorphToMany')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->relationType())->toBe('morphToMany')
        ->and($field->supportsAfterSave())->toBeTrue();
});

it('reports the belongsToMany field type (see CORE-008)', function () {
    expect(MorphToMany::make('Labels', 'labels')->getType())->toBe('belongsToMany');
});

it('is shown on detail and forms but not on index by default', function () {
    $field = MorphToMany::make('Labels', 'labels');
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeTrue()
        ->and($field->isShowOnUpdate($request, null))->toBeTrue()
        ->and($field->isAppliedInIndexQuery())->toBeFalse()
        ->and($field->isAppliedInShowQuery())->toBeTrue();
});

it('collects pivot columns from withPivot, pivotFields and withTimestamps', function () {
    $field = MorphToMany::make('Labels', 'labels')
        ->withPivot(['created_at'])
        ->pivotFields([Input::make('Note', 'note')])
        ->withTimestamps();

    expect($field->getPivotColumns())->toBe(['created_at', 'note', 'updated_at'])
        ->and($field->hasPivotColumns())->toBeTrue()
        ->and(MorphToMany::make('L', 'labels')->hasPivotColumns())->toBeFalse()
        ->and($field->toArray(createNadotaRequest())['pivotFields'][0]['key'])->toBe('note');
});

// --- resolve ------------------------------------------------------------

it('resolves the labels of the student only', function () {
    [$student] = morphToManyLabelledStudent();
    // A grade with the same id carrying another label must not leak in.
    $grade = Grade::create(['name' => 'G']);
    DB::table('fx_labelables')->insert([
        'label_id' => Label::create(['name' => 'Grade label'])->id,
        'labelable_type' => Grade::class,
        'labelable_id' => $grade->id,
    ]);

    $value = MorphToMany::make('Labels', 'labels', LabelResource::class)
        ->resolve(createNadotaRequest(), $student, new StudentResource());

    expect($student->id)->toBe($grade->id)
        ->and(collect($value['data'])->pluck('label')->sort()->values()->all())->toBe(['New', 'VIP'])
        ->and($value['data'][0])->toHaveKeys(['id', 'key', 'label', 'resource', 'deletedAt', 'permissions'])
        ->and($value['meta'])->toMatchArray([
            'total' => 2,
            'hasMore' => false,
            'resource' => 'label',
            'pivotColumns' => [],
            'isPolymorphic' => true,
        ]);
});

it('resolves basic items with pivot timestamps when configured and eager loaded', function () {
    [$student] = morphToManyLabelledStudent();
    $field = MorphToMany::make('Labels', 'labels')->withPivot([])->withTimestamps();

    $value = $field->resolve(createNadotaRequest(), morphToManyEagerLoad($field, $student), null);

    expect($value['data'][0])->toHaveKeys(['id', 'label', 'deletedAt', 'pivot'])
        ->and($value['data'][0]['pivot'])->toHaveKeys(['created_at', 'updated_at'])
        ->and($value['meta'])->toBe(['total' => 2, 'hasMore' => false, 'pivotColumns' => [], 'isPolymorphic' => true]);
});

it('applies limit and orderBy to the inline items', function () {
    [$student] = morphToManyLabelledStudent();
    $student->labels()->attach(Label::create(['name' => 'Alumni'])->id);

    $value = MorphToMany::make('Labels', 'labels', LabelResource::class)->orderBy('name', 'asc')->limit(2)
        ->resolve(createNadotaRequest(), $student, null);

    expect(collect($value['data'])->pluck('label')->all())->toBe(['Alumni', 'New'])
        ->and($value['meta']['hasMore'])->toBeTrue();
});

it('returns an empty polymorphic paginated stub when paginated', function () {
    [$student] = morphToManyLabelledStudent();

    $value = MorphToMany::make('Labels', 'labels', LabelResource::class)->paginated()
        ->resolve(createNadotaRequest(), $student, null);

    expect($value)->toBe([
        'data' => [],
        'meta' => ['total' => 0, 'hasMore' => false, 'paginated' => true, 'pivotColumns' => [], 'isPolymorphic' => true],
    ]);
});

// --- props --------------------------------------------------------------

it('serializes pivot props and attach, detach and sync urls for an existing parent', function () {
    $student = Student::create(['name' => 'Ana']);

    $props = MorphToMany::make('Labels', 'labels', LabelResource::class)->withPivot(['note'])
        ->toArray(createNadotaRequest(), $student, new StudentResource())['props'];

    expect($props)->toMatchArray([
        'relationType' => 'morphToMany',
        'resource' => 'label',
        'pivotColumns' => ['note'],
        'withPivot' => true,
        'withTimestamps' => false,
        'isPolymorphic' => true,
    ])
        ->and($props['urls'])->toBe([
            'options' => "/nadota-api/student/resource/field/labels/options?resourceId={$student->id}",
            'attach' => "/nadota-api/student/resource/{$student->id}/attach/labels",
            'detach' => "/nadota-api/student/resource/{$student->id}/detach/labels",
            'sync' => "/nadota-api/student/resource/{$student->id}/sync/labels",
        ])
        ->and($props)->not->toHaveKey('createContext');
});

it('only has the options url in a create form and adds the pagination url when paginated', function () {
    $student = Student::create(['name' => 'Ana']);
    $field = MorphToMany::make('Labels', 'labels', LabelResource::class)->paginated();

    expect($field->toArray(createNadotaRequest(), null, new StudentResource())['props']['urls'])
        ->toBe(['options' => '/nadota-api/student/resource/field/labels/options'])
        ->and($field->toArray(createNadotaRequest(), $student, new StudentResource())['props']['paginationUrl'])
        ->toBe("/nadota-api/student/resource/{$student->id}/relation/labels");
});

it('drops attach, detach and sync urls when attachable(false)', function () {
    $student = Student::create(['name' => 'Ana']);

    $props = MorphToMany::make('Labels', 'labels', LabelResource::class)->attachable(false)
        ->toArray(createNadotaRequest(), $student, new StudentResource())['props'];

    expect(array_keys($props['urls']))->toBe(['options']);
})->skip('ISSUE REL-012: MorphToMany always emits attach/detach/sync urls regardless of attachable() or policy');

// --- eager loading ------------------------------------------------------

it('selects the related columns qualified and applies withPivot when eager loading', function () {
    [$student] = morphToManyLabelledStudent();
    $field = MorphToMany::make('Labels', 'labels', LabelResource::class)->withTimestamps();

    DB::enableQueryLog();
    $loaded = morphToManyEagerLoad($field, $student);
    $sql = collect(DB::getQueryLog())->pluck('query')->last();

    expect($sql)->toContain('"fx_labels"."name"', 'as "pivot_created_at"')
        ->and($loaded->labels)->toHaveCount(2);
});

// --- afterSave ----------------------------------------------------------

it('does not fill the parent model before it is saved', function () {
    $student = new Student(['name' => 'Ana']);

    MorphToMany::make('Labels', 'labels')->fill(createNadotaRequest(['labels' => [1]]), $student);

    expect($student->getDirty())->toBe(['name' => 'Ana']);
});

it('syncs a flat list of ids after save, storing the morph type', function () {
    [$student, $vip] = morphToManyLabelledStudent();
    $alumni = Label::create(['name' => 'Alumni']);

    MorphToMany::make('Labels', 'labels')->afterSave(createNadotaRequest(['labels' => [$vip->id, $alumni->id]]), $student);

    expect($student->labels()->pluck('fx_labels.id')->sort()->values()->all())->toBe([$vip->id, $alumni->id])
        ->and(DB::table('fx_labelables')->distinct()->pluck('labelable_type')->all())->toBe([Student::class]);
});

it('syncs id-bearing entries after save', function () {
    [$student, $vip] = morphToManyLabelledStudent();

    MorphToMany::make('Labels', 'labels')->afterSave(createNadotaRequest(['labels' => [['id' => $vip->id]]]), $student);

    expect($student->labels()->pluck('fx_labels.id')->all())->toBe([$vip->id]);
});

it('detaches everything for an empty list and ignores a request without the key', function () {
    [$student] = morphToManyLabelledStudent();
    $field = MorphToMany::make('Labels', 'labels');

    $field->afterSave(createNadotaRequest(['name' => 'x']), $student);
    $untouched = $student->labels()->count();
    $field->afterSave(createNadotaRequest(['labels' => []]), $student);

    expect($untouched)->toBe(2)
        ->and($student->labels()->count())->toBe(0);
});

// --- sorting / filters / export ----------------------------------------

it('does not alter the parent query when sorting', function () {
    $student = new Student();
    $query = $student->newQuery();

    expect(MorphToMany::make('Labels', 'labels')->applySorting($query, 'asc', $student))->toBe($query)
        ->and($query->getQuery()->orders)->toBeNull();
});

it('exposes a whereHas relation filter keyed by the relation when filterable', function () {
    $filters = MorphToMany::make('Labels', 'labels', LabelResource::class)->filterable()->filters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(RelationFilter::class)
        ->and($filters[0]->key())->toBe('labels');
});

it('exports the related labels joined with the separator', function () {
    [$student] = morphToManyLabelledStudent();

    $value = MorphToMany::make('Labels', 'labels')->exportSeparator(' / ')->resolveForExport(createNadotaRequest(), $student, null);

    expect(explode(' / ', $value))->toEqualCanonicalizing(['VIP', 'New'])
        ->and(MorphToMany::make('Labels', 'labels')->resolveForExport(createNadotaRequest(), Student::create(['name' => 'B']), null))
        ->toBe('');
});
