<?php
namespace Said\Nadota\Http\Controllers;

use Said\Nadota\Contracts\MenuServiceInterface;
use Said\Nadota\Http\Requests\NadotaRequest;

readonly class MenuController
{
    public function __construct(
        protected MenuServiceInterface $menuService
    ) {
    }

    public function menu(NadotaRequest $request)
    {
        return $this->menuService->handle($request);
    }
}
