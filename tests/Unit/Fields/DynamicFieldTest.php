<?php

use SchoolAid\Nadota\Http\Fields\DynamicField;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Fields\Date;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\Checkbox;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\RelatedModelResource;
use Illuminate\Http\Request;

/** A DynamicField over the "description" column, typed by "status". */
function answerDynamicField(): DynamicField
{
    return DynamicField::make('Answer', 'description')
        ->basedOn('status')
        ->types([
            'bool' => Checkbox::make('Answer', 'description')->trueValue('yes')->falseValue('no'),
            'number' => Number::make('Answer', 'description')->min(0),
        ])
        ->defaultType(Input::make('Answer', 'description'));
}

function answerModel(?string $status, mixed $description): TestModel
{
    return new TestModel(['status' => $status, 'description' => $description]);
}

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
        ])
        ->withAllTypes();

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
        ->when(2, Number::make('Number', 'value'))
        ->withAllTypes();

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
        ])
        ->withAllTypes();

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

it('does not include all types by default', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type')
        ->types([
            1 => Input::make('Text', 'value'),
            2 => Number::make('Number', 'value'),
        ]);

    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);

    expect($array['props'])->not->toHaveKey('types');
});

it('includes matched field config for the current type value', function () {
    $field = DynamicField::make('Value', 'value')
        ->basedOn('type')
        ->types([
            1 => Input::make('Text', 'value'),
            2 => Number::make('Number', 'value'),
        ]);

    $request = createNadotaRequest(['type' => 2]);
    $array = $field->toArray($request, null, null);

    expect($array['props']['matchedType'])->toBe(2)
        ->and($array['props']['matchedField']['type'])->toBe('number');
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
        ->defaultType(Input::make('Default', 'value'))
        ->withAllTypes();

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
        ])
        ->withAllTypes();

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

// =========================================================================
// RESOLUTION
// =========================================================================

it('is rendered by the FieldDynamic component', function () {
    expect(DynamicField::make('Value', 'value')->getComponent())->toBe('FieldDynamic');
});

it('resolves the value through the field matched by the model type', function () {
    expect(answerDynamicField()->resolve(createNadotaRequest(), answerModel('bool', 'no'), null))->toBeFalse()
        ->and(answerDynamicField()->resolve(createNadotaRequest(), answerModel('number', '7'), null))->toBe('7')
        ->and(answerDynamicField()->resolve(createNadotaRequest(), answerModel('other', 'free text'), null))->toBe('free text');
});

it('takes the type from the request when the model has none', function () {
    $array = answerDynamicField()->toArray(createNadotaRequest(['status' => 'number']), answerModel(null, null));

    expect($array['props']['matchedType'])->toBe('number')
        ->and($array['props']['matchedField']['type'])->toBe('number');
});

it('prefers the model type over the request type', function () {
    $array = answerDynamicField()->toArray(createNadotaRequest(['status' => 'number']), answerModel('bool', 'yes'));

    expect($array['props']['matchedType'])->toBe('bool')
        ->and($array['value'])->toBeTrue();
});

it('falls back to the raw value without a type field or matching type', function () {
    $model = answerModel('x', 'raw');

    expect(DynamicField::make('Answer', 'description')->resolve(createNadotaRequest(), $model, null))->toBe('raw')
        ->and(DynamicField::make('Answer', 'description')->basedOn('status')->resolve(createNadotaRequest(), $model, null))->toBe('raw');
});

it('passes the model and the request to a closure type', function () {
    $received = [];
    $field = DynamicField::make('Answer', 'description')->basedOn('status')->types([
        'lazy' => function ($model, $request) use (&$received) {
            $received = [$model, $request];

            return Input::make('Answer', 'description');
        },
    ]);
    $model = answerModel('lazy', 'v');
    $request = createNadotaRequest();

    expect($field->resolve($request, $model, null))->toBe('v')
        ->and($received[0])->toBe($model)
        ->and($received[1])->toBe($request);
});

it('resolves the type through a relation with dot notation', function () {
    $field = DynamicField::make('Answer', 'description')->basedOn('profile.kind');

    expect($field->getTypeFieldRelation())->toBe('profile')
        ->and($field->getRequiredRelations())->toBe(['profile'])
        ->and(DynamicField::make('A', 'a')->basedOn('kind')->getTypeFieldRelation())->toBeNull()
        ->and(DynamicField::make('A', 'a')->getRequiredRelations())->toBe([]);
});

it('resolves each model with its own type', function () {
    $field = answerDynamicField();
    $request = createNadotaRequest();

    $first = $field->resolveForExport($request, answerModel('bool', 'yes'), null);
    $second = $field->resolveForExport($request, answerModel('number', '42'), null);

    expect($first)->toBeTrue()
        ->and($second)->toBe('42');
})->skip('ISSUE FLD-008: resolve() caches the matched field on the instance; the export reuses the first row type for every row');

// =========================================================================
// PERSISTENCE
// =========================================================================

it('delegates fill to the matched field', function () {
    $model = answerModel('bool', null);

    answerDynamicField()->fill(new Request(['status' => 'bool', 'description' => 'true']), $model);

    expect($model->description)->toBe('true');
});

it('converts the value with the matched field before storing it', function () {
    $model = answerModel('bool', null);

    expect(answerDynamicField()->resolveForStore(createNadotaRequest(), $model, null, true))->toBe('yes');
})->skip('ISSUE FLD-007: DynamicField does not delegate resolveForStore/Update; store and update persist the raw value');

it('validates with the rules of the matched field', function () {
    $field = answerDynamicField();
    $field->toArray(createNadotaRequest(['status' => 'number']), answerModel('number', null));

    expect($field->getRules())->toContain('numeric')->toContain('min:0');
})->skip('ISSUE FLD-006: DynamicField::getRules() is inside an unterminated docblock, so only the base rules apply');

it('delegates the save hooks to the matched field', function () {
    $model = answerModel('bool', null);
    $request = new Request(['status' => 'bool']);
    $calls = [];
    $spy = new class('Answer', 'description') extends Input {
        public array $calls = [];

        public function beforeSave(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, string $operation): void
        {
            $this->calls[] = "before:{$operation}";
        }

        public function afterSave(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model): void
        {
            $this->calls[] = 'after';
        }

        public function supportsAfterSave(): bool
        {
            return true;
        }
    };

    $field = DynamicField::make('Answer', 'description')->basedOn('status')->types(['bool' => $spy]);

    expect($field->supportsAfterSave())->toBeTrue();

    $field->beforeSave($request, $model, 'store');
    $field->afterSave($request, $model);

    expect($spy->calls)->toBe(['before:store', 'after'])
        ->and(answerDynamicField()->supportsAfterSave())->toBeFalse();
});

// =========================================================================
// QUERY
// =========================================================================

it('selects its own column, the type column and the columns of every type', function () {
    $field = DynamicField::make('Answer', 'description')->basedOn('status')->types([
        'a' => Input::make('A', 'name'),
        'b' => Input::make('B', 'email'),
        'c' => fn () => Input::make('C', 'never_selected'),
    ])->defaultType(Input::make('D', 'age'));

    expect(array_values($field->getColumnsForSelect(TestModel::class)))->toBe(['description', 'status', 'name', 'email', 'age'])
        ->and(DynamicField::make('A', 'description')->basedOn('profile.kind')->getColumnsForSelect(TestModel::class))->toBe(['description']);
});

it('reports the relation fields of its types for eager loading', function () {
    $relation = BelongsTo::make('Related', 'relatedModel', RelatedModelResource::class);
    $field = DynamicField::make('Answer', 'description')->basedOn('status')->types([
        'rel' => $relation,
        'text' => Input::make('T', 'description'),
    ]);

    expect($field->getNestedRelationFields())->toBe([$relation])
        ->and($field->hasNestedRelations())->toBeTrue()
        ->and(answerDynamicField()->hasNestedRelations())->toBeFalse();
});

