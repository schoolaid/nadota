<?php

namespace Said\Nadota\Http\Traits;

use Said\Nadota\Resource;
use Said\Nadota\ResourceManager;

trait PrepareResource
{
    protected ?Resource $resource = null;
    public function getResource(): Resource
    {
        $this->prepareResource();
        return $this->resource;
    }
    public function prepareResource(): void
    {
        if (!$this->resource) {
            $resourceKey = $this->route('resourceKey');
            $this->resource = app(ResourceManager::getResourceByKey($resourceKey));
        }
    }
}
