<?php

namespace SchoolAid\Nadota\Http\Fields\DataTransferObjects;

use SchoolAid\Nadota\Http\Fields\Enums\DependencyOperator;

class DependencyDTO
{
    /**
     * Fields that this field depends on.
     *
     * @var array<string>
     */
    public array $fields = [];

    /**
     * Visibility conditions.
     *
     * @var array<array{field: string, operator: string, value?: mixed}>
     */
    public array $visibility = [];

    /**
     * Disabled state conditions.
     *
     * @var array<array{field: string, operator: string, value?: mixed}>
     */
    public array $disabled = [];

    /**
     * Required state conditions.
     *
     * @var array<array{field: string, operator: string, value?: mixed}>
     */
    public array $required = [];

    /**
     * Dynamic options configuration.
     *
     * @var array{endpoint?: string, paramField?: string, paramName?: string, cascadeFrom?: string}|null
     */
    public ?array $options = null;

    /**
     * Computed value formula (evaluated in frontend).
     */
    public ?string $compute = null;

    /**
     * Clear value when dependency changes.
     */
    public bool $clearOnChange = false;

    /**
     * Debounce time in milliseconds for dependency updates.
     */
    public int $debounce = 0;

    public function __construct(array $fields = [])
    {
        $this->fields = $fields;
    }

    /**
     * Add a visibility condition.
     */
    public function addVisibilityCondition(string $field, DependencyOperator $operator, mixed $value = null): static
    {
        $condition = [
            'field' => $field,
            'operator' => $operator->value,
        ];

        if ($operator->requiresValue()) {
            $condition['value'] = $value;
        }

        $this->visibility[] = $condition;

        return $this;
    }

    /**
     * Add a disabled condition.
     */
    public function addDisabledCondition(string $field, DependencyOperator $operator, mixed $value = null): static
    {
        $condition = [
            'field' => $field,
            'operator' => $operator->value,
        ];

        if ($operator->requiresValue()) {
            $condition['value'] = $value;
        }

        $this->disabled[] = $condition;

        return $this;
    }

    /**
     * Add a required condition.
     */
    public function addRequiredCondition(string $field, DependencyOperator $operator, mixed $value = null): static
    {
        $condition = [
            'field' => $field,
            'operator' => $operator->value,
        ];

        if ($operator->requiresValue()) {
            $condition['value'] = $value;
        }

        $this->required[] = $condition;

        return $this;
    }

    /**
     * Set dynamic options configuration.
     */
    public function setOptionsConfig(string $endpoint, ?string $paramField = null, ?string $paramName = null): static
    {
        $this->options = [
            'endpoint' => $endpoint,
            'paramField' => $paramField,
            'paramName' => $paramName ?? $paramField,
        ];

        return $this;
    }

    /**
     * Set cascade options from another field.
     */
    public function setCascadeFrom(string $field): static
    {
        $this->options = $this->options ?? [];
        $this->options['cascadeFrom'] = $field;

        return $this;
    }

    /**
     * Set computed formula.
     */
    public function setCompute(string $formula): static
    {
        $this->compute = $formula;

        return $this;
    }

    /**
     * Check if this DTO has any dependencies configured.
     */
    public function hasDependencies(): bool
    {
        return !empty($this->fields)
            || !empty($this->visibility)
            || !empty($this->disabled)
            || !empty($this->required)
            || $this->options !== null
            || $this->compute !== null;
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        if (!$this->hasDependencies()) {
            return [];
        }

        $data = [];

        if (!empty($this->fields)) {
            $data['fields'] = $this->fields;
        }

        if (!empty($this->visibility)) {
            $data['visibility'] = $this->visibility;
        }

        if (!empty($this->disabled)) {
            $data['disabled'] = $this->disabled;
        }

        if (!empty($this->required)) {
            $data['required'] = $this->required;
        }

        if ($this->options !== null) {
            $data['options'] = $this->options;
        }

        if ($this->compute !== null) {
            $data['compute'] = $this->compute;
        }

        if ($this->clearOnChange) {
            $data['clearOnChange'] = true;
        }

        if ($this->debounce > 0) {
            $data['debounce'] = $this->debounce;
        }

        return $data;
    }
}
