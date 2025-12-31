<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use SchoolAid\Nadota\Http\Fields\DataTransferObjects\DependencyDTO;
use SchoolAid\Nadota\Http\Fields\Enums\DependencyOperator;

trait DependsOnTrait
{
    /**
     * Dependency configuration DTO.
     */
    protected ?DependencyDTO $dependencyConfig = null;

    /**
     * Get or initialize the dependency configuration.
     */
    protected function getDependencyDTO(): DependencyDTO
    {
        if ($this->dependencyConfig === null) {
            $this->dependencyConfig = new DependencyDTO();
        }

        return $this->dependencyConfig;
    }

    /**
     * Define which fields this field depends on.
     *
     * @param string|array<string> $fields Field key(s) to observe
     * @return static
     */
    public function dependsOn(string|array $fields): static
    {
        $fields = is_array($fields) ? $fields : [$fields];
        $dto = $this->getDependencyDTO();
        $dto->fields = array_unique(array_merge($dto->fields, $fields));

        return $this;
    }

    // =========================================================================
    // VISIBILITY CONDITIONS
    // =========================================================================

    /**
     * Show this field when another field equals a specific value.
     *
     * @param string $field The field to observe
     * @param mixed $value The value to compare against
     * @return static
     */
    public function showWhenEquals(string $field, mixed $value): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addVisibilityCondition($field, DependencyOperator::EQUALS, $value);

        return $this;
    }

    /**
     * Show this field when another field does not equal a specific value.
     *
     * @param string $field The field to observe
     * @param mixed $value The value to compare against
     * @return static
     */
    public function showWhenNotEquals(string $field, mixed $value): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addVisibilityCondition($field, DependencyOperator::NOT_EQUALS, $value);

        return $this;
    }

    /**
     * Show this field when another field has any value (not empty).
     *
     * @param string $field The field to observe
     * @return static
     */
    public function showWhenHasValue(string $field): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addVisibilityCondition($field, DependencyOperator::HAS_VALUE);

        return $this;
    }

    /**
     * Show this field when another field is empty.
     *
     * @param string $field The field to observe
     * @return static
     */
    public function showWhenEmpty(string $field): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addVisibilityCondition($field, DependencyOperator::IS_EMPTY);

        return $this;
    }

    /**
     * Show this field when another field's value is in a list.
     *
     * @param string $field The field to observe
     * @param array $values The list of acceptable values
     * @return static
     */
    public function showWhenIn(string $field, array $values): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addVisibilityCondition($field, DependencyOperator::IN, $values);

        return $this;
    }

    /**
     * Show this field when another field's value is not in a list.
     *
     * @param string $field The field to observe
     * @param array $values The list of values to exclude
     * @return static
     */
    public function showWhenNotIn(string $field, array $values): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addVisibilityCondition($field, DependencyOperator::NOT_IN, $values);

        return $this;
    }

    /**
     * Show this field when another field is truthy.
     *
     * @param string $field The field to observe
     * @return static
     */
    public function showWhenTruthy(string $field): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addVisibilityCondition($field, DependencyOperator::IS_TRUTHY);

        return $this;
    }

    /**
     * Show this field when another field is falsy.
     *
     * @param string $field The field to observe
     * @return static
     */
    public function showWhenFalsy(string $field): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addVisibilityCondition($field, DependencyOperator::IS_FALSY);

        return $this;
    }

    /**
     * Show this field when another field is greater than a value.
     *
     * @param string $field The field to observe
     * @param mixed $value The value to compare against
     * @return static
     */
    public function showWhenGreaterThan(string $field, mixed $value): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addVisibilityCondition($field, DependencyOperator::GREATER_THAN, $value);

        return $this;
    }

    /**
     * Show this field when another field is less than a value.
     *
     * @param string $field The field to observe
     * @param mixed $value The value to compare against
     * @return static
     */
    public function showWhenLessThan(string $field, mixed $value): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addVisibilityCondition($field, DependencyOperator::LESS_THAN, $value);

        return $this;
    }

    /**
     * Show this field when another field contains a value.
     *
     * @param string $field The field to observe
     * @param mixed $value The value to search for
     * @return static
     */
    public function showWhenContains(string $field, mixed $value): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addVisibilityCondition($field, DependencyOperator::CONTAINS, $value);

        return $this;
    }

    // =========================================================================
    // DISABLED STATE CONDITIONS
    // =========================================================================

    /**
     * Disable this field when another field equals a specific value.
     *
     * @param string $field The field to observe
     * @param mixed $value The value to compare against
     * @return static
     */
    public function disableWhenEquals(string $field, mixed $value): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addDisabledCondition($field, DependencyOperator::EQUALS, $value);

        return $this;
    }

    /**
     * Disable this field when another field is empty.
     *
     * @param string $field The field to observe
     * @return static
     */
    public function disableWhenEmpty(string $field): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addDisabledCondition($field, DependencyOperator::IS_EMPTY);

        return $this;
    }

    /**
     * Disable this field when another field has a value.
     *
     * @param string $field The field to observe
     * @return static
     */
    public function disableWhenHasValue(string $field): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addDisabledCondition($field, DependencyOperator::HAS_VALUE);

        return $this;
    }

    /**
     * Disable this field when another field is truthy.
     *
     * @param string $field The field to observe
     * @return static
     */
    public function disableWhenTruthy(string $field): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addDisabledCondition($field, DependencyOperator::IS_TRUTHY);

        return $this;
    }

    /**
     * Disable this field when another field is falsy.
     *
     * @param string $field The field to observe
     * @return static
     */
    public function disableWhenFalsy(string $field): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addDisabledCondition($field, DependencyOperator::IS_FALSY);

        return $this;
    }

    // =========================================================================
    // REQUIRED STATE CONDITIONS
    // =========================================================================

    /**
     * Make this field required when another field equals a specific value.
     *
     * @param string $field The field to observe
     * @param mixed $value The value to compare against
     * @return static
     */
    public function requiredWhenEquals(string $field, mixed $value): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addRequiredCondition($field, DependencyOperator::EQUALS, $value);

        return $this;
    }

    /**
     * Make this field required when another field has a value.
     *
     * @param string $field The field to observe
     * @return static
     */
    public function requiredWhenHasValue(string $field): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addRequiredCondition($field, DependencyOperator::HAS_VALUE);

        return $this;
    }

    /**
     * Make this field required when another field is truthy.
     *
     * @param string $field The field to observe
     * @return static
     */
    public function requiredWhenTruthy(string $field): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addRequiredCondition($field, DependencyOperator::IS_TRUTHY);

        return $this;
    }

    /**
     * Make this field required when another field's value is in a list.
     *
     * @param string $field The field to observe
     * @param array $values The list of values that trigger required
     * @return static
     */
    public function requiredWhenIn(string $field, array $values): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->addRequiredCondition($field, DependencyOperator::IN, $values);

        return $this;
    }

    // =========================================================================
    // DYNAMIC OPTIONS
    // =========================================================================

    /**
     * Load options from an endpoint when dependency changes.
     *
     * @param string $endpoint The API endpoint to fetch options from
     * @param string|null $paramField The dependency field to use as parameter
     * @param string|null $paramName The query parameter name (defaults to paramField)
     * @return static
     */
    public function optionsFromEndpoint(string $endpoint, ?string $paramField = null, ?string $paramName = null): static
    {
        if ($paramField) {
            $this->dependsOn($paramField);
        }

        $this->getDependencyDTO()->setOptionsConfig($endpoint, $paramField, $paramName);

        return $this;
    }

    /**
     * Cascade options from another field (e.g., cities from country).
     * Uses the field's optionsUrl with the parent field value as parameter.
     *
     * @param string $field The parent field to cascade from
     * @return static
     */
    public function cascadeFrom(string $field): static
    {
        $this->dependsOn($field);
        $this->getDependencyDTO()->setCascadeFrom($field);

        return $this;
    }

    // =========================================================================
    // COMPUTED VALUES
    // =========================================================================

    /**
     * Set a formula to compute this field's value in the frontend.
     * The formula can reference other field values using their keys.
     *
     * Examples:
     * - 'quantity * price' (multiplication)
     * - 'subtotal + tax' (addition)
     * - 'total / items' (division)
     *
     * @param string $formula The computation formula
     * @param array $fields The fields used in the formula (auto-detected if not provided)
     * @return static
     */
    public function computeUsing(string $formula, array $fields = []): static
    {
        // Auto-detect field references if not provided
        if (empty($fields)) {
            // Match word characters that could be field names (excluding numbers-only)
            preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', $formula, $matches);
            $fields = array_unique($matches[1] ?? []);

            // Filter out common math functions/keywords
            $reserved = ['Math', 'abs', 'ceil', 'floor', 'round', 'min', 'max', 'pow', 'sqrt'];
            $fields = array_diff($fields, $reserved);
        }

        if (!empty($fields)) {
            $this->dependsOn($fields);
        }

        $this->getDependencyDTO()->setCompute($formula);

        return $this;
    }

    // =========================================================================
    // BEHAVIOR OPTIONS
    // =========================================================================

    /**
     * Clear this field's value when any dependency changes.
     *
     * @param bool $clear Whether to clear value on change
     * @return static
     */
    public function clearOnDependencyChange(bool $clear = true): static
    {
        $this->getDependencyDTO()->clearOnChange = $clear;

        return $this;
    }

    /**
     * Set debounce time for dependency updates.
     *
     * @param int $milliseconds Debounce time in milliseconds
     * @return static
     */
    public function debounce(int $milliseconds): static
    {
        $this->getDependencyDTO()->debounce = $milliseconds;

        return $this;
    }

    // =========================================================================
    // GETTERS
    // =========================================================================

    /**
     * Check if this field has any dependencies.
     *
     * @return bool
     */
    public function hasDependencies(): bool
    {
        return $this->dependencyConfig !== null && $this->dependencyConfig->hasDependencies();
    }

    /**
     * Get the dependency configuration for JSON serialization.
     *
     * @return array
     */
    public function getDependencyConfig(): array
    {
        if ($this->dependencyConfig === null) {
            return [];
        }

        return $this->dependencyConfig->toArray();
    }

    /**
     * Get the fields this field depends on.
     *
     * @return array<string>
     */
    public function getDependsOnFields(): array
    {
        if ($this->dependencyConfig === null) {
            return [];
        }

        return $this->dependencyConfig->fields;
    }
}
