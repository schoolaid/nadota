<?php

namespace SchoolAid\Nadota\Http\Fields;

class CheckboxList extends Field
{
    protected string $component = 'field-checkbox-list';
    protected array $options = [];
    protected ?int $minSelections = null;
    protected ?int $maxSelections = null;
    protected bool $inline = false;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute);
        $this->type = 'checkbox_list';
        $this->component = config('nadota.fields.checkboxList.component', 'FieldCheckboxList');
    }

    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function min(int $min): static
    {
        $this->minSelections = $min;
        return $this;
    }

    public function max(int $max): static
    {
        $this->maxSelections = $max;
        return $this;
    }

    public function limit(int $max): static
    {
        return $this->max($max);
    }

    public function inline(bool $inline = true): static
    {
        $this->inline = $inline;
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'options' => $this->formatOptions(),
            'minSelections' => $this->minSelections,
            'maxSelections' => $this->maxSelections,
            'inline' => $this->inline,
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
        
        if ($value === null) {
            return [];
        }
        
        return is_array($value) ? $value : json_decode($value, true) ?? [];
    }

    public function getRules(): array
    {
        $rules = parent::getRules();
        
        if ($this->minSelections !== null) {
            $rules[] = "array";
            $rules[] = "min:{$this->minSelections}";
        }
        
        if ($this->maxSelections !== null) {
            $rules[] = "array";
            $rules[] = "max:{$this->maxSelections}";
        }
        
        return array_unique($rules);
    }
}
