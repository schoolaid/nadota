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
        return str_replace(' ', '', strtolower($this->name));
    }

    public function default(): string
    {
        return '';
    }

    public function props(): array
    {
        return [];
    }

    public function toArray($request): array
    {
        return [
            'id' => $this->id(),
            'key' => $this->key(),
            'name' => $this->name(),
            'component' => $this->component(),
            'options' => collect($this->resources($request))->map(function ($value, $label) {
                return is_array($value)
                    ? collect($value)->put('label', $label)->all()
                    : ['label' => $label ?? $value, 'value' => $value];
            })->values()->all(),
            'value' => $this->default() ?: '',
            'props' => $this->props(),
        ];
    }
}
