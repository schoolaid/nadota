<?php

namespace Said\Nadota\Http\Resources;

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
            'allowedSoftDeletes' => method_exists($this->resource, 'getSoftDeletes'),
            'canCreate' => $this->resource->canCreate,
        ];
    }
}
