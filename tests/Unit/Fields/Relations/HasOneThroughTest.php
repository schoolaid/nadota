<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\HasOneThrough;
use SchoolAid\Nadota\Http\Filters\RelationFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Models\StudentProfile;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Models\CustomKeysGrade;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Resources\RelCustomKeysGradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentProfileResource;

beforeEach(function () {
    FixtureSchema::create();
});

afterEach(function () {
    RelCustomKeysGradeResource::reset();
});

function hasOneThroughGrade(?string $nickname = 'Annie'): Grade
{
    $grade = Grade::create(['name' => '5th A']);
    $student = Student::create(['name' => 'Ana', 'grade_id' => $grade->id]);

    if ($nickname !== null) {
        StudentProfile::create(['student_id' => $student->id, 'nickname' => $nickname]);
    }

    return $grade;
}

function hasOneThroughRequest(Resource $parent): NadotaRequest
{
    $request = createNadotaRequest();
    $request->setResource($parent);

    return $request;
}

// --- construction -------------------------------------------------------

it('keeps the relation and an empty attribute', function () {
    $field = HasOneThrough::make('First Profile', 'firstStudentProfile', StudentProfileResource::class);

    expect($field)->toBeField()
        ->and($field->getRelation())->toBe('firstStudentProfile')
        ->and($field->getAttribute())->toBe('')
        ->and($field->getComponent())->toBe('FieldHasOneThrough')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->relationType())->toBe('hasOneThrough')
        ->and($field->getResource())->toBe(StudentProfileResource::class)
        ->and($field->getColumnsForSelect(Grade::class))->toBe([]);
});

it('uses the relation name as the field key like every other relation field', function () {
    $field = HasOneThrough::make('First Profile', 'firstStudentProfile');

    expect($field->key())->toBe('firstStudentProfile')
        ->and($field->toArray(createNadotaRequest(), null, new GradeResource())['optionsUrl'])
        ->toBe('/nadota-api/grade/resource/field/firstStudentProfile/options');
})->skip('ISSUE REL-001: HasOneThrough never sets the key to the relation name, so key() is ""');

it('reports the hasOne field type (see CORE-008)', function () {
    expect(HasOneThrough::make('P', 'firstStudentProfile')->getType())->toBe('hasOne');
});

it('is only shown on detail and only applied to the show query by default', function () {
    $field = HasOneThrough::make('First Profile', 'firstStudentProfile');
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeFalse()
        ->and($field->isShowOnUpdate($request, null))->toBeFalse()
        ->and($field->isAppliedInIndexQuery())->toBeFalse()
        ->and($field->isAppliedInShowQuery())->toBeTrue();
});

// --- resolve ------------------------------------------------------------

it('resolves the far related model with the related resource key', function () {
    $grade = hasOneThroughGrade('Annie');
    $profile = StudentProfile::first();

    $value = HasOneThrough::make('First Profile', 'firstStudentProfile', StudentProfileResource::class)
        ->displayAttribute('nickname')
        ->resolve(createNadotaRequest(), $grade, new GradeResource());

    expect($value)->toBe([
        'id' => $profile->id,
        'key' => $profile->id,
        'label' => 'Annie',
        'resource' => 'student-profile',
        'deletedAt' => null,
    ]);
});

it('formats without resource as id, label, null resource and deletedAt', function () {
    $grade = hasOneThroughGrade('Annie');
    $profile = StudentProfile::first();

    $value = HasOneThrough::make('First Profile', 'firstStudentProfile')
        ->displayUsing(fn ($p) => "@{$p->nickname}")
        ->resolve(createNadotaRequest(), $grade, null);

    expect($value)->toBe(['id' => $profile->id, 'label' => '@Annie', 'resource' => null, 'deletedAt' => null]);
});

it('includes rendered fields as attributes only with withFields()', function () {
    $grade = hasOneThroughGrade('Annie');

    $value = HasOneThrough::make('First Profile', 'firstStudentProfile', StudentProfileResource::class)
        ->fields([Input::make('Nickname', 'nickname')])
        ->withFields()
        ->resolve(createNadotaRequest(), $grade, null);

    expect($value['attributes'])->toHaveCount(1)
        ->and($value['attributes'][0]['value'])->toBe('Annie')
        ->and($value)->not->toHaveKey('permissions');
});

it('returns null, or the default, when the far model does not exist', function () {
    $grade = hasOneThroughGrade(null);
    $request = createNadotaRequest();

    expect(HasOneThrough::make('P', 'firstStudentProfile')->resolve($request, $grade, null))->toBeNull()
        ->and(HasOneThrough::make('P', 'firstStudentProfile')->default('n/a')->resolve($request, $grade, null))->toBe('n/a')
        ->and(HasOneThrough::make('P', 'missingRelation')->resolve($request, $grade, null))->toBeNull();
});

// --- props --------------------------------------------------------------

it('only exposes relationType and resource in props', function () {
    $grade = hasOneThroughGrade();

    $props = HasOneThrough::make('First Profile', 'firstStudentProfile', StudentProfileResource::class)
        ->toArray(createNadotaRequest(), $grade, new GradeResource())['props'];

    expect($props)->toBe(['relationType' => 'hasOneThrough', 'resource' => 'student-profile']);
});

// --- eager loading ------------------------------------------------------

it('eager loads the far model with the related resource columns', function () {
    $grade = hasOneThroughGrade('Annie');
    $resource = new GradeResource();

    $loaded = Grade::with(['firstStudentProfile' => $resource->getEagerLoadRelations(hasOneThroughRequest($resource))['firstStudentProfile']])
        ->find($grade->id);

    expect($loaded->relationLoaded('firstStudentProfile'))->toBeTrue()
        ->and($loaded->firstStudentProfile->nickname)->toBe('Annie');
});

it('does not add intermediate-table columns to the far table select', function () {
    RelCustomKeysGradeResource::using(fn () => [
        HasOneThrough::make('By age', 'profileByAge', StudentProfileResource::class),
    ]);
    $grade = CustomKeysGrade::create(['name' => 'G']);
    Student::create(['name' => 'Seven', 'age' => 7, 'grade_id' => $grade->id]);
    StudentProfile::create(['student_id' => 7, 'nickname' => 'seven']);

    $resource = new RelCustomKeysGradeResource();
    $request = hasOneThroughRequest($resource);

    // "age" is the second local key on fx_students; selecting fx_student_profiles.age fails.
    expect(HasOneThrough::make('By age', 'profileByAge', StudentProfileResource::class)->getRelatedColumns($request))
        ->not->toContain('age');

    $loaded = CustomKeysGrade::with($resource->getEagerLoadRelations($request))->find($grade->id);

    expect($loaded->profileByAge->nickname)->toBe('seven');
})->skip('ISSUE REL-006: getRelatedColumns() adds getSecondLocalKeyName() (an intermediate-table column) to the far table select');

// --- fill / sorting / filters / export ---------------------------------

it('is read-only: fill never touches the parent model', function () {
    $grade = new Grade(['name' => 'G']);

    HasOneThrough::make('P', 'firstStudentProfile')->fill(createNadotaRequest(['' => 'x', 'firstStudentProfile' => 1]), $grade);

    expect($grade->getDirty())->toBe(['name' => 'G']);
});

it('does not alter the parent query when sorting', function () {
    $grade = new Grade();
    $query = $grade->newQuery();

    expect(HasOneThrough::make('P', 'firstStudentProfile')->applySorting($query, 'asc', $grade))->toBe($query)
        ->and($query->getQuery()->orders)->toBeNull();
});

it('exposes a whereHas relation filter when filterable', function () {
    $filters = HasOneThrough::make('P', 'firstStudentProfile', StudentProfileResource::class)->filterable()->filters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(RelationFilter::class)
        ->and($filters[0]->key())->toBe('firstStudentProfile');
})->skip('ISSUE REL-001: the filter is keyed by the empty field key ""');

it('exports the far related label like HasOne does', function () {
    $grade = hasOneThroughGrade('Annie');

    $value = HasOneThrough::make('P', 'firstStudentProfile', StudentProfileResource::class)->displayAttribute('nickname')
        ->resolveForExport(createNadotaRequest(), $grade, new GradeResource());

    expect($value)->toBe('Annie');
})->skip('ISSUE REL-008: HasOneThrough has no resolveForExport(); export falls back to resolve() and json-encodes the item');

it('is exported as a single value when shown on the index, like HasOne', function () {
    $resource = new class extends Resource {
        public string $model = Grade::class;
        public function fields(NadotaRequest $request): array
        {
            return [HasOneThrough::make('Related', 'firstStudentProfile', StudentProfileResource::class)->showOnIndex()];
        }
    };

    expect($resource->getExportableFields(createNadotaRequest()))->toHaveCount(1);
})->skip('ISSUE REL-008: ResourceExportable::isFieldExportable() only whitelists BelongsTo, MorphTo and HasOne single-value relations');
