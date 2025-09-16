<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Json extends Field
{
    protected bool $prettyPrint = true;
    protected bool $editable = true;
    protected ?int $indentSize = 2;
    protected bool $showLineNumbers = false;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::JSON->value, config('nadota.fields.json.component', 'FieldJson'));

        // JSON fields are not suitable for index view by default
        $this->hideFromIndex();
    }

    public function prettyPrint(bool $prettyPrint = true): static
    {
        $this->prettyPrint = $prettyPrint;
        return $this;
    }

    public function editable(bool $editable = true): static
    {
        $this->editable = $editable;
        return $this;
    }

    public function indentSize(int $indentSize): static
    {
        $this->indentSize = $indentSize;
        return $this;
    }

    public function showLineNumbers(bool $showLineNumbers = true): static
    {
        $this->showLineNumbers = $showLineNumbers;
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'prettyPrint' => $this->prettyPrint,
            'editable' => $this->editable,
            'indentSize' => $this->indentSize,
            'showLineNumbers' => $this->showLineNumbers,
        ]);
    }

    public function resolve(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};

        // If value is already an array/object, return as is
        if (is_array($value) || is_object($value)) {
            return $value;
        }

        // If it's a string, try to decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
        }

        return $value;
    }

    public function fill(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model): void
    {
        $value = $request->get($this->getAttribute());

        // Handle JSON string input
        if (is_string($value)) {
            // Try to decode to validate JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Store as JSON string if the model casts to json
                $casts = $model->getCasts();
                if (isset($casts[$this->getAttribute()]) && in_array($casts[$this->getAttribute()], ['json', 'array', 'object', 'collection'])) {
                    $model->{$this->getAttribute()} = $decoded;
                } else {
                    $model->{$this->getAttribute()} = $value;
                }
            } else {
                // Invalid JSON, store as is or handle error
                $model->{$this->getAttribute()} = $value;
            }
        } else {
            // Already an array/object
            $model->{$this->getAttribute()} = $value;
        }
    }
}