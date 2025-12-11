<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Number extends Field
{
    protected ?float $min = null;
    protected ?float $max = null;
    protected ?float $step = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::NUMBER->value, static::safeConfig('nadota.fields.number.component', 'FieldNumber'));
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

    public function step(float $step): static
    {
        $this->step = $step;
        return $this;
    }

    public function getRules(): array
    {
        $rules = parent::getRules();

        if ($this->min !== null) {
            $rules[] = 'min:' . $this->min;
        }

        if ($this->max !== null) {
            $rules[] = 'max:' . $this->max;
        }

        // Add numeric validation
        $rules[] = 'numeric';

        return $rules;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
        ]);
    }
}