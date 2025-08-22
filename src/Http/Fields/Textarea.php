<?php

namespace Said\Nadota\Http\Fields;

use Said\Nadota\Http\Fields\Enums\FieldType;

class Textarea extends Field
{
    protected ?int $rows = null;
    protected ?int $cols = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute);
        $this->type(FieldType::TEXTAREA);
        $this->component(config('nadota.fields.textarea.component', 'field-textarea'));
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

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\Said\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'rows' => $this->rows,
            'cols' => $this->cols,
        ]);
    }
}