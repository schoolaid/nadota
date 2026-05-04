<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

trait ValidationTrait
{
    protected array $rules = [];
    protected bool $required = false;
    protected array $conditionalRules = [];
    protected array $creationRules = [];
    protected array $updateRules = [];

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

    /**
     * Set rules that only apply when creating a resource.
     *
     * @param array $rules
     * @return static
     */
    public function creationRules(array $rules): static
    {
        $this->creationRules = $rules;
        return $this;
    }

    /**
     * Set rules that only apply when updating a resource.
     *
     * @param array $rules
     * @return static
     */
    public function updateRules(array $rules): static
    {
        $this->updateRules = $rules;
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

    /**
     * Get base rules (without context). Used by frontend/toArray.
     */
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

        return $this->uniqueRules($rules);
    }

    /**
     * Get rules for a specific operation context (store or update).
     * Merges base rules with context-specific rules.
     * Context rules override conflicting base rules (e.g., nullable overrides required).
     *
     * @param bool $isUpdate
     * @return array
     */
    public function getRulesFor(bool $isUpdate): array
    {
        $rules = $this->getRules();

        $contextRules = $isUpdate ? $this->updateRules : $this->creationRules;

        if (!empty($contextRules)) {
            // Context rules override conflicting base rules
            if (in_array('nullable', $contextRules)) {
                $rules = array_values(array_diff($rules, ['required']));
            }
            if (in_array('required', $contextRules)) {
                $rules = array_values(array_diff($rules, ['nullable']));
            }

            $rules = array_merge($rules, $contextRules);
        }

        return $this->uniqueRules($rules);
    }

    /**
     * Deduplicate rules without triggering Closure-to-string conversion.
     * String/object rules are deduplicated; closures are always kept as-is.
     */
    private function uniqueRules(array $rules): array
    {
        $strings = [];
        $objects = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $strings[$rule] = $rule;
            } else {
                $objects[] = $rule;
            }
        }

        return [...array_values($strings), ...$objects];
    }

    /**
     * Check if field is required, considering operation context.
     *
     * @param bool|null $isUpdate null = no context (base), true = update, false = creation
     * @return bool
     */
    public function isRequired(?bool $isUpdate = null): bool
    {
        if ($isUpdate === null) {
            return $this->required;
        }

        $rules = $this->getRulesFor($isUpdate);

        return in_array('required', $rules) && !in_array('nullable', $rules);
    }
}
