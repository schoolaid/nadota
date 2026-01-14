<?php

namespace SchoolAid\Nadota\Tests\Mocks;

use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class MockResourceAuthorizationService implements ResourceAuthorizationInterface
{
    public $model;

    public function setModel($model): self
    {
        $this->model = $model;
        return $this;
    }

    public function authorizedTo(NadotaRequest $request, string $action, array $context = []): bool
    {
        // Always authorize in tests
        return true;
    }
}