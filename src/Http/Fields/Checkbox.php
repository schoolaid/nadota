<?php

namespace Said\Nadota\Http\Fields;

class Checkbox extends Field
{
    protected string $component = 'field-checkbox';
    protected mixed $trueValue = 1;
    protected mixed $falseValue = 0;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute);
        $this->type = 'checkbox';
        $this->component = config('nadota.fields.checkbox.component', $this->component);
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

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\Said\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'trueValue' => $this->trueValue,
            'falseValue' => $this->falseValue,
        ]);
    }

    public function resolve(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\Said\Nadota\Contracts\ResourceInterface $resource): mixed
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
