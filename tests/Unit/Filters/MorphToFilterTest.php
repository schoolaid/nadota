<?php

use SchoolAid\Nadota\Http\Filters\MorphToFilter;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Models\RelatedModel;

it('generates type filter label from translation key', function () {
    $filter = new MorphToFilter(
        'fields.targetable',
        'targetable_type',
        'targetable_id',
        [
            'student' => ['model' => TestModel::class, 'label' => 'Student'],
            'user' => ['model' => RelatedModel::class, 'label' => 'User'],
        ],
        'form-target'
    );

    $filters = $filter->generateFilters();

    expect($filters)->toHaveCount(2);

    $typeFilter = $filters[0];
    $request = createNadotaRequest([]);
    $typeArray = $typeFilter->toArray($request);

    // Should use fields.targetable_type as label, not "fields.targetable - Tipo"
    expect($typeArray['label'])->toBe('fields.targetable_type')
        ->and($typeArray['label'])->not->toContain(' - Tipo')
        ->and($typeArray['label'])->not->toContain(' - Type');
});

it('generates entity filter with original label', function () {
    $filter = new MorphToFilter(
        'fields.targetable',
        'targetable_type',
        'targetable_id',
        [
            'student' => ['model' => TestModel::class, 'label' => 'Student'],
            'user' => ['model' => RelatedModel::class, 'label' => 'User'],
        ],
        'form-target'
    );

    $filters = $filter->generateFilters();
    $entityFilter = $filters[1];

    $request = createNadotaRequest([]);
    $entityArray = $entityFilter->toArray($request);

    // Entity filter should keep the original label
    expect($entityArray['label'])->toBe('fields.targetable');
});

it('uses correct translation key pattern for different field names', function () {
    // Test with nested translation keys
    $filter = new MorphToFilter(
        'resources.forms.commentable',
        'commentable_type',
        'commentable_id',
        [
            'post' => ['model' => TestModel::class, 'label' => 'Post'],
        ],
        'comments'
    );

    $filters = $filter->generateFilters();
    $typeFilter = $filters[0];

    $request = createNadotaRequest([]);
    $typeArray = $typeFilter->toArray($request);

    // Should use resources.forms.commentable_type
    expect($typeArray['label'])->toBe('resources.forms.commentable_type');
});

it('preserves backward compatibility for non-translation labels', function () {
    $filter = new MorphToFilter(
        'Targetable',
        'targetable_type',
        'targetable_id',
        [
            'student' => ['model' => TestModel::class, 'label' => 'Student'],
        ],
        'form-target'
    );

    $filters = $filter->generateFilters();
    $typeFilter = $filters[0];

    $request = createNadotaRequest([]);
    $typeArray = $typeFilter->toArray($request);

    // Non-translation labels (no dots) should remain unchanged
    expect($typeArray['label'])->toBe('Targetable');
});

it('generates correct options format', function () {
    $filter = new MorphToFilter(
        'fields.targetable',
        'targetable_type',
        'targetable_id',
        [
            'student' => ['model' => TestModel::class, 'label' => 'Student'],
            'user' => ['model' => RelatedModel::class, 'label' => 'User'],
        ],
        'form-target'
    );

    $filters = $filter->generateFilters();
    $typeFilter = $filters[0];

    $request = createNadotaRequest([]);
    $typeArray = $typeFilter->toArray($request);

    expect($typeArray['options'])->toBeArray()
        ->and($typeArray['options'])->toHaveCount(2)
        ->and($typeArray['options'][0])->toMatchArray([
            'label' => 'Student',
            'value' => 'student',
        ])
        ->and($typeArray['options'][1])->toMatchArray([
            'label' => 'User',
            'value' => 'user',
        ]);
});

it('entity filter has morph endpoint configuration', function () {
    // Create a resource for the request
    $resource = new class extends \SchoolAid\Nadota\Resource {
        public string $model = TestModel::class;
        public static function getKey(): string { return 'form-target'; }
        public function fields(\SchoolAid\Nadota\Http\Requests\NadotaRequest $request): array { return []; }
    };

    $filter = new MorphToFilter(
        'fields.targetable',
        'targetable_type',
        'targetable_id',
        [
            'student' => ['model' => TestModel::class, 'label' => 'Student'],
        ],
        'form-target'
    );

    $filters = $filter->generateFilters();
    $entityFilter = $filters[1];

    $request = createNadotaRequest([]);
    $request->setResource($resource);
    $entityArray = $entityFilter->toArray($request);

    expect($entityArray['props']['isMorphEndpoint'])->toBe(true)
        ->and($entityArray['props'])->toHaveKey('endpointTemplate')
        ->and($entityArray['props']['endpointTemplate'])->toContain('{morphType}')
        ->and($entityArray['endpoint'])->toContain('/morph-options/{morphType}')
        ->and($entityArray['endpoint'])->toContain('/form-target/resource/field/');
});
