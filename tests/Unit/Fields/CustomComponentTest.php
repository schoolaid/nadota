<?php

use SchoolAid\Nadota\Http\Fields\CustomComponent;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Tests\Models\TestModel;

it('uses the component path as its component and a derived dummy attribute', function () {
    $field = CustomComponent::make('Progress Chart', 'StudentProgressChart');

    expect($field)->toBeField()
        ->and($field->getType())->toBe(FieldType::CUSTOM_COMPONENT->value)
        ->and($field->getComponent())->toBe('StudentProgressChart')
        ->and($field->getComponentPath())->toBe('StudentProgressChart')
        ->and($field->getAttribute())->toBe('custom_component_progress_chart')
        ->and($field->key())->toBe('custom_component_progress_chart');
});

it('is shown only on the detail view by default', function () {
    $field = CustomComponent::make('Chart', 'Chart');
    $request = createNadotaRequest();

    expect($field->isShowOnDetail($request))->toBeTrue()
        ->and($field->isShowOnIndex($request))->toBeFalse()
        ->and($field->isShowOnCreation($request))->toBeFalse()
        ->and($field->isShowOnUpdate($request))->toBeFalse();
});

it('can be moved to the forms only', function () {
    $field = CustomComponent::make('Chart', 'Chart')->onlyOnForms();
    $request = createNadotaRequest();

    expect($field->isShowOnCreation($request))->toBeTrue()
        ->and($field->isShowOnUpdate($request))->toBeTrue()
        ->and($field->isShowOnDetail($request))->toBeFalse();
});

it('never touches the model or the query', function () {
    $field = CustomComponent::make('Chart', 'Chart');
    $model = new TestModel();

    $field->fill(createNadotaRequest(['custom_component_chart' => 'x']), $model);

    expect($model->getAttributes())->toBe([])
        ->and($field->shouldSkipFill())->toBeTrue()
        ->and($field->isComputed())->toBeFalse()
        ->and($field->isAppliedInIndexQuery())->toBeFalse()
        ->and($field->isAppliedInShowQuery())->toBeFalse()
        ->and($field->getColumnsForSelect(TestModel::class))->toBe([]);
});

it('renders inside the detail card unless told otherwise', function (callable $configure, string $position) {
    $field = $configure(CustomComponent::make('Chart', 'Chart'));

    expect($field->toArray(createNadotaRequest())['props']['position'])->toBe($position);
})->with([
    'default' => [fn ($field) => $field, 'inside'],
    'below' => [fn ($field) => $field->below(), 'below'],
    'below then inside' => [fn ($field) => $field->below()->inside(), 'inside'],
    'custom position' => [fn ($field) => $field->position('sidebar'), 'sidebar'],
]);

it('merges extra component props', function () {
    $props = CustomComponent::make('Chart', 'Chart')
        ->withProps(['color' => 'green', 'size' => 'sm'])
        ->withProps(['size' => 'lg'])
        ->withProp('legend', true)
        ->toArray(createNadotaRequest())['props'];

    expect($props['componentPath'])->toBe('Chart')
        ->and($props['componentProps'])->toBe(['color' => 'green', 'size' => 'lg', 'legend' => true]);
});

it('resolves to null without a data callback', function () {
    expect(CustomComponent::make('Chart', 'Chart')->resolve(createNadotaRequest(), new TestModel(), null))->toBeNull();
});

it('passes the model and the resource to the data callback and returns its result as value', function () {
    $model = new TestModel(['name' => 'Ana', 'age' => 9]);
    $resource = createTestResource();
    $received = [];

    $field = CustomComponent::make('Chart', 'Chart')->withData(function ($item, $res) use (&$received) {
        $received = [$item, $res];

        return ['age' => $item->age];
    });

    $array = $field->toArray(createNadotaRequest(), $model, $resource);

    expect($array['value'])->toBe(['age' => 9])
        ->and($array['props']['componentData'])->toBe(['age' => 9])
        ->and($received[0])->toBe($model)
        ->and($received[1])->toBe($resource);
});

it('does not call the data callback when there is no model', function () {
    $calls = 0;
    $field = CustomComponent::make('Chart', 'Chart')->withData(function () use (&$calls) {
        $calls++;

        return [];
    });

    $props = $field->toArray(createNadotaRequest())['props'];

    expect($calls)->toBe(0)
        ->and($props)->not->toHaveKey('componentData')
        ->and($props)->not->toHaveKey('data');
});

it('evaluates the data callback once per serialization', function () {
    $calls = 0;
    $field = CustomComponent::make('Chart', 'Chart')->withData(function () use (&$calls) {
        $calls++;

        return ['n' => $calls];
    });

    $field->toArray(createNadotaRequest(), new TestModel(), null);

    expect($calls)->toBe(1);
});

it('sends the same data under value, props.data and props.componentData', function () {
    $array = CustomComponent::make('Chart', 'Chart')
        ->withData(fn ($item) => ['age' => $item->age])
        ->toArray(createNadotaRequest(), new TestModel(['age' => 9]), null);

    expect($array['value'])->toBe(['age' => 9])
        ->and($array['props']['data'])->toBe(['age' => 9])
        ->and($array['props']['componentData'])->toBe(['age' => 9]);
});

it('computes the data again for each row and outside a serialization', function () {
    $calls = 0;
    $field = CustomComponent::make('Chart', 'Chart')->withData(function ($item) use (&$calls) {
        $calls++;

        return ['age' => $item->age];
    });
    $ana = new TestModel(['age' => 9]);
    $luis = new TestModel(['age' => 12]);

    expect($field->toArray(createNadotaRequest(), $ana, null)['value'])->toBe(['age' => 9])
        ->and($field->toArray(createNadotaRequest(), $luis, null)['value'])->toBe(['age' => 12]);

    // A direct resolve() (export) never reuses the result of a previous serialization.
    $ana->age = 10;
    expect($field->resolve(createNadotaRequest(), $ana, null))->toBe(['age' => 10])
        ->and($field->resolve(createNadotaRequest(), $ana, null))->toBe(['age' => 10])
        ->and($calls)->toBe(4);
});

it('keeps the serialized component in sync with component()', function () {
    $field = CustomComponent::make('Chart', 'OldChart')->component('NewChart');

    $array = $field->toArray(createNadotaRequest());

    expect($array['props']['componentPath'])->toBe('NewChart')
        ->and($array['component'])->toBe('NewChart');
});
