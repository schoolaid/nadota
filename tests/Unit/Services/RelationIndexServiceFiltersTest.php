<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\RelationIndexService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use ReflectionClass;

beforeEach(function () {
    $this->service = new RelationIndexService();
    $this->reflection = new ReflectionClass(RelationIndexService::class);

    // Create a simple related resource with various filterable fields
    $this->relatedResource = new class extends Resource {
        public string $model = RelatedModel::class;

        public static function getKey(): string
        {
            return 'related-models';
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Title', 'title')
                    ->filterable()
                    ->searchable(),
                BelongsTo::make('Test Model', 'testModel', TestModelResource::class)
                    ->filterable(),
            ];
        }
    };

    // Parent resource
    $this->parentResource = new class extends Resource {
        public string $model = TestModel::class;

        public static function getKey(): string
        {
            return 'test-models';
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name'),
                HasMany::make('Related Models', 'relatedModels', get_class($GLOBALS['relatedResource'] ?? null)),
            ];
        }
    };
});

it('includes filter configuration in relation metadata', function () {
    $testModel = TestModel::factory()->create();
    RelatedModel::factory()->count(3)->create(['test_model_id' => $testModel->id]);

    $request = createNadotaRequest([]);

    $query = $testModel->relatedModels();
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    $method = $this->reflection->getMethod('getAvailableFilters');
    $method->setAccessible(true);
    $filters = $method->invoke($this->service, $request, $field, $this->relatedResource);

    expect($filters)->toBeArray()
        ->and($filters)->not->toBeEmpty();

    // Should have filters for the filterable fields
    $filterKeys = array_column($filters, 'key');
    expect($filterKeys)->toContain('title');
});

it('generates correct filter endpoints using related resource key', function () {
    $testModel = TestModel::factory()->create();
    RelatedModel::factory()->count(3)->create(['test_model_id' => $testModel->id]);

    $request = createNadotaRequest([]);

    $query = $testModel->relatedModels();
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    $method = $this->reflection->getMethod('getAvailableFilters');
    $method->setAccessible(true);
    $filters = $method->invoke($this->service, $request, $field, $this->relatedResource);

    // Find the BelongsTo filter (it's a DynamicSelectFilter)
    $belongsToFilter = collect($filters)->firstWhere('key', 'test_model_id');

    expect($belongsToFilter)->not->toBeNull()
        ->and($belongsToFilter)->toHaveKey('endpoint')
        // Endpoint should use the RELATED resource key (related-models), not parent (test-models)
        ->and($belongsToFilter['endpoint'])->toContain('/related-models/resource/field/');
});

it('includes all filter properties in serialized output', function () {
    $testModel = TestModel::factory()->create();
    RelatedModel::factory()->count(3)->create(['test_model_id' => $testModel->id]);

    $request = createNadotaRequest([]);

    $query = $testModel->relatedModels();
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    $method = $this->reflection->getMethod('getAvailableFilters');
    $method->setAccessible(true);
    $filters = $method->invoke($this->service, $request, $field, $this->relatedResource);

    // Each filter should have all required properties
    foreach ($filters as $filter) {
        expect($filter)->toHaveKey('key')
            ->and($filter)->toHaveKey('label')
            ->and($filter)->toHaveKey('component')
            ->and($filter)->toHaveKey('type')
            ->and($filter)->toHaveKey('options')
            ->and($filter)->toHaveKey('value')
            ->and($filter)->toHaveKey('props')
            ->and($filter)->toHaveKey('isRange')
            ->and($filter)->toHaveKey('filterKeys');
    }
});

it('creates temporary request with related resource for filter serialization', function () {
    $testModel = TestModel::factory()->create();

    // Original request has parent resource
    $request = createNadotaRequest([]);
    $request->setResource($this->parentResource);

    $query = $testModel->relatedModels();
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    // Get the createRelatedResourceRequest method
    $method = $this->reflection->getMethod('createRelatedResourceRequest');
    $method->setAccessible(true);
    $relatedRequest = $method->invoke($this->service, $request, $this->relatedResource);

    // The cloned request should have the related resource
    expect($relatedRequest->getResource())->toBe($this->relatedResource);

    // Original request should still have parent resource
    expect($request->getResource())->toBe($this->parentResource);
});

it('handles MorphTo filters correctly in relation context', function () {
    // Need to create actual resource classes for MorphTo
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

    // Create a resource with a MorphTo field
    $morphResource = new class($studentResource, $userResource) extends Resource {
        protected $studentResource;
        protected $userResource;

        public function __construct($studentResource, $userResource) {
            $this->studentResource = $studentResource;
            $this->userResource = $userResource;
            parent::__construct();
        }

        public string $model = RelatedModel::class;

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

    $testModel = TestModel::factory()->create();
    $request = createNadotaRequest([]);

    $query = $testModel->relatedModels();
    $field = HasMany::make('Related Models', 'relatedModels', get_class($morphResource));

    $method = $this->reflection->getMethod('getAvailableFilters');
    $method->setAccessible(true);
    $filters = $method->invoke($this->service, $request, $field, $morphResource);

    // MorphTo should generate 2 filters
    $morphFilters = collect($filters)->filter(fn($f) =>
        str_contains($f['key'], 'targetable')
    );

    expect($morphFilters->count())->toBe(2);

    // Type filter
    $typeFilter = $morphFilters->firstWhere('key', 'targetable_type');
    expect($typeFilter)->not->toBeNull()
        ->and($typeFilter['component'])->toBe('FilterSelect')
        ->and($typeFilter['options'])->toBeArray()
        ->and(count($typeFilter['options']))->toBe(2);

    // Entity filter
    $entityFilter = $morphFilters->firstWhere('key', 'targetable_id');
    expect($entityFilter)->not->toBeNull()
        ->and($entityFilter['component'])->toBe('FilterDynamicSelect')
        ->and($entityFilter['endpoint'])->toContain('/morph-options/{morphType}')
        // Should use the RELATED resource key (morph-models)
        ->and($entityFilter['endpoint'])->toContain('/morph-models/resource/field/')
        ->and($entityFilter['props']['isMorphEndpoint'])->toBe(true)
        ->and($entityFilter['props']['endpointTemplate'])->toContain('{morphType}');
});

// Helper to create TestModelResource class reference
class TestModelResource extends Resource
{
    public string $model = TestModel::class;

    public static function getKey(): string
    {
        return 'test-models';
    }

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name'),
        ];
    }
}
