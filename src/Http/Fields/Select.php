<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Select extends Field
{
    protected string $component = 'field-select';
    protected array $options = [];
    protected bool $multiple = false;
    protected bool $clearable = false;
    protected ?string $placeholder = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::SELECT->value, config('nadota.fields.select.component', 'FieldSelect'));
    }

    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;
        return $this;
    }

    public function clearable(bool $clearable = true): static
    {
        $this->clearable = $clearable;
        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;
        parent::placeholder($placeholder);
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'options' => $this->formatOptions(),
            'multiple' => $this->multiple,
            'clearable' => $this->clearable,
            'placeholder' => $this->placeholder,
        ]);
    }

    protected function formatOptions(): array
    {
        // If options are already in the correct format, return them
        if ($this->isFormattedOptions($this->options)) {
            return $this->options;
        }

        // Convert associative array to proper format
        $formatted = [];
        foreach ($this->options as $value => $label) {
            $formatted[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $formatted;
    }

    protected function isFormattedOptions(array $options): bool
    {
        if (empty($options)) {
            return true;
        }

        $firstOption = reset($options);
        return is_array($firstOption) && isset($firstOption['value']) && isset($firstOption['label']);
    }

    public function resolve(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};
        
        // Handle multiple values
        if ($this->multiple && is_string($value)) {
            // Try to decode JSON if it's a string
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
        }
        
        return $value;
    }
}
