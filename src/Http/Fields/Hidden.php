<?php

namespace Said\Nadota\Http\Fields;

use Said\Nadota\Http\Fields\Enums\FieldType;

class Hidden extends Field
{
    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute);

        $this->type(FieldType::HIDDEN);
        $this->component(config('nadota.fields.hidden.component', 'field-hidden'));
        
        // Hidden fields are typically not shown on index or detail views
        $this->hideFromIndex()->hideFromDetail();
    }
}
