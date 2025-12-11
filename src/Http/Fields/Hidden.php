<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Hidden extends Field
{
    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::HIDDEN->value, static::safeConfig('nadota.fields.hidden.component', 'FieldHidden'));
        
        // Hidden fields are typically not shown on index or detail views
        $this->hideFromIndex()->hideFromDetail();
    }
}
