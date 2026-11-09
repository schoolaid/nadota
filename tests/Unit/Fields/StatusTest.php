<?php

use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Status;
use SchoolAid\Nadota\Tests\Models\TestModel;

enum StatusTestPaymentState: string
{
    case PAID = 'paid';
    case PENDING = 'pending';
}

function statusFieldModel(mixed $status, ?string $cast = null): TestModel
{
    $model = new TestModel();

    if ($cast !== null) {
        $model->mergeCasts(['status' => $cast]);
    }

    $model->status = $status;

    return $model;
}

function paymentStatusField(): Status
{
    return Status::make('Payment', 'status')
        ->addStatus('paid', 'Paid', 'green', 'check')
        ->addStatus('pending', 'Pending', 'yellow')
        ->addStatus('failed', 'Failed', 'red');
}

it('is a status field rendered by the FieldStatus component', function () {
    $field = Status::make('Payment', 'status');

    expect($field)->toBeField()
        ->and($field->getType())->toBe(FieldType::STATUS->value)
        ->and($field->getComponent())->toBe('FieldStatus');
});

it('builds the status list from addStatus() with gray as default color and optional icon', function () {
    $field = Status::make('Payment', 'status')
        ->addStatus('paid', 'Paid', 'green', 'check')
        ->addStatus(3, 'Three');

    // Key order inside each entry is not part of the contract, hence toEqual().
    expect($field->getOptions())->toEqual([
        ['value' => 'paid', 'label' => 'Paid', 'color' => 'green', 'icon' => 'check'],
        ['value' => 3, 'label' => 'Three', 'color' => 'gray'],
    ]);
});

it('accepts backed enum cases in addStatus()', function () {
    $field = Status::make('Payment', 'status')
        ->addStatus(StatusTestPaymentState::PAID, 'Paid', 'green')
        ->addStatus(StatusTestPaymentState::PENDING, 'Pending', 'yellow');

    expect(array_column($field->getOptions(), 'value'))->toBe(['paid', 'pending']);
});

it('normalizes a map of shorthand labels and full configs', function () {
    $field = Status::make('Payment', 'status')->map([
        'draft' => 'Draft',
        'failed' => ['label' => 'Failed', 'color' => 'red'],
        'review' => ['label' => 'Review'],
    ]);

    expect($field->getOptions())->toEqual([
        ['value' => 'draft', 'label' => 'Draft', 'color' => 'gray'],
        ['value' => 'failed', 'color' => 'red', 'label' => 'Failed'],
        ['value' => 'review', 'color' => 'gray', 'label' => 'Review'],
    ]);
});

it('replaces earlier statuses when map() is called', function () {
    $field = paymentStatusField()->map(['only' => 'Only']);

    expect($field->getOptions())->toHaveCount(1);
});

it('exposes statuses, clearable, placeholder and translation flag in props', function () {
    $array = paymentStatusField()->clearable()->placeholder('Pick one')->withoutTranslation()
        ->toArray(createNadotaRequest());

    expect($array['props']['statuses'])->toHaveCount(3)
        ->and($array['props']['clearable'])->toBeTrue()
        ->and($array['props']['placeholder'])->toBe('Pick one')
        ->and($array['props']['translateLabels'])->toBeFalse()
        ->and($array['placeholder'])->toBe('Pick one');
});

it('translates labels and is not clearable by default', function () {
    $props = Status::make('Payment', 'status')->toArray(createNadotaRequest())['props'];

    expect($props['translateLabels'])->toBeTrue()
        ->and($props['clearable'])->toBeFalse()
        ->and($props['placeholder'])->toBeNull();
});

it('resolves the raw value by default', function () {
    expect(paymentStatusField()->resolve(createNadotaRequest(), statusFieldModel('pending'), null))->toBe('pending')
        ->and(paymentStatusField()->resolve(createNadotaRequest(), statusFieldModel(null), null))->toBeNull();
});

it('resolves the full status object with withStatus()', function () {
    $field = paymentStatusField()->withStatus();

    expect($field->resolve(createNadotaRequest(), statusFieldModel('paid'), null))
        ->toEqual(['value' => 'paid', 'label' => 'Paid', 'color' => 'green', 'icon' => 'check'])
        ->and($field->resolve(createNadotaRequest(), statusFieldModel('archived'), null))
        ->toBe(['value' => 'archived', 'label' => 'archived', 'color' => 'gray'])
        ->and($field->resolve(createNadotaRequest(), statusFieldModel(null), null))->toBeNull();
});

it('matches integer statuses against string database values', function () {
    $field = Status::make('Level', 'status')->addStatus(1, 'One', 'blue')->withStatus();

    expect($field->resolve(createNadotaRequest(), statusFieldModel('1'), null)['label'])->toBe('One');
});

it('unwraps an enum cast attribute to its backing value', function () {
    $model = statusFieldModel(StatusTestPaymentState::PAID, StatusTestPaymentState::class);

    expect(paymentStatusField()->resolve(createNadotaRequest(), $model, null))->toBe('paid')
        ->and(paymentStatusField()->withStatus()->resolve(createNadotaRequest(), $model, null)['label'])->toBe('Paid')
        ->and(paymentStatusField()->resolveForExport(createNadotaRequest(), $model, null))->toBe('Paid');
});

it('exports the status label and falls back to the raw value', function () {
    expect(paymentStatusField()->resolveForExport(createNadotaRequest(), statusFieldModel('failed'), null))->toBe('Failed')
        ->and(paymentStatusField()->resolveForExport(createNadotaRequest(), statusFieldModel('unknown'), null))->toBe('unknown')
        ->and(paymentStatusField()->resolveForExport(createNadotaRequest(), statusFieldModel(null), null))->toBeNull();
});

it('rejects a value that is not in the status map', function () {
    $validator = Validator::make(['status' => 'bogus'], ['status' => paymentStatusField()->getRules()]);

    expect($validator->fails())->toBeTrue();
})->skip('ISSUE FLD-023: option based fields (Select, Radio, CheckboxList, Status) add no in: rule');
