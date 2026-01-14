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
        return array_merge(
            $this->resource->toInfoArray($request),
            $this->additionalData
        );
    }
}
