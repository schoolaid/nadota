<?php

use SchoolAid\Nadota\Http\Filters\NumberFilter;
use SchoolAid\Nadota\Http\Filters\SelectFilter;
use SchoolAid\Nadota\Http\Resources\Filters\FilterResource;

it('returns the serialised filter array untouched', function () {
    $descriptor = NumberFilter::make('Age Range', 'age')->range()->toArray(createNadotaRequest());

    expect((new FilterResource($descriptor))->toArray(createNadotaRequest()))->toBe($descriptor);
});

it('wraps a collection of descriptors under "data", preserving order', function () {
    $request = createNadotaRequest();
    $descriptors = [
        SelectFilter::make('Status', 'status')->options(['Draft' => 'draft'])->toArray($request),
        NumberFilter::make('Age', 'age')->toArray($request),
    ];

    $payload = FilterResource::collection($descriptors)->response($request)->getData(true);

    expect($payload)->toBe(['data' => $descriptors]);
});

it('serialises an empty filter list as an empty data array', function () {
    expect(FilterResource::collection([])->response(createNadotaRequest())->getData(true))->toBe(['data' => []]);
});
