<?php

use SchoolAid\Nadota\Http\Traits\MemoizesFields;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Section;
use Illuminate\Support\Collection;

beforeEach(function () {
    // Create a test resource class that uses the trait
    $this->resourceClass = new class {
        use MemoizesFields;

        public int $fieldsCallCount = 0;

        public function fields(NadotaRequest $request): array
        {
            $this->fieldsCallCount++;
            
            return [
                Input::make('Name', 'name'),
                Input::make('Email', 'email'),
                new Section('Details', [
                    Input::make('Phone', 'phone'),
                    Input::make('Address', 'address'),
                ]),
            ];
        }

        public function flattenFieldsWithoutMemoization(NadotaRequest $request): Collection
        {
            $fields = $this->getMemoizedFields($request);
            
            return collect($fields)
                ->flatMap(function ($item) {
                    if ($item instanceof Section) {
                        return $item->getFields();
                    }
                    return [$item];
                })
                ->values();
        }
    };

    $this->request = new NadotaRequest();
});

it('memoizes fields() calls', function () {
    $resource = $this->resourceClass;
    $request = $this->request;

    // First call
    $fields1 = $resource->getMemoizedFields($request);
    expect($resource->fieldsCallCount)->toBe(1);

    // Second call - should use cache
    $fields2 = $resource->getMemoizedFields($request);
    expect($resource->fieldsCallCount)->toBe(1);

    // Should return same array reference
    expect($fields1)->toBe($fields2);
});

it('memoizes flattened fields', function () {
    $resource = $this->resourceClass;
    $request = $this->request;

    // First call
    $flattened1 = $resource->getMemoizedFlattenedFields($request);
    expect($resource->fieldsCallCount)->toBe(1);
    expect($flattened1)->toBeInstanceOf(Collection::class);
    expect($flattened1->count())->toBe(4); // 2 top-level + 2 in section

    // Second call - should use cache
    $flattened2 = $resource->getMemoizedFlattenedFields($request);
    expect($resource->fieldsCallCount)->toBe(1); // Still only 1 call

    // Should return same collection reference
    expect($flattened1)->toBe($flattened2);
});

it('can clear memoization cache', function () {
    $resource = $this->resourceClass;
    $request = $this->request;

    // First call
    $resource->getMemoizedFields($request);
    expect($resource->fieldsCallCount)->toBe(1);

    // Clear cache
    $resource->clearFieldMemoizationCache();

    // Next call should re-execute fields()
    $resource->getMemoizedFields($request);
    expect($resource->fieldsCallCount)->toBe(2);
});

it('caches fields and flattened fields independently', function () {
    $resource = $this->resourceClass;
    $request = $this->request;

    // Call getMemoizedFields
    $resource->getMemoizedFields($request);
    expect($resource->fieldsCallCount)->toBe(1);

    // Call getMemoizedFlattenedFields - should reuse fields cache
    $resource->getMemoizedFlattenedFields($request);
    expect($resource->fieldsCallCount)->toBe(1); // No additional calls

    // Both should be cached now
    $resource->getMemoizedFields($request);
    $resource->getMemoizedFlattenedFields($request);
    expect($resource->fieldsCallCount)->toBe(1); // Still only 1 call total
});
