<?php

namespace Said\Nadota\Http\Services\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Said\Nadota\Http\DataTransferObjects\IndexRequestDTO;
use Said\Nadota\Http\Fields\Relations\RelationField;

class BuildQueryPipe
{
    public function handle(IndexRequestDTO $data, Closure $next)
    {
        $data->prepareQuery();
        $this->addTrashedCondition(
                $data->query,
                $data->request->boolean('withTrashed'),
                $data->resource->usesSoftDeletes()
        );

        $this->addRelations($data->query, $data->getFields());

        return $next($data);
    }

    protected function addTrashedCondition(Builder $query, $withTrashed, $usesSoftDeletes): Builder
    {
        if (!isset($withTrashed) || !$usesSoftDeletes) {
            return $query;
        }

        if ($withTrashed) {
            $query->onlyTrashed();
        } else {
            $query->withTrashed();
        }

        return $query;
    }

    protected function addRelations(Builder $query, Collection $fields): void
    {
        $relations = $fields
            ->filter(fn($field) => $field instanceof RelationField && $field->isAppliedInIndexQuery())
            ->map(fn($field) => $field->getRelation())
            ->unique()
            ->values()
            ->all();

        if (!empty($relations)) {
            $query->with($relations);
        }
    }
}
