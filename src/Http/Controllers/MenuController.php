<?php
namespace SchoolAid\Nadota\Http\Controllers;

use SchoolAid\Nadota\Contracts\MenuServiceInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

readonly class MenuController
{
    public function __construct(
        protected MenuServiceInterface $menuService
    ) {
    }

    public function menu(NadotaRequest $request)
    {
        return $this->menuService->all($request);
    }
}
