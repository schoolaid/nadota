<?php

namespace Said\Nadota\Http\Fields;

use Said\Nadota\Http\Fields\Enums\FieldType;

class Email extends Field
{
    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute);
        $this->type(FieldType::EMAIL);
        $this->component(config('nadota.fields.email.component', 'field-email'));
        
        // Add email validation by default
        $this->rules(['email']);
    }
}