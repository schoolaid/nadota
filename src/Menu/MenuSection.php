<?php

namespace SchoolAid\Nadota\Menu;

use SchoolAid\Nadota\Contracts\MenuItemInterface;
use SchoolAid\Nadota\Http\Traits\VisibleWhen;

class MenuSection implements MenuItemInterface
{
    use VisibleWhen;

    protected string $title;
    protected ?string $icon;
    protected array $children;
    protected bool $enableSearch;
    protected int $order;
    protected bool $isResource = false;

    public function __construct(
        string $title,
        ?string $icon = null,
        array $children = [],
        int $order = 2,
        bool $enableSearch = false
    ) {
        $this->title = $title;
        $this->icon = $icon;
        $this->children = $children;
        $this->order = $order;
        $this->enableSearch = $enableSearch;
    }

    public function getLabel(): string
    {
        return $this->title;
    }

    public function getKey(): string
    {
        return '';
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getApiUrl(): ?string
    {
        return null;
    }

    public function getFrontendUrl(): ?string
    {
        return null;
    }

    public function getParent(): ?string
    {
        return null;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function setChildren(array $children): void
    {
        $this->children = $children;
    }

    public function isSearchEnabled(): bool
    {
        return $this->enableSearch;
    }

    public function isResource(): bool
    {
        return $this->isResource;
    }

    public function getOrder(): int
    {
        return $this->order;
    }
}
