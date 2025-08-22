<?php

namespace SchoolAid\Nadota\Contracts;

interface MenuItemInterface
{
    public function getLabel(): string;
    public function getKey(): string;
    public function getIcon(): ?string;
    public function getApiUrl(): ?string;
    public function getFrontendUrl(): ?string;
    public function getParent(): ?string;
    public function getChildren(): array;
    public function setChildren(array $children): void;

    public function isResource(): bool;

    public function getOrder(): int;
}
