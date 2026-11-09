<?php

use Illuminate\Http\Request;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Tests\Models\TestModel;

/*
 * Behaviour that lives in the abstract Field class itself, exercised through
 * Input (which adds nothing to it). Trait behaviour (visibility, validation,
 * defaults, filtering...) is covered in tests/Unit/Fields/Traits.
 */

it('derives key and placeholder from the constructor arguments', function () {
    $field = Input::make('Full Name', 'full_name');

    expect($field->key())->toBe('full_name')
        ->and($field->getKey())->toBe('full_name')
        ->and($field->getPlaceholder())->toBe('Full Name')
        ->and($field->getType())->toBe('text')
        ->and($field->getComponent())->toBe('FieldText');
});

it('lets the key and the attribute change independently', function () {
    $field = Input::make('Name', 'name')->withKey('display_name')->withAttribute('first_name');

    expect($field->key())->toBe('display_name')
        ->and($field->getAttribute())->toBe('first_name')
        ->and($field->toArray(createNadotaRequest())['key'])->toBe('display_name');
});

it('lets type and component be overridden', function () {
    $field = Input::make('Name', 'name')->type('custom')->component('MyInput');

    expect($field->getType())->toBe('custom')
        ->and($field->getComponent())->toBe('MyInput');
});

it('serializes every key the frontend relies on', function () {
    $array = Input::make('Name', 'name')->toArray(createNadotaRequest());

    expect(array_keys($array))->toBe([
        'label', 'attribute', 'key', 'placeholder', 'type', 'component',
        'readonly', 'disabled', 'required', 'helpText', 'alert', 'default',
        'sortable', 'searchable', 'filterable', 'filterableRange', 'filterKeys',
        'showOnIndex', 'showOnDetail', 'showOnCreation', 'showOnUpdate', 'exportable',
        'props', 'rules', 'optionsUrl', 'dependencies',
    ]);
});

it('only adds the value when a model is given', function () {
    $model = new TestModel(['name' => 'Ana']);

    expect(Input::make('Name', 'name')->toArray(createNadotaRequest()))->not->toHaveKey('value')
        ->and(Input::make('Name', 'name')->toArray(createNadotaRequest(), $model))->toHaveKey('value', 'Ana');
});

it('reports required and rules for the operation named in the action parameter', function (string $action, bool $required, array $rules) {
    $field = Input::make('Name', 'name')->creationRules(['required'])->updateRules(['nullable']);

    $array = $field->toArray(createNadotaRequest(['action' => $action]));

    expect($array['required'])->toBe($required)
        ->and($array['rules'])->toBe($rules);
})->with([
    'create' => ['create', true, ['required']],
    'store' => ['store', true, ['required']],
    'edit' => ['edit', false, ['nullable']],
    'update' => ['update', false, ['nullable']],
    'no context' => ['show', false, []],
]);

it('serializes help text, alert and default', function () {
    $array = Input::make('Name', 'name')
        ->help('Your legal name')
        ->alert('Check spelling', 'warning')
        ->default('Anonymous')
        ->readonly()
        ->disabled()
        ->toArray(createNadotaRequest());

    expect($array['helpText'])->toBe('Your legal name')
        ->and($array['alert'])->toBe(['text' => 'Check spelling', 'type' => 'warning'])
        ->and($array['default'])->toBe('Anonymous')
        ->and($array['readonly'])->toBeTrue()
        ->and($array['disabled'])->toBeTrue();
});

it('has empty props unless layout options are set', function () {
    expect(Input::make('Name', 'name')->toArray(createNadotaRequest())['props'])->toBe([]);
});

it('exposes the width shortcuts', function (string $method, string $width) {
    expect(Input::make('Name', 'name')->{$method}()->toArray(createNadotaRequest())['props'])->toBe(['width' => $width]);
})->with([
    ['fullWidth', 'full'],
    ['halfWidth', '1/2'],
    ['oneThirdWidth', '1/3'],
    ['twoThirdsWidth', '2/3'],
    ['oneQuarterWidth', '1/4'],
    ['threeQuartersWidth', '3/4'],
]);

it('exposes tab size, heights and a custom width', function () {
    $props = Input::make('Name', 'name')->width('250px')->tabSize(2)->maxHeight(400)->minHeight(100)
        ->toArray(createNadotaRequest())['props'];

    expect($props)->toBe(['width' => '250px', 'tabSize' => 2, 'maxHeight' => 400, 'minHeight' => 100]);
});

it('omits the default tab size of 4', function () {
    expect(Input::make('Name', 'name')->tabSize(4)->toArray(createNadotaRequest())['props'])->not->toHaveKey('tabSize');
});

it('adds the withData() result to props when a model is given', function () {
    $model = new TestModel(['name' => 'Ana']);
    $field = Input::make('Name', 'name')->withData(fn ($item, $resource) => ['upper' => strtoupper($item->name)]);

    expect($field->toArray(createNadotaRequest(), $model)['props']['data'])->toBe(['upper' => 'ANA'])
        ->and($field->toArray(createNadotaRequest())['props'])->not->toHaveKey('data');
});

it('marks custom fields and their component path', function () {
    $custom = Input::make('Name', 'name')->customField();
    $withPath = Input::make('Name', 'name')->componentPath('@/fields/MyField.vue');

    expect($custom->isCustomField())->toBeTrue()
        ->and($custom->toArray(createNadotaRequest())['props'])->toBe(['isCustomField' => true])
        ->and($withPath->isCustomField())->toBeTrue()
        ->and($withPath->getComponentPath())->toBe('@/fields/MyField.vue')
        ->and($withPath->toArray(createNadotaRequest())['props'])
        ->toBe(['isCustomField' => true, 'componentPath' => '@/fields/MyField.vue']);
});

it('makes a computed field readonly and hides it from both forms', function () {
    $field = Input::make('Name', 'name')->computed();
    $request = createNadotaRequest();

    expect($field->isComputed())->toBeTrue()
        ->and($field->isReadonly())->toBeTrue()
        ->and($field->isShowOnCreation($request))->toBeFalse()
        ->and($field->isShowOnUpdate($request))->toBeFalse()
        ->and($field->isShowOnIndex($request))->toBeTrue()
        ->and($field->isShowOnDetail($request))->toBeTrue();
});

it('can turn computed off without touching visibility', function () {
    $field = Input::make('Name', 'name')->computed(false);

    expect($field->isComputed())->toBeFalse()
        ->and($field->isReadonly())->toBeFalse()
        ->and($field->isShowOnCreation(createNadotaRequest()))->toBeTrue();
});

it('resolves through the displayUsing() callback with the model and the resource', function () {
    $model = new TestModel(['name' => 'Ana']);
    $resource = createTestResource();

    $field = Input::make('Greeting', 'name')->displayUsing(fn ($item, $res) => "Hi {$item->name} from " . class_basename($res));

    expect($field->resolve(createNadotaRequest(), $model, $resource))->toBe('Hi Ana from TestResource')
        ->and($field->resolveForExport(createNadotaRequest(), $model, $resource))->toBe('Hi Ana from TestResource')
        ->and($field->isComputed())->toBeTrue();
});

it('treats a displayUsing() field like a computed one: readonly and off the forms', function () {
    $field = Input::make('Greeting', 'name')->displayUsing(fn () => 'x');

    expect($field->isReadonly())->toBeTrue()
        ->and($field->isShowOnCreation(createNadotaRequest()))->toBeFalse()
        ->and($field->isShowOnUpdate(createNadotaRequest()))->toBeFalse();
})->skip('ISSUE FLD-030: displayUsing() sets $computed directly; unlike computed() it leaves the field editable and on both forms, contradicting its docblock');

it('keeps virtual fields out of the select clause and the fill', function () {
    $field = Input::make('Send Email', 'send_email')->virtual();

    expect($field->isVirtual())->toBeTrue()
        ->and($field->shouldSkipFill())->toBeTrue()
        ->and($field->getColumnsForSelect(TestModel::class))->toBe([])
        ->and(Input::make('Name', 'name')->getColumnsForSelect(TestModel::class))->toBe(['name']);
});

it('can opt out of filling while staying a regular column', function () {
    $field = Input::make('Name', 'name')->skipFill();

    expect($field->shouldSkipFill())->toBeTrue()
        ->and($field->isVirtual())->toBeFalse()
        ->and($field->getColumnsForSelect(TestModel::class))->toBe(['name'])
        ->and(Input::make('Name', 'name')->skipFill()->skipFill(false)->shouldSkipFill())->toBeFalse();
});

it('fills the attribute only when the request carries it', function () {
    $model = new TestModel(['name' => 'Old']);

    Input::make('Name', 'name')->fill(new Request(['email' => 'x@y.z']), $model);
    expect($model->name)->toBe('Old');

    Input::make('Name', 'name')->fill(new Request(['name' => 'New']), $model);
    expect($model->name)->toBe('New');

    Input::make('Name', 'name')->fill(new Request(['name' => null]), $model);
    expect($model->name)->toBeNull();
});

it('does not fill computed, readonly or disabled fields', function (callable $configure) {
    $model = new TestModel(['name' => 'Old']);

    $configure(Input::make('Name', 'name'))->fill(new Request(['name' => 'New']), $model);

    expect($model->name)->toBe('Old');
})->with([
    'computed' => [fn ($field) => $field->computed()],
    'readonly' => [fn ($field) => $field->readonly()],
    'disabled' => [fn ($field) => $field->disabled()],
]);

it('stores and updates the submitted value, or the default for null', function () {
    // Only null is asserted: replacing 0 / false / '' by the default (loose == null) is HTTP-009.
    $field = Input::make('Name', 'name')->default('Anonymous');
    $request = createNadotaRequest();
    $model = new TestModel();

    expect($field->resolveForStore($request, $model, null, 'Ana'))->toBe('Ana')
        ->and($field->resolveForStore($request, $model, null, null))->toBe('Anonymous')
        ->and($field->resolveForUpdate($request, $model, null, null))->toBe('Anonymous')
        ->and(Input::make('Name', 'name')->resolveForStore($request, $model, null, null))->toBeNull();
});

it('configures export options', function () {
    $field = Input::make('Name', 'name');

    expect($field->isExportable())->toBeFalse()
        ->and($field->getExportSeparator())->toBe(', ')
        ->and($field->getExportLimit())->toBeNull()
        ->and($field->getExportAttribute())->toBeNull();

    $field->exportUsing('title')->exportSeparator(' | ')->exportLimit(3);

    expect($field->isExportable())->toBeTrue()
        ->and($field->getExportAttribute())->toBe('title')
        ->and($field->getExportSeparator())->toBe(' | ')
        ->and($field->getExportLimit())->toBe(3)
        ->and($field->exportable(false)->isExportable())->toBeFalse();
});

it('exports the resolved value by default', function () {
    $model = new TestModel(['name' => 'Ana']);

    expect(Input::make('Name', 'name')->resolveForExport(createNadotaRequest(), $model, null))->toBe('Ana');
});

it('includes the id in responses unless told otherwise', function () {
    expect(Input::make('Name', 'name')->shouldIncludeId())->toBeTrue()
        ->and(Input::make('Name', 'name')->withoutId()->shouldIncludeId())->toBeFalse();
});

it('selects every related column when it has no resource', function () {
    $field = Input::make('Name', 'name')->except(['secret'])->exceptFields(['notes']);

    expect($field->getRelatedColumns(createNadotaRequest()))->toBeNull()
        ->and($field->getExceptFieldKeys())->toBe(['notes']);
});

it('has no save hooks and no options by default', function () {
    $field = Input::make('Name', 'name');
    $model = new TestModel(['name' => 'Ana']);

    $field->beforeSave(new Request(['name' => 'x']), $model, 'store');
    $field->afterSave(new Request(['name' => 'x']), $model);

    expect($field->supportsAfterSave())->toBeFalse()
        ->and($field->getOptions())->toBe([])
        ->and($model->name)->toBe('Ana');
});

it('exposes protected state through __get and rejects unknown properties', function () {
    $field = Input::make('Name', 'name')->width('1/2');

    expect($field->width)->toBe('1/2')
        ->and($field->fieldData->label)->toBe('Name')
        ->and(fn () => $field->doesNotExist)->toThrow(InvalidArgumentException::class, 'Property doesNotExist does not exist');
});

it('can be subclassed with a custom type and component', function () {
    $field = new class('Rating', 'rating', 'stars', 'FieldStars') extends Field {};

    expect($field->getType())->toBe('stars')
        ->and($field->getComponent())->toBe('FieldStars')
        ->and($field->toArray(createNadotaRequest())['type'])->toBe('stars');
});
