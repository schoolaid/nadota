<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Toggle extends Field
{
    protected string $component = 'field-toggle';
    protected string $trueLabel = 'On';
    protected string $falseLabel = 'Off';
    protected mixed $trueValue = 1;
    protected mixed $falseValue = 0;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::BOOLEAN->value, config('nadota.fields.toggle.component', 'FieldToggle'));
    }

    public function trueLabel(string $label): static
    {
        $this->trueLabel = $label;
        return $this;
    }

    public function falseLabel(string $label): static
    {
        $this->falseLabel = $label;
        return $this;
    }

    public function trueValue(mixed $value): static
    {
        $this->trueValue = $value;
        return $this;
    }

    public function falseValue(mixed $value): static
    {
        $this->falseValue = $value;
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'trueLabel' => $this->trueLabel,
            'falseLabel' => $this->falseLabel,
            'trueValue' => $this->trueValue,
            'falseValue' => $this->falseValue,
        ]);
    }

    public function resolve(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};
        
        // Return the actual value if it matches our custom values
        if ($value === $this->trueValue) {
            return true;
        }
        
        if ($value === $this->falseValue || $value === null) {
            return false;
        }
        
        // Fallback to boolean cast
        return (bool) $value;
    }
}
