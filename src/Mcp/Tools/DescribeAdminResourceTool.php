<?php

namespace SchoolAid\Nadota\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Mcp\ExposedResources;
use SchoolAid\Nadota\Mcp\QueryCatalog;
use SchoolAid\Nadota\Mcp\RequestScope;

#[IsReadOnly]
#[IsIdempotent]
class DescribeAdminResourceTool extends NadotaTool
{
    protected string $name = 'describe-admin-resource';

    protected string $description = 'Forma de un resource (ver list-admin-resources): sus campos (key, label, type, si salen en el listado o solo en el detalle, opciones de los enum), los filtros que acepta query-admin-resource (key exacta, kind, opciones) y si tiene búsqueda. Llámala ANTES de consultar: un filtro o un sortField fuera de esta lista es error.';

    public function __construct(
        ExposedResources $resources,
        RequestScope $scope,
        protected readonly QueryCatalog $catalog,
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
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'resource' => 'required|string|max:120',
        ]);

        $exposed = $this->resolveResource($request, $validated['resource']);

        if ($exposed instanceof Response) {
            return $exposed;
        }

        return $this->withinNadota($request, $exposed, [], fn (NadotaRequest $nadota): Response => Response::json(
            $this->catalog->describe($exposed, $nadota),
        ));
    }
}
