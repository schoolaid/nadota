<?php
namespace SchoolAid\Nadota\Http\Resources\Index;

use Illuminate\Http\Resources\Json\JsonResource;

class FieldResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource;
    }
}
