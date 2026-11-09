<?php

use Illuminate\Support\Arr;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Services\ResourceIndexService;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

describe('configuration', function () {
    it('starts with nothing searchable under the globalSearch key', function () {
        $resource = AdHocResource::withFields([]);

        expect($resource->getSearchKey())->toBe('globalSearch')
            ->and($resource->isSearchable())->toBeFalse()
            ->and($resource->getSearchableAttributes())->toBe([])
            ->and($resource->getSearchableRelations())->toBe([])
            ->and($resource->getAllSearchableItems())->toBe([]);
    });

    it('reads the attributes and relations a resource declares', function () {
        $resource = new StudentResource();

        expect($resource->getSearchableAttributes())->toBe(['name', 'email'])
            ->and($resource->getSearchableRelations())->toBe(['grade.name'])
            ->and($resource->getAllSearchableItems())->toBe(['name', 'email', 'grade.name'])
            ->and($resource->isAttributeSearchable('email'))->toBeTrue()
            ->and($resource->isAttributeSearchable('bio'))->toBeFalse()
            ->and($resource->isRelationSearchable('grade.name'))->toBeTrue();
    });

    it('is searchable with relations only', function () {
        expect(AdHocResource::withFields([], ['searchableRelations' => ['grade.name']])->isSearchable())->toBeTrue();
    });

    it('adds without duplicates and removes keeping a list', function () {
        $resource = AdHocResource::withFields([])
            ->addSearchableAttribute('name')
            ->addSearchableAttribute('email')
            ->addSearchableAttribute('name')
            ->addSearchableRelation('grade.name')
            ->addSearchableRelation('grade.name');

        expect($resource->getSearchableAttributes())->toBe(['name', 'email'])
            ->and($resource->getSearchableRelations())->toBe(['grade.name']);

        $resource->removeSearchableAttribute('name')->removeSearchableRelation('grade.name');

        expect($resource->getSearchableAttributes())->toBe(['email'])
            ->and($resource->getSearchableRelations())->toBe([]);
    });

    it('replaces and clears the configuration', function () {
        $resource = (new StudentResource())
            ->setSearchableAttributes(['bio'])
            ->setSearchableRelations(['profile.nickname']);

        expect($resource->getAllSearchableItems())->toBe(['bio', 'profile.nickname']);

        $resource->clearSearchable();

        expect($resource->isSearchable())->toBeFalse();
    });

    it('tells the frontend the search key and whether search is enabled', function () {
        $info = AdHocResource::withFields([], ['searchKey' => 'q', 'searchableAttributes' => ['name']])->toInfoArray(createNadotaRequest());

        expect($info['search'])->toBe(['key' => 'q', 'enabled' => true]);
    });

    it('reports search as disabled when only fields are marked ->searchable()', function () {
        // Field-level ->searchable() flags are not read by any search (TRT-012).
        $resource = AdHocResource::withFields([Input::make('Name', 'name')->searchable()]);

        expect($resource->isSearchable())->toBeFalse()
            ->and($resource->toInfoArray(createNadotaRequest())['search']['enabled'])->toBeFalse();
    });
});

describe('index search', function () {
    beforeEach(function () {
        FixtureSchema::create();

        $north = School::create(['name' => 'North High']);
        $first = Grade::create(['name' => 'First', 'school_id' => $north->id]);
        $second = Grade::create(['name' => 'Second']);

        Student::create(['name' => 'Ana', 'email' => 'ana@example.com', 'grade_id' => $second->id, 'bio' => 'zebra']);
        Student::create(['name' => 'Beto', 'email' => 'beto@school.test', 'grade_id' => $first->id]);
        Student::create(['name' => 'Carla', 'email' => 'carla@example.com', 'bio' => 'zebra']);
    });

    $search = function ($resource, array $query): array {
        $request = createNadotaRequest($query + ['sortField' => 'name', 'sortDirection' => 'asc']);
        $request->setResource($resource);

        $payload = (new ResourceIndexService())->handle($request)->response()->getData(true);

        return collect($payload['data'])
            ->map(fn ($row) => Arr::first(Arr::wrap(collect($row['attributes'])->firstWhere('key', 'name')['value'])))
            ->all();
    };

    it('matches any searchable attribute', function () use ($search) {
        expect($search(new StudentResource(), ['globalSearch' => 'example']))->toBe(['Ana', 'Carla'])
            ->and($search(new StudentResource(), ['globalSearch' => 'bet']))->toBe(['Beto']);
    });

    it('matches through a searchable relation', function () use ($search) {
        expect($search(new StudentResource(), ['globalSearch' => 'Second']))->toBe(['Ana']);
    });

    it('follows nested relation paths', function () use ($search) {
        $resource = AdHocResource::withFields(
            [Input::make('Name', 'name')->sortable()],
            ['searchableRelations' => ['grade.school.name']],
        );

        expect($search($resource, ['globalSearch' => 'North']))->toBe(['Beto']);
    });

    it('reads the term from the resource search key only', function () use ($search) {
        $resource = AdHocResource::withFields(
            [Input::make('Name', 'name')->sortable()],
            ['searchKey' => 'q', 'searchableAttributes' => ['name']],
        );

        expect($search($resource, ['q' => 'Carla']))->toBe(['Carla'])
            ->and($search($resource, ['globalSearch' => 'Carla']))->toBe(['Ana', 'Beto', 'Carla']);
    });

    it('lets the resource add its own search logic through applySearch()', function () use ($search) {
        $resource = new class([Input::make('Name', 'name')->sortable()], ['searchableAttributes' => ['name']]) extends AdHocResource {
            public function applySearch($query, string $search): void
            {
                $query->orWhere('bio', $search);
            }
        };

        expect($search($resource, ['globalSearch' => 'zebra']))->toBe(['Ana', 'Carla'])
            ->and($search($resource, ['globalSearch' => 'Beto']))->toBe(['Beto']);
    });

    it('ignores fields marked ->searchable() when the resource declares nothing', function () use ($search) {
        // Current behaviour (TRT-012): the search term is dropped and every row comes back.
        $resource = AdHocResource::withFields([Input::make('Name', 'name')->sortable()->searchable()]);

        expect($search($resource, ['globalSearch' => 'Carla']))->toBe(['Ana', 'Beto', 'Carla']);
    });

    it('falls back to the common display attributes when nothing is configured, as documented', function () use ($search) {
        // ResourceSearchable's docblock: "If no searchable attributes are configured,
        // the system falls back to: ['name', 'title', 'label', ...]".
        $resource = AdHocResource::withFields([Input::make('Name', 'name')->sortable()]);

        expect($search($resource, ['globalSearch' => 'Carla']))->toBe(['Carla']);
    })->skip('ISSUE TRT-013: the documented fallback does not exist for the index; the term is ignored');
});
