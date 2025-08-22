<?php

namespace Said\Nadota\Http\Fields;

use Said\Nadota\Http\Fields\Enums\FieldType;

class URL extends Field
{
    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute);
        $this->type(FieldType::URL);
        $this->component(config('nadota.fields.url.component', 'field-url'));
        
        // Add URL validation by default
        $this->rules(['url']);
    }
}