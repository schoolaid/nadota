<?php

use SchoolAid\Nadota\Http\Filters\DynamicSelectFilter;
use SchoolAid\Nadota\Http\Filters\MorphToFilter;
use SchoolAid\Nadota\Http\Filters\MorphTypeFilter;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
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

// --- generated pair -----------------------------------------------------------------------

function notableMorphToFilter(): MorphToFilter
{
    return new MorphToFilter('Notable', 'notable_type', 'notable_id', [
        'student' => ['model' => Student::class, 'label' => 'Student'],
        'grade' => Grade::class,
    ]);
}

it('generates a MorphTypeFilter on the type column and a DynamicSelectFilter on the id column', function () {
    [$type, $entity] = notableMorphToFilter()->generateFilters();

    expect($type)->toBeInstanceOf(MorphTypeFilter::class)
        ->and($type->key())->toBe('notable_type')
        ->and($type->type)->toBe('select')
        ->and($entity)->toBeInstanceOf(DynamicSelectFilter::class)
        ->and($entity->key())->toBe('notable_id')
        ->and($entity->type)->toBe('dynamicSelect');
});

it('makes the entity filter depend on, and send, the type filter', function () {
    [, $entity] = notableMorphToFilter()->generateFilters();

    expect($entity->props())->toMatchArray([
        'searchable' => true,
        'multiple' => false,
        'dependsOn' => ['notable_type'],
        'filtersToSend' => ['notable_type'],
        'isMorphEndpoint' => true,
    ]);
});

it('forwards searchable() and multiple() to the entity filter', function () {
    [, $entity] = notableMorphToFilter()->searchable(false)->multiple()->generateFilters();

    expect($entity->props())->toMatchArray(['searchable' => false, 'multiple' => true]);
});

it('derives a label from the alias when the morph type is a bare class string', function () {
    $filter = new MorphToFilter('Notable', 'notable_type', 'notable_id', ['blog_post' => Student::class, 'news-item' => Grade::class]);

    expect($filter->generateFilters()[0]->toArray(createNadotaRequest())['options'])->toBe([
        ['label' => 'Blog post', 'value' => 'blog_post'],
        ['label' => 'News item', 'value' => 'news-item'],
    ]);
});

it('replaces the morph types set through the constructor with morphTypes()', function () {
    $filter = notableMorphToFilter()->morphTypes(['grade' => Grade::class]);

    expect($filter->generateFilters()[0]->toArray(createNadotaRequest())['options'])->toBe([
        ['label' => 'Grade', 'value' => 'grade'],
    ]);
});

it('does not use the resource key it is given when building the entity endpoint', function () {
    // The endpoint comes from the request's resource; resourceKey is stored but never read (FLT-023).
    [, $entity] = notableMorphToFilter()->resourceKey('note')->generateFilters();

    expect($entity->toArray(createNadotaRequest()))->not->toHaveKey('endpoint');
});

// --- apply() helper -----------------------------------------------------------------------

function seedMorphToFilterNotes(): array
{
    FixtureSchema::create();
    $ana = Student::create(['name' => 'Ana']);
    $bob = Student::create(['name' => 'Bob']);
    $grade = Grade::create(['name' => 'G1']);
    $ana->notes()->create(['body' => 'ana note']);
    $bob->notes()->create(['body' => 'bob note']);
    $grade->notes()->create(['body' => 'grade note']);

    return compact('ana', 'bob', 'grade');
}

function morphToFilterBodies(MorphToFilter $filter, array $values): array
{
    $query = Note::query();
    $filter->apply(createNadotaRequest(), $query, $values);

    return $query->orderBy('body')->pluck('body')->all();
}

it('applies type and id together, resolving the alias to the model class', function () {
    ['ana' => $ana, 'grade' => $grade] = seedMorphToFilterNotes();

    expect(morphToFilterBodies(notableMorphToFilter(), ['notable_type' => 'student', 'notable_id' => $ana->id]))->toBe(['ana note'])
        ->and(morphToFilterBodies(notableMorphToFilter(), ['notable_type' => 'grade', 'notable_id' => $grade->id]))->toBe(['grade note']);
});

it('does not match an id of another morph type', function () {
    ['grade' => $grade] = seedMorphToFilterNotes();

    // Grade #1 and Student #1 share the id; only the type tells them apart.
    expect(morphToFilterBodies(notableMorphToFilter(), ['notable_type' => 'student', 'notable_id' => $grade->id]))->toBe(['ana note']);
});

it('matches several ids when multiple', function () {
    ['ana' => $ana, 'bob' => $bob] = seedMorphToFilterNotes();

    expect(morphToFilterBodies(notableMorphToFilter()->multiple(), ['notable_type' => 'student', 'notable_id' => [$ana->id, $bob->id]]))
        ->toBe(['ana note', 'bob note']);
});

it('is a no-op unless both halves are present and the alias resolves', function (array $values) {
    seedMorphToFilterNotes();

    expect(morphToFilterBodies(notableMorphToFilter(), $values))->toBe(['ana note', 'bob note', 'grade note']);
})->with([
    'type only' => [['notable_type' => 'student']],
    'id only' => [['notable_id' => 1]],
    'unknown alias' => [['notable_type' => 'teacher', 'notable_id' => 1]],
    'nothing' => [[]],
]);
