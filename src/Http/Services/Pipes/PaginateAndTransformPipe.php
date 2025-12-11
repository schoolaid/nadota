<?php

namespace SchoolAid\Nadota\Http\Services\Pipes;

use Closure;
use SchoolAid\Nadota\Http\DataTransferObjects\IndexRequestDTO;
use Illuminate\Support\Collection;

class PaginateAndTransformPipe
{
    public function handle(IndexRequestDTO $data, Closure $next)
    {
        $defaultPerPage = $data->resource->getPerPage();
        $perPage = $data->request->input('perPage', $defaultPerPage);

        /** @var Collection $collection */
        $collection = $data->query->paginate($perPage);

        $fields = $data->getFields();

        $collection->transform(function ($item) use ($data, $fields) {
            return $data->resource
                ->transformForIndex($item, $data->request, $fields);
        });

        return $next($collection);
    }
}
