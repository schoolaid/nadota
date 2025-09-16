<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Email extends Field
{
    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::EMAIL->value, config('nadota.fields.email.component', 'FieldEmail'));
        
        // Add email validation by default
        $this->rules(['email']);
    }
}