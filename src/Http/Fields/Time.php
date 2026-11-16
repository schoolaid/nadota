<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Time extends Field
{
    use Traits\AppendsFormatRuleTrait;

    /**
     * Added last by getRules() (AppendsFormatRuleTrait): without it a value that is not a time
     * reached the database and failed there (500 on a TIME column). One format only: normalizeInput()
     * turns `H:i` into `H:i:s` first. With two formats (`H:i,H:i:s`) Laravel's after/before read the
     * other field with the first one and could not compare `08:00:00`.
     */
    protected string $formatRule = 'date_format:H:i:s';

    /**
     * `8:00` / `08:00` / `08:00:00` → `08:00:00`, before validation, so the rule and any after/before
     * of the app compare the same shape. A value it cannot read stays as is (and fails the rule); a
     * field that declares its own `date_format` is left alone.
     */
    public function normalizeInput(mixed $value): mixed
    {
        if (!is_string($value) || $this->declaresOwnDateFormat()) {
            return $value;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($value), $m)) {
            return sprintf('%02d:%s:%s', (int) $m[1], $m[2], $m[3] ?? '00');
        }

        return $value;
    }

    protected function declaresOwnDateFormat(): bool
    {
        foreach (parent::getRules() as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'date_format:') && $rule !== $this->formatRule) {
                return true;
            }
        }

        return false;
    }

    protected string $format = 'H:i:s';
    protected ?string $minTime = null;
    protected ?string $maxTime = null;
    protected int $step = 60;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::TIME->value, static::safeConfig('nadota.fields.time.component', 'FieldTime'));
    }

    public function format(string $format): static
    {
        $this->format = $format;
        return $this;
    }

    public function min(string $time): static
    {
        $this->minTime = $time;
        return $this;
    }

    public function max(string $time): static
    {
        $this->maxTime = $time;
        return $this;
    }

    /**
     * Set the step interval in seconds.
     */
    public function step(int $seconds): static
    {
        $this->step = $seconds;
        return $this;
    }

    /**
     * Set step to 1 minute intervals.
     */
    public function minuteStep(): static
    {
        $this->step = 60;
        return $this;
    }

    /**
     * Set step to 15 minute intervals.
     */
    public function quarterHourStep(): static
    {
        $this->step = 900;
        return $this;
    }

    /**
     * Set step to 30 minute intervals.
     */
    public function halfHourStep(): static
    {
        $this->step = 1800;
        return $this;
    }

    /**
     * Set step to 1 hour intervals.
     */
    public function hourStep(): static
    {
        $this->step = 3600;
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'format' => $this->format,
            'min' => $this->minTime,
            'max' => $this->maxTime,
            'step' => $this->step,
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
