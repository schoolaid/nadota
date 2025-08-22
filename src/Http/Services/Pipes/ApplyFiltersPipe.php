<?php

namespace Said\Nadota\Http\Services\Pipes;

use Closure;
use Said\Nadota\Http\Criteria\FilterCriteria;
use Said\Nadota\Http\DataTransferObjects\IndexRequestDTO;

class ApplyFiltersPipe
{
    public function handle(IndexRequestDTO $data, Closure $next)
    {
        $filters = array_merge($data->getFields()
            ->filter(fn($field) => $field->isFilterable())
            ->flatMap(fn($field) => $field->filters())
            ->all(), $data->getFilters());

        $keys = array_map(function ($filter) {
            return $filter->key();
        }, $filters);

        $requestFilters = $data->request->get('filters', []);

        (new FilterCriteria(
            array_filter($requestFilters, fn($value, $key) => in_array($key, $keys), ARRAY_FILTER_USE_BOTH)
        ))->apply($data->request, $data->query, $filters);

        return $next($data);
    }
}
