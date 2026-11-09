<?php

use Illuminate\Support\Facades\Validator;
use SchoolAid\Nadota\Http\Fields\DateTime;
use SchoolAid\Nadota\Tests\Models\TestModel;
use Carbon\Carbon;

function storeDateTime(DateTime $field, mixed $value): mixed
{
    return $field->resolveForStore(createNadotaRequest(), new TestModel(), null, $value);
}

it('can be instantiated', function () {
    $field = DateTime::make('Published At', 'published_at');
    
    expect($field)
        ->toBeField()
        ->toHaveFieldAttribute('published_at')
        ->and($field->getName())->toBe('Published At');
});

it('resolves datetime value from model', function () {
    $date = now();
    $model = createTestModel(['published_at' => $date]);
    $field = DateTime::make('Published At', 'published_at');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe($date->format('Y-m-d H:i:s'));
});

it('can set date format', function () {
    $field = DateTime::make('Published At', 'published_at')
        ->format('Y-m-d');
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['format'])->toBe('Y-m-d');
});

it('can be date only', function () {
    $field = DateTime::make('Date', 'date')
        ->dateOnly();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['dateOnly'])->toBeTrue()
        ->and($array['props']['format'])->toBe('Y-m-d');
});

it('can be time only', function () {
    $field = DateTime::make('Time', 'time')
        ->timeOnly();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['timeOnly'])->toBeTrue()
        ->and($array['props']['format'])->toBe('H:i:s');
});

it('can set min date', function () {
    $field = DateTime::make('Published At', 'published_at')
        ->min(Carbon::parse('2026-01-15 13:00:00'));
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['min'])->toBe('2026-01-15');
});

it('can set max date', function () {
    $field = DateTime::make('Published At', 'published_at')
        ->max(Carbon::parse('2026-03-01'));
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['props']['max'])->toBe('2026-03-01');
});

it('can be made sortable', function () {
    $field = DateTime::make('Published At', 'published_at')
        ->sortable();
    
    expect($field->isSortable())->toBeTrue();
});

it('can be made filterable', function () {
    $field = DateTime::make('Published At', 'published_at')
        ->filterable();
    
    expect($field->isFilterable())->toBeTrue();
});

it('serializes to array correctly', function () {
    $model = createTestModel(['published_at' => now()]);
    $field = DateTime::make('Published At', 'published_at')
        ->format('Y-m-d')
        ->sortable();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, $model, null);
    
    expect($array)
        ->toHaveKey('label', 'Published At')
        ->toHaveKey('attribute', 'published_at')
        ->toHaveKey('sortable', true)
        ->toHaveKey('props')
        ->and($array['props']['format'])->toBe('Y-m-d');
});

it('handles null datetime values', function () {
    $model = createTestModel(['published_at' => null]);
    $field = DateTime::make('Published At', 'published_at');
    $request = createNadotaRequest();
    
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeNull();
});
it('is a datetime field rendered by the FieldDateTime component', function () {
    $field = DateTime::make('Published At', 'published_at');

    expect($field->getType())->toBe('datetime')
        ->and($field->getComponent())->toBe('FieldDateTime')
        ->and($field->toArray(createNadotaRequest())['props'])->toBe([
            'format' => 'Y-m-d H:i:s', 'min' => null, 'max' => null, 'dateOnly' => false, 'timeOnly' => false,
        ]);
});

it('formats the cast value with the display format', function () {
    $model = createTestModel(['published_at' => Carbon::parse('2024-01-15 10:30:45')]);

    expect(DateTime::make('P', 'published_at')->format('d/m/Y H:i')->resolve(createNadotaRequest(), $model, null))->toBe('15/01/2024 10:30')
        ->and(DateTime::make('P', 'published_at')->dateOnly()->resolve(createNadotaRequest(), $model, null))->toBe('2024-01-15')
        ->and(DateTime::make('P', 'published_at')->timeOnly()->resolve(createNadotaRequest(), $model, null))->toBe('10:30:45');
});

it('switching between date only and time only resets the other mode', function () {
    $props = DateTime::make('P', 'p')->dateOnly()->timeOnly()->toArray(createNadotaRequest())['props'];

    expect($props['timeOnly'])->toBeTrue()->and($props['dateOnly'])->toBeFalse();
});

it('converts submitted values to the database format', function (callable $configure, mixed $input, ?string $expected) {
    expect(storeDateTime($configure(DateTime::make('P', 'published_at')), $input))->toBe($expected);
})->with([
    'iso 8601 from the frontend' => [fn ($f) => $f, '2024-01-15T10:30:00.000Z', '2024-01-15 10:30:00'],
    'database format' => [fn ($f) => $f, '2024-01-15 10:30:00', '2024-01-15 10:30:00'],
    'DateTimeInterface' => [fn ($f) => $f, new DateTimeImmutable('2024-02-29 23:59:59'), '2024-02-29 23:59:59'],
    'custom display format' => [fn ($f) => $f->format('d/m/Y H:i'), '15/01/2024 10:30', '2024-01-15 10:30:00'],
    'custom store format' => [fn ($f) => $f->storeFormat('Y-m-d\TH:i'), '2024-01-15 10:30:00', '2024-01-15T10:30'],
    'date only' => [fn ($f) => $f->dateOnly(), '2024-01-15T10:30:00Z', '2024-01-15'],
    'time only' => [fn ($f) => $f->timeOnly(), '2024-01-15 07:05:09', '07:05:09'],
    'empty string' => [fn ($f) => $f, '', null],
    'null' => [fn ($f) => $f, null, null],
]);

it('applies the same conversion on update', function () {
    expect(DateTime::make('P', 'p')->resolveForUpdate(createNadotaRequest(), new TestModel(), null, '2024-01-15T10:30:00Z'))
        ->toBe('2024-01-15 10:30:00');
});

it('hands an unparseable value to the database untouched', function () {
    expect(storeDateTime(DateTime::make('P', 'p'), 'not a date'))->toBe('not a date');
});

it('stores midnight for a date-only display format', function () {
    expect(storeDateTime(DateTime::make('P', 'p')->format('d/m/Y'), '15/01/2024'))->toBe('2024-01-15 00:00:00');
})->skip('ISSUE FLD-021: createFromFormat() without "!" keeps the current time of day for fields missing from the format');

it('rejects an impossible calendar date instead of rolling it over', function () {
    expect(storeDateTime(DateTime::make('P', 'p')->format('d/m/Y H:i'), '31/02/2024 10:00'))->not->toBe('2024-03-02 10:00:00');
})->skip('ISSUE FLD-021: createFromFormat() overflows 31/02 into March instead of rejecting it');

it('validates the submitted value as a date', function () {
    expect(Validator::make(['p' => 'not a date'], ['p' => DateTime::make('P', 'p')->getRules()])->fails())->toBeTrue();
})->skip('ISSUE FLD-013: DateTime adds no date rule, an unparseable value reaches the Eloquent cast and fails with HTTP 500');

it('shows the default when the model has no date', function () {
    $field = DateTime::make('P', 'published_at')->default('2020-01-01 00:00:00');

    expect($field->resolve(createNadotaRequest(), createTestModel(['published_at' => null]), null))->toBe('2020-01-01 00:00:00');
})->skip('ISSUE FLD-014: DateTime::resolve() overrides Field::resolve() and ignores default() and displayUsing()');
