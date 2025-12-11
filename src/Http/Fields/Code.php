<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class Code extends Field
{
    protected string $language = 'javascript';
    protected ?string $theme = 'light';
    protected bool $showLineNumbers = true;
    protected bool $editable = true;
    protected bool $syntaxHighlighting = true;
    protected bool $wordWrap = false;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::CODE->value, static::safeConfig('nadota.fields.code.component', 'FieldCode'));

        // Code fields are not suitable for index view by default
        $this->hideFromIndex();
    }

    public function language(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function theme(string $theme): static
    {
        $this->theme = $theme;
        return $this;
    }

    public function showLineNumbers(bool $showLineNumbers = true): static
    {
        $this->showLineNumbers = $showLineNumbers;
        return $this;
    }

    public function editable(bool $editable = true): static
    {
        $this->editable = $editable;
        return $this;
    }

    public function syntaxHighlighting(bool $syntaxHighlighting = true): static
    {
        $this->syntaxHighlighting = $syntaxHighlighting;
        return $this;
    }

    public function wordWrap(bool $wordWrap = true): static
    {
        $this->wordWrap = $wordWrap;
        return $this;
    }

    public function php(): static
    {
        return $this->language('php');
    }

    public function javascript(): static
    {
        return $this->language('javascript');
    }

    public function python(): static
    {
        return $this->language('python');
    }

    public function html(): static
    {
        return $this->language('html');
    }

    public function css(): static
    {
        return $this->language('css');
    }

    public function sql(): static
    {
        return $this->language('sql');
    }

    public function json(): static
    {
        return $this->language('json');
    }

    public function yaml(): static
    {
        return $this->language('yaml');
    }

    public function xml(): static
    {
        return $this->language('xml');
    }

    public function markdown(): static
    {
        return $this->language('markdown');
    }

    public function shell(): static
    {
        return $this->language('shell');
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'language' => $this->language,
            'theme' => $this->theme,
            'showLineNumbers' => $this->showLineNumbers,
            'editable' => $this->editable,
            'syntaxHighlighting' => $this->syntaxHighlighting,
            'wordWrap' => $this->wordWrap,
        ]);
    }
}