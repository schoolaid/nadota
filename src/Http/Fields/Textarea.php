<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Textarea extends Field
{
    protected ?int $rows = null;
    protected ?int $cols = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::TEXTAREA->value, static::safeConfig('nadota.fields.textarea.component', 'FieldTextarea'));
    }

    public function rows(int $rows): static
    {
        $this->rows = $rows;
        return $this;
    }

    public function cols(int $cols): static
    {
        $this->cols = $cols;
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'rows' => $this->rows,
            'cols' => $this->cols,
        ]);
    }
}