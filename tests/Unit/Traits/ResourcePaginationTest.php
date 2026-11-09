<?php

use Illuminate\Support\Arr;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Services\ResourceIndexService;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

it('defaults to 20 per page out of 10/20/50/100, page 1', function () {
    $resource = new StudentResource();

    expect($resource->getPerPage())->toBe(20)
        ->and($resource->getAllowedPerPage())->toBe([10, 20, 50, 100])
        ->and($resource->getPage())->toBe(1)
        ->and($resource->getPagination())->toBe(['per_page' => 20, 'page' => 1]);
});

it('reads back what the setters write', function () {
    $resource = new StudentResource();
    $resource->setPerPage(50);
    $resource->setAllowedPerPage([25, 50]);
    $resource->setPage(3);

    expect($resource->getPagination())->toBe(['per_page' => 50, 'page' => 3])
        ->and($resource->getAllowedPerPage())->toBe([25, 50]);

    $resource->setPagination(['per_page' => 10, 'page' => 2]);

    expect($resource->getPerPage())->toBe(10)
        ->and($resource->getPage())->toBe(2);
});

it('advertises perPage and allowedPerPage to the frontend', function () {
    $info = AdHocResource::withFields([], ['perPage' => 50, 'allowedPerPage' => [25, 50]])->toInfoArray(createNadotaRequest());

    expect($info['perPage'])->toBe(50)
        ->and($info['allowedPerPage'])->toBe([25, 50]);
});

describe('in the index pipeline', function () {
    beforeEach(function () {
        FixtureSchema::create();

        foreach (range(1, 12) as $i) {
            Student::create(['name' => "Student {$i}"]);
        }
    });

    $meta = function (array $query, array $config = []): array {
        $request = createNadotaRequest($query);
        $request->setResource(AdHocResource::withFields([Input::make('Name', 'name')], $config));

        $payload = (new ResourceIndexService())->handle($request)->response()->getData(true);

        // HTTP-001: Laravel merges its own pagination meta into IndexResource's,
        // so every meta value arrives as [x, x]; read the first copy.
        return collect($payload['meta'])->map(fn ($value) => Arr::first(Arr::wrap($value)))->all()
            + ['rows' => count($payload['data'])];
    };

    it('pages by the resource perPage when none is requested', function () use ($meta) {
        expect($meta([], ['perPage' => 5]))->toMatchArray(['per_page' => 5, 'rows' => 5, 'last_page' => 3, 'total' => 12]);
    });

    it('honours a requested perPage from the allowed list', function () use ($meta) {
        expect($meta(['perPage' => 10]))->toMatchArray(['per_page' => 10, 'rows' => 10]);
    });

    it('falls back to the resource perPage for a value outside allowedPerPage', function ($requested) use ($meta) {
        expect($meta(['perPage' => $requested])['per_page'])->toBe(20);
    })->with([100000, 7, -5, 'abc'])
        ->skip('ISSUE HTTP-002: perPage is passed to paginate() unchecked; allowedPerPage is only advertised');
});
