<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Color extends Field
{
    public function __construct(string $label, string $attribute)
    {
        parent::__construct($label, $attribute, FieldType::COLOR->value, static::safeConfig('nadota.fields.color.component', 'FieldColor'));
    }
}
