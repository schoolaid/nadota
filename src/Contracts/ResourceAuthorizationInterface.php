<?php
namespace SchoolAid\Nadota\Contracts;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;

interface ResourceAuthorizationInterface
{
    public function authorizedTo(NadotaRequest $request, string $action): bool;

    public function setModel($model): self;

}
