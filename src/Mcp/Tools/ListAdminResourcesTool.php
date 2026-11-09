<?php

namespace SchoolAid\Nadota\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use SchoolAid\Nadota\Mcp\ExposedResource;
use SchoolAid\Nadota\Mcp\Labels;

#[IsReadOnly]
#[IsIdempotent]
class ListAdminResourcesTool extends NadotaTool
{
    protected string $name = 'list-admin-resources';

    protected string $description = 'Resources del panel que puedes leer con las tools genéricas, según tus permisos: key, título y si aceptan búsqueda. Úsala para registros que no cubren las tools específicas. Flujo: list-admin-resources → describe-admin-resource → query-admin-resource / get-admin-record.';

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        $visible = $this->resources->visibleTo($request->user());

        if ($visible === []) {
            return Response::error('Permission denied.');
        }

        return Response::json([
            'resources' => array_map(fn (ExposedResource $exposed): array => [
                'key' => $exposed->key,
                'title' => app(Labels::class)->translate((string) $exposed->resource->title()),
                'searchable' => $exposed->search && $exposed->resource->isSearchable(),
            ], $visible),
        ]);
    }
}
