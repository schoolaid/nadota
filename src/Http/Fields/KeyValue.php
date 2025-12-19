<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class KeyValue extends Field
{
    /**
     * The key-value pairs schema
     */
    protected array $schema = [];

    /**
     * Whether to allow adding new keys dynamically
     */
    protected bool $allowNewKeys = false;

    /**
     * Whether to show labels instead of keys
     */
    protected bool $showLabels = true;

    /**
     * Custom labels for keys
     */
    protected array $labels = [];

    /**
     * Keys that should be readonly
     */
    protected array $readonlyKeys = [];

    /**
     * Keys that should be hidden
     */
    protected array $hiddenKeys = [];

    /**
     * Validation rules for specific keys
     */
    protected array $keyRules = [];

    /**
     * Input types for specific keys
     */
    protected array $inputTypes = [];

    /**
     * Options for select/radio keys
     */
    protected array $keyOptions = [];

    /**
     * Default values for keys
     */
    protected array $defaults = [];

    /**
     * Keys to group together
     */
    protected array $groups = [];

    /**
     * Whether to show as a table
     */
    protected bool $asTable = false;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::KEY_VALUE->value, static::safeConfig('nadota.fields.keyvalue.component', 'FieldKeyValue'));

        // Key-value fields are typically better for detail/forms than index
        $this->hideFromIndex();
    }

    /**
     * Define the schema for the key-value pairs
     */
    public function schema(array $schema): static
    {
        $this->schema = $schema;

        // Extract labels, types, options, etc. from schema
        foreach ($schema as $key => $config) {
            if (is_string($config)) {
                // Simple label
                $this->labels[$key] = $config;
            } elseif (is_array($config)) {
                if (isset($config['label'])) {
                    $this->labels[$key] = $config['label'];
                }
                if (isset($config['type'])) {
                    $this->inputTypes[$key] = $config['type'];
                }
                if (isset($config['options'])) {
                    $this->keyOptions[$key] = $config['options'];
                }
                if (isset($config['rules'])) {
                    $this->keyRules[$key] = $config['rules'];
                }
                if (isset($config['default'])) {
                    $this->defaults[$key] = $config['default'];
                }
                if (isset($config['readonly']) && $config['readonly']) {
                    $this->readonlyKeys[] = $key;
                }
                if (isset($config['hidden']) && $config['hidden']) {
                    $this->hiddenKeys[] = $key;
                }
            }
        }

        return $this;
    }

    /**
     * Allow users to add new key-value pairs
     */
    public function allowNewKeys(bool $allow = true): static
    {
        $this->allowNewKeys = $allow;
        return $this;
    }

    /**
     * Set custom labels for keys
     */
    public function keyLabels(array $labels): static
    {
        $this->labels = array_merge($this->labels, $labels);
        return $this;
    }

    /**
     * Set a single key label
     */
    public function keyLabel(string $key, string $label): static
    {
        $this->labels[$key] = $label;
        return $this;
    }

    /**
     * Mark keys as readonly
     */
    public function readonlyKeys(string|array $keys): static
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $this->readonlyKeys = array_unique(array_merge($this->readonlyKeys, $keys));
        return $this;
    }

    /**
     * Hide specific keys
     */
    public function hideKeys(string|array $keys): static
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $this->hiddenKeys = array_unique(array_merge($this->hiddenKeys, $keys));
        return $this;
    }

    /**
     * Set validation rules for a key
     */
    public function keyRules(string $key, array|string $rules): static
    {
        $this->keyRules[$key] = is_array($rules) ? $rules : [$rules];
        return $this;
    }

    /**
     * Set input type for a key
     */
    public function inputType(string $key, string $type, ?array $options = null): static
    {
        $this->inputTypes[$key] = $type;
        if ($options !== null) {
            $this->keyOptions[$key] = $options;
        }
        return $this;
    }

    /**
     * Group keys together
     */
    public function group(string $groupName, array $keys): static
    {
        $this->groups[$groupName] = $keys;
        return $this;
    }

    /**
     * Display as a table
     */
    public function asTable(bool $asTable = true): static
    {
        $this->asTable = $asTable;
        return $this;
    }

    /**
     * Use keys as labels (no translation)
     */
    public function useKeys(): static
    {
        $this->showLabels = false;
        return $this;
    }

    /**
     * Set default values
     */
    public function defaults(array $defaults): static
    {
        $this->defaults = array_merge($this->defaults, $defaults);
        return $this;
    }

    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $value = parent::resolve($request, $model, $resource);

        // Decode JSON if it's a string
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = $decoded !== null ? $decoded : [];
        }

        // Ensure it's an array
        if (!is_array($value)) {
            $value = [];
        }

        // Apply defaults for missing keys
        foreach ($this->defaults as $key => $default) {
            if (!isset($value[$key])) {
                $value[$key] = $default;
            }
        }

        // Filter hidden keys for display
        foreach ($this->hiddenKeys as $hiddenKey) {
            unset($value[$hiddenKey]);
        }

        return $value;
    }

    public function fill(Request $request, Model $model): void
    {
        // Don't fill if field is readonly or disabled
        if ($this->isReadonly() || $this->isDisabled()) {
            return;
        }

        $requestAttribute = $this->getAttribute();
        $data = $request->get($requestAttribute, []);

        // Ensure data is an array
        if (!is_array($data)) {
            $data = [];
        }

        // Get existing data to preserve readonly and hidden keys
        $existingData = $model->{$this->getAttribute()};
        if (is_string($existingData)) {
            $existingData = json_decode($existingData, true) ?: [];
        }
        if (!is_array($existingData)) {
            $existingData = [];
        }

        // Merge data, preserving readonly keys
        foreach ($this->readonlyKeys as $readonlyKey) {
            if (isset($existingData[$readonlyKey])) {
                $data[$readonlyKey] = $existingData[$readonlyKey];
            }
        }

        // Preserve hidden keys
        foreach ($this->hiddenKeys as $hiddenKey) {
            if (isset($existingData[$hiddenKey])) {
                $data[$hiddenKey] = $existingData[$hiddenKey];
            }
        }

        // Remove keys if not allowed to add new ones
        if (!$this->allowNewKeys && !empty($this->schema)) {
            $allowedKeys = array_keys($this->schema);
            $data = array_intersect_key($data, array_flip($allowedKeys));
        }

        // Store in the model - encode to JSON if model doesn't have array cast
        $attribute = $this->getAttribute();
        $casts = $model->getCasts();

        if (isset($casts[$attribute]) && in_array($casts[$attribute], ['array', 'json', 'object', 'collection'])) {
            // Model has cast, assign array directly
            $model->{$attribute} = $data;
        } else {
            // No cast, encode to JSON string
            $model->{$attribute} = json_encode($data);
        }
    }

    public function getRules(): array
    {
        $rules = parent::getRules();

        // Add JSON validation
        $rules[] = 'array';

        // Add key-specific validation
        foreach ($this->keyRules as $key => $keyRuleSet) {
            $keyAttribute = $this->getAttribute() . '.' . $key;
            // This would need to be processed by the validator
            // The actual implementation would depend on how the validator is set up
        }

        return $rules;
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'schema' => $this->schema,
            'allowNewKeys' => $this->allowNewKeys,
            'showLabels' => $this->showLabels,
            'labels' => $this->labels,
            'readonlyKeys' => $this->readonlyKeys,
            'hiddenKeys' => $this->hiddenKeys,
            'inputTypes' => $this->inputTypes,
            'keyOptions' => $this->keyOptions,
            'defaults' => $this->defaults,
            'groups' => $this->groups,
            'asTable' => $this->asTable,
        ]);
    }
}