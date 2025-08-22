<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

trait ValidationTrait
{
    protected array $rules = [];
    protected bool $required = false;
    protected array $conditionalRules = [];

    public function rules($rules): static
    {
        // Handle both array and callable
        if (is_callable($rules)) {
            $rules = call_user_func($rules);
        }
        
        $this->rules = array_merge($this->rules, (array) $rules);
        return $this;
    }

    public function required(): static
    {
        $this->required = true;
        if (!in_array('required', $this->rules)) {
            $this->rules[] = 'required';
        }
        return $this;
    }

    public function nullable(): static
    {
        if (!in_array('nullable', $this->rules)) {
            $this->rules[] = 'nullable';
        }
        return $this;
    }

    public function sometimes(callable $callback): static
    {
        $this->conditionalRules[] = $callback;
        return $this;
    }

    public function requiredIf(string $field, mixed $value): static
    {
        $this->rules[] = "required_if:{$field},{$value}";
        return $this;
    }

    public function requiredUnless(string $field, mixed $value): static
    {
        $this->rules[] = "required_unless:{$field},{$value}";
        return $this;
    }

    public function getRules(): array
    {
        $rules = $this->rules;
        
        // Apply conditional rules
        foreach ($this->conditionalRules as $callback) {
            $conditionalRules = call_user_func($callback);
            if ($conditionalRules) {
                $rules = array_merge($rules, (array) $conditionalRules);
            }
        }

        return array_unique($rules);
    }

    public function isRequired(): bool
    {
        return $this->required;
    }
}
