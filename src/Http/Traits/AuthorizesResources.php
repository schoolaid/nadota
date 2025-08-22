<?php

namespace SchoolAid\Nadota\Http\Traits;

use SchoolAid\Nadota\ResourceManager;

trait AuthorizesResources
{
    public function validateResource(): void
    {
        $resourceKey = $this->route('resourceKey');
        if (!ResourceManager::exists($resourceKey)) {
            abort(404);
        }
    }
    public function authorized(string $action, $model = null): void
    {
        $this->prepareResource();

        if (!$this->resource->authorizedTo($this, $action, $model)) {
            abort(403);
        }
    }
}
