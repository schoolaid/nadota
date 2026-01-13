<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class VariableText extends Field
{
    /**
     * Required variables that must be present in the text.
     */
    protected array $requiredVariables = [];

    /**
     * Optional variables that can be used in the text.
     */
    protected array $optionalVariables = [];

    /**
     * Variable metadata (labels, descriptions, types, defaults).
     */
    protected array $variablesMeta = [];

    /**
     * The prefix used for variables (e.g., '$', ':', '{{').
     */
    protected string $variablePrefix = '$';

    /**
     * The suffix used for variables (e.g., '', '}}').
     */
    protected string $variableSuffix = '';

    /**
     * Number of rows for the textarea.
     */
    protected ?int $rows = null;

    /**
     * Maximum length of the text.
     */
    protected ?int $maxLength = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct(
            $name,
            $attribute,
            FieldType::VARIABLE_TEXT->value,
            static::safeConfig('nadota.fields.variableText.component', 'FieldVariableText')
        );
    }

    /**
     * Set the required variables.
     *
     * @param array $variables Array of variable names
     * @return static
     */
    public function requiredVariables(array $variables): static
    {
        $this->requiredVariables = $variables;
        return $this;
    }

    /**
     * Set the optional variables.
     *
     * @param array $variables Array of variable names
     * @return static
     */
    public function optionalVariables(array $variables): static
    {
        $this->optionalVariables = $variables;
        return $this;
    }

    /**
     * Set labels for variables.
     *
     * @param array $labels Associative array of variable => label
     * @return static
     */
    public function variableLabels(array $labels): static
    {
        foreach ($labels as $variable => $label) {
            $this->variablesMeta[$variable]['label'] = $label;
        }
        return $this;
    }

    /**
     * Set descriptions for variables.
     *
     * @param array $descriptions Associative array of variable => description
     * @return static
     */
    public function variableDescriptions(array $descriptions): static
    {
        foreach ($descriptions as $variable => $description) {
            $this->variablesMeta[$variable]['description'] = $description;
        }
        return $this;
    }

    /**
     * Set types for variables (for frontend hints).
     *
     * @param array $types Associative array of variable => type (text, date, time, number, etc.)
     * @return static
     */
    public function variableTypes(array $types): static
    {
        foreach ($types as $variable => $type) {
            $this->variablesMeta[$variable]['type'] = $type;
        }
        return $this;
    }

    /**
     * Set default values for variables.
     *
     * @param array $defaults Associative array of variable => defaultValue
     * @return static
     */
    public function variableDefaults(array $defaults): static
    {
        foreach ($defaults as $variable => $default) {
            $this->variablesMeta[$variable]['default'] = $default;
        }
        return $this;
    }

    /**
     * Configure a variable with all its metadata at once.
     *
     * @param string $name Variable name
     * @param array $config Configuration array with keys: label, description, type, default, required
     * @return static
     */
    public function variable(string $name, array $config): static
    {
        $isRequired = $config['required'] ?? false;

        if ($isRequired && !in_array($name, $this->requiredVariables)) {
            $this->requiredVariables[] = $name;
        } elseif (!$isRequired && !in_array($name, $this->optionalVariables)) {
            $this->optionalVariables[] = $name;
        }

        unset($config['required']);
        $this->variablesMeta[$name] = array_merge($this->variablesMeta[$name] ?? [], $config);

        return $this;
    }

    /**
     * Configure multiple variables at once.
     *
     * @param array $variables Associative array of variable => config
     * @return static
     */
    public function variables(array $variables): static
    {
        foreach ($variables as $name => $config) {
            $this->variable($name, $config);
        }
        return $this;
    }

    /**
     * Set the variable prefix.
     *
     * @param string $prefix
     * @return static
     */
    public function variablePrefix(string $prefix): static
    {
        $this->variablePrefix = $prefix;
        return $this;
    }

    /**
     * Set the variable suffix.
     *
     * @param string $suffix
     * @return static
     */
    public function variableSuffix(string $suffix): static
    {
        $this->variableSuffix = $suffix;
        return $this;
    }

    /**
     * Use mustache-style variables {{ variable }}.
     *
     * @return static
     */
    public function mustacheStyle(): static
    {
        $this->variablePrefix = '{{';
        $this->variableSuffix = '}}';
        return $this;
    }

    /**
     * Use colon-style variables :variable.
     *
     * @return static
     */
    public function colonStyle(): static
    {
        $this->variablePrefix = ':';
        $this->variableSuffix = '';
        return $this;
    }

    /**
     * Set the number of rows for the textarea.
     *
     * @param int $rows
     * @return static
     */
    public function rows(int $rows): static
    {
        $this->rows = $rows;
        return $this;
    }

    /**
     * Set the maximum length of the text.
     *
     * @param int $maxLength
     * @return static
     */
    public function maxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;
        return $this;
    }

    /**
     * Get the formatted variable string.
     *
     * @param string $name
     * @return string
     */
    public function formatVariable(string $name): string
    {
        return $this->variablePrefix . $name . $this->variableSuffix;
    }

    /**
     * Get all variables (required + optional).
     *
     * @return array
     */
    public function getAllVariables(): array
    {
        return array_unique(array_merge($this->requiredVariables, $this->optionalVariables));
    }

    /**
     * Build the variables configuration for the frontend.
     *
     * @return array
     */
    protected function buildVariablesConfig(): array
    {
        $allVariables = $this->getAllVariables();
        $config = [];

        foreach ($allVariables as $variable) {
            $meta = $this->variablesMeta[$variable] ?? [];
            $config[$variable] = [
                'name' => $variable,
                'formatted' => $this->formatVariable($variable),
                'required' => in_array($variable, $this->requiredVariables),
                'label' => $meta['label'] ?? ucfirst(str_replace('_', ' ', $variable)),
                'description' => $meta['description'] ?? null,
                'type' => $meta['type'] ?? 'text',
                'default' => $meta['default'] ?? null,
            ];
        }

        return $config;
    }

    /**
     * Check if the text contains all required variables.
     *
     * @param string $text
     * @return array Array of missing required variables
     */
    public function getMissingRequiredVariables(string $text): array
    {
        $missing = [];

        foreach ($this->requiredVariables as $variable) {
            $formatted = $this->formatVariable($variable);
            if (strpos($text, $formatted) === false) {
                $missing[] = $variable;
            }
        }

        return $missing;
    }

    /**
     * Validate that all required variables are present.
     *
     * @param string $text
     * @return bool
     */
    public function validateRequiredVariables(string $text): bool
    {
        return empty($this->getMissingRequiredVariables($text));
    }

    /**
     * Get the validation rules for this field.
     * Adds custom validation for required variables.
     *
     * @return array
     */
    public function getRules(): array
    {
        $rules = parent::getRules();

        // Add custom validation rule for required variables
        if (!empty($this->requiredVariables)) {
            $rules[] = function ($attribute, $value, $fail) {
                $missing = $this->getMissingRequiredVariables($value ?? '');
                if (!empty($missing)) {
                    $formatted = array_map(fn($v) => $this->formatVariable($v), $missing);
                    $fail("The {$attribute} must contain the following variables: " . implode(', ', $formatted));
                }
            };
        }

        return $rules;
    }

    /**
     * Get props for frontend component.
     *
     * @param Request $request
     * @param Model|null $model
     * @param ResourceInterface|null $resource
     * @return array
     */
    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'variables' => $this->buildVariablesConfig(),
            'requiredVariables' => $this->requiredVariables,
            'optionalVariables' => $this->optionalVariables,
            'variablePrefix' => $this->variablePrefix,
            'variableSuffix' => $this->variableSuffix,
            'rows' => $this->rows,
            'maxLength' => $this->maxLength,
        ]);
    }
}
