<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class CheckboxList extends Field
{
    protected array $options = [];
    protected ?int $minSelections = null;
    protected ?int $maxSelections = null;
    protected bool $inline = false;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::CHECKBOX_LIST->value, static::safeConfig('nadota.fields.checkboxList.component', 'FieldCheckboxList'));
    }

    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function min(int $min): static
    {
        $this->minSelections = $min;
        return $this;
    }

    public function max(int $max): static
    {
        $this->maxSelections = $max;
        return $this;
    }

    public function limit(int $max): static
    {
        return $this->max($max);
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
            'minSelections' => $this->minSelections,
            'maxSelections' => $this->maxSelections,
            'inline' => $this->inline,
        ]);
    }

    protected function formatOptions(): array
    {
        if ($this->isFormattedOptions($this->options)) {
            return $this->options;
        }

        $formatted = [];
        foreach ($this->options as $value => $label) {
            $formatted[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $formatted;
    }

    protected function isFormattedOptions(array $options): bool
    {
        if (empty($options)) {
            return true;
        }

        $firstOption = reset($options);
        return is_array($firstOption) && isset($firstOption['value']) && isset($firstOption['label']);
    }

    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};

        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function resolveForStore(Request $request, Model $model, ?ResourceInterface $resource, $value): mixed
    {
        return $this->prepareValueForPersistence($model, $value);
    }

    public function resolveForUpdate(Request $request, Model $model, ?ResourceInterface $resource, $value): mixed
    {
        return $this->prepareValueForPersistence($model, $value);
    }

    protected function prepareValueForPersistence(Model $model, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        $value = array_values($value);

        if ($this->modelCastsAttributeToArray($model)) {
            return $value;
        }

        return json_encode($value);
    }

    protected function modelCastsAttributeToArray(Model $model): bool
    {
        $casts = $model->getCasts();
        $cast = $casts[$this->getAttribute()] ?? null;

        if ($cast === null) {
            return false;
        }

        return in_array($cast, ['array', 'json', 'object', 'collection'], true)
            || str_starts_with($cast, 'encrypted:array')
            || str_starts_with($cast, 'encrypted:json')
            || str_starts_with($cast, 'encrypted:collection');
    }

    public function getRules(): array
    {
        $rules = parent::getRules();

        if ($this->minSelections !== null || $this->maxSelections !== null) {
            $rules[] = 'array';
        }

        if ($this->minSelections !== null) {
            $rules[] = "min:{$this->minSelections}";
        }

        if ($this->maxSelections !== null) {
            $rules[] = "max:{$this->maxSelections}";
        }

        return array_values(array_unique($rules));
    }
}
