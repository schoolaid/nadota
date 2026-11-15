<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Email extends Field
{
    use Traits\AppendsFormatRuleTrait;

    /** Added last by getRules(), see AppendsFormatRuleTrait. */
    protected string $formatRule = 'email';

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::EMAIL->value, static::safeConfig('nadota.fields.email.component', 'FieldEmail'));
    }
}
