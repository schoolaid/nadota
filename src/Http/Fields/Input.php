<?php

namespace SchoolAid\Nadota\Http\Fields;

class Input extends Field
{
    public string $type = 'text';
    public string $component = 'field-text';
    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute);
        // Component will be set from config when needed, not during construction
        $this->fieldData->component = $this->component;
    }
    
    public function getComponent(): string
    {
        // Try to get from config if available, fallback to default
        if (function_exists('config') && config('nadota.fields.text.component')) {
            return config('nadota.fields.text.component');
        }
        return $this->component;
    }
}
