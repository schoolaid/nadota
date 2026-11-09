<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\HasOne;
use SchoolAid\Nadota\Http\Filters\RelationFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Models\StudentProfile;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentProfileResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;
use SchoolAid\Nadota\Tests\Models\Profile;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;

beforeEach(function () {
    FixtureSchema::create();
});

function hasOneStudentWithProfile(string $nickname = 'Annie'): Student
{
    $student = Student::create(['name' => 'Ana']);
    StudentProfile::create(['student_id' => $student->id, 'nickname' => $nickname]);

    return $student;
}

function hasOneRequestFor(Resource $resource): NadotaRequest
{
    $request = createNadotaRequest();
    $request->setResource($resource);

    return $request;
}

// --- construction -------------------------------------------------------

it('uses the relation name as key and leaves the attribute empty', function () {
    $field = HasOne::make('Profile', 'profile');

    // An empty attribute means the field never adds a column to the parent SELECT.
    expect($field)->toBeField()
        ->and($field->key())->toBe('profile')
        ->and($field->getRelation())->toBe('profile')
        ->and($field->getAttribute())->toBe('')
        ->and($field->getType())->toBe('hasOne')
        ->and($field->getComponent())->toBe('FieldHasOne')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->relationType())->toBe('hasOne')
        ->and($field->isHasOne())->toBeTrue()
        ->and($field->getColumnsForSelect(Student::class))->toBe([]);
});

it('is only shown on detail and only applied to the show query by default', function () {
    $field = HasOne::make('Profile', 'profile');
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeTrue()
        ->and($field->isShowOnCreation($request, null))->toBeFalse()
        ->and($field->isShowOnUpdate($request, null))->toBeFalse()
        ->and($field->isAppliedInIndexQuery())->toBeFalse()
        ->and($field->isAppliedInShowQuery())->toBeTrue();
});

it('can set the related model and display attribute', function () {
    $field = HasOne::make('Profile', 'profile')->model(Profile::class)->displayAttribute('bio');

    expect($field->getModel())->toBe(Profile::class)
        ->and($field->getAttributeForDisplay())->toBe('bio');
});

// --- resolve ------------------------------------------------------------

it('resolves the related model with the related resource key', function () {
    $student = hasOneStudentWithProfile('Annie');
    $profile = $student->profile;

    $value = HasOne::make('Profile', 'profile', StudentProfileResource::class)
        ->displayAttribute('nickname')
        ->resolve(createNadotaRequest(), $student, null);

    expect($value)->toBe([
        'id' => $profile->id,
        'key' => $profile->id,
        'label' => 'Annie',
        'resource' => 'student-profile',
        'deletedAt' => null,
    ]);
});

it('resolves the label from the related resource displayLabel when no display attribute is set', function () {
    $student = hasOneStudentWithProfile();

    // StudentProfile has none of the common label attributes, so displayLabel falls back to the key.
    $value = HasOne::make('Profile', 'profile', StudentProfileResource::class)
        ->resolve(createNadotaRequest(), $student, null);

    expect($value['label'])->toBe((string) $student->profile->id);
});

it('formats without resource as id, label, null resource and deletedAt', function () {
    $testModel = TestModel::factory()->create();
    $profile = Profile::factory()->create(['test_model_id' => $testModel->id, 'bio' => 'User bio content']);

    $value = HasOne::make('Profile', 'profile')->displayAttribute('bio')
        ->resolve(createNadotaRequest(), $testModel, null);

    expect($value)->toBe([
        'id' => $profile->id,
        'label' => 'User bio content',
        'resource' => null,
        'deletedAt' => null,
    ]);
});

it('uses the displayUsing callback for the label', function () {
    $student = hasOneStudentWithProfile('Annie');

    $value = HasOne::make('Profile', 'profile', StudentProfileResource::class)
        ->displayUsing(fn ($profile) => strtoupper($profile->nickname))
        ->resolve(createNadotaRequest(), $student, null);

    expect($value['label'])->toBe('ANNIE');
});

it('includes rendered related fields as attributes only with withFields()', function () {
    $student = hasOneStudentWithProfile('Annie');
    $request = createNadotaRequest();

    $without = HasOne::make('Profile', 'profile', StudentProfileResource::class)->resolve($request, $student, null);
    $with = HasOne::make('Profile', 'profile', StudentProfileResource::class)
        ->fields([Input::make('Nickname', 'nickname')])
        ->withFields()
        ->resolve($request, $student, null);

    expect($without)->not->toHaveKey('attributes')
        ->and($with['attributes'])->toHaveCount(1)
        ->and($with['attributes'][0]['value'])->toBe('Annie')
        ->and($with)->not->toHaveKey('permissions');
});

it('returns null when no related model exists', function () {
    $testModel = TestModel::factory()->create();

    expect(HasOne::make('Profile', 'profile')->resolve(createNadotaRequest(), $testModel, null))->toBeNull();
});

it('returns the default when no related model exists and a default is set', function () {
    $student = Student::create(['name' => 'Ana']);

    $value = HasOne::make('Profile', 'profile')->default(['label' => 'none'])
        ->resolve(createNadotaRequest(), $student, null);

    expect($value)->toBe(['label' => 'none']);
});

it('returns null when the parent model does not define the relation method', function () {
    $student = Student::create(['name' => 'Ana']);

    expect(HasOne::make('Passport', 'passport')->resolve(createNadotaRequest(), $student, null))->toBeNull();
});

// --- toArray / props ----------------------------------------------------

it('serializes field metadata and the resolved value', function () {
    $testModel = TestModel::factory()->create();
    $profile = Profile::factory()->create(['test_model_id' => $testModel->id, 'bio' => 'User bio']);

    $array = HasOne::make('Profile', 'profile')
        ->displayAttribute('bio')
        ->sortable()
        ->filterable()
        ->toArray(createNadotaRequest(), $testModel, new TestResource());

    expect($array)
        ->toHaveKey('label', 'Profile')
        ->toHaveKey('key', 'profile')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->and($array['props'])->toBe(['relationType' => 'hasOne', 'resource' => null])
        ->and($array['value'])->toMatchArray(['id' => $profile->id, 'label' => 'User bio'])
        ->and($array['optionsUrl'])->toBe('/nadota-api/test/resource/field/profile/options');
});

it('adds create/show urls and a foreign-key createContext with a model and related resource', function () {
    $student = Student::create(['name' => 'Ana']);

    $props = HasOne::make('Profile', 'profile', StudentProfileResource::class)
        ->toArray(createNadotaRequest(), $student, new StudentResource())['props'];

    expect($props['relationType'])->toBe('hasOne')
        ->and($props['resource'])->toBe('student-profile')
        ->and($props['urls'])->toBe([
            'create' => '/nadota-api/student-profile/resource/create',
            'show' => '/nadota-api/student-profile/resource',
        ])
        ->and($props['createContext'])->toBe([
            'parentResource' => 'student',
            'parentId' => $student->id,
            'relatedResource' => 'student-profile',
            'foreignKey' => 'student_id',
            'prefill' => ['student_id' => $student->id],
            'lock' => ['student_id'],
            'returnUrl' => "/resources/student/{$student->id}",
            'createUrl' => '/nadota-api/student-profile/resource/create',
            'storeUrl' => '/nadota-api/student-profile/resource',
        ]);
});

it('omits urls and createContext without a model', function () {
    $props = HasOne::make('Profile', 'profile', StudentProfileResource::class)
        ->toArray(createNadotaRequest(), null, new StudentResource())['props'];

    expect($props)->toBe(['relationType' => 'hasOne', 'resource' => 'student-profile']);
});

// --- eager loading ------------------------------------------------------

it('adds the related foreign key to the eager-load columns', function () {
    $columns = HasOne::make('Profile', 'profile', StudentProfileResource::class)
        ->getRelatedColumns(hasOneRequestFor(new StudentResource()));

    // StudentProfileResource: nickname, about, student (BelongsTo -> student_id) + id
    expect($columns)->toEqualCanonicalizing(['nickname', 'about', 'student_id', 'id']);
});

it('adds the foreign key even when the related resource does not select it', function () {
    $profileResource = new class extends Resource {
        public string $model = StudentProfile::class;
        public function fields(NadotaRequest $request): array { return [Input::make('Nickname', 'nickname')]; }
    };

    $columns = HasOne::make('Profile', 'profile', get_class($profileResource))
        ->getRelatedColumns(hasOneRequestFor(new StudentResource()));

    expect($columns)->toEqualCanonicalizing(['nickname', 'id', 'student_id']);
});

// --- fill / sorting / filters / export ---------------------------------

it('never fills the parent model', function () {
    $student = new Student(['name' => 'Ana']);

    HasOne::make('Profile', 'profile')->fill(createNadotaRequest(['profile' => ['nickname' => 'x'], '' => 'y']), $student);

    expect($student->getDirty())->toBe(['name' => 'Ana']);
});

it('does not alter the parent query when sorting', function () {
    $testModel = new TestModel();
    $query = $testModel->newQuery();

    $sortedQuery = HasOne::make('Profile', 'profile')->displayAttribute('bio')->applySorting($query, 'asc', $testModel);

    expect($sortedQuery)->toBe($query)
        ->and($sortedQuery->getQuery()->joins)->toBeNull()
        ->and($sortedQuery->getQuery()->orders)->toBeNull();
});

it('exposes a whereHas relation filter keyed by the relation when filterable', function () {
    $filters = HasOne::make('Profile', 'profile', StudentProfileResource::class)->filterable()->filters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(RelationFilter::class)
        ->and($filters[0]->key())->toBe('profile');
});

it('exports the related label', function () {
    $student = hasOneStudentWithProfile('Annie');

    $value = HasOne::make('Profile', 'profile', StudentProfileResource::class)->displayAttribute('nickname')
        ->resolveForExport(createNadotaRequest(), $student, new StudentResource());

    expect($value)->toBe('Annie');
});

it('exports null when there is no related model', function () {
    $student = Student::create(['name' => 'Ana']);

    expect(HasOne::make('Profile', 'profile')->resolveForExport(createNadotaRequest(), $student, null))->toBeNull();
});

it('is exported as a single value when shown on the index', function () {
    $resource = new class extends Resource {
        public string $model = Student::class;
        public function fields(NadotaRequest $request): array
        {
            return [Input::make('Name', 'name'), HasOne::make('Profile', 'profile', StudentProfileResource::class)->showOnIndex()];
        }
    };

    expect($resource->getExportableFields(createNadotaRequest())->map->key()->values()->all())->toBe(['name', 'profile']);
});
