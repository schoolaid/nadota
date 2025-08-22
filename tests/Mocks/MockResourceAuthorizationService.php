<?php

namespace Said\Nadota\Tests\Mocks;

use Said\Nadota\Contracts\ResourceAuthorizationInterface;
use Said\Nadota\Http\Requests\NadotaRequest;

class MockResourceAuthorizationService implements ResourceAuthorizationInterface
{
    public $model;

    public function setModel($model): self
    {
        $this->model = $model;
        return $this;
    }

    public function authorizedTo(NadotaRequest $request, string $action): bool
    {
        // Always authorize in tests
        return true;
    }
}