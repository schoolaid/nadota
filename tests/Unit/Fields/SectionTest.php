<?php

use SchoolAid\Nadota\Http\Fields\Section;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Toggle;

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
