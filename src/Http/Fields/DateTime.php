<?php

namespace Said\Nadota\Http\Fields;

class DateTime extends Field
{
    protected string $component = 'field-datetime';
    protected string $format = 'Y-m-d H:i:s';
    protected ?\DateTimeInterface $minDate = null;
    protected ?\DateTimeInterface $maxDate = null;
    protected bool $dateOnly = false;
    protected bool $timeOnly = false;
    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute);
        $this->type = 'datetime';
        $this->component = config('nadota.fields.datetime.component', $this->component);
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

    public function dateOnly(): static
    {
        $this->dateOnly = true;
        $this->timeOnly = false;
        $this->format = 'Y-m-d';
        return $this;
    }

    public function timeOnly(): static
    {
        $this->timeOnly = true;
        $this->dateOnly = false;
        $this->format = 'H:i:s';
        return $this;
    }
    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\Said\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'format' => $this->format,
            'min' => $this->minDate?->format('Y-m-d'),
            'max' => $this->maxDate?->format('Y-m-d'),
            'dateOnly' => $this->dateOnly,
            'timeOnly' => $this->timeOnly,
        ]);
    }
    
    public function resolve(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\Said\Nadota\Contracts\ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};
        if ($value instanceof \DateTimeInterface) {
            return $value->format($this->format);
        }
        return $value;
    }
}
