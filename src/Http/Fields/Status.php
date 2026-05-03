<?php

namespace SchoolAid\Nadota\Http\Fields;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Status extends Field
{
    protected array $statuses = [];
    protected bool $clearable = false;
    protected ?string $placeholder = null;
    protected bool $resolveWithStatus = false;
    protected bool $translateLabels = true;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::STATUS->value, static::safeConfig('nadota.fields.status.component', 'FieldStatus'));
    }

    /**
     * Define the status map.
     *
     * Keys can be strings, ints, or BackedEnum cases.
     * Values can be shorthand strings or full config arrays.
     *
     * @param array $statuses e.g. [
     *     StatusEnum::PAID    => ['label' => 'Paid',    'color' => 'green'],
     *     StatusEnum::PENDING => ['label' => 'Pending', 'color' => 'yellow'],
     *     'failed'            => ['label' => 'Failed',  'color' => 'red'],
     *     'draft'             => 'Draft',
     * ]
     */
    public function map(array $statuses): static
    {
        $this->statuses = $statuses;
        return $this;
    }

    /**
     * Add a single status entry.
     *
     * @param string|int|BackedEnum $value  The database value or enum case
     * @param string                $label  Display label
     * @param string                $color  Color name (green, yellow, red, blue, gray, etc.)
     * @param string|null           $icon   Optional icon name
     */
    public function addStatus(string|int|BackedEnum $value, string $label, string $color = 'gray', ?string $icon = null): static
    {
        $key = $value instanceof BackedEnum ? $value->value : $value;
        $entry = ['label' => $label, 'color' => $color];

        if ($icon !== null) {
            $entry['icon'] = $icon;
        }

        $this->statuses[$key] = $entry;
        return $this;
    }

    public function translateLabels(bool $translate = true): static
    {
        $this->translateLabels = $translate;
        return $this;
    }

    public function withoutTranslation(): static
    {
        return $this->translateLabels(false);
    }

    public function clearable(bool $clearable = true): static
    {
        $this->clearable = $clearable;
        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;
        parent::placeholder($placeholder);
        return $this;
    }

    /**
     * Make resolve() return the full status object {value, label, color} instead of the raw value.
     * Useful for index/detail views that need color info without a separate lookup.
     */
    public function withStatus(bool $withStatus = true): static
    {
        $this->resolveWithStatus = $withStatus;
        return $this;
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'statuses' => $this->formatStatuses(),
            'clearable' => $this->clearable,
            'placeholder' => $this->placeholder,
            'translateLabels' => $this->translateLabels,
        ]);
    }

    /**
     * Normalize statuses to a consistent format.
     * Enum keys are converted to their backing value.
     */
    protected function formatStatuses(): array
    {
        $formatted = [];

        foreach ($this->statuses as $key => $config) {
            $value = $key instanceof BackedEnum ? $key->value : $key;

            if (is_string($config)) {
                $formatted[] = ['value' => $value, 'label' => $config, 'color' => 'gray'];
            } else {
                $formatted[] = array_merge(['value' => $value, 'color' => 'gray'], $config);
            }
        }

        return $formatted;
    }

    /**
     * Extract the raw scalar value from a potential BackedEnum or scalar.
     */
    protected function extractRawValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * Find the status config for a given value (supports BackedEnum).
     */
    protected function findStatus(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $raw = $this->extractRawValue($value);

        foreach ($this->formatStatuses() as $status) {
            if ((string) $status['value'] === (string) $raw) {
                return $status;
            }
        }

        return null;
    }

    /**
     * Returns the raw value by default (aligns with Select behavior, safe for form pre-population).
     * Use withStatus() to return the full status object {value, label, color}.
     */
    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};
        $raw = $this->extractRawValue($value);

        if ($this->resolveWithStatus && $raw !== null) {
            $status = $this->findStatus($raw);
            return $status ?? ['value' => $raw, 'label' => $raw, 'color' => 'gray'];
        }

        return $raw;
    }

    public function resolveForExport(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};
        $status = $this->findStatus($value);

        return $status['label'] ?? $this->extractRawValue($value);
    }

    public function getOptions(): array
    {
        return $this->formatStatuses();
    }
}
