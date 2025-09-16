<?php

namespace SchoolAid\Nadota\Http\Traits;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

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
}
