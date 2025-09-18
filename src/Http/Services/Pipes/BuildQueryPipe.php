<?php

namespace SchoolAid\Nadota\Http\Services\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\DataTransferObjects\IndexRequestDTO;

class BuildQueryPipe
{
    public function handle(IndexRequestDTO $data, Closure $next)
    {
        $data->prepareQuery();
        $this->addTrashedCondition(
                $data->query,
                $data->request->get('withTrashed'),
                $data->resource->getUseSoftDeletes()
        );

        $this->addRelations($data->query, $data->getFields());

        return $next($data);
    }

    /**
     * Handle soft delete conditions for the query
     * 
     * @param Builder $query
     * @param mixed $trashedParam Can be: 'with' (all), 'only' (only trashed), null/false (not trashed)
     * @param bool $usesSoftDeletes
     * @return Builder
     */
    protected function addTrashedCondition(Builder $query, mixed $trashedParam, bool $usesSoftDeletes): Builder
    {
        if (!$usesSoftDeletes) {
            return $query;
        }

        // Convert to string if numeric
        $trashedParam = is_numeric($trashedParam) ? (string) $trashedParam : $trashedParam;

        // Handle different parameter values
        switch ($trashedParam) {
            case 'with':
            case 'all':
            case '2':
                case 'true':
                // Show all records (including soft deleted)
                $query->withTrashed();
                break;
            
            case 'only':
            case 'deleted':
            case '1':
                // Show only soft deleted records
                $query->onlyTrashed();
                break;
            
            case 'without':
            case 'active':
            case '0':
            case null:
            case false:
            case '':
            default:
                // Show only non-deleted records (default behavior)
                // No action needed as this is the default
                break;
        }

        return $query;
    }

    protected function addRelations(Builder $query, Collection $fields): void
    {
        $relations = $fields
            ->filter(fn($field) => $field->isRelationship() && $field->isAppliedInIndexQuery())
            ->map(fn($field) => $field->getRelation())
            ->unique()
            ->values()
            ->all();

        if (!empty($relations)) {
            $query->with($relations);
        }
    }
}
