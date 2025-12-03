<?php

namespace SchoolAid\Nadota\Http\Fields\DataTransferObjects;

class FieldDTO
{
    public string $label;
    public string $attribute;
    public string $key;
    public string $placeholder;
    public string $type;
    public string $component;

    public function __construct(
        string $label,
        string $attribute,
        string $key,
        string $placeholder,
        string $type,
        string $component
    ) {
        $this->label = $label;
        $this->attribute = $attribute;
        $this->key = $key;
        $this->placeholder = $placeholder;
        $this->type = $type;
        $this->component = $component;
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'attribute' => $this->attribute,
            'key' => $this->key,
            'placeholder' => $this->placeholder,
            'type' => $this->type,
            'component' => $this->component,
        ];
    }
}
