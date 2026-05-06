<?php

namespace SchoolAid\Nadota\Http\Filters;

use SchoolAid\Nadota\Contracts\FilterInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Helpers\Helpers;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

abstract class Filter implements FilterInterface
{
    use \SchoolAid\Nadota\Http\Traits\Makeable;
    public string $name = 'Filter';
    public string $type = 'text';
    public string $component = 'select-filter';
    protected string $field;
    protected string $id;
    public string $key;

    public function __construct(string $name = null, string $field = null, string $type = null, string $component =  null, $id = null)
    {
        if ($name) {
            $this->name = $name;
        }

        if ($field) {
            $this->field = $field;
        }

        if ($type) {
            $this->type = $type;
        }

        if($component){
            $this->component = $component;
        }else{
            $this->component = 'Filter' . ucfirst($this->type);
        }

        if($id){
            $this->id = $id;
        }else{
            $this->id = Helpers::slug($this->name);
        }

        
        $this->key = $this->key();
    }
    abstract public function apply(NadotaRequest $request, $query, $value);

    public function resources(NadotaRequest $request): array
    {
        return [];
    }

    public function id(): string
    {
        return $this->id;
    }

    public function component(): string
    {
        return $this->component;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function key(): string
    {
        // Usar field (atributo) como key para garantizar unicidad
        return $this->field ?? str_replace(' ', '', strtolower($this->name));
    }

    public function default(): string
    {
        return '';
    }

    public function props(): array
    {
        return [];
    }

    /**
     * Get the filter keys for this filter.
     * Override in subclasses for range filters.
     */
    public function getFilterKeys(): array
    {
        return [
            'value' => $this->field ?? $this->key(),
        ];
    }

    /**
     * Check if this filter is a range filter.
     */
    public function isRange(): bool
    {
        return false;
    }

    public function toArray($request): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->name(),
            'component' => $this->component(),
            'type' => $this->type,
            'options' => collect($this->resources($request))->map(function ($value, $label) {
                // Already in {value, label} format — pass through untouched.
                // Handles output from Select::getOptions() / formatOptions().
                if (is_array($value) && array_key_exists('value', $value) && array_key_exists('label', $value)) {
                    return $value;
                }
                // Object array with extra keys — add label from the collection key
                if (is_array($value)) {
                    return array_merge($value, ['label' => $label]);
                }
                // Simple [display_label => db_value] format (e.g. BooleanFilter: ['Sí' => true])
                return ['label' => $label ?? $value, 'value' => $value];
            })->values()->all(),
            'value' => $this->default() ?: '',
            'props' => $this->props(),
            'isRange' => $this->isRange(),
            'filterKeys' => $this->getFilterKeys(),
        ];
    }
}
