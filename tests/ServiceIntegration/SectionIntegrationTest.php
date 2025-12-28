<?php

use SchoolAid\Nadota\Http\Fields\Section;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Fields\Traits\ManagesFieldVisibility;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

// Create a test class that uses ManagesFieldVisibility
class TestResourceWithSections
{
    use ManagesFieldVisibility;

    private array $testFields;

    public function __construct(array $fields = [])
    {
        $this->testFields = $fields;
    }

    public function fields(NadotaRequest $request): array
    {
        return $this->testFields;
    }
}

it('flattens fields from sections correctly', function () {
    $resource = new TestResourceWithSections([
        Input::make('Email', 'email'),
        Section::make('Personal', [
            Input::make('Name', 'name'),
            Input::make('Phone', 'phone'),
        ]),
        Toggle::make('Active', 'is_active'),
    ]);

    $request = createNadotaRequest();
    $flatFields = $resource->flattenFields($request);

    expect($flatFields)->toHaveCount(4)
        ->and($flatFields[0]->getAttribute())->toBe('email')
        ->and($flatFields[1]->getAttribute())->toBe('name')
        ->and($flatFields[2]->getAttribute())->toBe('phone')
        ->and($flatFields[3]->getAttribute())->toBe('is_active');
});

it('returns structured sections for show', function () {
    $resource = new TestResourceWithSections([
        Input::make('Email', 'email'),
        Section::make('Personal Info', [
            Input::make('Name', 'name'),
        ])->icon('user'),
    ]);

    $request = createNadotaRequest();
    $sections = $resource->sectionsForShow($request, 'show');

    expect($sections)->toHaveCount(2);

    // First section should be default (loose fields)
    expect($sections[0]['type'])->toBe('default')
        ->and($sections[0]['fields'])->toHaveCount(1)
        ->and($sections[0]['fields'][0]->getAttribute())->toBe('email');

    // Second should be the named section
    expect($sections[1]['type'])->toBe('section')
        ->and($sections[1]['title'])->toBe('Personal Info')
        ->and($sections[1]['icon'])->toBe('user')
        ->and($sections[1]['fields'])->toHaveCount(1);
});

it('excludes hidden fields from sections', function () {
    $resource = new TestResourceWithSections([
        Section::make('Info', [
            Input::make('Name', 'name'),
            Input::make('Secret', 'secret')->hideFromDetail(),
        ]),
    ]);

    $request = createNadotaRequest();
    $sections = $resource->sectionsForShow($request, 'show');

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['fields'])->toHaveCount(1)
        ->and($sections[0]['fields'][0]->getAttribute())->toBe('name');
});

it('excludes empty sections', function () {
    $resource = new TestResourceWithSections([
        Section::make('Visible', [
            Input::make('Name', 'name'),
        ]),
        Section::make('Hidden', [
            Input::make('Secret', 'secret')->hideFromDetail(),
        ]),
    ]);

    $request = createNadotaRequest();
    $sections = $resource->sectionsForShow($request, 'show');

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['title'])->toBe('Visible');
});

it('excludes hidden sections entirely', function () {
    $resource = new TestResourceWithSections([
        Section::make('Visible Section', [
            Input::make('Name', 'name'),
        ]),
        Section::make('Admin Only', [
            Input::make('Admin Notes', 'admin_notes'),
        ])->hideFromDetail(),
    ]);

    $request = createNadotaRequest();
    $sections = $resource->sectionsForShow($request, 'show');

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['title'])->toBe('Visible Section');
});

it('handles sections for form context', function () {
    $resource = new TestResourceWithSections([
        Section::make('Create Info', [
            Input::make('Name', 'name'),
            Input::make('Update Only', 'update_field')->hideFromCreation(),
        ]),
    ]);

    $request = createNadotaRequest();

    // For create (model = null)
    $createSections = $resource->sectionsForForm($request, null);
    expect($createSections[0]['fields'])->toHaveCount(1);

    // For update (model = true simulates existing model)
    $updateSections = $resource->sectionsForForm($request, true);
    expect($updateSections[0]['fields'])->toHaveCount(2);
});

it('preserves section metadata in output', function () {
    $resource = new TestResourceWithSections([
        Section::make('Profile', [
            Input::make('Name', 'name'),
        ])
            ->icon('user')
            ->description('User profile information')
            ->collapsible()
            ->collapsed(),
    ]);

    $request = createNadotaRequest();
    $sections = $resource->sectionsForShow($request, 'show');

    expect($sections[0])
        ->toHaveKey('type', 'section')
        ->toHaveKey('title', 'Profile')
        ->toHaveKey('icon', 'user')
        ->toHaveKey('description', 'User profile information')
        ->toHaveKey('collapsible', true)
        ->toHaveKey('collapsed', true);
});

it('groups consecutive loose fields together', function () {
    $resource = new TestResourceWithSections([
        Input::make('Field 1', 'field1'),
        Input::make('Field 2', 'field2'),
        Section::make('Section A', [
            Input::make('Field 3', 'field3'),
        ]),
        Input::make('Field 4', 'field4'),
        Input::make('Field 5', 'field5'),
    ]);

    $request = createNadotaRequest();
    $sections = $resource->sectionsForShow($request, 'show');

    // Should have: default (1,2), Section A (3), default (4,5)
    expect($sections)->toHaveCount(3)
        ->and($sections[0]['type'])->toBe('default')
        ->and($sections[0]['fields'])->toHaveCount(2)
        ->and($sections[1]['type'])->toBe('section')
        ->and($sections[1]['fields'])->toHaveCount(1)
        ->and($sections[2]['type'])->toBe('default')
        ->and($sections[2]['fields'])->toHaveCount(2);
});
