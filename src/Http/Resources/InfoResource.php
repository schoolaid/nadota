<?php

namespace SchoolAid\Nadota\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InfoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'key' => $this->resource->getKey(),
            'title' => $this->resource->title(),
            'description' => $this->resource->description(),
            'perPage' => $this->resource->getPerPage(),
            'allowedPerPage' => $this->resource->getAllowedPerPage(),
            'allowedSoftDeletes' => $this->resource->getUseSoftDeletes(),
            'canCreate' => $this->resource->canCreate,
            'components' => $this->resource->getComponents(),
            'search' => [
                'key' => $this->resource->getSearchKey(),
                'enabled' => $this->resource->isSearchable(),
            ],
        ];
    }
}
