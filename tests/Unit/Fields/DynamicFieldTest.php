<?php

use SchoolAid\Nadota\Http\Fields\DynamicField;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Fields\Date;
use SchoolAid\Nadota\Http\Fields\File;

// =========================================================================
// BASIC SETUP
// =========================================================================

it('can create a dynamic field', function () {
    $field = DynamicField::make('Value', 'value');

    expect($field)->toBeInstanceOf(DynamicField::class)
        ->and($field->getAttribute())->toBe('value');
});

it('sets the correct field type', function () {
    $field = DynamicField::make('Value', 'value');
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['type'])->toBe('dynamic');
});

// =========================================================================
// TYPE MAPPING
// =========================================================================

it('can set type field to base on', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('item_type');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['typeField'])->toBe('item_type');
});

it('can define type mappings', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type')
        ->types([
            1 => Input::make('Text', 'value'),
            2 => Number::make('Number', 'value'),
            3 => Toggle::make('Boolean', 'value'),
        ]);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['types'])->toHaveCount(3)
        ->and($array['props']['types'][1]['type'])->toBe('text')
        ->and($array['props']['types'][2]['type'])->toBe('number')
        ->and($array['props']['types'][3]['type'])->toBe('boolean');
});

it('can add single type mapping with when()', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type')
        ->when(1, Input::make('Text', 'value'))
        ->when(2, Number::make('Number', 'value'));

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['types'])->toHaveCount(2);
});

it('can set default field', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type')
        ->types([
            1 => Input::make('Text', 'value'),
        ])
        ->defaultType(Input::make('Default', 'value')->placeholder('Default value'));

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['defaultField'])->not->toBeNull()
        ->and($array['props']['defaultField']['placeholder'])->toBe('Default value');
});

// =========================================================================
// CLOSURE SUPPORT
// =========================================================================

it('supports closure for dynamic field creation', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type')
        ->types([
            'select' => fn($model) => Select::make('Options', 'value')
                ->options(['a' => 'A', 'b' => 'B']),
        ]);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    // Closure is evaluated during serialization
    expect($array['props']['types']['select']['type'])->toBe('select');
});

// =========================================================================
// DEPENDENCIES
// =========================================================================

it('automatically adds dependsOn for type field', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('item_type');

    expect($field->getDependsOnFields())->toContain('item_type');
});

it('includes dependency config in output', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('item_type');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['dependencies']['fields'])->toContain('item_type');
});

// =========================================================================
// SERIALIZATION
// =========================================================================

it('includes isDynamic flag in props', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['isDynamic'])->toBeTrue();
});

it('can exclude all types from response', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type')
        ->types([
            1 => Input::make('Text', 'value'),
            2 => Number::make('Number', 'value'),
        ])
        ->onlyMatchedType();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props'])->not->toHaveKey('types');
});

// =========================================================================
// FORM BUILDER USE CASE
// =========================================================================

it('works with form builder item types', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('form_item_type')
        ->types([
            1 => Input::make('Text', 'value'),           // TEXT
            2 => Toggle::make('Boolean', 'value'),       // BOOLEAN
            3 => Select::make('Select', 'value'),        // SELECT
            4 => Number::make('Number', 'value'),        // NUMBER
            5 => Date::make('Date', 'value'),            // DATE
            // 6 => CustomComponent for SIGNATURE
            7 => File::make('File', 'value'),            // FILE
        ])
        ->defaultType(Input::make('Default', 'value'));

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['types'])->toHaveCount(6)
        ->and($array['props']['types'][1]['type'])->toBe('text')
        ->and($array['props']['types'][2]['type'])->toBe('boolean')
        ->and($array['props']['types'][3]['type'])->toBe('select')
        ->and($array['props']['types'][4]['type'])->toBe('number')
        ->and($array['props']['types'][5]['type'])->toBe('date')
        ->and($array['props']['types'][7]['type'])->toBe('file');
});

// =========================================================================
// STRING TYPE KEYS
// =========================================================================

it('works with string type keys', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('field_type')
        ->types([
            'text' => Input::make('Text', 'value'),
            'number' => Number::make('Number', 'value'),
            'boolean' => Toggle::make('Boolean', 'value'),
            'date' => Date::make('Date', 'value'),
        ]);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props']['types']['text']['type'])->toBe('text')
        ->and($array['props']['types']['number']['type'])->toBe('number')
        ->and($array['props']['types']['boolean']['type'])->toBe('boolean')
        ->and($array['props']['types']['date']['type'])->toBe('date');
});

// =========================================================================
// VISIBILITY INTEGRATION
// =========================================================================

it('inherits visibility from base field', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type')
        ->hideFromIndex();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['showOnIndex'])->toBeFalse()
        ->and($array['showOnDetail'])->toBeTrue();
});

// =========================================================================
// VALIDATION
// =========================================================================

it('can have base validation rules', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type')
        ->required()
        ->rules('max:1000');

    expect($field->getRules())->toContain('required')
        ->and($field->getRules())->toContain('max:1000');
});

// =========================================================================
// ENUM SUPPORT
// =========================================================================

enum TestFormItemType: int
{
    case TEXT = 1;
    case BOOLEAN = 2;
    case SELECT = 3;
    case NUMBER = 4;
}

it('normalizes backed enum values to their backing value', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type')
        ->types([
            1 => Input::make('Text', 'value'),
            2 => Toggle::make('Boolean', 'value'),
            3 => Select::make('Select', 'value'),
            4 => Number::make('Number', 'value'),
        ]);

    // Use reflection to test normalizeTypeValue
    $reflection = new ReflectionClass($field);
    $method = $reflection->getMethod('normalizeTypeValue');
    $method->setAccessible(true);

    // Test with BackedEnum
    expect($method->invoke($field, TestFormItemType::TEXT))->toBe(1)
        ->and($method->invoke($field, TestFormItemType::BOOLEAN))->toBe(2)
        ->and($method->invoke($field, TestFormItemType::SELECT))->toBe(3)
        ->and($method->invoke($field, TestFormItemType::NUMBER))->toBe(4);
});

it('handles null values in normalizeTypeValue', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type');

    $reflection = new ReflectionClass($field);
    $method = $reflection->getMethod('normalizeTypeValue');
    $method->setAccessible(true);

    expect($method->invoke($field, null))->toBeNull();
});

it('passes through scalar values unchanged', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type');

    $reflection = new ReflectionClass($field);
    $method = $reflection->getMethod('normalizeTypeValue');
    $method->setAccessible(true);

    expect($method->invoke($field, 1))->toBe(1)
        ->and($method->invoke($field, 'text'))->toBe('text')
        ->and($method->invoke($field, 'select'))->toBe('select');
});

it('extracts primary key from Eloquent models', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type');

    // Create a mock model with id = 3
    $mockModel = new class extends \Illuminate\Database\Eloquent\Model {
        protected $primaryKey = 'id';
        public $id = 3;

        public function getKey()
        {
            return $this->id;
        }
    };

    $reflection = new ReflectionClass($field);
    $method = $reflection->getMethod('normalizeTypeValue');
    $method->setAccessible(true);

    expect($method->invoke($field, $mockModel))->toBe(3);
});
