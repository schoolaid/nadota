<?php

namespace SchoolAid\Nadota\Http\Traits;

use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\ResourceManager;

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

    /**
     * Set the resource instance manually.
     * Useful for relation contexts where we need to temporarily use a different resource.
     *
     * @param Resource $resource
     * @return void
     */
    public function setResource(Resource $resource): void
    {
        $this->resource = $resource;
    }
}
