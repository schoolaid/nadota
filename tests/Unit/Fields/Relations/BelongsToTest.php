<?php

use Illuminate\Database\Eloquent\Builder;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Filters\DynamicSelectFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;

beforeEach(function () {
    FixtureSchema::create();
});

function belongsToStudentInGrade(string $gradeName = '5th A'): Student
{
    $grade = Grade::create(['name' => $gradeName, 'level' => 5]);

    return Student::create(['name' => 'Ana', 'grade_id' => $grade->id]);
}

// --- construction -------------------------------------------------------

it('infers the foreign key attribute from the relation name', function () {
    $field = BelongsTo::make('Test Model', 'testModel');

    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('test_model_id')
        ->and($field->key())->toBe('test_model_id')
        ->and($field->fieldData->label)->toBe('Test Model')
        ->and($field->getRelation())->toBe('testModel')
        ->and($field->getType())->toBe('belongsTo')
        ->and($field->getComponent())->toBe('FieldBelongsTo')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->relationType())->toBe('belongsTo')
        ->and($field->isBelongsTo())->toBeTrue();
});

it('is visible everywhere and applied to both index and show queries by default', function () {
    $field = BelongsTo::make('Grade', 'grade', GradeResource::class);
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeTrue()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeTrue()
        ->and($field->isShowOnUpdate($request, null))->toBeTrue()
        ->and($field->isAppliedInIndexQuery())->toBeTrue()
        ->and($field->isAppliedInShowQuery())->toBeTrue()
        ->and($field->getResource())->toBe(GradeResource::class);
});

it('stores the related model and adds an exists rule for it', function () {
    $field = BelongsTo::make('Test Model', 'testModel')->relatedModel(TestModel::class);

    expect($field->relatedModelClass)->toBe(TestModel::class)
        ->and($field->getModel())->toBe(TestModel::class)
        ->and($field->getRules())->toContain('exists:test_models,id');
});

it('lets foreignKey() override both the inferred key and the field attribute', function () {
    $field = BelongsTo::make('Creator', 'creator')->foreignKey('created_by_user_id');

    expect($field->getForeignKey())->toBe('created_by_user_id')
        ->and($field->getAttribute())->toBe('created_by_user_id')
        ->and($field->resolveForeignKeyFromModel(Student::class))->toBe('created_by_user_id');
});

it('treats relationAttribute() as an alias of displayAttribute()', function () {
    $field = BelongsTo::make('Test Model', 'testModel')->relationAttribute('email');

    expect($field->getAttributeForDisplay())->toBe('email');
});

// --- foreign key / column selection ------------------------------------

it('resolves the real foreign key from the Eloquent relation', function () {
    $field = BelongsTo::make('Grade', 'grade');

    expect($field->resolveForeignKeyFromModel(Student::class))->toBe('grade_id')
        ->and($field->getStorageAttribute(Student::class))->toBe('grade_id')
        ->and($field->getColumnsForSelect(Student::class))->toBe(['grade_id']);
});

it('falls back to the inferred attribute when the model has no such relation', function () {
    $field = BelongsTo::make('Mentor', 'mentor');

    expect($field->resolveForeignKeyFromModel(Student::class))->toBe('mentor_id');
});

it('selects only the related resource columns plus the primary key when eager loading', function () {
    $field = BelongsTo::make('Grade', 'grade', GradeResource::class);

    // GradeResource: name, level, school (BelongsTo -> school_id); relation fields with
    // an empty attribute (HasMany, HasOneThrough, MorphMany) add nothing.
    expect($field->getRelatedColumns(createNadotaRequest()))
        ->toEqualCanonicalizing(['name', 'level', 'school_id', 'id']);
});

it('drops columns listed in except() from the eager-load select', function () {
    $field = BelongsTo::make('Grade', 'grade', GradeResource::class)->except(['level']);

    expect($field->getRelatedColumns(createNadotaRequest()))->not->toContain('level')
        ->toContain('id');
});

it('selects every related column when it has no resource', function () {
    expect(BelongsTo::make('Grade', 'grade')->getRelatedColumns(createNadotaRequest()))->toBeNull();
});

// --- resolve ------------------------------------------------------------

it('resolves the related model with the related resource key and displayLabel', function () {
    $student = belongsToStudentInGrade('5th A');

    $value = BelongsTo::make('Grade', 'grade', GradeResource::class)
        ->resolve(createNadotaRequest(), $student, new StudentResource());

    expect($value)->toBe([
        'id' => $student->grade_id,
        'key' => $student->grade_id,
        'label' => '5th A',
        'resource' => 'grade',
        'deletedAt' => null,
    ]);
});

it('resolves the label with displayUsing first, then displayAttribute, then the resource', function () {
    $student = belongsToStudentInGrade('5th A');
    $request = createNadotaRequest();

    $byCallback = BelongsTo::make('Grade', 'grade', GradeResource::class)
        ->displayAttribute('level')
        ->displayUsing(fn ($grade) => "Grade {$grade->name}");
    $byAttribute = BelongsTo::make('Grade', 'grade', GradeResource::class)->displayAttribute('level');

    expect($byCallback->resolve($request, $student, null)['label'])->toBe('Grade 5th A')
        ->and($byAttribute->resolve($request, $student, null)['label'])->toBe(5);
});

it('keeps displayUsing() from turning a relation field into a computed field', function () {
    $field = BelongsTo::make('Grade', 'grade')->displayUsing(fn ($grade) => $grade->name);

    expect($field->isComputed())->toBeFalse()
        ->and($field->isShowOnCreation(createNadotaRequest(), null))->toBeTrue();
});

it('formats without resource as id, label, null resource and deletedAt', function () {
    $testModel = TestModel::factory()->create(['name' => 'Parent Model']);
    $relatedModel = RelatedModel::factory()->create(['test_model_id' => $testModel->id, 'title' => 'Child']);

    $value = BelongsTo::make('Test Model', 'testModel')
        ->relatedModel(TestModel::class)
        ->relationAttribute('name')
        ->resolve(createNadotaRequest(), $relatedModel, null);

    expect($value)->toBe([
        'id' => $testModel->id,
        'label' => 'Parent Model',
        'resource' => null,
        'deletedAt' => null,
    ]);
});

it('falls back to the primary key as label when nothing else is configured', function () {
    $student = belongsToStudentInGrade();

    $value = BelongsTo::make('Grade', 'grade')->resolve(createNadotaRequest(), $student, null);

    expect($value['label'])->toBe($student->grade_id);
});

it('includes the rendered related fields as attributes only with withFields()', function () {
    $student = belongsToStudentInGrade('5th A');
    $request = createNadotaRequest();

    $without = BelongsTo::make('Grade', 'grade', GradeResource::class)->resolve($request, $student, null);
    $with = BelongsTo::make('Grade', 'grade', GradeResource::class)
        ->fields([Input::make('Name', 'name')])
        ->withFields()
        ->resolve($request, $student, null);

    expect($without)->not->toHaveKey('attributes')
        ->and($with['attributes'])->toHaveCount(1)
        ->and($with['attributes'][0]['key'])->toBe('name')
        ->and($with['attributes'][0]['value'])->toBe('5th A');
});

it('never includes per-item permissions in the resolved value', function () {
    $student = belongsToStudentInGrade();

    $value = BelongsTo::make('Grade', 'grade', GradeResource::class)->resolve(createNadotaRequest(), $student, null);

    expect($value)->not->toHaveKey('permissions');
});

it('excludes related fields listed in exceptFields() from withFields output', function () {
    $student = belongsToStudentInGrade();

    $value = BelongsTo::make('Grade', 'grade', GradeResource::class)
        ->withFields()
        ->exceptFields(['level', 'school'])
        ->resolve(createNadotaRequest(), $student, null);

    expect(collect($value['attributes'])->pluck('key')->all())->toBe(['name']);
});

it('returns null when no related model exists', function () {
    $relatedModel = RelatedModel::factory()->make(['test_model_id' => null]);

    $value = BelongsTo::make('Test Model', 'testModel')
        ->relatedModel(TestModel::class)
        ->resolve(createNadotaRequest(), $relatedModel, null);

    expect($value)->toBeNull();
});

it('returns the default when no related model exists and a default is set', function () {
    $student = Student::create(['name' => 'No grade']);

    $value = BelongsTo::make('Grade', 'grade')->default(42)->resolve(createNadotaRequest(), $student, null);

    expect($value)->toBe(42);
});

it('returns null when the parent model does not define the relation method', function () {
    $student = Student::create(['name' => 'Ana']);

    expect(BelongsTo::make('Mentor', 'mentor')->resolve(createNadotaRequest(), $student, null))->toBeNull();
});

it('formats basically when the declared resource is not a Nadota resource', function () {
    $student = belongsToStudentInGrade('5th A');

    $value = BelongsTo::make('Grade', 'grade', \stdClass::class)->resolve(createNadotaRequest(), $student, null);

    expect($value)->toMatchArray(['id' => $student->grade_id, 'resource' => null]);
});

// --- toArray ------------------------------------------------------------

it('serializes field metadata, options url and the resolved value', function () {
    $student = belongsToStudentInGrade('5th A');

    $array = BelongsTo::make('Grade', 'grade', GradeResource::class)
        ->sortable()
        ->filterable()
        ->toArray(createNadotaRequest(), $student, new StudentResource());

    expect($array)
        ->toHaveKey('label', 'Grade')
        ->toHaveKey('attribute', 'grade_id')
        ->toHaveKey('key', 'grade_id')
        ->toHaveKey('type', 'belongsTo')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->toHaveKey('optionsUrl', '/nadota-api/student/resource/field/grade_id/options')
        ->toHaveKey('props', [])
        ->and($array['value']['label'])->toBe('5th A');
});

it('has no options url without a parent resource', function () {
    $array = BelongsTo::make('Grade', 'grade', GradeResource::class)->toArray(createNadotaRequest());

    expect($array['optionsUrl'])->toBeNull()
        ->and($array)->not->toHaveKey('value');
});

// --- fill ---------------------------------------------------------------

it('fills the resolved foreign key from the request', function () {
    $student = new Student(['name' => 'Ana']);

    BelongsTo::make('Grade', 'grade')->fill(createNadotaRequest(['grade_id' => 7]), $student);

    expect($student->grade_id)->toBe(7);
});

it('fills the custom foreign key set with foreignKey()', function () {
    $student = new Student();

    BelongsTo::make('Homeroom', 'homeroom')->foreignKey('grade_id')
        ->fill(createNadotaRequest(['grade_id' => 3]), $student);

    expect($student->grade_id)->toBe(3);
});

it('fills an explicit null to detach the related model', function () {
    $student = belongsToStudentInGrade();

    BelongsTo::make('Grade', 'grade')->fill(createNadotaRequest(['grade_id' => null]), $student);

    expect($student->grade_id)->toBeNull();
});

it('leaves the foreign key untouched when the request does not carry it', function () {
    $student = belongsToStudentInGrade();
    $gradeId = $student->grade_id;

    BelongsTo::make('Grade', 'grade')->fill(createNadotaRequest(['name' => 'x']), $student);

    expect($student->grade_id)->toBe($gradeId);
});

it('does not fill when readonly or disabled', function (string $state) {
    $student = new Student();

    BelongsTo::make('Grade', 'grade')->{$state}()->fill(createNadotaRequest(['grade_id' => 9]), $student);

    expect($student->grade_id)->toBeNull();
})->with(['readonly', 'disabled']);

// --- options ------------------------------------------------------------

it('builds value/label options from the related model', function () {
    $first = TestModel::factory()->create(['name' => 'First']);
    $second = TestModel::factory()->create(['name' => 'Second']);

    $options = BelongsTo::make('Test Model', 'testModel')
        ->relatedModel(TestModel::class)
        ->relationAttribute('name')
        ->getOptions();

    expect($options)->toBe([
        ['value' => $first->id, 'label' => 'First'],
        ['value' => $second->id, 'label' => 'Second'],
    ]);
});

it('returns no options without a related model', function () {
    expect(BelongsTo::make('Grade', 'grade')->getOptions())->toBe([]);
});

// --- filters ------------------------------------------------------------

it('exposes a searchable dynamic-select filter keyed by the foreign key when filterable', function () {
    $filters = BelongsTo::make('Grade', 'grade', GradeResource::class)->filterable()->filters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(DynamicSelectFilter::class)
        ->and($filters[0]->key())->toBe('grade_id');
});

it('exposes no filters unless filterable', function () {
    expect(BelongsTo::make('Grade', 'grade')->filters())->toBe([]);
});

// --- sorting ------------------------------------------------------------

it('sorts by joining the related table and ordering by the display attribute', function () {
    $student = new Student();
    $query = $student->newQuery();

    $sorted = BelongsTo::make('Grade', 'grade')->displayAttribute('name')->applySorting($query, 'asc', $student);
    $base = $sorted->getQuery();

    expect($sorted)->toBeInstanceOf(Builder::class)
        ->and($base->joins)->toHaveCount(1)
        ->and($base->joins[0]->table)->toBe('fx_grades')
        ->and($base->orders)->toBe([['column' => 'fx_grades.name', 'direction' => 'asc']])
        ->and($base->columns)->toBe(['fx_students.*']);
});

it('orders by the owner key when no display attribute is set', function () {
    $student = new Student();

    $sorted = BelongsTo::make('Grade', 'grade')->applySorting($student->newQuery(), 'desc', $student);

    expect($sorted->getQuery()->orders)->toBe([['column' => 'fx_grades.id', 'direction' => 'desc']]);
});

it('returns rows ordered by the related display attribute', function () {
    $zeta = Grade::create(['name' => 'Zeta']);
    $alpha = Grade::create(['name' => 'Alpha']);
    Student::create(['name' => 'In Zeta', 'grade_id' => $zeta->id]);
    Student::create(['name' => 'In Alpha', 'grade_id' => $alpha->id]);
    $student = new Student();

    $names = BelongsTo::make('Grade', 'grade')->displayAttribute('name')
        ->applySorting($student->newQuery(), 'asc', $student)
        ->pluck('fx_students.name')
        ->all();

    expect($names)->toBe(['In Alpha', 'In Zeta']);
});

// --- export -------------------------------------------------------------

it('exports the related label using the related resource displayLabel', function () {
    $student = belongsToStudentInGrade('5th A');

    $parentResource = new class extends Resource {
        public string $model = Student::class;
        public function fields(NadotaRequest $request): array { return []; }
        public function displayLabel($model): string { return 'WRONG RESOURCE'; }
    };

    $value = BelongsTo::make('Grade', 'grade', GradeResource::class)
        ->resolveForExport(createNadotaRequest(), $student, $parentResource);

    expect($value)->toBe('5th A');
});

it('exports null when there is no related model', function () {
    $student = Student::create(['name' => 'No grade']);

    expect(BelongsTo::make('Grade', 'grade', GradeResource::class)->resolveForExport(createNadotaRequest(), $student, null))
        ->toBeNull();
});

it('exports the display attribute over the resource label', function () {
    $school = School::create(['name' => 'North', 'city' => 'Lima']);
    $grade = Grade::create(['name' => 'G', 'school_id' => $school->id]);

    $value = BelongsTo::make('School', 'school')->displayAttribute('city')
        ->resolveForExport(createNadotaRequest(), $grade, null);

    expect($value)->toBe('Lima');
});
