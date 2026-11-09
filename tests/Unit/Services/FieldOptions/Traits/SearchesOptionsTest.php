<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Services\FieldOptions\OptionsConfig;
use SchoolAid\Nadota\Http\Services\FieldOptions\Traits\SearchesOptions;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

/**
 * Query helpers shared by the field-options strategies
 * (AbstractOptionsStrategy, MorphToOptionsStrategy). The HTTP behaviour of the
 * options endpoints belongs to the options tests.
 */

function searchesOptionsHost(): object
{
    return new class {
        use SearchesOptions;

        public function __call($method, $arguments)
        {
            return $this->{$method}(...$arguments);
        }
    };
}

function searchesOptionsNames($query): array
{
    return $query->orderBy('name')->pluck('name')->all();
}

beforeEach(function () {
    FixtureSchema::create();

    $north = School::create(['name' => 'North High']);
    $first = Grade::create(['name' => 'First', 'school_id' => $north->id]);
    $second = Grade::create(['name' => 'Second']);

    Student::create(['name' => 'Ana', 'email' => 'ana@example.com', 'grade_id' => $second->id, 'status' => 'draft']);
    Student::create(['name' => 'Beto', 'email' => 'beto@school.test', 'grade_id' => $first->id, 'status' => 'enrolled']);
    Student::create(['name' => 'Carla', 'email' => 'carla@example.com', 'status' => null]);
});

describe('applySearch()', function () {
    it('does nothing for an empty term', function () {
        $query = searchesOptionsHost()->applySearch(Student::query(), '', new StudentResource());

        expect(searchesOptionsNames($query))->toBe(['Ana', 'Beto', 'Carla']);
    });

    it('matches any searchable attribute of the resource', function () {
        expect(searchesOptionsNames(searchesOptionsHost()->applySearch(Student::query(), 'example', new StudentResource())))
            ->toBe(['Ana', 'Carla']);
    });

    it('matches through a relation and a nested relation', function () {
        $nested = AdHocResource::withFields([], ['searchableRelations' => ['grade.school.name']]);

        expect(searchesOptionsNames(searchesOptionsHost()->applySearch(Student::query(), 'Second', new StudentResource())))->toBe(['Ana'])
            ->and(searchesOptionsNames(searchesOptionsHost()->applySearch(Student::query(), 'North', $nested)))->toBe(['Beto']);
    });

    it('keeps the search inside one parenthesised group', function () {
        $query = Student::query()->where('is_active', true);

        searchesOptionsHost()->applySearch($query, 'x', new StudentResource());

        expect($query->toSql())->toContain('where "is_active" = ? and ("name" like ? or "email" like ? or exists');
    });

    it('calls the resource applySearch() hook inside the group', function () {
        $resource = new class([], ['searchableAttributes' => ['name']]) extends AdHocResource {
            public function applySearch($query, string $search): void
            {
                $query->orWhere('status', $search);
            }
        };

        expect(searchesOptionsNames(searchesOptionsHost()->applySearch(Student::query(), 'enrolled', $resource)))->toBe(['Beto']);
    });

    it('searches the fallback attributes without a resource', function () {
        $sql = searchesOptionsHost()->applySearch(Grade::query(), 'x')->toSql();

        foreach (OptionsConfig::FALLBACK_SEARCH_ATTRIBUTES as $attribute) {
            expect($sql)->toContain("\"{$attribute}\" like ?");
        }
    });

    it('still filters by the term when the resource declares nothing searchable', function () {
        // FieldOptionsService::applySearch() and ResourceOptionsService::applySearch()
        // fall back to OptionsConfig::FALLBACK_SEARCH_ATTRIBUTES here; this trait adds
        // an empty group and every row matches.
        $query = searchesOptionsHost()->applySearch(Student::query(), 'Carla', AdHocResource::withFields([]));

        expect(searchesOptionsNames($query))->toBe(['Carla']);
    })->skip('ISSUE TRT-013: an empty searchable config turns the search into a no-op (all rows)');
});

describe('query helpers', function () {
    it('excludes the given keys', function () {
        $ana = Student::where('name', 'Ana')->value('id');

        expect(searchesOptionsNames(searchesOptionsHost()->applyExclude(Student::query(), [$ana])))->toBe(['Beto', 'Carla'])
            ->and(searchesOptionsNames(searchesOptionsHost()->applyExclude(Student::query(), [])))->toHaveCount(3);
    });

    it('orders only when an order column is given', function () {
        expect(searchesOptionsHost()->applyOrdering(Student::query(), 'name', 'desc')->pluck('name')->all())->toBe(['Carla', 'Beto', 'Ana'])
            ->and(searchesOptionsHost()->applyOrdering(Student::query())->toSql())->not->toContain('order by');
    });

    it('applies the resource optionsQuery() customisation', function () {
        $resource = new class([]) extends AdHocResource {
            public function optionsQuery(\Illuminate\Database\Eloquent\Builder $query, \SchoolAid\Nadota\Http\Requests\NadotaRequest $request, array $params = []): \Illuminate\Database\Eloquent\Builder
            {
                return $query->where('status', $params['status']);
            }
        };

        $query = searchesOptionsHost()->applyResourceOptionsQuery(Student::query(), $resource, createNadotaRequest(), ['status' => 'draft']);

        expect(searchesOptionsNames($query))->toBe(['Ana'])
            ->and(searchesOptionsHost()->applyResourceOptionsQuery(Student::query(), null, createNadotaRequest())->count())->toBe(3);
    });
});

describe('applyFilters()', function () {
    $filtered = fn (array $filters, $resource = null) => searchesOptionsNames(
        searchesOptionsHost()->applyFilters(Student::query(), $filters, $resource)
    );

    it('matches a plain value with LIKE', function () use ($filtered) {
        expect($filtered(['email' => 'example']))->toBe(['Ana', 'Carla']);
    });

    it('matches an array of values with IN', function () use ($filtered) {
        expect($filtered(['status' => ['draft', 'enrolled']]))->toBe(['Ana', 'Beto']);
    });

    it('turns null and "null" into IS NULL', function () use ($filtered) {
        expect($filtered(['status' => null]))->toBe(['Carla'])
            ->and($filtered(['status' => 'null']))->toBe(['Carla']);
    });

    it('supports an explicit operator', function () use ($filtered) {
        expect($filtered(['status' => ['value' => 'draft', 'operator' => '!=']]))->toBe(['Beto'])
            ->and($filtered(['name' => ['value' => 'ar', 'operator' => 'LIKE']]))->toBe(['Carla'])
            ->and($filtered(['status' => ['value' => null, 'operator' => '!=']]))->toBe(['Ana', 'Beto']);
    });

    it('skips an unsupported operator', function () use ($filtered) {
        expect($filtered(['status' => ['value' => 'x', 'operator' => 'regexp']]))->toBe(['Ana', 'Beto', 'Carla']);
    });

    it('only applies the filters a resource allows when it declares them', function () use ($filtered) {
        $resource = new class([]) extends AdHocResource {
            public function getAllowedOptionsFilters(): array
            {
                return ['status'];
            }
        };

        expect($filtered(['status' => 'draft', 'email' => 'school'], $resource))->toBe(['Ana']);
    });
});

describe('formatting and parameters', function () {
    it('formats value/label pairs, with a custom label resolver', function () {
        $results = Student::orderBy('name')->get();

        expect(searchesOptionsHost()->formatAsOptions($results->take(1), 'id'))->toBe([['value' => $results[0]->id, 'label' => 'Ana']])
            ->and(searchesOptionsHost()->formatAsOptions($results->take(1), 'email', fn ($s) => strtoupper($s->name)))
            ->toBe([['value' => 'ana@example.com', 'label' => 'ANA']]);
    });

    it('falls back to the first filled common attribute, then the key', function () {
        $host = searchesOptionsHost();

        $labelOnly = new Grade(['level' => 3]);
        $labelOnly->id = 12;

        expect($host->resolveDefaultLabel(new School(['name' => 'North'])))->toBe('North')
            ->and($host->resolveDefaultLabel($labelOnly))->toBe(12);
    });

    it('reads search, limit, exclude, order, filters and scope with defaults', function () {
        expect(searchesOptionsHost()->getCommonParams(createNadotaRequest()))->toBe([
            'search' => '',
            'limit' => OptionsConfig::DEFAULT_LIMIT,
            'exclude' => [],
            'orderBy' => null,
            'orderDirection' => 'asc',
            'filters' => [],
            'scope' => [],
        ]);
    });

    it('caps the limit at the maximum and lets explicit params win', function () {
        $params = searchesOptionsHost()->getCommonParams(
            createNadotaRequest(['limit' => 5000, 'search' => 'from-request']),
            ['search' => 'from-params'],
        );

        expect($params['limit'])->toBe(OptionsConfig::MAX_LIMIT)
            ->and($params['search'])->toBe('from-params');
    });
});
