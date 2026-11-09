<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Filters\NumberFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\RelationIndexService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;

beforeEach(function () {
    $this->service = new RelationIndexService();
    $this->reflection = new ReflectionClass(RelationIndexService::class);

    // Related resource with a filterable text field and a filterable BelongsTo.
    $this->relatedResource = new class extends Resource {
        public string $model = RelatedModel::class;

        public static function getKey(): string
        {
            return 'related-models';
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Title', 'title')->filterable()->searchable(),
                BelongsTo::make('Test Model', 'testModel', TestResource::class)->filterable(),
            ];
        }
    };

    $this->parentResource = new class extends Resource {
        public string $model = TestModel::class;

        public static function getKey(): string
        {
            return 'test-models';
        }

        public function fields(NadotaRequest $request): array
        {
            return [Input::make('Name', 'name')];
        }
    };

    $this->invoke = function (string $method, ...$arguments) {
        $reflected = $this->reflection->getMethod($method);
        $reflected->setAccessible(true);

        return $reflected->invoke($this->service, ...$arguments);
    };
});

// --- getAvailableFilters(): what the relation listing advertises under meta.filters --------

it('includes filter configuration in relation metadata', function () {
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    $filters = ($this->invoke)('getAvailableFilters', createNadotaRequest(), $field, $this->relatedResource);

    expect(array_column($filters, 'key'))->toBe(['title', 'test_model_id']);
});

it('returns no filters when the relation has no related resource', function () {
    $field = HasMany::make('Related Models', 'relatedModels');

    expect(($this->invoke)('getAvailableFilters', createNadotaRequest(), $field, null))->toBe([]);
});

it('generates correct filter endpoints using related resource key', function () {
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    $filters = ($this->invoke)('getAvailableFilters', createNadotaRequest(), $field, $this->relatedResource);
    $belongsToFilter = collect($filters)->firstWhere('key', 'test_model_id');

    // The endpoint uses the RELATED resource key (related-models), not the parent's (test-models).
    expect($belongsToFilter['endpoint'])->toBe('/nadota-api/related-models/resource/field/test_model_id/options');
});

it('includes all filter properties in serialized output', function () {
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    $filters = ($this->invoke)('getAvailableFilters', createNadotaRequest(), $field, $this->relatedResource);

    foreach ($filters as $filter) {
        expect($filter)->toHaveKeys(['key', 'label', 'component', 'type', 'options', 'value', 'props', 'isRange', 'filterKeys']);
    }
});

it('creates temporary request with related resource for filter serialization', function () {
    $request = createNadotaRequest();
    $request->setResource($this->parentResource);

    $relatedRequest = ($this->invoke)('createRelatedResourceRequest', $request, $this->relatedResource);

    expect($relatedRequest->getResource())->toBe($this->relatedResource)
        ->and($request->getResource())->toBe($this->parentResource);
});

it('handles MorphTo filters correctly in relation context', function () {
    $studentResource = new class extends Resource {
        public string $model = TestModel::class;
        public static function getKey(): string { return 'students'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $userResource = new class extends Resource {
        public string $model = RelatedModel::class;
        public static function getKey(): string { return 'users'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $morphResource = new class($studentResource, $userResource) extends Resource {
        public string $model = RelatedModel::class;

        public function __construct(protected $studentResource, protected $userResource)
        {
            parent::__construct();
        }

        public static function getKey(): string
        {
            return 'morph-models';
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Title', 'title'),
                MorphTo::make('Targetable', 'targetable', [
                    'student' => get_class($this->studentResource),
                    'user' => get_class($this->userResource),
                ])->filterable(),
            ];
        }
    };

    $field = HasMany::make('Related Models', 'relatedModels', get_class($morphResource));
    $filters = collect(($this->invoke)('getAvailableFilters', createNadotaRequest(), $field, $morphResource));

    expect($filters->pluck('key')->all())->toBe(['targetable_type', 'targetable_id']);

    $typeFilter = $filters->firstWhere('key', 'targetable_type');
    expect($typeFilter['component'])->toBe('FilterSelect')
        ->and(array_column($typeFilter['options'], 'value'))->toBe(['student', 'user']);

    $entityFilter = $filters->firstWhere('key', 'targetable_id');
    expect($entityFilter['component'])->toBe('FilterDynamicSelect')
        // The morph endpoint is built from the RELATED resource key (morph-models).
        ->and($entityFilter['endpoint'])->toBe('/nadota-api/morph-models/resource/field/targetable/morph-options/{morphType}')
        ->and($entityFilter['props']['isMorphEndpoint'])->toBeTrue()
        ->and($entityFilter['props']['endpointTemplate'])->toBe($entityFilter['endpoint']);
});

// --- applyFilters(): what the relation listing actually does with filters[...] ----------

function relationIndexFilteredNames(object $test, array $requestFilters, array $fields, array $resourceFilters = []): array
{
    FixtureSchema::create();
    $grade = Grade::create(['name' => 'G1']);
    $other = Grade::create(['name' => 'G2']);
    foreach ([['Ana', 8, 'draft'], ['Bob', 10, 'enrolled'], ['Cid', 12, 'enrolled']] as [$name, $age, $status]) {
        Student::create(['name' => $name, 'age' => $age, 'status' => $status, 'grade_id' => $grade->id]);
    }
    Student::create(['name' => 'Zed', 'age' => 10, 'status' => 'enrolled', 'grade_id' => $other->id]);

    $studentResource = new class($fields, $resourceFilters) extends Resource {
        public string $model = Student::class;

        public function __construct(private array $testFields, private array $testFilters)
        {
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            return $this->testFields;
        }

        public function filters(NadotaRequest $request): array
        {
            return $this->testFilters;
        }
    };

    $field = HasMany::make('Students', 'students', get_class($studentResource));
    $query = ($test->invoke)('applyFilters', createNadotaRequest(['filters' => $requestFilters]), $grade->students(), $field, $studentResource);

    return $query->orderBy('name')->pluck('name')->all();
}

it('applies the related resource field filters to the relation query only', function () {
    $names = relationIndexFilteredNames($this, ['age' => '10'], [Number::make('Age', 'age')->filterable()]);

    // Zed is 10 too, but belongs to another grade.
    expect($names)->toBe(['Bob']);
});

it('applies the related resource filters() too, AND-combined with field filters', function () {
    $names = relationIndexFilteredNames(
        $this,
        ['status' => 'enrolled', 'age' => '12'],
        [Select::make('Status', 'status')->options(['draft' => 'Draft', 'enrolled' => 'Enrolled'])->filterable()],
        [new NumberFilter('Age', 'age')]
    );

    expect($names)->toBe(['Cid']);
});

it('leaves the relation query untouched without filters or with unknown keys', function () {
    expect(relationIndexFilteredNames($this, [], [Number::make('Age', 'age')->filterable()]))->toBe(['Ana', 'Bob', 'Cid'])
        ->and(relationIndexFilteredNames($this, ['nope' => '1'], [Number::make('Age', 'age')->filterable()]))->toBe(['Ana', 'Bob', 'Cid']);
});

it('normalises {key}_from / {key}_to ranges like the main index does', function () {
    $names = relationIndexFilteredNames($this, ['age_from' => '9', 'age_to' => '12'], [Number::make('Age', 'age')->filterableRange()]);

    expect($names)->toBe(['Bob', 'Cid']);
})->skip('ISSUE FLT-010: RelationIndexService::applyFilters() passes filters[] straight to FilterCriteria, skipping ApplyFiltersPipe::normalizeRangeFilters()');

it('ignores filters of related fields hidden from the index, which it does not advertise either', function () {
    // Listing (getAvailableFilters) and applying both use fieldsForIndex() here, so they agree —
    // unlike the main index, whose /filters endpoint uses flattenFields() (FLT-004).
    $names = relationIndexFilteredNames($this, ['age' => '10'], [Number::make('Age', 'age')->filterable()->hideFromIndex()]);

    expect($names)->toBe(['Ana', 'Bob', 'Cid']);
});
