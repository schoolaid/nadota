<?php
namespace Said\Nadota\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Said\Nadota\Http\Requests\NadotaRequest;
use Said\Nadota\Http\Resources\Filters\FilterResource;
use Said\Nadota\Http\Resources\Index\FieldResource;
use Said\Nadota\Http\Resources\InfoResource;

class ResourceIndexController extends Controller
{
    public function __construct(NadotaRequest $request)
    {
        if (! App::runningInConsole()) {
            $request->validateResource();
        }
    }

    /**
     * @param NadotaRequest $request
     * @return InfoResource
     */
    public function info(NadotaRequest $request): InfoResource
    {
        $request->authorized('viewAny');
        $resource            = $request->getResource();
        $resource->canCreate = $resource->authorizedTo($request, 'create');

        return new InfoResource($resource);
    }

    /**
     * @param NadotaRequest $request
     * @return AnonymousResourceCollection
     */
    public function fields(NadotaRequest $request): AnonymousResourceCollection
    {
        $request->authorized('viewAny');
        $resource = $request->getResource();

        $fields = collect($resource->fields($request))
            ->filter(fn($field) => $field->showOnIndex())
            ->map(fn($field) => array_merge($field->toArray($request), [
                // 'filters' => collect($field->filters())->map(fn($filter) => $filter->toArray($request))->toArray(),
            ]));

        return FieldResource::collection($fields);
    }

    /**
     * @param NadotaRequest $request
     * @return AnonymousResourceCollection
     */
    public function filters(NadotaRequest $request): AnonymousResourceCollection
    {
        $request->authorized('viewAny');
        $resource = $request->getResource();

        $filters = array_merge(
            $this->getFieldFilters($resource->fields($request), $request),
            $this->getResourceFilters($resource, $request)
        );

        return FilterResource::collection($filters);
    }

    /**
     * @param array $fields
     * @param NadotaRequest $request
     * @return array
     */
    private function getFieldFilters(array $fields, NadotaRequest $request): array
    {
        return collect($fields)
            ->filter(fn($field) => $field->isFilterable())
            ->flatMap(fn($field) => $field->filters())
            ->map(fn($filter) => $filter->toArray($request))
            ->toArray();
    }

    /**
     * @param mixed $resource
     * @param NadotaRequest $request
     * @return array
     */
    private function getResourceFilters(mixed $resource, NadotaRequest $request): array
    {
        return collect($resource->filters($request))
            ->map(fn($filter) => $filter->toArray($request))
            ->toArray();
    }

    /**
     * TODO: New service
     * @param mixed $resource
     * @param NadotaRequest $request
     * @return array
     */
    public function compact(NadotaRequest $request)
    {
        $request->authorized('viewAny');
        $resource    = $request->getResource();
        $model       = $resource->model::query();
        $validFields = ['id'];
        $validFields = array_merge($validFields, collect($resource->fields($request))
                ->filter(fn($field) => ! $field->isAppliedInIndexQuery())
                ->map(fn($field) => $field->getAttribute())
                ->toArray());
        $fields  = $request->input('fields');
        $columns = explode(',', $fields);
        $columns = array_values(array_intersect($columns, $validFields));
        if ($columns) {
            $model->select($columns);
        }

        return $model->get();

    }
}
