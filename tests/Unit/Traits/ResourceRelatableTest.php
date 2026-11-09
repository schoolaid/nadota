<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Traits\ResourceRelatable;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

it('is part of every Resource', function () {
    expect(class_uses(Resource::class))->toContain(ResourceRelatable::class);
});

it('keys records by id unless the resource says otherwise', function () {
    expect(StudentResource::$attributeKey)->toBe('id')
        ->and(GradeResource::$attributeKey)->toBe('id');
});

it('lets a resource redeclare its key without affecting the others', function () {
    $uuidKeyed = new class extends Resource {
        public string $model = Student::class;
        public static string $attributeKey = 'uuid';

        public function fields(NadotaRequest $request): array
        {
            return [Input::make('Name', 'name')];
        }
    };

    expect($uuidKeyed::$attributeKey)->toBe('uuid')
        ->and(StudentResource::$attributeKey)->toBe('id')
        ->and($uuidKeyed->optionsFormat(new Student(['uuid' => 'u-1', 'name' => 'Ana'])))->toBe(['value' => 'u-1', 'label' => 'Ana']);
});

it('starts with an empty public $with list', function () {
    // Nothing in src/ reads $with; eager loading uses $withOnIndex / $withOnShow (TRT-019).
    expect((new StudentResource())->with)->toBe([]);
});
