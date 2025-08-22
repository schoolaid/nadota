<?php

namespace Said\Nadota\Http\Traits;

use Said\Nadota\Http\Requests\NadotaRequest;

trait ResourceMenuOptions
{
    public function displayInMenu(NadotaRequest $request): bool
    {
        return true;
    }

    public function displayInSubMenu(): string|null
    {
        return null;
    }
    public function displayIcon(): string|null
    {
        return null;
    }

    public function orderInMenu(): int
    {
        return 1;
    }
    public function menuChildren(): array
    {
        return [];
    }
}
