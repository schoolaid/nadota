<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Date extends Field
{
    protected string $format = 'Y-m-d';
    protected ?\DateTimeInterface $minDate = null;
    protected ?\DateTimeInterface $maxDate = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::DATE->value, static::safeConfig('nadota.fields.date.component', 'FieldDate'));
    }

    public function format(string $format): static
    {
        $this->format = $format;
        return $this;
    }

    public function min(\DateTimeInterface $date): static
    {
        $this->minDate = $date;
        return $this;
    }

    public function max(\DateTimeInterface $date): static
    {
        $this->maxDate = $date;
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'format' => $this->format,
            'min' => $this->minDate?->format('Y-m-d'),
            'max' => $this->maxDate?->format('Y-m-d'),
        ]);
    }

    public function resolve(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};
        if ($value instanceof \DateTimeInterface) {
            return $value->format($this->format);
        }
        return $value;
    }
}
