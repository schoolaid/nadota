<?php

namespace SchoolAid\Nadota\Http\Resources\Menu;

use Illuminate\Http\Resources\Json\JsonResource;
use SchoolAid\Nadota\Menu\MenuSection;

class MenuSectionResource extends JsonResource
{
    /**
     * @var MenuSection
     */
    public $resource;

    public function toArray($request)
    {
        return [
            'isSection' => true,
            'title' => $this->resource->getLabel(),
            'icon' => $this->resource->getIcon(),
            'enableSearch' => $this->resource->isSearchEnabled(),
            'children' => new MenuResource($this->resource->getChildren()),
            'order' => $this->resource->getOrder(),
        ];
    }
}
