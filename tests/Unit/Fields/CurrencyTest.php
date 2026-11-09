<?php

use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\Currency;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Tests\Models\TestModel;

function currencyModel(mixed $balance): TestModel
{
    $model = new TestModel();
    $model->balance = $balance;

    return $model;
}

it('is a currency field rendered by the FieldCurrency component', function () {
    $field = Currency::make('Balance', 'balance');

    expect($field)->toBeField()
        ->toHaveFieldAttribute('balance')
        ->and($field->getType())->toBe(FieldType::CURRENCY->value)
        ->and($field->getComponent())->toBe('FieldCurrency');
});

it('defaults to a dollar prefix, two decimals and US separators', function () {
    $props = Currency::make('Balance', 'balance')->toArray(createNadotaRequest())['props'];

    expect($props)->toBe([
        'symbol' => '$',
        'symbolPosition' => 'prefix',
        'decimals' => 2,
        'thousandsSeparator' => ',',
        'decimalSeparator' => '.',
        'min' => null,
        'max' => null,
    ]);
});

it('exposes the formatting options in props', function () {
    $props = Currency::make('Price', 'price')
        ->symbol('€')
        ->suffix()
        ->decimals(3)
        ->thousandsSeparator('.')
        ->decimalSeparator(',')
        ->min(0)
        ->max(999.5)
        ->toArray(createNadotaRequest())['props'];

    expect($props['symbol'])->toBe('€')
        ->and($props['symbolPosition'])->toBe('suffix')
        ->and($props['decimals'])->toBe(3)
        ->and($props['thousandsSeparator'])->toBe('.')
        ->and($props['decimalSeparator'])->toBe(',')
        ->and($props['min'])->toBe(0.0)
        ->and($props['max'])->toBe(999.5);
});

it('can switch the symbol back to a prefix', function () {
    $props = Currency::make('Price', 'price')->suffix()->prefix()->toArray(createNadotaRequest())['props'];

    expect($props['symbolPosition'])->toBe('prefix');
});

it('always validates as numeric and adds min/max when set', function () {
    expect(Currency::make('Price', 'price')->getRules())->toBe(['numeric'])
        ->and(Currency::make('Price', 'price')->min(1)->max(10.5)->required()->getRules())
        ->toBe(['required', 'numeric', 'min:1', 'max:10.5']);
});

it('rejects non numeric amounts and amounts out of range', function (mixed $amount, bool $passes) {
    $field = Currency::make('Price', 'price')->min(0)->max(100);

    $validator = Validator::make(['price' => $amount], ['price' => $field->getRules()]);

    expect($validator->passes())->toBe($passes);
})->with([
    'integer' => [10, true],
    'decimal string' => ['99.99', true],
    'zero' => [0, true],
    'negative' => [-1, false],
    'above max' => [100.01, false],
    'text' => ['ten dollars', false],
]);

it('resolves the raw stored amount for display', function () {
    expect(Currency::make('Balance', 'balance')->resolve(createNadotaRequest(), currencyModel('1234.50'), null))
        ->toBe('1234.50');
});

it('exports a formatted amount with the symbol', function (Currency $field, mixed $amount, ?string $expected) {
    expect($field->resolveForExport(createNadotaRequest(), currencyModel($amount), null))->toBe($expected);
})->with([
    'default format' => [fn () => Currency::make('Balance', 'balance'), 1234.5, '$1,234.50'],
    'string amount' => [fn () => Currency::make('Balance', 'balance'), '1000000', '$1,000,000.00'],
    'suffix with european separators' => [
        fn () => Currency::make('Balance', 'balance')->symbol(' €')->suffix()->thousandsSeparator('.')->decimalSeparator(','),
        1234.5,
        '1.234,50 €',
    ],
    'no decimals' => [fn () => Currency::make('Balance', 'balance')->symbol('Q')->decimals(0), 99.6, 'Q100'],
    'null amount' => [fn () => Currency::make('Balance', 'balance'), null, null],
]);
