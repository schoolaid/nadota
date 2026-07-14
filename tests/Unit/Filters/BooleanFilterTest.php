<?php

use SchoolAid\Nadota\Http\Filters\BooleanFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

beforeEach(function () {
    $this->request = Mockery::mock(NadotaRequest::class);
});

afterEach(function () {
    Mockery::close();
});

it('falls back to the Sí/No labels when the app sets no config', function () {
    $filter = new BooleanFilter('Liquidated', 'is_liquidated');

    expect($filter->resources($this->request))->toBe([
        'Sí' => true,
        'No' => false,
    ]);
});

it('uses the labels configured by the app, so it can hand i18n keys to the frontend', function () {
    config()->set('nadota.filters.boolean.true_label', 'nadota.yes');
    config()->set('nadota.filters.boolean.false_label', 'nadota.no');

    $filter = new BooleanFilter('Liquidated', 'is_liquidated');

    expect($filter->resources($this->request))->toBe([
        'nadota.yes' => true,
        'nadota.no' => false,
    ]);
});

it('serializes the configured labels as the filter options', function () {
    config()->set('nadota.filters.boolean.true_label', 'nadota.yes');
    config()->set('nadota.filters.boolean.false_label', 'nadota.no');

    $filter = new BooleanFilter('creditCardHistoryResource.fields.isLiquidated', 'is_liquidated');

    expect($filter->toArray($this->request)['options'])->toBe([
        ['label' => 'nadota.yes', 'value' => true],
        ['label' => 'nadota.no', 'value' => false],
    ]);
});
