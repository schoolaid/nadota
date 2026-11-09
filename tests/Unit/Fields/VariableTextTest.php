<?php

use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\VariableText;
use SchoolAid\Nadota\Tests\Models\TestModel;

function variableTextPasses(VariableText $field, mixed $value): bool
{
    return Validator::make(['template' => $value], ['template' => $field->getRules()])->passes();
}

it('is a variable text field rendered by the FieldVariableText component', function () {
    $field = VariableText::make('Template', 'template');

    expect($field)->toBeField()
        ->and($field->getType())->toBe(FieldType::VARIABLE_TEXT->value)
        ->and($field->getComponent())->toBe('FieldVariableText');
});

it('uses $ prefixed variables by default', function () {
    $field = VariableText::make('Template', 'template');

    expect($field->formatVariable('name'))->toBe('$name')
        ->and($field->toArray(createNadotaRequest())['props'])->toMatchArray([
            'variables' => [],
            'requiredVariables' => [],
            'optionalVariables' => [],
            'variablePrefix' => '$',
            'variableSuffix' => '',
            'rows' => null,
            'maxLength' => null,
        ]);
});

it('supports mustache, colon and custom variable styles', function () {
    expect(VariableText::make('T', 't')->mustacheStyle()->formatVariable('name'))->toBe('{{name}}')
        ->and(VariableText::make('T', 't')->mustacheStyle()->colonStyle()->formatVariable('name'))->toBe(':name')
        ->and(VariableText::make('T', 't')->variablePrefix('[')->variableSuffix(']')->formatVariable('name'))->toBe('[name]');
});

it('builds the variables config with labels, descriptions, types and defaults', function () {
    $field = VariableText::make('Template', 'template')
        ->requiredVariables(['student_name'])
        ->optionalVariables(['date'])
        ->variableLabels(['student_name' => 'Student'])
        ->variableDescriptions(['date' => 'Delivery date'])
        ->variableTypes(['date' => 'date'])
        ->variableDefaults(['date' => 'today'])
        ->rows(4)
        ->maxLength(500);

    $props = $field->toArray(createNadotaRequest())['props'];

    expect($props['variables'])->toBe([
        'student_name' => [
            'name' => 'student_name', 'formatted' => '$student_name', 'required' => true,
            'label' => 'Student', 'description' => null, 'type' => 'text', 'default' => null,
        ],
        'date' => [
            'name' => 'date', 'formatted' => '$date', 'required' => false,
            'label' => 'Date', 'description' => 'Delivery date', 'type' => 'date', 'default' => 'today',
        ],
    ])
        ->and($props['rows'])->toBe(4)
        ->and($props['maxLength'])->toBe(500);
});

it('registers variables with a single config call', function () {
    $field = VariableText::make('Template', 'template')->variables([
        'name' => ['label' => 'Name', 'required' => true],
        'grade' => ['label' => 'Grade', 'type' => 'number'],
    ]);

    $props = $field->toArray(createNadotaRequest())['props'];

    expect($props['requiredVariables'])->toBe(['name'])
        ->and($props['optionalVariables'])->toBe(['grade'])
        ->and($props['variables']['name']['required'])->toBeTrue()
        ->and($props['variables']['name']['label'])->toBe('Name')
        ->and($props['variables']['grade']['type'])->toBe('number')
        ->and($field->getAllVariables())->toBe(['name', 'grade']);
});

it('lists the required variables missing from a text', function () {
    $field = VariableText::make('Template', 'template')->requiredVariables(['name', 'grade'])->mustacheStyle();

    expect($field->getMissingRequiredVariables('Hi {{name}}'))->toBe(['grade'])
        ->and($field->validateRequiredVariables('Hi {{name}} of {{grade}}'))->toBeTrue()
        ->and($field->validateRequiredVariables('Hi name'))->toBeFalse();
});

it('adds no rule when there are no required variables', function () {
    expect(VariableText::make('Template', 'template')->getRules())->toBe([]);
});

it('fails validation when a required variable is missing and names it', function () {
    $field = VariableText::make('Template', 'template')->requiredVariables(['name'])->mustacheStyle();

    $validator = Validator::make(['template' => 'Hello there'], ['template' => $field->getRules()]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('template'))->toBe('The template must contain the following variables: {{name}}')
        ->and(variableTextPasses($field, 'Hello {{name}}'))->toBeTrue();
});

it('skips the variables check for an empty string, and for null only when nullable', function () {
    $field = VariableText::make('Template', 'template')->requiredVariables(['name']);

    // Laravel runs non-implicit rules on a present null, so null is checked unless the field is nullable.
    expect(variableTextPasses($field, ''))->toBeTrue()
        ->and(variableTextPasses($field, null))->toBeFalse()
        ->and(variableTextPasses(VariableText::make('Template', 'template')->requiredVariables(['name'])->nullable(), null))->toBeTrue();
});

it('does not accept a longer variable as the required one', function () {
    $field = VariableText::make('Template', 'template')->requiredVariables(['name']);

    expect(variableTextPasses($field, 'Hi $name_full'))->toBeFalse();
})->skip('ISSUE FLD-022: required variables are detected with strpos(), so $name_full satisfies $name');

it('rejects a value longer than maxLength', function () {
    $field = VariableText::make('Template', 'template')->maxLength(5);

    expect(variableTextPasses($field, 'longer than five'))->toBeFalse();
})->skip('ISSUE FLD-022: maxLength() is only a frontend prop, no max rule is added');

it('fails validation instead of throwing for a non string value', function () {
    $field = VariableText::make('Template', 'template')->requiredVariables(['name']);

    expect(variableTextPasses($field, ['$name']))->toBeFalse();
})->skip('ISSUE FLD-022: the required-variables closure passes an array to a string parameter (TypeError, HTTP 500)');

it('resolves and stores the raw text', function () {
    $model = new TestModel();
    $model->description = 'Dear $name';

    $field = VariableText::make('Template', 'description');

    expect($field->resolve(createNadotaRequest(), $model, null))->toBe('Dear $name')
        ->and($field->resolveForStore(createNadotaRequest(), $model, null, 'Bye $name'))->toBe('Bye $name');
});
