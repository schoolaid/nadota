<?php

namespace Said\Nadota\Http\Resources\Filters;
use Illuminate\Http\Resources\Json\JsonResource;

class FilterResource  extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource;
    }
}
