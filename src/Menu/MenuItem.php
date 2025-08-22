<?php

namespace Said\Nadota\Menu;

use Said\Nadota\Contracts\MenuItemInterface;
use Said\Nadota\Http\Traits\Makeable;
use Said\Nadota\Http\Traits\VisibleWhen;

class MenuItem implements MenuItemInterface
{
    use Makeable, VisibleWhen;

    protected string $label;
    protected string $key;
    protected ?string $icon;
    protected ?string $apiUrl;
    protected ?string $frontendUrl;
    protected ?string $parent;
    protected array $children = [];
    protected int $order;
    protected bool $isResource = false;


    public function __construct(
        string $label = null,
        string $key = null,
        ?string $icon = null,
        ?string $apiUrl = null,
        ?string $frontendUrl = null,
        ?string $parent = null,
        array $children = [],
        int $order = 2,
        bool $isResource = false
    ) {
        $this->label = $label;
        $this->key = $key;
        $this->icon = $icon;
        $this->apiUrl = $apiUrl;
        $this->frontendUrl = $frontendUrl;
        $this->parent = $parent;
        $this->children = $children;
        $this->order = $order;
        $this->isResource = $isResource;
    }

    public static function fromResource($resource): MenuItem
    {
        $resourceInstance = new $resource;

        return static::make(
            $resourceInstance->title(),
            $resourceInstance->getKey(),
            $resourceInstance->displayIcon(),
            $resourceInstance->apiUrl(),
            $resourceInstance->frontendUrl(),
            $resourceInstance->displayInSubMenu()
        );
    }
    public function addChild(MenuItemInterface $child): void
    {
        $this->children[] = $child;
    }

    public function order($order): static
    {
        $this->order = $order;
        return $this;
    }
    public function getLabel(): string
    {
        return $this->label;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getApiUrl(): ?string
    {
        return $this->apiUrl;
    }

    public function getFrontendUrl(): ?string
    {
        return $this->frontendUrl;
    }

    public function getParent(): ?string
    {
        return $this->parent;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function setChildren(array $children): void
    {
        $this->children = $children;
    }

    public function isResource(): bool
    {
        return $this->isResource;
    }
}
