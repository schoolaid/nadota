<?php

namespace SchoolAid\Nadota\Http\Resources\Menu;

use Illuminate\Http\Resources\Json\ResourceCollection;
use SchoolAid\Nadota\Contracts\MenuItemInterface;

class MenuResource extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->map(function (MenuItemInterface $item) use ($request) {
            if ($item instanceof \SchoolAid\Nadota\Menu\MenuSection) {
                return (new MenuSectionResource($item))->toArray($request);
            } else {
                return (new MenuItemResource($item))->toArray($request);
            }
        })->all();
    }
}
