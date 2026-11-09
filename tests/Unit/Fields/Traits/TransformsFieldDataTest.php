<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Traits\TransformsFieldData;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;

beforeEach(fn () => FixtureSchema::create());

function transformIndexFields(): array
{
    return [
        Input::make('Name', 'name'),
        Input::make('Shout', 'shout')->displayUsing(fn ($model) => strtoupper($model->name)),
    ];
}

it('turns a model into id, attributes, deletedAt and permissions', function () {
    $student = Student::create(['name' => 'Ana']);
    $resource = AdHocResource::withFields(transformIndexFields());
    $request = createNadotaRequest();

    $row = $resource->transformForIndex($student, $request, $resource->fieldsForIndex($request));

    expect(array_keys($row))->toBe(['id', 'attributes', 'deletedAt', 'permissions'])
        ->and($row['id'])->toBe($student->id)
        ->and($row['deletedAt'])->toBeNull()
        ->and($row['permissions'])->toMatchArray(['view' => true, 'update' => true, 'delete' => true]);
});

it('serialises each field with its value, in field order', function () {
    $student = Student::create(['name' => 'Ana']);
    $resource = AdHocResource::withFields(transformIndexFields());
    $request = createNadotaRequest();

    $attributes = $resource->transformForIndex($student, $request, $resource->fieldsForIndex($request))['attributes'];

    expect(array_column($attributes, 'key'))->toBe(['name', 'shout'])
        ->and(array_column($attributes, 'value'))->toBe(['Ana', 'ANA'])
        ->and(array_keys($attributes))->toBe([0, 1]);
});

it('omits the id when the resource opts out', function () {
    $student = Student::create(['name' => 'Ana']);
    $resource = AdHocResource::withFields(transformIndexFields())->withoutId();
    $request = createNadotaRequest();

    expect($resource->transformForIndex($student, $request, $resource->fieldsForIndex($request)))->not->toHaveKey('id');
});

it('reports the deletion time of a trashed record', function () {
    $student = Student::create(['name' => 'Ana']);
    $student->delete();
    $trashed = Student::withTrashed()->find($student->id);
    $resource = AdHocResource::withFields(transformIndexFields());
    $request = createNadotaRequest();

    $row = $resource->transformForIndex($trashed, $request, $resource->fieldsForIndex($request));

    expect($row['deletedAt'])->not->toBeNull()
        ->and($row['permissions']['restore'])->toBeTrue();
});

it('takes the id from the resource attributeKey', function () {
    $student = Student::create(['name' => 'Ana', 'uuid' => 'abc-123']);
    $resource = new class extends Resource {
        public string $model = Student::class;
        public static string $attributeKey = 'uuid';

        public function fields(NadotaRequest $request): array
        {
            return [Input::make('Name', 'name')];
        }
    };
    $request = createNadotaRequest();

    expect($resource->transformForIndex($student, $request, $resource->fieldsForIndex($request))['id'])->toBe('abc-123');
});

it('only serialises the fields it is given', function () {
    $student = Student::create(['name' => 'Ana']);
    $resource = AdHocResource::withFields(transformIndexFields());

    $row = $resource->transformForIndex($student, createNadotaRequest(), collect([Input::make('Name', 'name')]));

    expect($row['attributes'])->toHaveCount(1);
});

it('is part of every Resource through InteractsWithFields', function () {
    expect(method_exists(Resource::class, 'transformForIndex'))->toBeTrue()
        ->and(class_uses(\SchoolAid\Nadota\Http\Fields\Traits\InteractsWithFields::class))->toContain(TransformsFieldData::class);
});
