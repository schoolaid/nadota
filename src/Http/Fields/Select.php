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
    protected bool $translateLabels = true;

    /**
     * The key to use as the option value.
     */
    protected string $valueKey = 'value';

    /**
     * The key to use as the option label.
     */
    protected string $labelKey = 'label';

    /**
     * Whether to include the label in the resolve response.
     */
    protected bool $resolveWithLabel = false;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::SELECT->value, static::safeConfig('nadota.fields.select.component', 'FieldSelect'));
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

    /**
     * Set whether the frontend should translate option labels.
     * Default is true.
     */
    public function translateLabels(bool $translate = true): static
    {
        $this->translateLabels = $translate;
        return $this;
    }

    /**
     * Disable label translation on the frontend.
     */
    public function withoutTranslation(): static
    {
        return $this->translateLabels(false);
    }

    /**
     * Set the key to use as the option value.
     * Useful when options have a different structure (e.g., 'uuid' instead of 'value').
     */
    public function valueKey(string $key): static
    {
        $this->valueKey = $key;
        return $this;
    }

    /**
     * Set the key to use as the option label.
     * Useful when options have a different structure (e.g., 'name' instead of 'label').
     */
    public function labelKey(string $key): static
    {
        $this->labelKey = $key;
        return $this;
    }

    /**
     * Configure option keys for uuid/value format.
     * Shorthand for ->valueKey('uuid')->labelKey('value')
     */
    public function uuidValueFormat(): static
    {
        return $this->optionKeys('uuid', 'value');
    }

    /**
     * Configure both value and label keys at once.
     *
     * @param string $valueKey The key to use as the option value (e.g., 'uuid', 'id')
     * @param string $labelKey The key to use as the option label (e.g., 'value', 'name', 'label')
     * @return static
     *
     * @example
     * // Options: [{"uuid":"ec1", "value":"Soltero(a)"}]
     * Select::make('Status', 'status')
     *     ->options($options)
     *     ->optionKeys('uuid', 'value')
     *     ->withLabel();
     */
    public function optionKeys(string $valueKey, string $labelKey): static
    {
        $this->valueKey = $valueKey;
        $this->labelKey = $labelKey;
        return $this;
    }

    /**
     * Make resolve() return an object with value and label instead of just the value.
     * Useful for showing the label in index views.
     */
    public function withLabel(bool $withLabel = true): static
    {
        $this->resolveWithLabel = $withLabel;
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'options' => $this->formatOptions(),
            'multiple' => $this->multiple,
            'clearable' => $this->clearable,
            'placeholder' => $this->placeholder,
            'translateLabels' => $this->translateLabels,
        ]);
    }

    protected function formatOptions(): array
    {
        // If options are already in the correct format, return them
        if ($this->isFormattedOptions($this->options)) {
            return $this->normalizeOptionsKeys($this->options);
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

    /**
     * Normalize options to use standard 'value' and 'label' keys.
     * This handles custom valueKey and labelKey configurations.
     */
    protected function normalizeOptionsKeys(array $options): array
    {
        // If using default keys, no normalization needed
        if ($this->valueKey === 'value' && $this->labelKey === 'label') {
            return $options;
        }

        return array_map(function ($option) {
            return [
                'value' => $option[$this->valueKey] ?? $option['value'] ?? null,
                'label' => $option[$this->labelKey] ?? $option['label'] ?? null,
            ];
        }, $options);
    }

    protected function isFormattedOptions(array $options): bool
    {
        if (empty($options)) {
            return true;
        }

        $firstOption = reset($options);

        // Check if it's an array with either standard keys or custom keys
        if (!is_array($firstOption)) {
            return false;
        }

        $hasStandardKeys = isset($firstOption['value']) && isset($firstOption['label']);
        $hasCustomKeys = isset($firstOption[$this->valueKey]) && isset($firstOption[$this->labelKey]);

        return $hasStandardKeys || $hasCustomKeys;
    }

    public function resolve(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};

        // Handle multiple values
        if ($this->multiple && is_string($value)) {
            // Try to decode JSON if it's a string
            $decoded = json_decode($value, true);
            $value = $decoded !== null ? $decoded : $value;
        }

        // If resolveWithLabel is enabled, return object with value and label
        if ($this->resolveWithLabel && $value !== null) {
            return $this->resolveValueWithLabel($value);
        }

        return $value;
    }

    /**
     * Resolve value with its corresponding label from options.
     */
    protected function resolveValueWithLabel(mixed $value): array|null
    {
        $options = $this->formatOptions();

        // Handle multiple values
        if ($this->multiple && is_array($value)) {
            return array_map(function ($v) use ($options) {
                return [
                    'value' => $v,
                    'label' => $this->findLabelForValue($v, $options),
                ];
            }, $value);
        }

        return [
            'value' => $value,
            'label' => $this->findLabelForValue($value, $options),
        ];
    }

    /**
     * Resolve the field value for export, converting value to label.
     */
    public function resolveForExport(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): mixed
    {
        $value = $this->resolve($request, $model, $resource);

        if ($value === null) {
            return null;
        }

        $options = $this->formatOptions();

        // Handle multiple values
        if ($this->multiple && is_array($value)) {
            return collect($value)
                ->map(fn($v) => $this->findLabelForValue($v, $options))
                ->filter()
                ->implode(', ');
        }

        return $this->findLabelForValue($value, $options);
    }

    /**
     * Find the label for a given value in the options array.
     */
    protected function findLabelForValue(mixed $value, array $options): ?string
    {
        // If value is an array, we can't compare directly
        if (is_array($value)) {
            return null;
        }

        foreach ($options as $option) {
            $optionValue = $option['value'] ?? null;

            // Skip if option value is an array
            if (is_array($optionValue)) {
                continue;
            }

            // Handle both string and numeric comparison
            if ((string) $optionValue === (string) $value) {
                return $option['label'] ?? null;
            }
        }

        // If no label found, return the original value as string
        return is_scalar($value) ? (string) $value : null;
    }
}
