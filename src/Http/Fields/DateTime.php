<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class DateTime extends Field
{
    protected string $component = 'field-datetime';
    protected string $format = 'Y-m-d H:i:s';
    protected ?string $storeFormat = null;
    protected ?\DateTimeInterface $minDate = null;
    protected ?\DateTimeInterface $maxDate = null;
    protected bool $dateOnly = false;
    protected bool $timeOnly = false;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::DATETIME->value, static::safeConfig('nadota.fields.datetime.component', 'FieldDateTime'));
    }

    /**
     * Set the format used when storing the value in the database.
     *
     * @param string $format
     * @return static
     */
    public function storeFormat(string $format): static
    {
        $this->storeFormat = $format;
        return $this;
    }

    /**
     * Get the store format, defaulting to the display format.
     *
     * @return string
     */
    protected function getStoreFormat(): string
    {
        if ($this->storeFormat !== null) {
            return $this->storeFormat;
        }

        // Use appropriate format based on field mode
        if ($this->dateOnly) {
            return 'Y-m-d';
        }

        if ($this->timeOnly) {
            return 'H:i:s';
        }

        return 'Y-m-d H:i:s';
    }

    /**
     * Transform a value for storage in the database.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function transformForStorage(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Already a DateTimeInterface
        if ($value instanceof \DateTimeInterface) {
            return $value->format($this->getStoreFormat());
        }

        // Try to parse the value (handles ISO 8601 format from frontend)
        try {
            $dateTime = new \DateTime($value);
            return $dateTime->format($this->getStoreFormat());
        } catch (\Exception $e) {
            // If parsing fails, return the original value and let the database handle/reject it
            return $value;
        }
    }

    /**
     * Resolve the field value for storing.
     * Transforms ISO 8601 format from frontend to database format.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param \SchoolAid\Nadota\Contracts\ResourceInterface|null $resource
     * @param mixed $value
     * @return mixed
     */
    public function resolveForStore(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource, $value): mixed
    {
        return $this->transformForStorage($value);
    }

    /**
     * Resolve the field value for updating.
     * Transforms ISO 8601 format from frontend to database format.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param \SchoolAid\Nadota\Contracts\ResourceInterface|null $resource
     * @param mixed $value
     * @return mixed
     */
    public function resolveForUpdate(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource, $value): mixed
    {
        return $this->transformForStorage($value);
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
    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'format' => $this->format,
            'min' => $this->minDate?->format('Y-m-d'),
            'max' => $this->maxDate?->format('Y-m-d'),
            'dateOnly' => $this->dateOnly,
            'timeOnly' => $this->timeOnly,
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
