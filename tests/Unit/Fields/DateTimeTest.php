<?php

use SchoolAid\Nadota\Http\Fields\DateTime;
use Carbon\Carbon;

it('can be instantiated', function () {
    $field = DateTime::make('Published At', 'published_at');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('published_at')
        ->and($field->getName())->toBe('Published At');
});

it('resolves datetime value from model', function () {
    $date = now();
    $model = createTestModel(['published_at' => $date]);
    $field = DateTime::make('Published At', 'published_at');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe($date->format('Y-m-d H:i:s'));
});

it('can set date format', function () {
    $field = DateTime::make('Published At', 'published_at')
        ->format('Y-m-d');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['format'])->toBe('Y-m-d');
});

it('can be date only', function () {
    $field = DateTime::make('Date', 'date')
        ->dateOnly();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['dateOnly'])->toBeTrue()
        ->and($array['props']['format'])->toBe('Y-m-d');
});

it('can be time only', function () {
    $field = DateTime::make('Time', 'time')
        ->timeOnly();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['timeOnly'])->toBeTrue()
        ->and($array['props']['format'])->toBe('H:i:s');
});

it('can set min date', function () {
    $minDate = now()->subDays(30);
    $field = DateTime::make('Published At', 'published_at')
        ->min($minDate);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props'])->toHaveKey('min');
});

it('can set max date', function () {
    $maxDate = now()->addDays(30);
    $field = DateTime::make('Published At', 'published_at')
        ->max($maxDate);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props'])->toHaveKey('max');
});

it('can be made sortable', function () {
    $field = DateTime::make('Published At', 'published_at')
        ->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = DateTime::make('Published At', 'published_at')
        ->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('serializes to array correctly', function () {
    $model = createTestModel(['published_at' => now()]);
    $field = DateTime::make('Published At', 'published_at')
        ->format('Y-m-d')
        ->sortable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('name', 'Published At')
        ->toHaveKey('attribute', 'published_at')
        ->toHaveKey('sortable', true)
        ->toHaveKey('props')
        ->and($array['props']['format'])->toBe('Y-m-d');
});

it('handles null datetime values', function () {
    $model = createTestModel(['published_at' => null]);
    $field = DateTime::make('Published At', 'published_at');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeNull();
});