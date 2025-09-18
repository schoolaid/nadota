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

        $actions = $data->resource->actions($data->request);
        $fields = $data->getFields();

        $collection->transform(function ($item) use ($data, $actions, $fields) {
            return $data->resource
                ->transformForIndex($item, $data->request, $actions, $fields);
        });

        return $next($collection);
    }
}
