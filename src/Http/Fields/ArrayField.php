<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class ArrayField extends Field
{
    /**
     * Type of values allowed in the array (string, number, boolean, etc.)
     */
    protected ?string $valueType = null;

    /**
     * Whether to allow duplicate values
     */
    protected bool $allowDuplicates = true;

    /**
     * Minimum number of items
     */
    protected ?int $minItems = null;

    /**
     * Maximum number of items
     */
    protected ?int $maxItems = null;

    /**
     * Whether items can be reordered
     */
    protected bool $sortable = true;

    /**
     * Placeholder for new items
     */
    protected string $itemPlaceholder = 'Enter value...';

    /**
     * Default values
     */
    protected array $defaultValues = [];

    /**
     * Validation rules for each item
     */
    protected array $itemRules = [];

    /**
     * Options for select-based array items
     */
    protected ?array $options = null;

    /**
     * Whether to display as inline chips/tags
     */
    protected bool $displayAsChips = false;

    /**
     * Add button text
     */
    protected string $addButtonText = 'Add Item';

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::ARRAY->value, config('nadota.fields.array.component', 'FieldArray'));

        // Arrays are typically better for detail/forms than index
        $this->hideFromIndex();
    }

    /**
     * Set the type of values allowed in the array
     *
     * @param string $type 'string', 'number', 'integer', 'boolean', 'email', 'url'
     * @return static
     */
    public function valueType(string $type): static
    {
        $this->valueType = $type;
        return $this;
    }

    /**
     * Allow or disallow duplicate values
     *
     * @param bool $allow
     * @return static
     */
    public function allowDuplicates(bool $allow = true): static
    {
        $this->allowDuplicates = $allow;
        return $this;
    }

    /**
     * Disallow duplicate values
     *
     * @return static
     */
    public function unique(): static
    {
        return $this->allowDuplicates(false);
    }

    /**
     * Set minimum number of items
     *
     * @param int $min
     * @return static
     */
    public function min(int $min): static
    {
        $this->minItems = $min;
        return $this;
    }

    /**
     * Set maximum number of items
     *
     * @param int $max
     * @return static
     */
    public function max(int $max): static
    {
        $this->maxItems = $max;
        return $this;
    }

    /**
     * Set both min and max to the same value (fixed length)
     *
     * @param int $length
     * @return static
     */
    public function length(int $length): static
    {
        $this->minItems = $length;
        $this->maxItems = $length;
        return $this;
    }

    /**
     * Allow or disallow sorting/reordering
     *
     * @param bool $sortable
     * @return static
     */
    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;
        return $this;
    }

    /**
     * Set placeholder text for new items
     *
     * @param string $placeholder
     * @return static
     */
    public function itemPlaceholder(string $placeholder): static
    {
        $this->itemPlaceholder = $placeholder;
        return $this;
    }

    /**
     * Set default values for the array
     *
     * @param array $values
     * @return static
     */
    public function defaultValues(array $values): static
    {
        $this->defaultValues = $values;
        return $this;
    }

    /**
     * Set validation rules for each item in the array
     *
     * @param array|string $rules
     * @return static
     */
    public function itemRules(array|string $rules): static
    {
        $this->itemRules = is_array($rules) ? $rules : [$rules];
        return $this;
    }

    /**
     * Provide options for select-based array items
     *
     * @param array $options
     * @return static
     */
    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Display as inline chips/tags
     *
     * @param bool $displayAsChips
     * @return static
     */
    public function displayAsChips(bool $displayAsChips = true): static
    {
        $this->displayAsChips = $displayAsChips;
        return $this;
    }

    /**
     * Set the add button text
     *
     * @param string $text
     * @return static
     */
    public function addButtonText(string $text): static
    {
        $this->addButtonText = $text;
        return $this;
    }

    /**
     * Shortcut for string array
     *
     * @return static
     */
    public function strings(): static
    {
        return $this->valueType('string');
    }

    /**
     * Shortcut for number array
     *
     * @return static
     */
    public function numbers(): static
    {
        return $this->valueType('number');
    }

    /**
     * Shortcut for integer array
     *
     * @return static
     */
    public function integers(): static
    {
        return $this->valueType('integer');
    }

    /**
     * Shortcut for email array
     *
     * @return static
     */
    public function emails(): static
    {
        return $this->valueType('email')->itemRules('email');
    }

    /**
     * Shortcut for URL array
     *
     * @return static
     */
    public function urls(): static
    {
        return $this->valueType('url')->itemRules('url');
    }

    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $value = parent::resolve($request, $model, $resource);

        // Handle JSON string from database
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = $decoded !== null ? $decoded : [];
        }

        // Ensure it's an array
        if (!is_array($value)) {
            $value = $value ? [$value] : [];
        }

        // Apply default values if empty
        if (empty($value) && !empty($this->defaultValues)) {
            $value = $this->defaultValues;
        }

        // Cast values to the appropriate type
        if ($this->valueType !== null) {
            $value = array_map(function ($item) {
                return $this->castValue($item);
            }, $value);
        }

        // Remove duplicates if not allowed
        if (!$this->allowDuplicates) {
            $value = array_values(array_unique($value));
        }

        return array_values($value); // Re-index array
    }

    public function fill(Request $request, Model $model): void
    {
        // Don't fill if field is readonly, disabled, or computed
        if ($this->isReadonly() || $this->isDisabled() || $this->isComputed()) {
            return;
        }

        $requestAttribute = $this->getAttribute();
        $data = $request->get($requestAttribute, []);

        // Ensure data is an array
        if (!is_array($data)) {
            $data = $data ? [$data] : [];
        }

        // Cast values to the appropriate type
        if ($this->valueType !== null) {
            $data = array_map(function ($item) {
                return $this->castValue($item);
            }, $data);
        }

        // Remove duplicates if not allowed
        if (!$this->allowDuplicates) {
            $data = array_values(array_unique($data));
        }

        // Remove null/empty values
        $data = array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });

        // Re-index array
        $data = array_values($data);

        // Store in the model
        $model->{$this->getAttribute()} = $data;
    }

    /**
     * Cast a value to the specified type
     *
     * @param mixed $value
     * @return mixed
     */
    protected function castValue(mixed $value): mixed
    {
        return match ($this->valueType) {
            'integer' => (int) $value,
            'number', 'float', 'double' => (float) $value,
            'boolean', 'bool' => (bool) $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    public function getRules(): array
    {
        $rules = parent::getRules();

        // Add array validation
        $rules[] = 'array';

        // Min/max items
        if ($this->minItems !== null) {
            $rules[] = "min:{$this->minItems}";
        }

        if ($this->maxItems !== null) {
            $rules[] = "max:{$this->maxItems}";
        }

        // Distinct values if duplicates not allowed
        if (!$this->allowDuplicates) {
            $rules[] = 'distinct';
        }

        return array_unique($rules);
    }

    /**
     * Get validation rules for array items
     *
     * @return array
     */
    public function getItemRules(): array
    {
        $rules = $this->itemRules;

        // Add type validation
        if ($this->valueType !== null) {
            $typeRule = match ($this->valueType) {
                'integer' => 'integer',
                'number', 'float', 'double' => 'numeric',
                'boolean', 'bool' => 'boolean',
                'string' => 'string',
                'email' => 'email',
                'url' => 'url',
                default => null,
            };

            if ($typeRule !== null && !in_array($typeRule, $rules)) {
                array_unshift($rules, $typeRule);
            }
        }

        return $rules;
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'valueType' => $this->valueType,
            'allowDuplicates' => $this->allowDuplicates,
            'minItems' => $this->minItems,
            'maxItems' => $this->maxItems,
            'sortable' => $this->sortable,
            'itemPlaceholder' => $this->itemPlaceholder,
            'defaultValues' => $this->defaultValues,
            'itemRules' => $this->getItemRules(),
            'options' => $this->options,
            'displayAsChips' => $this->displayAsChips,
            'addButtonText' => $this->addButtonText,
        ]);
    }
}