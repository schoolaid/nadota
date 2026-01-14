<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\RelationIndexService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use Illuminate\Http\Request;
use ReflectionClass;

beforeEach(function () {
    $this->service = new RelationIndexService();
    $this->reflection = new ReflectionClass(RelationIndexService::class);

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
            ];
        }
    };
});

it('applyFilters method exists and is callable', function () {
    expect($this->reflection->hasMethod('applyFilters'))->toBeTrue();

    $method = $this->reflection->getMethod('applyFilters');
    $method->setAccessible(true);

    expect($method->isProtected())->toBeTrue();
});

it('applies filter to relation query', function () {
    $testModel = TestModel::factory()->create();

    RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Alpha'
    ]);
    RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Beta'
    ]);
    RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Alpha Two'
    ]);

    // Create request with filter
    $request = createNadotaRequest(['filters' => ['title' => 'Alpha']]);

    // Get the query
    $query = $testModel->relatedModels();

    // Create a HasMany field
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    // Call applyFilters method
    $method = $this->reflection->getMethod('applyFilters');
    $method->setAccessible(true);
    $filteredQuery = $method->invoke($this->service, $request, $query, $field, $this->relatedResource);

    // Execute query and check results
    $results = $filteredQuery->get();

    expect($results)->toHaveCount(2);
    expect($results->pluck('title')->toArray())->toContain('Alpha')
        ->and($results->pluck('title')->toArray())->toContain('Alpha Two')
        ->and($results->pluck('title')->toArray())->not->toContain('Beta');
});

it('returns unmodified query when no filters provided', function () {
    $testModel = TestModel::factory()->create();

    RelatedModel::factory()->count(3)->create(['test_model_id' => $testModel->id]);

    // Create request without filters
    $request = createNadotaRequest([]);

    // Get the query
    $query = $testModel->relatedModels();

    // Create a HasMany field
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    // Call applyFilters method
    $method = $this->reflection->getMethod('applyFilters');
    $method->setAccessible(true);
    $filteredQuery = $method->invoke($this->service, $request, $query, $field, $this->relatedResource);

    // Execute query - should return all records
    $results = $filteredQuery->get();

    expect($results)->toHaveCount(3);
});

it('returns unmodified query when resource has no filterable fields', function () {
    $noFilterResource = new class extends Resource {
        public string $model = RelatedModel::class;

        public static function getKey(): string
        {
            return 'related-models';
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Title', 'title'), // Not filterable
            ];
        }
    };

    $testModel = TestModel::factory()->create();

    RelatedModel::factory()->count(3)->create(['test_model_id' => $testModel->id]);

    // Create request with filter
    $request = createNadotaRequest(['filters' => ['title' => 'something']]);

    // Get the query
    $query = $testModel->relatedModels();

    // Create a HasMany field
    $field = HasMany::make('Related Models', 'relatedModels', get_class($noFilterResource));

    // Call applyFilters method
    $method = $this->reflection->getMethod('applyFilters');
    $method->setAccessible(true);
    $filteredQuery = $method->invoke($this->service, $request, $query, $field, $noFilterResource);

    // Execute query - should return all records (filter ignored)
    $results = $filteredQuery->get();

    expect($results)->toHaveCount(3);
});

it('handles empty filter values correctly', function () {
    $testModel = TestModel::factory()->create();

    RelatedModel::factory()->count(3)->create(['test_model_id' => $testModel->id]);

    // Create request with empty filter value
    $request = createNadotaRequest(['filters' => ['title' => '']]);

    // Get the query
    $query = $testModel->relatedModels();

    // Create a HasMany field
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    // Call applyFilters method
    $method = $this->reflection->getMethod('applyFilters');
    $method->setAccessible(true);
    $filteredQuery = $method->invoke($this->service, $request, $query, $field, $this->relatedResource);

    // Execute query
    $results = $filteredQuery->get();

    // Empty filter should still filter (looking for empty strings)
    // This tests that the method doesn't crash with empty values
    expect($results)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

it('applies multiple filters correctly', function () {
    // Add is_active column for testing
    Schema::table('related_models', function ($table) {
        if (!Schema::hasColumn('related_models', 'is_active')) {
            $table->boolean('is_active')->default(true);
        }
    });

    $multiFilterResource = new class extends Resource {
        public string $model = RelatedModel::class;

        public static function getKey(): string
        {
            return 'related-models';
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Title', 'title')->filterable(),
                Toggle::make('Active', 'is_active')->filterable(),
            ];
        }
    };

    $testModel = TestModel::factory()->create();

    RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Important Task',
        'is_active' => true
    ]);
    RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Important Note',
        'is_active' => false
    ]);
    RelatedModel::factory()->create([
        'test_model_id' => $testModel->id,
        'title' => 'Random Task',
        'is_active' => true
    ]);

    // Create request with multiple filters
    $request = createNadotaRequest([
        'filters' => [
            'title' => 'Important',
            'is_active' => 'true'
        ]
    ]);

    // Get the query
    $query = $testModel->relatedModels();

    // Create a HasMany field
    $field = HasMany::make('Related Models', 'relatedModels', get_class($multiFilterResource));

    // Call applyFilters method
    $method = $this->reflection->getMethod('applyFilters');
    $method->setAccessible(true);
    $filteredQuery = $method->invoke($this->service, $request, $query, $field, $multiFilterResource);

    // Execute query
    $results = $filteredQuery->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->title)->toBe('Important Task');
});

it('returns unmodified query when resource is null', function () {
    $testModel = TestModel::factory()->create();

    RelatedModel::factory()->count(3)->create(['test_model_id' => $testModel->id]);

    // Create request with filter
    $request = createNadotaRequest(['filters' => ['title' => 'something']]);

    // Get the query
    $query = $testModel->relatedModels();

    // Create a HasMany field
    $field = HasMany::make('Related Models', 'relatedModels');

    // Call applyFilters method with null resource
    $method = $this->reflection->getMethod('applyFilters');
    $method->setAccessible(true);
    $filteredQuery = $method->invoke($this->service, $request, $query, $field, null);

    // Execute query - should return all records
    $results = $filteredQuery->get();

    expect($results)->toHaveCount(3);
});

it('includes available filters in metadata', function () {
    $testModel = TestModel::factory()->create();

    RelatedModel::factory()->count(3)->create(['test_model_id' => $testModel->id]);

    // Create request
    $request = createNadotaRequest([]);

    // Get the query
    $query = $testModel->relatedModels();

    // Create a HasMany field
    $field = HasMany::make('Related Models', 'relatedModels', get_class($this->relatedResource));

    // Call getAvailableFilters method
    $method = $this->reflection->getMethod('getAvailableFilters');
    $method->setAccessible(true);
    $filters = $method->invoke($this->service, $request, $field, $this->relatedResource);

    // Should return array of filter configurations
    expect($filters)->toBeArray();
    expect($filters)->not->toBeEmpty();

    // Each filter should have required properties
    foreach ($filters as $filter) {
        expect($filter)->toHaveKey('key');
        expect($filter)->toHaveKey('label');
        expect($filter)->toHaveKey('component');
    }
});

it('returns empty array when resource has no filterable fields', function () {
    $noFilterResource = new class extends Resource {
        public string $model = RelatedModel::class;

        public static function getKey(): string
        {
            return 'related-models';
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Title', 'title'), // Not filterable
            ];
        }
    };

    $testModel = TestModel::factory()->create();

    RelatedModel::factory()->count(3)->create(['test_model_id' => $testModel->id]);

    // Create request
    $request = createNadotaRequest([]);

    // Get the query
    $query = $testModel->relatedModels();

    // Create a HasMany field
    $field = HasMany::make('Related Models', 'relatedModels', get_class($noFilterResource));

    // Call getAvailableFilters method
    $method = $this->reflection->getMethod('getAvailableFilters');
    $method->setAccessible(true);
    $filters = $method->invoke($this->service, $request, $field, $noFilterResource);

    // Should return empty array
    expect($filters)->toBeArray();
    expect($filters)->toBeEmpty();
});

it('returns empty array when resource is null', function () {
    $testModel = TestModel::factory()->create();

    // Create request
    $request = createNadotaRequest([]);

    // Get the query
    $query = $testModel->relatedModels();

    // Create a HasMany field
    $field = HasMany::make('Related Models', 'relatedModels');

    // Call getAvailableFilters method with null resource
    $method = $this->reflection->getMethod('getAvailableFilters');
    $method->setAccessible(true);
    $filters = $method->invoke($this->service, $request, $field, null);

    // Should return empty array
    expect($filters)->toBeArray();
    expect($filters)->toBeEmpty();
});
