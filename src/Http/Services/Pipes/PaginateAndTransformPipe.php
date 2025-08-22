<?php

namespace Said\Nadota\Http\Services\Pipes;

use Closure;
use Said\Nadota\Http\DataTransferObjects\IndexRequestDTO;
use Illuminate\Support\Collection;
class PaginateAndTransformPipe
{
    public function handle(IndexRequestDTO $data, Closure $next)
    {
        $perPage = $data->request->input('perPage', 10);

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
