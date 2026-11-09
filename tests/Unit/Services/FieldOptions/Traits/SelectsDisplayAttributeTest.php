<?php

use Illuminate\Database\Eloquent\Casts\Attribute;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Services\FieldOptions\Traits\SelectsDisplayAttribute;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;

function selectsDisplayAttributeHost(): object
{
    return new class {
        use SelectsDisplayAttribute;

        public function __call($method, $arguments)
        {
            return $this->{$method}(...$arguments);
        }
    };
}

it('adds the display attribute the related resource does not select', function () {
    $field = BelongsTo::make('Grade', 'grade')->displayAttribute('level');

    expect(selectsDisplayAttributeHost()->withDisplayAttribute(['id', 'name'], $field, Grade::query()))
        ->toBe(['id', 'name', 'level']);
});

it('does not add it twice', function () {
    $field = BelongsTo::make('Grade', 'grade')->displayAttribute('name');

    expect(selectsDisplayAttributeHost()->withDisplayAttribute(['id', 'name'], $field, Grade::query()))
        ->toBe(['id', 'name']);
});

it('leaves a select-all alone', function () {
    $field = BelongsTo::make('Grade', 'grade')->displayAttribute('level');

    expect(selectsDisplayAttributeHost()->withDisplayAttribute(['*'], $field, Grade::query()))->toBe(['*']);
});

it('changes nothing without a display attribute', function () {
    expect(selectsDisplayAttributeHost()->withDisplayAttribute(['id'], BelongsTo::make('Grade', 'grade'), Grade::query()))
        ->toBe(['id']);
});

it('does not select accessors, legacy or Attribute-style', function (string $accessor) {
    $model = new class extends Grade {
        protected $table = 'fx_grades';

        public function getLabelTextAttribute(): string
        {
            return "{$this->name} ({$this->level})";
        }

        protected function shortName(): Attribute
        {
            return Attribute::get(fn () => substr($this->name, 0, 3));
        }
    };

    $field = BelongsTo::make('Grade', 'grade')->displayAttribute($accessor);

    expect(selectsDisplayAttributeHost()->withDisplayAttribute(['id', 'name'], $field, $model->newQuery()))
        ->toBe(['id', 'name']);
})->with(['label_text', 'short_name']);
