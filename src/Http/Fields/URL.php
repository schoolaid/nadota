<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class URL extends Field
{
    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::URL->value, static::safeConfig('nadota.fields.url.component', 'FieldUrl'));
        
        // Add URL validation by default
        $this->rules(['url']);
    }
}