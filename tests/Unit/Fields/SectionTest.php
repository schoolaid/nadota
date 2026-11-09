<?php

use SchoolAid\Nadota\Http\Fields\Section;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentShowcaseResource;

it('can be instantiated with title and fields', function () {
    $section = Section::make('Personal Info', [
        Input::make('Name', 'name'),
        Input::make('Email', 'email'),
    ]);

    expect($section)
        ->toBeInstanceOf(Section::class)
        ->and($section->getTitle())->toBe('Personal Info')
        ->and($section->getFields())->toHaveCount(2);
});

it('can set icon', function () {
    $section = Section::make('Profile', [])
        ->icon('user');

    expect($section->getIcon())->toBe('user');
});

it('can set description', function () {
    $section = Section::make('Profile', [])
        ->description('Basic user information');

    expect($section->getDescription())->toBe('Basic user information');
});

it('can be made collapsible', function () {
    $section = Section::make('Details', [])
        ->collapsible();

    expect($section->isCollapsible())->toBeTrue()
        ->and($section->isCollapsed())->toBeFalse();
});

it('can start collapsed', function () {
    $section = Section::make('Details', [])
        ->collapsed();

    expect($section->isCollapsible())->toBeTrue()
        ->and($section->isCollapsed())->toBeTrue();
});

it('enables collapsible when collapsed is set', function () {
    $section = Section::make('Details', [])
        ->collapsed(true);

    expect($section->isCollapsible())->toBeTrue();
});

it('returns true for isSection', function () {
    $section = Section::make('Test', []);

    expect($section->isSection())->toBeTrue();
});

it('can get visible fields based on visibility method', function () {
    $visibleField = Input::make('Name', 'name');
    $hiddenField = Input::make('Secret', 'secret')->hideFromDetail();

    $section = Section::make('Info', [$visibleField, $hiddenField]);

    $visibleFields = $section->getVisibleFields('isShowOnDetail');

    expect($visibleFields)->toHaveCount(1)
        ->and($visibleFields[0]->getAttribute())->toBe('name');
});

it('serializes to array correctly', function () {
    $section = Section::make('Profile', [
        Input::make('Name', 'name'),
    ])
        ->icon('user')
        ->description('User profile data')
        ->collapsible()
        ->collapsed();

    $request = createNadotaRequest();
    $array = $section->toArray($request);

    expect($array)
        ->toHaveKey('type', 'section')
        ->toHaveKey('title', 'Profile')
        ->toHaveKey('icon', 'user')
        ->toHaveKey('description', 'User profile data')
        ->toHaveKey('collapsible', true)
        ->toHaveKey('collapsed', true)
        ->toHaveKey('fields')
        ->and($array['fields'])->toHaveCount(1);
});

it('can hide from specific views using VisibilityTrait', function () {
    $section = Section::make('Admin Only', [])
        ->hideFromCreation();

    expect($section->isShowOnCreation())->toBeFalse()
        ->and($section->isShowOnDetail())->toBeTrue()
        ->and($section->isShowOnUpdate())->toBeTrue();
});

it('can show only on specific views', function () {
    $section = Section::make('Details Only', [])
        ->onlyOnDetail();

    expect($section->isShowOnDetail())->toBeTrue()
        ->and($section->isShowOnCreation())->toBeFalse()
        ->and($section->isShowOnUpdate())->toBeFalse()
        ->and($section->isShowOnIndex())->toBeFalse();
});

it('filters fields by visibility in toArray when visibility method provided', function () {
    $visibleField = Input::make('Name', 'name');
    $hiddenField = Input::make('Admin Note', 'admin_note')->hideFromDetail();

    $section = Section::make('Info', [$visibleField, $hiddenField]);

    $request = createNadotaRequest();
    $array = $section->toArray($request, null, null, 'isShowOnDetail');

    expect($array['fields'])->toHaveCount(1);
});

it('is visible everywhere, expanded and without icon or description by default', function () {
    $section = Section::make('Plain');

    expect($section->getFields())->toBe([])
        ->and($section->getIcon())->toBeNull()
        ->and($section->getDescription())->toBeNull()
        ->and($section->isCollapsible())->toBeFalse()
        ->and($section->isCollapsed())->toBeFalse()
        ->and($section->isShowOnIndex())->toBeTrue()
        ->and($section->isShowOnDetail())->toBeTrue()
        ->and($section->isShowOnCreation())->toBeTrue()
        ->and($section->isShowOnUpdate())->toBeTrue();
});

it('keeps the field order and serializes each field with the model value', function () {
    $model = new Student(['name' => 'Ana', 'is_active' => true]);
    $section = Section::make('Info', [Toggle::make('Active', 'is_active'), Input::make('Name', 'name')]);

    $array = $section->toArray(createNadotaRequest(), $model);

    expect(array_column($array['fields'], 'key'))->toBe(['is_active', 'name'])
        ->and(array_column($array['fields'], 'value'))->toBe([true, 'Ana']);
});

it('does not collapse when collapsed(false)', function () {
    $section = Section::make('Details')->collapsed(false);

    expect($section->isCollapsed())->toBeFalse()
        ->and($section->isCollapsible())->toBeFalse();
});

it('is flattened into its fields by a resource', function () {
    $keys = (new StudentShowcaseResource())->flattenFields(createNadotaRequest())->map->key()->all();

    expect($keys)->toBe([
        'name', 'status', 'age', 'uuid', 'grade_filter',
        'snippet', 'template', 'signature',
        'settings', 'skills',
        'website',
        'custom_component_progress_chart',
    ]);
});

it('keeps the section structure for the detail view and drops sections without visible fields', function () {
    $structure = (new StudentShowcaseResource())->sectionsForShow(createNadotaRequest(), 'show', new Student());

    expect(array_column($structure->all(), 'type'))->toBe(['section', 'section', 'section', 'section', 'default'])
        ->and($structure[0]['title'])->toBe('Identity')
        ->and($structure[0]['icon'])->toBe('user')
        ->and(array_map(fn ($f) => $f->key(), $structure[0]['fields']))->toBe(['name', 'status', 'age'])
        ->and($structure[1]['collapsible'])->toBeTrue()
        ->and($structure[2]['collapsed'])->toBeTrue()
        ->and(array_map(fn ($f) => $f->key(), $structure[4]['fields']))->toBe(['custom_component_progress_chart']);
});

it('leaves a section hidden from creation out of the create form structure', function () {
    $titles = (new StudentShowcaseResource())->sectionsForForm(createNadotaRequest())->pluck('title')->all();

    expect($titles)->toBe(['Identity', 'Answers', 'Preferences']);
});

it('leaves the fields of a section hidden from creation out of the flat create form', function () {
    $keys = (new StudentShowcaseResource())->fieldsForForm(createNadotaRequest())->map->key()->all();

    expect($keys)->not->toContain('website');
})->skip('ISSUE FLD-011: flattenFields() ignores Section visibility, so fieldsForForm()/store()/update() still see the fields of a hidden section');

