<?php

namespace Said\Nadota\Http\Fields\DataTransferObjects;

use Said\Nadota\Http\Fields\Enums\FieldType;

class FieldDTO
{
    public string $name;
    public string $label;
    public string $id;
    public string $attribute;
    public string $placeholder;
    public FieldType $type;
    public string $component;

    public function __construct(
        string $name,
        string $label,
        string $id,
        string $attribute,
        string $placeholder,
        FieldType $type,
        string $component
    ) {
        $this->name = $name;
        $this->label = $label;
        $this->id = $id;
        $this->attribute = $attribute;
        $this->placeholder = $placeholder;
        $this->type = $type;
        $this->component = $component;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'id' => $this->id,
            'attribute' => $this->attribute,
            'placeholder' => $this->placeholder,
            'type' => $this->type->value,
            'component' => $this->component,
        ];
    }
}
