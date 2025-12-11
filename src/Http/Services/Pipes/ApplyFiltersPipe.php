<?php

namespace SchoolAid\Nadota\Http\Services\Pipes;

use Closure;
use SchoolAid\Nadota\Http\Criteria\FilterCriteria;
use SchoolAid\Nadota\Http\DataTransferObjects\IndexRequestDTO;

class ApplyFiltersPipe
{
    public function handle(IndexRequestDTO $data, Closure $next)
    {
        $filters = array_merge($data->getFields()
            ->filter(fn($field) => $field->isFilterable())
            ->flatMap(fn($field) => $field->filters())
            ->all(), $data->getFilters());

        $requestFilters = $data->request->get('filters', []);

        // Normalizar filtros de rango (convertir _from/_to a formato start/end)
        $normalizedFilters = $this->normalizeRangeFilters($requestFilters, $filters);

        (new FilterCriteria($normalizedFilters))->apply($data->request, $data->query, $filters);

        return $next($data);
    }

    /**
     * Normaliza los filtros de rango convirtiendo keys separadas (_from/_to)
     * al formato esperado por los filtros (start/end).
     */
    protected function normalizeRangeFilters(array $requestFilters, array $filters): array
    {
        $normalized = [];
        $processedRangeKeys = [];

        foreach ($filters as $filter) {
            $key = $filter->key();
            $filterKeys = $filter->getFilterKeys();

            // Si es un filtro de rango con keys separadas
            if ($filter->isRange() && isset($filterKeys['from']) && isset($filterKeys['to'])) {
                $fromKey = $filterKeys['from'];
                $toKey = $filterKeys['to'];

                $fromValue = $requestFilters[$fromKey] ?? null;
                $toValue = $requestFilters[$toKey] ?? null;

                // Si hay al menos un valor de rango, crear el objeto para el filtro
                if ($fromValue !== null || $toValue !== null) {
                    $normalized[$key] = [
                        'start' => $fromValue,
                        'end' => $toValue,
                    ];
                }

                $processedRangeKeys[] = $fromKey;
                $processedRangeKeys[] = $toKey;
            }
            // Si el filtro ya viene en formato tradicional (key con valor directo o array)
            elseif (isset($requestFilters[$key])) {
                $normalized[$key] = $requestFilters[$key];
            }
        }

        return $normalized;
    }
}
