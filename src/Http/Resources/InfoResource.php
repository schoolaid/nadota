<?php

namespace SchoolAid\Nadota\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InfoResource extends JsonResource
{
    protected array $additionalData = [];

    /**
     * Add additional data to the resource.
     */
    public function withAdditionalData(array $data): static
    {
        $this->additionalData = $data;
        return $this;
    }

    public function toArray($request): array
    {
        return array_merge([
            'key' => $this->resource->getKey(),
            'title' => $this->resource->title(),
            'description' => $this->resource->description(),
            'perPage' => $this->resource->getPerPage(),
            'allowedPerPage' => $this->resource->getAllowedPerPage(),
            'allowedSoftDeletes' => $this->resource->getUseSoftDeletes(),
            'canCreate' => $this->resource->canCreate,
            'components' => $this->resource->getComponents(),
            'detailCardWidth' => $this->resource->getDetailCardWidth(),
            'search' => [
                'key' => $this->resource->getSearchKey(),
                'enabled' => $this->resource->isSearchable(),
            ],
            'selection' => $this->resource->getSelectionConfig(),
        ], $this->additionalData);
    }
}
