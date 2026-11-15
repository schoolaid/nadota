<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class URL extends Field
{
    use Traits\AppendsFormatRuleTrait;

    /** Added last by getRules(), see AppendsFormatRuleTrait. */
    protected string $formatRule = 'url';

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::URL->value, static::safeConfig('nadota.fields.url.component', 'FieldUrl'));
    }
}
