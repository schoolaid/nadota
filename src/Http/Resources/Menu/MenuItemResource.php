<?php

namespace Said\Nadota\Http\Resources\Menu;

use Illuminate\Http\Resources\Json\JsonResource;
use Said\Nadota\Contracts\MenuItemInterface;

class MenuItemResource extends JsonResource
{
    /**
     * @var MenuItemInterface
     */
    public $resource;

    public function toArray($request): array
    {
        return [
            'label' => $this->resource->getLabel(),
            'key' => $this->resource->getKey(),
            'icon' => $this->resource->getIcon(),
            'apiUrl' => $this->resource->getApiUrl(),
            'frontendUrl' => $this->resource->getFrontendUrl(),
            'children' => [],
            'order' => $this->resource->getOrder(),
            'isResource' => $this->resource->isResource(),
        ];
    }
}
