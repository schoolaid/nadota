<?php

use SchoolAid\Nadota\Http\Traits\ResourceExportable;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\HasOne;
use SchoolAid\Nadota\Http\Fields\Traits\ManagesFieldVisibility;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Models\Profile;

// Create a test class that uses ResourceExportable
class TestExportableResource
{
    use ResourceExportable;
    use ManagesFieldVisibility;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name'),
            Input::make('Email', 'email'),
            Input::make('Secret', 'secret')->hideFromIndex(),
        ];
    }

    public function authorizedTo($request, $action): bool
    {
        return true;
    }
}

it('has export enabled by default', function () {
    $resource = new TestExportableResource();

    expect($resource->isExportEnabled())->toBeTrue();
});

it('returns default allowed export formats', function () {
    $resource = new TestExportableResource();

    expect($resource->getAllowedExportFormats())->toBe(['excel', 'csv']);
});

it('returns formats with extensions', function () {
    $resource = new TestExportableResource();

    $formats = $resource->getAllowedExportFormatsWithExtensions();

    expect($formats)->toBe([
        ['format' => 'excel', 'extension' => 'xlsx'],
        ['format' => 'csv', 'extension' => 'csv'],
    ]);
});

it('returns default sync export limit', function () {
    $resource = new TestExportableResource();

    expect($resource->getSyncExportLimit())->toBe(1000);
});

it('gets exportable fields based on index visibility', function () {
    $resource = new TestExportableResource();
    $request = createNadotaRequest();

    $fields = $resource->getExportableFields($request);

    // Should have 2 fields (name and email, not secret which is hidden from index)
    expect($fields)->toHaveCount(2);

    $keys = $fields->map(fn($f) => $f->key())->toArray();
    expect($keys)->toContain('name')
        ->and($keys)->toContain('email')
        ->and($keys)->not->toContain('secret');
});

it('generates export headers from fields', function () {
    $resource = new TestExportableResource();
    $request = createNadotaRequest();

    $headers = $resource->getExportHeaders($request);

    expect($headers)->toHaveKey('name', 'Name')
        ->and($headers)->toHaveKey('email', 'Email');
});

it('returns export config for frontend', function () {
    $resource = new TestExportableResource();
    $request = createNadotaRequest();

    $config = $resource->getExportConfig($request);

    expect($config)
        ->toHaveKey('enabled', true)
        ->toHaveKey('formats', [
            ['format' => 'excel', 'extension' => 'xlsx'],
            ['format' => 'csv', 'extension' => 'csv'],
        ])
        ->toHaveKey('syncLimit', 1000)
        ->toHaveKey('defaultColumns', null)
        ->toHaveKey('columns');

    expect($config['columns'])->toBeArray()
        ->and(count($config['columns']))->toBe(2);

    // All columns should be selected by default when defaultColumns is null
    foreach ($config['columns'] as $column) {
        expect($column)->toHaveKey('selected', true);
    }
});

it('returns default export columns as null by default', function () {
    $resource = new TestExportableResource();

    expect($resource->getDefaultExportColumns())->toBeNull();
});

// HasOne export tests

it('isHasOne returns true on HasOne field', function () {
    $field = HasOne::make('Profile', 'profile');

    expect($field->isHasOne())->toBeTrue();
});

it('excludes hasOne fields not marked exportable', function () {
    $resource = new class {
        use ResourceExportable;
        use ManagesFieldVisibility;

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name'),
                HasOne::make('Profile', 'profile'),
            ];
        }

        public function authorizedTo($request, $action): bool { return true; }
    };

    $request = createNadotaRequest();
    $fields = $resource->getExportableFields($request);
    $keys = $fields->map(fn($f) => $f->key())->toArray();

    expect($keys)->toContain('name')
        ->and($keys)->not->toContain('profile');
});

it('includes hasOne field when marked exportable', function () {
    $resource = new class {
        use ResourceExportable;
        use ManagesFieldVisibility;

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name'),
                HasOne::make('Profile', 'profile')->exportable(),
            ];
        }

        public function authorizedTo($request, $action): bool { return true; }
    };

    $request = createNadotaRequest();
    $fields = $resource->getExportableFields($request);
    $keys = $fields->map(fn($f) => $f->key())->toArray();

    expect($keys)->toContain('name')
        ->and($keys)->toContain('profile');
});

it('resolveForExport on hasOne returns related model label', function () {
    $testModel = TestModel::factory()->create();
    Profile::factory()->create([
        'test_model_id' => $testModel->id,
        'bio' => 'Test bio label',
    ]);

    $field = HasOne::make('Profile', 'profile')
        ->displayAttribute('bio')
        ->exportable();

    $request = createNadotaRequest();
    $value = $field->resolveForExport($request, $testModel, null);

    expect($value)->toBe('Test bio label');
});

it('resolveForExport on hasOne returns null when no related model', function () {
    $testModel = TestModel::factory()->create();

    $field = HasOne::make('Profile', 'profile')->exportable();

    $request = createNadotaRequest();
    $value = $field->resolveForExport($request, $testModel, null);

    expect($value)->toBeNull();
});
