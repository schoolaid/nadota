<?php
namespace Said\Nadota\Contracts;

use Said\Nadota\Http\Requests\NadotaRequest;

interface ResourceAuthorizationInterface
{
    public function authorizedTo(NadotaRequest $request, string $action): bool;

    public function setModel($model): self;

}
