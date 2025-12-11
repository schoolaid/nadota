<?php

namespace SchoolAid\Nadota\Menu;

class MenuSectionDefinition
{
    protected string $key;
    protected string $label;
    protected ?string $icon = null;
    protected int $order = 0;
    protected bool $collapsible = true;
    protected bool $defaultCollapsed = false;
    protected mixed $visibleWhen = null;

    public function __construct(string $key, string $label)
    {
        $this->key = $key;
        $this->label = $label;
    }

    public static function make(string $key, string $label): static
    {
        return new static($key, $label);
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function order(int $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;
        return $this;
    }

    public function defaultCollapsed(bool $defaultCollapsed = true): static
    {
        $this->defaultCollapsed = $defaultCollapsed;
        return $this;
    }

    public function visibleWhen(callable $callback): static
    {
        $this->visibleWhen = $callback;
        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    public function isDefaultCollapsed(): bool
    {
        return $this->defaultCollapsed;
    }

    public function isVisible(mixed $request): bool
    {
        if ($this->visibleWhen === null) {
            return true;
        }

        return call_user_func($this->visibleWhen, $request);
    }

    public function toMenuSection(array $children = []): MenuSection
    {
        return new MenuSection(
            $this->label,
            $this->icon,
            $children,
            $this->order,
            false
        );
    }
}
