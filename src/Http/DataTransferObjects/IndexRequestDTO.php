<?php

namespace SchoolAid\Nadota\Http\DataTransferObjects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use Illuminate\Support\Collection;

class IndexRequestDTO
{
    public Builder $query;
    public Model $modelInstance;
    public function __construct(
        public NadotaRequest $request,
        public ?Resource $resource
    ) {}
    public function prepareQuery(): void
    {
        $this->prepareModel();
        $this->query = $this->resource->getQuery($this->request, $this->modelInstance);
        $this->query = $this->resource->queryIndex($this->request, $this->query);
    }
    protected function prepareModel(): void
    {
        if ($this->resource) {
            $this->modelInstance = new $this->resource->model;
        }
    }
    public function getFields(): Collection
    {
        return $this->resource->fieldsForIndex($this->request);
    }
    public function getFilters(): array
    {
        return $this->resource->filters($this->request);
    }
}
