<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Status extends Field
{
    protected string $component = 'field-status';
    protected array $statuses = [];

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::STATUS->value, static::safeConfig('nadota.fields.status.component', 'FieldStatus'));
    }

    /**
     * Define the status map.
     *
     * Accepts an associative array where keys are the database values
     * and values define the display configuration.
     *
     * @param array $statuses e.g. [
     *     'paid'    => ['label' => 'Paid',    'color' => 'green'],
     *     'pending' => ['label' => 'Pending', 'color' => 'yellow'],
     *     'failed'  => ['label' => 'Failed',  'color' => 'red'],
     * ]
     * Or shorthand: ['paid' => 'Paid', 'pending' => 'Pending']
     * @return static
     */
    public function map(array $statuses): static
    {
        $this->statuses = $statuses;
        return $this;
    }

    /**
     * Add a single status entry.
     *
     * @param string|int $value   The database value
     * @param string     $label   Display label
     * @param string     $color   Color name (green, yellow, red, blue, gray, etc.)
     * @param string|null $icon   Optional icon name
     * @return static
     */
    public function addStatus(string|int $value, string $label, string $color = 'gray', ?string $icon = null): static
    {
        $entry = ['label' => $label, 'color' => $color];

        if ($icon !== null) {
            $entry['icon'] = $icon;
        }

        $this->statuses[$value] = $entry;
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'statuses' => $this->formatStatuses(),
        ]);
    }

    /**
     * Normalize statuses to a consistent format.
     */
    protected function formatStatuses(): array
    {
        $formatted = [];

        foreach ($this->statuses as $value => $config) {
            if (is_string($config)) {
                // Shorthand: 'paid' => 'Paid'
                $formatted[] = [
                    'value' => $value,
                    'label' => $config,
                    'color' => 'gray',
                ];
            } else {
                $formatted[] = array_merge([
                    'value' => $value,
                    'color' => 'gray',
                ], $config);
            }
        }

        return $formatted;
    }

    /**
     * Find the status config for a given value.
     */
    protected function findStatus(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        foreach ($this->formatStatuses() as $status) {
            if ((string) $status['value'] === (string) $value) {
                return $status;
            }
        }

        return null;
    }

    public function resolve(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};

        $status = $this->findStatus($value);

        if ($status) {
            return $status;
        }

        // Fallback: return the raw value with default color
        return [
            'value' => $value,
            'label' => $value,
            'color' => 'gray',
        ];
    }

    /**
     * For export, return just the label.
     */
    public function resolveForExport(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};
        $status = $this->findStatus($value);

        return $status['label'] ?? $value;
    }

    public function getOptions(): array
    {
        return $this->formatStatuses();
    }
}
