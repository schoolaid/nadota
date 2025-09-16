<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Radio extends Field
{
    protected array $options = [];
    protected bool $inline = false;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::RADIO->value, config('nadota.fields.radio.component', 'FieldRadio'));
    }

    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function inline(bool $inline = true): static
    {
        $this->inline = $inline;
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'options' => $this->formatOptions(),
            'inline' => $this->inline,
        ]);
    }

    protected function formatOptions(): array
    {
        return collect($this->options)
            ->map(fn($label, $value) => [
                'value' => is_numeric($value) ? $value : $value,
                'label' => $label
            ])
            ->values()
            ->toArray();
    }
}
