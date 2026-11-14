<?php

namespace SchoolAid\Nadota\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Mcp\ExposedResource;
use SchoolAid\Nadota\Mcp\ExposedResources;
use SchoolAid\Nadota\Mcp\IndexQuery;
use SchoolAid\Nadota\Mcp\PaginatedJson;
use SchoolAid\Nadota\Mcp\QueryCatalog;
use SchoolAid\Nadota\Mcp\RecordProjector;
use SchoolAid\Nadota\Mcp\RequestScope;

/**
 * Pages a resource with the panel's query. An app that narrows the context per call (a branch
 * office, say) subclasses it: extraSchema() and extraRules() add the argument, and beforeQuery()
 * applies it once the resource passed the gatekeeper, or answers an error.
 */
#[IsReadOnly]
#[IsIdempotent]
class QueryAdminResourceTool extends NadotaTool
{
    protected const MAX_PER_PAGE = 50;

    protected const DEFAULT_PER_PAGE = 20;

    protected string $name = 'query-admin-resource';

    protected string $description = 'Lista registros de un resource (ver describe-admin-resource), paginado: perPage 1-50, default 20. Montos como {amount, currency}, enums como {value, label}, relaciones como {id, label}; los campos que solo salen en el detalle se piden con get-admin-record. filters: mapa key → valor con las keys de describe-admin-resource (una lista de valores solo en filtros oneOf; un objeto {from, to} con fechas YYYY-MM-DD en filtros dateRange). Respeta tus permisos y el alcance de tu sesión del panel.';

    public function __construct(
        ExposedResources $resources,
        RequestScope $scope,
        protected readonly QueryCatalog $catalog,
        protected readonly IndexQuery $query,
        protected readonly RecordProjector $projector,
    ) {
        parent::__construct($resources, $scope);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('Key del resource (ver list-admin-resources).')->required(),
            'search' => $schema->string()->description('Texto libre; solo si describe-admin-resource dice search.enabled.'),
            'filters' => $schema->object()->description('Mapa key → valor con las keys de describe-admin-resource (filters). Una lista de valores solo en filtros oneOf; un objeto {"from": "YYYY-MM-DD", "to": "YYYY-MM-DD"} en filtros dateRange.'),
            'sortField' => $schema->string()->description('Key de un campo con sortable: true en describe-admin-resource.'),
            'sortDirection' => $schema->string()->enum(['asc', 'desc'])->description('Default desc.'),
            'perPage' => $schema->integer()->description('1-'.static::MAX_PER_PAGE.', default '.static::DEFAULT_PER_PAGE.'.'),
            'page' => $schema->integer()->description('Página, desde 1.'),
            ...$this->extraSchema($schema),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'resource' => 'required|string|max:120',
            'search' => 'nullable|string|max:255',
            'filters' => 'nullable|array',
            'sortField' => 'nullable|string|max:120',
            'sortDirection' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|integer|min:1|max:'.static::MAX_PER_PAGE,
            'page' => 'nullable|integer|min:1|max:100000',
            ...$this->extraRules(),
        ], [
            'perPage.min' => 'perPage va de 1 a '.static::MAX_PER_PAGE.'.',
            'perPage.max' => 'perPage va de 1 a '.static::MAX_PER_PAGE.'.',
            'sortDirection.in' => 'sortDirection debe ser asc o desc.',
        ]);

        if (isset($validated['sortDirection']) && ! filled($validated['sortField'] ?? null)) {
            return Response::error('sortDirection va con sortField (ver defaultSort en describe-admin-resource).');
        }

        $exposed = $this->resolveResource($request, $validated['resource']);

        if ($exposed instanceof Response) {
            return $exposed;
        }

        if ($error = $this->beforeQuery($request, $exposed, $validated)) {
            return $error;
        }

        $search = $validated['search'] ?? null;
        $filters = $validated['filters'] ?? [];
        $sortField = $validated['sortField'] ?? null;
        $perPage = (int) ($validated['perPage'] ?? static::DEFAULT_PER_PAGE);
        $page = (int) ($validated['page'] ?? 1);

        $input = array_filter([
            $exposed->resource->getSearchKey() => $search,
            'sortField' => $sortField,
            'sortDirection' => $validated['sortDirection'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return $this->withinNadota($request, $exposed, $input, function (NadotaRequest $nadota) use ($exposed, $search, $filters, $sortField, $perPage, $page): Response {
            $rejection = $this->catalog->rejection($exposed, $nadota, $search, $filters, $sortField);

            if ($rejection !== null) {
                return Response::error($rejection);
            }

            $paginator = $this->query->paginate($exposed, $nadota, $filters, $perPage, $page);
            $project = $this->projector->rowProjector($exposed, $nadota, $exposed->resource->fieldsForIndex($nadota));

            return Response::json(PaginatedJson::from($paginator, $project));
        });
    }

    /**
     * Extra arguments of an app's subclass.
     *
     * @return array<string, JsonSchema>
     */
    protected function extraSchema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Validation rules of the extra arguments.
     *
     * @return array<string, mixed>
     */
    protected function extraRules(): array
    {
        return [];
    }

    /**
     * Runs after the resource passed the gatekeeper and before the query: an app applies its
     * extra arguments here. A Response aborts the call with it.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function beforeQuery(Request $request, ExposedResource $exposed, array $validated): ?Response
    {
        return null;
    }
}
