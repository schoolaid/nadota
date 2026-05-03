<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Currency extends Field
{
    protected string $symbol = '$';
    protected string $symbolPosition = 'prefix';
    protected int $decimals = 2;
    protected string $thousandsSeparator = ',';
    protected string $decimalSeparator = '.';
    protected ?float $min = null;
    protected ?float $max = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::CURRENCY->value, static::safeConfig('nadota.fields.currency.component', 'FieldCurrency'));
    }

    public function symbol(string $symbol): static
    {
        $this->symbol = $symbol;
        return $this;
    }

    public function prefix(): static
    {
        $this->symbolPosition = 'prefix';
        return $this;
    }

    public function suffix(): static
    {
        $this->symbolPosition = 'suffix';
        return $this;
    }

    public function decimals(int $decimals): static
    {
        $this->decimals = $decimals;
        return $this;
    }

    public function thousandsSeparator(string $separator): static
    {
        $this->thousandsSeparator = $separator;
        return $this;
    }

    public function decimalSeparator(string $separator): static
    {
        $this->decimalSeparator = $separator;
        return $this;
    }

    public function min(float $min): static
    {
        $this->min = $min;
        return $this;
    }

    public function max(float $max): static
    {
        $this->max = $max;
        return $this;
    }

    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules[] = 'numeric';

        if ($this->min !== null) {
            $rules[] = 'min:' . $this->min;
        }

        if ($this->max !== null) {
            $rules[] = 'max:' . $this->max;
        }

        return $rules;
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'symbol' => $this->symbol,
            'symbolPosition' => $this->symbolPosition,
            'decimals' => $this->decimals,
            'thousandsSeparator' => $this->thousandsSeparator,
            'decimalSeparator' => $this->decimalSeparator,
            'min' => $this->min,
            'max' => $this->max,
        ]);
    }

    public function resolveForExport(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};

        if ($value === null) {
            return null;
        }

        $formatted = number_format(
            (float) $value,
            $this->decimals,
            $this->decimalSeparator,
            $this->thousandsSeparator
        );

        return $this->symbolPosition === 'prefix'
            ? $this->symbol . $formatted
            : $formatted . $this->symbol;
    }
}
