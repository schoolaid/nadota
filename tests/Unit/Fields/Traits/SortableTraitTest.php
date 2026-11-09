<?php

use Illuminate\Database\Eloquent\Builder;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Services\ResourceIndexService;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;

it('is not sortable until asked', function () {
    $field = Input::make('Name', 'name');

    expect($field->isSortable())->toBeFalse()
        ->and($field->toArray(createNadotaRequest())['sortable'])->toBeFalse();

    $field->sortable();

    expect($field->isSortable())->toBeTrue()
        ->and($field->toArray(createNadotaRequest())['sortable'])->toBeTrue();
});

it('orders the query by its attribute in the given direction', function () {
    $query = Input::make('Name', 'name')->applySorting(Student::query(), 'desc', new Student());

    expect($query->toSql())->toEndWith('order by "name" desc');
});

it('lets Laravel reject an unknown direction', function () {
    expect(fn () => Input::make('Name', 'name')->applySorting(Student::query(), 'sideways', new Student()))
        ->toThrow(InvalidArgumentException::class);
});

describe('in the index pipeline', function () {
    beforeEach(function () {
        FixtureSchema::create();

        foreach ([['Carla', 30, '2024-01-03'], ['Ana', 10, '2024-01-01'], ['Beto', 20, '2024-01-02']] as [$name, $age, $created]) {
            Student::create(['name' => $name, 'age' => $age, 'created_at' => $created, 'updated_at' => $created]);
        }
    });

    $names = function (array $query, array $config = [], ?array $fields = null): array {
        $request = createNadotaRequest($query);
        $request->setResource(AdHocResource::withFields($fields ?? [
            Input::make('Name', 'name')->sortable(),
            Number::make('Age', 'age'),
        ], $config));

        $payload = (new ResourceIndexService())->handle($request)->response()->getData(true);

        return collect($payload['data'])
            ->map(fn ($row) => collect($row['attributes'])->firstWhere('key', 'name')['value'])
            ->all();
    };

    it('sorts by a sortable field in both directions', function () use ($names) {
        expect($names(['sortField' => 'name', 'sortDirection' => 'asc']))->toBe(['Ana', 'Beto', 'Carla'])
            ->and($names(['sortField' => 'name', 'sortDirection' => 'desc']))->toBe(['Carla', 'Beto', 'Ana']);
    });

    it('defaults the direction to desc', function () use ($names) {
        expect($names(['sortField' => 'name']))->toBe(['Carla', 'Beto', 'Ana']);
    });

    it('ignores a field that is not sortable', function () use ($names) {
        // age is not ->sortable(): falls back to created_at desc
        expect($names(['sortField' => 'age', 'sortDirection' => 'asc']))->toBe(['Carla', 'Beto', 'Ana']);
    });

    it('ignores an unknown sort field', function () use ($names) {
        expect($names(['sortField' => 'drop table', 'sortDirection' => 'asc']))->toBe(['Carla', 'Beto', 'Ana']);
    });

    it('uses the resource default sort when no sort is requested', function () use ($names) {
        expect($names([], ['defaultSort' => ['field' => 'age', 'direction' => 'asc']]))->toBe(['Ana', 'Beto', 'Carla']);
    });

    it('falls back to created_at desc without a default sort', function () use ($names) {
        expect($names([]))->toBe(['Carla', 'Beto', 'Ana']);
    });

    it('ignores a default sort on a field hidden from the index', function () use ($names) {
        // Current behaviour (see TRT-015): the default sort is looked up among index fields only.
        $fields = [Input::make('Name', 'name'), Number::make('Age', 'age')->hideFromIndex()];

        expect($names([], ['defaultSort' => ['field' => 'age', 'direction' => 'asc']], $fields))->toBe(['Carla', 'Beto', 'Ana']);
    });

    it('falls back to a valid direction instead of failing on an unknown one', function () use ($names) {
        expect($names(['sortField' => 'name', 'sortDirection' => 'sideways']))->toBe(['Carla', 'Beto', 'Ana']);
    })->skip('ISSUE HTTP-003: sortDirection is passed unchecked to orderBy(), InvalidArgumentException (HTTP 500)');

    it('lets a custom field decide how it is sorted', function () use ($names) {
        // A non-relation field overriding applySorting(): sort "name" by age instead.
        $byAge = new class('Name', 'name') extends Input {
            public function applySorting(Builder $query, $sortDirection, $modelInstance): Builder
            {
                return $query->orderBy('age', $sortDirection === 'asc' ? 'desc' : 'asc');
            }
        };

        expect($names(['sortField' => 'name', 'sortDirection' => 'asc'], [], [$byAge->sortable()]))->toBe(['Carla', 'Beto', 'Ana']);
    })->skip('ISSUE TRT-009: ApplySortingPipe only calls applySorting() for relation fields');
});
