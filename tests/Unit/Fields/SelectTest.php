<?php

use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Tests\Models\TestModel;

enum SelectTestColor: string
{
    case RED = 'red';
    case BLUE = 'blue';
}

it('can be instantiated', function () {
    $field = Select::make('Status', 'status');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('status')
        ->and($field->getName())->toBe('Status');
});

it('can set options', function () {
    $field = Select::make('Status', 'status')
        ->options([
            'active' => 'Active',
            'inactive' => 'Inactive',
            'pending' => 'Pending'
        ]);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array)->toHaveKey('props')
        ->and($array['props'])->toHaveKey('options')
        ->and($array['props']['options'])->toHaveCount(3);
});

it('can enable multiple selection', function () {
    $field = Select::make('Tags', 'tags')
        ->multiple();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['multiple'])->toBeTrue();
});

it('can make select clearable', function () {
    $field = Select::make('Category', 'category_id')
        ->clearable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['clearable'])->toBeTrue();
});

it('can set placeholder', function () {
    $field = Select::make('Country', 'country')
        ->placeholder('Select a country');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['placeholder'])->toBe('Select a country');
});

it('formats options correctly', function () {
    $field = Select::make('Status', 'status')
        ->options([
            'active' => 'Active',
            'inactive' => 'Inactive'
        ]);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['options'])->toBe([
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'inactive', 'label' => 'Inactive']
    ]);
});

it('accepts pre-formatted options', function () {
    $options = [
        ['value' => 1, 'label' => 'Option 1'],
        ['value' => 2, 'label' => 'Option 2']
    ];
    
    $field = Select::make('Choice', 'choice')
        ->options($options);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['options'])->toBe($options);
});

it('can be made sortable', function () {
    $field = Select::make('Priority', 'priority')
        ->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = Select::make('Status', 'status')
        ->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('resolves single value from model', function () {
    $model = createTestModel(['status' => 'active']);
    $field = Select::make('Status', 'status');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('active');
});

it('resolves multiple values from model', function () {
    // 'status' has no cast, so the JSON string reaches the field as stored. On 'tags' (array cast)
    // the string would be double encoded by Eloquent and only round-trip by accident.
    $model = createTestModel(['status' => '["php","laravel"]']);
    $field = Select::make('Tags', 'status')
        ->multiple();
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe(['php', 'laravel']);
});

it('serializes to array correctly', function () {
    $model = createTestModel(['status' => 'active']);
    $field = Select::make('Status', 'status')
        ->options([
            'active' => 'Active',
            'inactive' => 'Inactive'
        ])
        ->clearable()
        ->placeholder('Choose status');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Status')
        ->toHaveKey('attribute', 'status')
        ->toHaveKey('value', 'active')
        ->toHaveKey('props')
        ->and($array['props']['clearable'])->toBeTrue()
        ->and($array['props']['placeholder'])->toBe('Choose status')
        ->and($array['props']['options'])->toHaveCount(2);
});
it('is a select field rendered by the FieldSelect component', function () {
    $field = Select::make('Status', 'status');

    expect($field->getType())->toBe('select')
        ->and($field->getComponent())->toBe('FieldSelect')
        ->and($field->toArray(createNadotaRequest())['props'])->toBe([
            'options' => [],
            'multiple' => false,
            'clearable' => false,
            'placeholder' => null,
            'translateLabels' => true,
        ]);
});

it('sets both the field placeholder and the select placeholder prop', function () {
    $array = Select::make('Country', 'country')->placeholder('Pick one')->toArray(createNadotaRequest());

    expect($array['placeholder'])->toBe('Pick one')
        ->and($array['props']['placeholder'])->toBe('Pick one');
});

it('can disable label translation', function () {
    expect(Select::make('S', 's')->withoutTranslation()->toArray(createNadotaRequest())['props']['translateLabels'])->toBeFalse()
        ->and(Select::make('S', 's')->translateLabels(false)->translateLabels()->toArray(createNadotaRequest())['props']['translateLabels'])->toBeTrue();
});

it('normalizes options that use custom value and label keys', function () {
    $options = [['uuid' => 'ec1', 'value' => 'Single'], ['uuid' => 'ec2', 'value' => 'Married']];

    expect(Select::make('Civil', 'civil')->options($options)->uuidValueFormat()->getOptions())->toBe([
        ['value' => 'ec1', 'label' => 'Single'],
        ['value' => 'ec2', 'label' => 'Married'],
    ])->and(Select::make('Civil', 'civil')->options([['id' => 1, 'name' => 'One']])->valueKey('id')->labelKey('name')->getOptions())
        ->toBe([['value' => 1, 'label' => 'One']]);
});

it('resolves the value with its label when asked to', function () {
    $field = Select::make('Status', 'status')->options(['active' => 'Active', 'inactive' => 'Inactive'])->withLabel();

    expect($field->resolve(createNadotaRequest(), createTestModel(['status' => 'active']), null))
        ->toBe(['value' => 'active', 'label' => 'Active'])
        ->and($field->resolve(createNadotaRequest(), createTestModel(['status' => 'other']), null))
        ->toBe(['value' => 'other', 'label' => 'other'])
        ->and($field->resolve(createNadotaRequest(), createTestModel(['status' => null]), null))->toBeNull();
});

it('resolves every selected value with its label for a multiple select', function () {
    $field = Select::make('Tags', 'tags')->options(['php' => 'PHP', 'js' => 'JavaScript'])->multiple()->withLabel();

    expect($field->resolve(createNadotaRequest(), createTestModel(['tags' => ['php', 'js']]), null))->toBe([
        ['value' => 'php', 'label' => 'PHP'],
        ['value' => 'js', 'label' => 'JavaScript'],
    ]);
});

it('matches labels across int and string values', function () {
    $field = Select::make('Level', 'status')->options([1 => 'One', 2 => 'Two'])->withLabel();

    expect($field->resolve(createNadotaRequest(), createTestModel(['status' => '2']), null)['label'])->toBe('Two');
});

it('unwraps an enum cast attribute to its backing value', function () {
    $model = new TestModel();
    $model->mergeCasts(['status' => SelectTestColor::class]);
    $model->status = SelectTestColor::BLUE;

    $field = Select::make('Color', 'status')->options(['red' => 'Red', 'blue' => 'Blue']);

    expect($field->resolve(createNadotaRequest(), $model, null))->toBe('blue')
        ->and($field->resolveForExport(createNadotaRequest(), $model, null))->toBe('Blue');
});

it('exports labels instead of values', function () {
    $field = Select::make('Status', 'status')->options(['active' => 'Active']);

    expect($field->resolveForExport(createNadotaRequest(), createTestModel(['status' => 'active']), null))->toBe('Active')
        ->and($field->resolveForExport(createNadotaRequest(), createTestModel(['status' => 'gone']), null))->toBe('gone')
        ->and($field->resolveForExport(createNadotaRequest(), createTestModel(['status' => null]), null))->toBeNull();
});

it('exports a multiple selection as a comma separated list of labels', function () {
    $field = Select::make('Tags', 'tags')->options(['php' => 'PHP', 'js' => 'JavaScript'])->multiple();

    expect($field->resolveForExport(createNadotaRequest(), createTestModel(['tags' => ['js', 'php']]), null))->toBe('JavaScript, PHP');
});

it('stores the submitted value as is', function () {
    expect(Select::make('Status', 'status')->resolveForStore(createNadotaRequest(), new TestModel(), null, 'active'))->toBe('active');
});

it('encodes a multiple selection for a column without an array cast', function () {
    $field = Select::make('Tags', 'status')->multiple();

    // 'status' is a plain string column: assigning an array would fail on save ("Array to string conversion").
    expect($field->resolveForStore(createNadotaRequest(), new TestModel(), null, ['php', 'js']))->toBe('["php","js"]');
})->skip('ISSUE FLD-032: Select::multiple() decodes JSON strings on resolve but never encodes on store (CheckboxList does)');

it('rejects a value that is not one of the options', function () {
    $field = Select::make('Status', 'status')->options(['active' => 'Active']);

    expect(Validator::make(['status' => 'bogus'], ['status' => $field->getRules()])->fails())->toBeTrue();
})->skip('ISSUE FLD-023: option based fields (Select, Radio, CheckboxList, Status) add no in: rule');
