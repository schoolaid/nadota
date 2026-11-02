<?php

use SchoolAid\Nadota\Http\Fields\Relations\HasOne;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Models\Profile;
use SchoolAid\Nadota\Tests\Resources\TestResource;

it('can be instantiated', function () {
    $field = HasOne::make('Profile', 'profile');

    // The relation name is the field key; the attribute stays empty so the
    // field never adds a column to the parent SELECT.
    expect($field)->toBeField()
        ->and($field->key())->toBe('profile')
        ->and($field->getRelation())->toBe('profile')
        ->and($field->getAttribute())->toBe('')
        ->and($field->isRelationship())->toBeTrue()
        ->and($field->fieldData->label)->toBe('Profile');
});

it('returns hasOne relation type', function () {
    $field = HasOne::make('Profile', 'profile');

    expect($field->relationType())->toBe('hasOne');
});

it('can set related model', function () {
    $field = HasOne::make('Profile', 'profile')
        ->model(Profile::class);

    expect($field->getModel())->toBe(Profile::class);
});

it('resolves relationship value from model', function () {
    $testModel = TestModel::factory()->create();
    $profile = Profile::factory()->create([
        'test_model_id' => $testModel->id,
        'bio' => 'User bio content'
    ]);

    $field = HasOne::make('Profile', 'profile')
        ->displayAttribute('bio');

    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);

    expect($value)->toBeArray()
        ->toHaveKey('id', $profile->id)
        ->toHaveKey('label', 'User bio content')
        ->toHaveKey('resource', null);
});

it('returns null when no relationship exists', function () {
    $testModel = TestModel::factory()->create();

    $field = HasOne::make('Profile', 'profile');

    $request = createNadotaRequest();
    $value = $field->resolve($request, $testModel, null);

    expect($value)->toBeNull();
});

it('can be made sortable', function () {
    $field = HasOne::make('Profile', 'profile')
        ->sortable();

    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = HasOne::make('Profile', 'profile')
        ->filterable();

    expect($field->isFilterable())->toBeTrue();
});

it('does not alter the parent query when sorting', function () {
    $testModel = new TestModel();
    $field = HasOne::make('Profile', 'profile')
        ->displayAttribute('bio');

    $query = $testModel->newQuery();
    $sortedQuery = $field->applySorting($query, 'asc', $testModel);

    expect($sortedQuery)->toBe($query)
        ->and($sortedQuery->getQuery()->joins)->toBeNull()
        ->and($sortedQuery->getQuery()->orders)->toBeNull();
});

it('serializes to array correctly', function () {
    $testModel = TestModel::factory()->create();
    $profile = Profile::factory()->create([
        'test_model_id' => $testModel->id,
        'bio' => 'User bio'
    ]);

    $field = HasOne::make('Profile', 'profile')
        ->displayAttribute('bio')
        ->sortable()
        ->filterable();

    $request = createNadotaRequest();
    $array = $field->toArray($request, $testModel, new TestResource());

    expect($array)
        ->toHaveKey('label', 'Profile')
        ->toHaveKey('key', 'profile')
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->and($array['props'])->toHaveKey('relationType', 'hasOne')
        ->and($array['value'])->toHaveKey('id', $profile->id)
        ->and($array['value'])->toHaveKey('label', 'User bio')
        ->and($array['optionsUrl'])->toContain('/resource/field/profile/options');
});

it('can set display attribute', function () {
    $field = HasOne::make('Profile', 'profile')
        ->displayAttribute('bio');

    expect($field->getAttributeForDisplay())->toBe('bio');
});

it('derives the foreign key from the eloquent relation', function () {
    $profileResource = new class extends Resource {
        public string $model = Profile::class;
        public static function getKey(): string { return 'profiles'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $testModel = TestModel::factory()->create();

    $field = HasOne::make('Profile', 'profile', get_class($profileResource));

    $array = $field->toArray(createNadotaRequest(), $testModel, new TestResource());

    expect($array['props']['createContext'])
        ->toHaveKey('foreignKey', 'test_model_id')
        ->toHaveKey('prefill', ['test_model_id' => $testModel->id])
        ->toHaveKey('lock', ['test_model_id']);
});

it('is hidden from index and shown on detail by default', function () {
    $field = HasOne::make('Profile', 'profile');
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeTrue();
});
