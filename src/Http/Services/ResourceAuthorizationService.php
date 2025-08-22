<?php

namespace Said\Nadota\Http\Services;
use Said\Nadota\Contracts\ResourceAuthorizationInterface;
use Illuminate\Support\Facades\Gate;
use Said\Nadota\Http\Requests\NadotaRequest;

class ResourceAuthorizationService implements ResourceAuthorizationInterface
{
    public $model;

    public function setModel($model): self
    {
        $this->model = $model;
        return $this;
    }

    public function authorizedTo(NadotaRequest $request, string $action): bool
    {

        $gate = Gate::getPolicyFor($this->model);

        if (!is_null($gate)) {
            if (method_exists($gate, $action)) {
                return Gate::forUser($request->user())->allows($action, $this->model);
            }
        }

        return true;
    }
}
