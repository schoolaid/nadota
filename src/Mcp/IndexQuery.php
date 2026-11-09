<?php

namespace SchoolAid\Nadota\Mcp;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\DataTransferObjects\IndexRequestDTO;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\Pipes\ApplySearchPipe;
use SchoolAid\Nadota\Http\Services\Pipes\ApplySortingPipe;
use SchoolAid\Nadota\Http\Services\Pipes\BuildQueryPipe;

/**
 * Listing and detail of an exposed resource. The listing runs the steps of Nadota's index
 * (ResourceIndexService: build the query with queryIndex and scopes, search, filters, sorting)
 * except three: the filters come from QueryCatalog (detail-only fields included), the sort ends
 * with a primary-key tiebreaker, and the page is built here, with the tools' cap. Parity with the
 * panel is pinned by tests/Mcp/IndexQueryTest.php.
 */
class IndexQuery
{
    public function __construct(
        private readonly QueryCatalog $catalog,
        private readonly FieldTypes $types,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters  already validated with QueryCatalog::rejection()
     */
    public function paginate(ExposedResource $exposed, NadotaRequest $request, array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        /** @var IndexRequestDTO $data */
        $data = app(Pipeline::class)
            ->send(new IndexRequestDTO($request, $exposed->resource))
            ->through([BuildQueryPipe::class, ApplySearchPipe::class])
            ->thenReturn();

        $catalog = $this->catalog->filters($exposed, $request);

        foreach ($filters as $key => $value) {
            if (! isset($catalog[$key])) {
                throw new InvalidArgumentException("Filter outside the catalog of {$exposed->key}: {$key}.");
            }

            $data->query = $this->catalog->applyFilter($catalog[$key], $request, $data->query, $value);
        }

        /** @var IndexRequestDTO $data */
        $data = app(Pipeline::class)
            ->send($data)
            ->through([ApplySortingPipe::class])
            ->thenReturn();

        $data->query->orderBy($data->query->getModel()->getQualifiedKeyName(), $this->tiebreakerDirection($exposed, $request));

        $this->addProjectionColumns($exposed, $data->query);

        return $data->query->paginate(perPage: $perPage, page: $page);
    }

    /**
     * The direction of the primary-key tiebreaker: the same as the sort ApplySortingPipe applied.
     * Without it, rows that tie on `created_at` (a whole batch written at once) come out in an
     * order that changes between pages.
     */
    private function tiebreakerDirection(ExposedResource $exposed, NadotaRequest $request): string
    {
        if ($request->input('sortField')) {
            return $request->input('sortDirection') ?? 'desc';
        }

        return $exposed->resource->getDefaultSort()['direction'] ?? 'desc';
    }

    /**
     * The same build as ResourceShowService (getQuery, the fields' columns and counts), then
     * the `view` policy on the record. One difference: only the relations of the fields that
     * leave are eager loaded. The panel loads every detail relation and the show `with`
     * (getWithOnShow), so a parent with a HasMany field would load all its children, and a
     * student its medical records, just for the projection to drop them. Whatever else the
     * label or a listed field reads is lazy loaded on this one record, which
     * preventLazyLoading allows: it only guards results of more than one model.
     */
    public function find(ExposedResource $exposed, NadotaRequest $request, int|string $id): ?Model
    {
        $resource = $exposed->resource;
        $fields = $resource->flattenFields($request);
        $projected = $fields->filter(fn (Field $field): bool => $exposed->lists($field->key()) && $this->types->of($field) !== null);

        $query = $resource->getQuery($request)
            ->with($resource->getEagerLoadRelations($request, $projected))
            ->select(...$resource->getSelectColumns($request, $fields));

        $withCount = $resource->getWithCountRelations($request, $fields);

        if (! empty($withCount)) {
            $query->withCount($withCount);
        }

        $withExists = $resource->getWithExistsRelations($request, $fields);

        if (! empty($withExists)) {
            $query->withExists($withExists);
        }

        $this->addProjectionColumns($exposed, $query);

        $model = $query->find($id);

        return $model !== null && Gate::forUser($request->user())->allows('view', $model) ? $model : null;
    }

    /**
     * Nadota selects only its fields' columns. Amounts computed by a model method may need the
     * whole record; column amounts need their amount and their currency.
     */
    private function addProjectionColumns(ExposedResource $exposed, Builder $query): void
    {
        $table = $query->getModel()->getTable();

        if ($exposed->usesComputedMoney()) {
            $query->addSelect("{$table}.*");

            return;
        }

        foreach ($exposed->moneyColumns() as $column) {
            $query->addSelect("{$table}.{$column}");
        }
    }
}
