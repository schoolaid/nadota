<?php

namespace SchoolAid\Nadota\Http\Resources\Index;

use Illuminate\Http\Resources\Json\ResourceCollection;

class IndexResource extends ResourceCollection
{
    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
