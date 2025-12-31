<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Number;

// =========================================================================
// BASIC DEPENDENCY SETUP
// =========================================================================

it('has no dependencies by default', function () {
    $field = Input::make('Name', 'name');

    expect($field->hasDependencies())->toBeFalse()
        ->and($field->getDependencyConfig())->toBe([])
        ->and($field->getDependsOnFields())->toBe([]);
});

it('can set single field dependency', function () {
    $field = Input::make('City', 'city')
        ->dependsOn('country_id');

    expect($field->hasDependencies())->toBeTrue()
        ->and($field->getDependsOnFields())->toBe(['country_id']);
});

it('can set multiple field dependencies', function () {
    $field = Number::make('Total', 'total')
        ->dependsOn(['quantity', 'price']);

    expect($field->getDependsOnFields())->toBe(['quantity', 'price']);
});

it('can chain multiple dependsOn calls', function () {
    $field = Input::make('Result', 'result')
        ->dependsOn('field_a')
        ->dependsOn('field_b');

    expect($field->getDependsOnFields())->toBe(['field_a', 'field_b']);
});

it('does not duplicate dependency fields', function () {
    $field = Input::make('Result', 'result')
        ->dependsOn('field_a')
        ->dependsOn('field_a');

    expect($field->getDependsOnFields())->toBe(['field_a']);
});

// =========================================================================
// VISIBILITY CONDITIONS
// =========================================================================

it('can show when equals', function () {
    $field = Input::make('RFC', 'rfc')
        ->showWhenEquals('customer_type', 'business');

    $config = $field->getDependencyConfig();

    expect($config['fields'])->toBe(['customer_type'])
        ->and($config['visibility'])->toHaveCount(1)
        ->and($config['visibility'][0])->toBe([
            'field' => 'customer_type',
            'operator' => 'equals',
            'value' => 'business',
        ]);
});

it('can show when not equals', function () {
    $field = Input::make('Alternative', 'alternative')
        ->showWhenNotEquals('status', 'completed');

    $config = $field->getDependencyConfig();

    expect($config['visibility'][0]['operator'])->toBe('notEquals')
        ->and($config['visibility'][0]['value'])->toBe('completed');
});

it('can show when has value', function () {
    $field = Select::make('City', 'city_id')
        ->showWhenHasValue('country_id');

    $config = $field->getDependencyConfig();

    expect($config['visibility'][0])->toBe([
        'field' => 'country_id',
        'operator' => 'hasValue',
    ]);
});

it('can show when empty', function () {
    $field = Input::make('Manual Entry', 'manual_entry')
        ->showWhenEmpty('auto_value');

    $config = $field->getDependencyConfig();

    expect($config['visibility'][0]['operator'])->toBe('isEmpty');
});

it('can show when in list', function () {
    $field = Input::make('Extra Info', 'extra_info')
        ->showWhenIn('type', ['premium', 'enterprise']);

    $config = $field->getDependencyConfig();

    expect($config['visibility'][0]['operator'])->toBe('in')
        ->and($config['visibility'][0]['value'])->toBe(['premium', 'enterprise']);
});

it('can show when not in list', function () {
    $field = Input::make('Standard Field', 'standard')
        ->showWhenNotIn('type', ['internal', 'test']);

    $config = $field->getDependencyConfig();

    expect($config['visibility'][0]['operator'])->toBe('notIn');
});

it('can show when truthy', function () {
    $field = Input::make('Enabled Feature', 'feature')
        ->showWhenTruthy('is_enabled');

    $config = $field->getDependencyConfig();

    expect($config['visibility'][0]['operator'])->toBe('isTruthy');
});

it('can show when falsy', function () {
    $field = Input::make('Fallback', 'fallback')
        ->showWhenFalsy('has_primary');

    $config = $field->getDependencyConfig();

    expect($config['visibility'][0]['operator'])->toBe('isFalsy');
});

it('can show when greater than', function () {
    $field = Input::make('Discount Code', 'discount_code')
        ->showWhenGreaterThan('total', 100);

    $config = $field->getDependencyConfig();

    expect($config['visibility'][0]['operator'])->toBe('greaterThan')
        ->and($config['visibility'][0]['value'])->toBe(100);
});

it('can show when less than', function () {
    $field = Input::make('Warning', 'warning')
        ->showWhenLessThan('stock', 10);

    $config = $field->getDependencyConfig();

    expect($config['visibility'][0]['operator'])->toBe('lessThan');
});

it('can show when contains', function () {
    $field = Input::make('Email Note', 'email_note')
        ->showWhenContains('email', '@company.com');

    $config = $field->getDependencyConfig();

    expect($config['visibility'][0]['operator'])->toBe('contains');
});

it('can combine multiple visibility conditions', function () {
    $field = Input::make('Complex Field', 'complex')
        ->showWhenEquals('type', 'special')
        ->showWhenHasValue('parent_id');

    $config = $field->getDependencyConfig();

    expect($config['visibility'])->toHaveCount(2)
        ->and($config['fields'])->toBe(['type', 'parent_id']);
});

// =========================================================================
// DISABLED STATE CONDITIONS
// =========================================================================

it('can disable when equals', function () {
    $field = Input::make('Locked Field', 'locked_field')
        ->disableWhenEquals('status', 'locked');

    $config = $field->getDependencyConfig();

    expect($config['disabled'][0])->toBe([
        'field' => 'status',
        'operator' => 'equals',
        'value' => 'locked',
    ]);
});

it('can disable when empty', function () {
    $field = Select::make('Sub Category', 'sub_category')
        ->disableWhenEmpty('category_id');

    $config = $field->getDependencyConfig();

    expect($config['disabled'][0]['operator'])->toBe('isEmpty');
});

it('can disable when has value', function () {
    $field = Input::make('Manual', 'manual')
        ->disableWhenHasValue('auto_generated');

    $config = $field->getDependencyConfig();

    expect($config['disabled'][0]['operator'])->toBe('hasValue');
});

it('can disable when truthy', function () {
    $field = Input::make('Editable', 'editable')
        ->disableWhenTruthy('is_readonly');

    $config = $field->getDependencyConfig();

    expect($config['disabled'][0]['operator'])->toBe('isTruthy');
});

it('can disable when falsy', function () {
    $field = Input::make('Premium Feature', 'premium')
        ->disableWhenFalsy('has_premium');

    $config = $field->getDependencyConfig();

    expect($config['disabled'][0]['operator'])->toBe('isFalsy');
});

// =========================================================================
// REQUIRED STATE CONDITIONS
// =========================================================================

it('can be required when equals', function () {
    $field = Input::make('Tax ID', 'tax_id')
        ->requiredWhenEquals('entity_type', 'business');

    $config = $field->getDependencyConfig();

    expect($config['required'][0])->toBe([
        'field' => 'entity_type',
        'operator' => 'equals',
        'value' => 'business',
    ]);
});

it('can be required when has value', function () {
    $field = Input::make('Confirmation', 'confirmation')
        ->requiredWhenHasValue('password');

    $config = $field->getDependencyConfig();

    expect($config['required'][0]['operator'])->toBe('hasValue');
});

it('can be required when truthy', function () {
    $field = Input::make('Details', 'details')
        ->requiredWhenTruthy('needs_details');

    $config = $field->getDependencyConfig();

    expect($config['required'][0]['operator'])->toBe('isTruthy');
});

it('can be required when in list', function () {
    $field = Input::make('License', 'license')
        ->requiredWhenIn('type', ['commercial', 'enterprise']);

    $config = $field->getDependencyConfig();

    expect($config['required'][0]['operator'])->toBe('in')
        ->and($config['required'][0]['value'])->toBe(['commercial', 'enterprise']);
});

// =========================================================================
// DYNAMIC OPTIONS
// =========================================================================

it('can load options from endpoint', function () {
    $field = Select::make('City', 'city_id')
        ->optionsFromEndpoint('/api/cities', 'country_id');

    $config = $field->getDependencyConfig();

    expect($config['fields'])->toBe(['country_id'])
        ->and($config['options'])->toBe([
            'endpoint' => '/api/cities',
            'paramField' => 'country_id',
            'paramName' => 'country_id',
        ]);
});

it('can load options from endpoint with custom param name', function () {
    $field = Select::make('City', 'city_id')
        ->optionsFromEndpoint('/api/cities', 'country_id', 'country');

    $config = $field->getDependencyConfig();

    expect($config['options']['paramName'])->toBe('country');
});

it('can cascade from another field', function () {
    $field = Select::make('District', 'district_id')
        ->cascadeFrom('city_id');

    $config = $field->getDependencyConfig();

    expect($config['fields'])->toBe(['city_id'])
        ->and($config['options']['cascadeFrom'])->toBe('city_id');
});

// =========================================================================
// COMPUTED VALUES
// =========================================================================

it('can compute value using formula', function () {
    $field = Number::make('Total', 'total')
        ->computeUsing('quantity * price');

    $config = $field->getDependencyConfig();

    expect($config['compute'])->toBe('quantity * price')
        ->and($config['fields'])->toContain('quantity')
        ->and($config['fields'])->toContain('price');
});

it('can compute with explicit fields', function () {
    $field = Number::make('Result', 'result')
        ->computeUsing('a + b', ['field_a', 'field_b']);

    $config = $field->getDependencyConfig();

    expect($config['fields'])->toBe(['field_a', 'field_b']);
});

it('auto-detects fields from formula', function () {
    $field = Number::make('Complex', 'complex')
        ->computeUsing('subtotal + tax - discount');

    expect($field->getDependsOnFields())
        ->toContain('subtotal')
        ->toContain('tax')
        ->toContain('discount');
});

it('excludes math functions from auto-detected fields', function () {
    $field = Number::make('Rounded', 'rounded')
        ->computeUsing('Math.round(value)');

    expect($field->getDependsOnFields())
        ->toContain('value')
        ->not->toContain('Math')
        ->not->toContain('round');
});

// =========================================================================
// BEHAVIOR OPTIONS
// =========================================================================

it('can clear value on dependency change', function () {
    $field = Select::make('City', 'city_id')
        ->dependsOn('country_id')
        ->clearOnDependencyChange();

    $config = $field->getDependencyConfig();

    expect($config['clearOnChange'])->toBeTrue();
});

it('can set debounce time', function () {
    $field = Input::make('Search', 'search')
        ->dependsOn('category')
        ->debounce(300);

    $config = $field->getDependencyConfig();

    expect($config['debounce'])->toBe(300);
});

// =========================================================================
// SERIALIZATION
// =========================================================================

it('includes dependencies in toArray output', function () {
    $field = Select::make('City', 'city_id')
        ->showWhenHasValue('country_id')
        ->clearOnDependencyChange();

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array)->toHaveKey('dependencies')
        ->and($array['dependencies'])->toHaveKey('fields')
        ->and($array['dependencies'])->toHaveKey('visibility')
        ->and($array['dependencies'])->toHaveKey('clearOnChange');
});

it('returns empty array for dependencies when none configured', function () {
    $field = Input::make('Name', 'name');

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['dependencies'])->toBe([]);
});

it('does not include empty arrays in dependency config', function () {
    $field = Input::make('Computed', 'computed')
        ->computeUsing('a + b');

    $config = $field->getDependencyConfig();

    expect($config)->not->toHaveKey('visibility')
        ->and($config)->not->toHaveKey('disabled')
        ->and($config)->not->toHaveKey('required')
        ->and($config)->toHaveKey('compute')
        ->and($config)->toHaveKey('fields');
});

// =========================================================================
// COMPLEX SCENARIOS
// =========================================================================

it('can combine visibility, disabled, and required conditions', function () {
    $field = Input::make('Complex', 'complex')
        ->showWhenEquals('type', 'special')
        ->disableWhenTruthy('is_locked')
        ->requiredWhenHasValue('parent_id');

    $config = $field->getDependencyConfig();

    expect($config['visibility'])->toHaveCount(1)
        ->and($config['disabled'])->toHaveCount(1)
        ->and($config['required'])->toHaveCount(1)
        ->and($config['fields'])->toBe(['type', 'is_locked', 'parent_id']);
});

it('works with all field types', function () {
    $input = Input::make('Input', 'input')->dependsOn('parent');
    $select = Select::make('Select', 'select')->dependsOn('parent');
    $number = Number::make('Number', 'number')->dependsOn('parent');

    expect($input->hasDependencies())->toBeTrue()
        ->and($select->hasDependencies())->toBeTrue()
        ->and($number->hasDependencies())->toBeTrue();
});
