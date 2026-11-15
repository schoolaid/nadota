<?php

use SchoolAid\Nadota\Http\Fields\URL;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('can be instantiated', function () {
    $field = URL::make('Website', 'website_url');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('website_url')
        ->and($field->fieldData->label)->toBe('Website');
});

it('has correct type and component', function () {
    $field = URL::make('Website', 'website_url');
    
    expect($field->getType())->toBe('url')
        ->and($field->getComponent())->toBe('FieldUrl');
});

it('adds url validation rule by default', function () {
    $field = URL::make('Website', 'website_url');
    
    $rules = $field->getRules();
    expect($rules)->toContain('url');
});

it('can add additional validation rules', function () {
    $field = URL::make('Website', 'website_url')
        ->required()
        ->rules(['starts_with:https://']);
    
    $rules = $field->getRules();
    expect($rules)->toContain('url')
        ->and($rules)->toContain('required')
        ->and($rules)->toContain('starts_with:https://');
});

it('resolves url value from model', function () {
    $model = TestModel::factory()->make(['name' => 'https://example.com']);
    $field = URL::make('Website', 'name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('https://example.com');
});

it('can be made sortable', function () {
    $field = URL::make('Website', 'website_url')->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = URL::make('Website', 'website_url')->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('can be made searchable', function () {
    $field = URL::make('Website', 'website_url')->searchable();
    
    expect($field->isSearchable())->toBeTrue();
});

it('serializes to array correctly', function () {
    $model = TestModel::factory()->make(['name' => 'https://www.example.com']);
    $field = URL::make('Website', 'name')
        ->required()
        ->sortable()
        ->filterable()
        ->searchable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Website')
        ->toHaveKey('attribute', 'name')
        ->toHaveKey('type', 'url')
        ->toHaveKey('component', 'FieldUrl')
        ->toHaveKey('value', 'https://www.example.com')
        ->toHaveKey('required', true)
        ->toHaveKey('sortable', true)
        ->toHaveKey('filterable', true)
        ->toHaveKey('searchable', true);
});
/**
 * Errores de un valor con las reglas del field (store o update).
 *
 * @return array<int, string>
 */
function urlErrors(URL $field, mixed $value, bool $isUpdate = false): array
{
    return \Illuminate\Support\Facades\Validator::make(['website' => $value], ['website' => $field->getRulesFor($isUpdate)])
        ->errors()->get('website');
}

it('reports only required when a required URL is left empty', function () {
    $field = URL::make('Website', 'website')->required();

    // `url` iba antes de `required` y fallaba también: el form mostraba «debe ser una URL válida»
    expect(urlErrors($field, null))->toHaveCount(1)
        ->and(urlErrors($field, null)[0])->toContain('required')
        ->and($field->getRules())->toBe(['required', 'url']);
});

it('lets an optional URL be left empty', function (mixed $empty) {
    $field = URL::make('Website', 'website');

    expect(urlErrors($field, $empty))->toBe([])
        ->and($field->getRules())->toBe(['nullable', 'url']);
})->with([null, '']);

it('still rejects what is not a URL', function (string $value) {
    expect(urlErrors(URL::make('Website', 'website'), $value))->not->toBe([]);
})->with(['abc', 'example.com', 'javascript:alert(1)', 'https://exa mple.com']);

it('does not add url when the app declares its own url rule', function () {
    $field = URL::make('Base', 'base_url')->required()->rules(['string', 'max:255', 'url:http,https']);

    expect($field->getRules())->toBe(['required', 'string', 'max:255', 'url:http,https'])
        ->and(urlErrors($field, 'ftp://example.com'))->not->toBe([])
        ->and(urlErrors($field, 'http://example.com'))->toBe([]);
});

it('keeps a creation-only required URL required on store and optional on update', function () {
    $field = URL::make('Website', 'website')->creationRules(['required']);

    expect(urlErrors($field, null, false))->toHaveCount(1)
        ->and(urlErrors($field, null, true))->toBe([]);
});
