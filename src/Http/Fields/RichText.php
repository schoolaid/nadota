<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class RichText extends Field
{
    protected bool $sanitize = true;
    protected ?array $allowedTags = null;
    protected ?array $toolbar = null;
    protected ?string $editorPlaceholder = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::RICH_TEXT->value, static::safeConfig('nadota.fields.rich_text.component', 'FieldRichText'));
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

    /**
     * Set the toolbar configuration for the rich text editor.
     * The format depends on the frontend editor implementation.
     */
    public function toolbar(array $toolbar): static
    {
        $this->toolbar = $toolbar;
        return $this;
    }

    /**
     * Set a placeholder for the rich text editor.
     */
    public function editorPlaceholder(string $placeholder): static
    {
        $this->editorPlaceholder = $placeholder;
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), array_filter([
            'sanitize' => $this->sanitize,
            'allowedTags' => $this->allowedTags,
            'toolbar' => $this->toolbar,
            'editorPlaceholder' => $this->editorPlaceholder,
        ], fn($value) => $value !== null));
    }
}
