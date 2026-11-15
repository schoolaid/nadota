<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Json extends Field
{
    protected bool $prettyPrint = true;
    protected bool $editable = true;
    protected ?int $indentSize = 2;
    protected bool $showLineNumbers = false;

    /**
     * Set by normalizeInput() when the submitted text is not valid JSON.
     */
    protected bool $invalidJsonInput = false;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::JSON->value, static::safeConfig('nadota.fields.json.component', 'FieldJson'));

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

    /**
     * Fill outside the persist service (which goes through normalizeInput() and resolveForStore()).
     */
    public function fill(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model): void
    {
        if (!$request->exists($this->getAttribute())) {
            return;
        }

        $model->{$this->getAttribute()} = $this->toStoredValue($model, $this->normalizeInput($request->get($this->getAttribute())));
    }

    /**
     * The form sends the JSON as text (multipart). Decode it before validation, so the rules
     * (and data-aware rules of other fields, e.g. `settings.allow_http`) see the structure the
     * model will store. Empty text is null; text that is not JSON stays as is and fails the
     * validation added by getRules().
     */
    public function normalizeInput(mixed $value): mixed
    {
        $this->invalidJsonInput = false;

        if (!is_string($value)) {
            return $value;
        }

        if (trim($value) === '') {
            return null;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->invalidJsonInput = true;

            return $value;
        }
    }

    public function getRules(): array
    {
        $rules = parent::getRules();

        // Only after normalizeInput() saw text that is not JSON (it runs before validation), so
        // the rules sent to the frontend stay plain strings.
        if ($this->invalidJsonInput) {
            $rules[] = static function (string $attribute, mixed $value, \Closure $fail): void {
                $fail('validation.json')->translate();
            };
        }

        return $rules;
    }

    public function resolveForStore(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource, $value): mixed
    {
        return $this->toStoredValue($model, parent::resolveForStore($request, $model, $resource, $value));
    }

    public function resolveForUpdate(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource, $value): mixed
    {
        return $this->toStoredValue($model, parent::resolveForUpdate($request, $model, $resource, $value));
    }

    /**
     * The decoded value for a JSON-cast attribute (the cast encodes it once); JSON text for a
     * plain column.
     */
    protected function toStoredValue(\Illuminate\Database\Eloquent\Model $model, mixed $value): mixed
    {
        if ($value === null || $this->modelCastsAttributeToJson($model)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function modelCastsAttributeToJson(\Illuminate\Database\Eloquent\Model $model): bool
    {
        $cast = $model->getCasts()[$this->getAttribute()] ?? null;

        if (!is_string($cast)) {
            return false;
        }

        // `encrypted:array` is one cast; `AsCollection:Foo` is the class plus an argument.
        $base = explode(':', $cast, 2)[0];
        $base = $base === 'encrypted' ? $cast : $base;

        return in_array(strtolower($base), ['array', 'json', 'object', 'collection', 'encrypted:array', 'encrypted:json', 'encrypted:object', 'encrypted:collection'], true)
            || is_a($base, \Illuminate\Database\Eloquent\Casts\AsArrayObject::class, true)
            || is_a($base, \Illuminate\Database\Eloquent\Casts\AsCollection::class, true)
            || is_a($base, \Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject::class, true)
            || is_a($base, \Illuminate\Database\Eloquent\Casts\AsEncryptedCollection::class, true);
    }
}
