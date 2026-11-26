<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Section;
use SchoolAid\Nadota\Http\Fields\Traits\BuildsSelectColumns;
use SchoolAid\Nadota\Http\Fields\Traits\InteractsWithFields;
use SchoolAid\Nadota\Http\Fields\Traits\ManagesFieldVisibility;
use SchoolAid\Nadota\Http\Fields\Traits\ManagesRelationLoading;
use SchoolAid\Nadota\Http\Fields\Traits\TransformsFieldData;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;

/**
 * InteractsWithFields only composes the four field-handling traits; their
 * behaviour is tested in their own files. This pins the composition and the
 * contract a host class must satisfy (fields(), $model, static $attributeKey).
 */

it('composes the select, relation-loading, visibility and transform traits', function () {
    // array_values: class_uses() is keyed by name, and PHPUnit 12 compares keys when canonicalizing.
    expect(array_values(class_uses(InteractsWithFields::class)))->toEqualCanonicalizing([
        BuildsSelectColumns::class,
        ManagesRelationLoading::class,
        ManagesFieldVisibility::class,
        TransformsFieldData::class,
    ]);
});

it('is the field API every Resource gets', function () {
    expect(class_uses(Resource::class))->toContain(InteractsWithFields::class);
});

it('works on any class that provides fields(), $model and $attributeKey', function () {
    $host = new class {
        use InteractsWithFields;

        public string $model = Student::class;
        public static string $attributeKey = 'id';

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name'),
                Section::make('School', [BelongsTo::make('Grade', 'grade', GradeResource::class)]),
            ];
        }
    };
    $request = createNadotaRequest();

    expect($host->flattenFields($request))->toHaveCount(2)
        ->and($host->getSelectColumns($request))->toBe(['name', 'grade_id', 'id'])
        ->and(array_keys($host->getEagerLoadRelations($request)))->toBe(['grade'])
        ->and($host->fieldsForIndex($request))->toHaveCount(2);
});
