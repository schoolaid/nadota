<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Html extends Field
{
    protected bool $sanitize = true;
    protected ?array $allowedTags = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::HTML->value, static::safeConfig('nadota.fields.html.component', 'FieldHtml'));
    }

    /**
     * Disable HTML sanitization (use with caution).
     */
    public function withoutSanitization(): static
    {
        $this->sanitize = false;
        return $this;
    }

    /**
     * Enable HTML sanitization.
     */
    public function sanitize(bool $sanitize = true): static
    {
        $this->sanitize = $sanitize;
        return $this;
    }

    /**
     * Set allowed HTML tags for sanitization.
     */
    public function allowedTags(array $tags): static
    {
        $this->allowedTags = $tags;
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'sanitize' => $this->sanitize,
            'allowedTags' => $this->allowedTags,
        ]);
    }
}
