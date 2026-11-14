<?php

namespace SchoolAid\Nadota\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Mcp\ExposedResources;
use SchoolAid\Nadota\Mcp\IndexQuery;
use SchoolAid\Nadota\Mcp\RecordProjector;
use SchoolAid\Nadota\Mcp\RequestScope;

/**
 * One record with its detail fields. Ids are positive integers; a server whose resources are
 * keyed by text (a tenant slug, a UUID) subclasses it with usesStringIds() returning true.
 */
#[IsReadOnly]
#[IsIdempotent]
class GetAdminRecordTool extends NadotaTool
{
    protected string $name = 'get-admin-record';

    protected string $description = 'Un registro de un resource por id, con los campos de detalle (incluye los que no salen en el listado). Mismo formato de valores que query-admin-resource. Un registro que no existe y uno sin acceso responden igual.';

    public function __construct(
        ExposedResources $resources,
        RequestScope $scope,
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
            'id' => $this->usesStringIds()
                ? $schema->string()->description('Id del registro (el `id` de query-admin-resource).')->required()
                : $schema->integer()->description('Id del registro (el `id` de query-admin-resource).')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'resource' => 'required|string|max:120',
            'id' => $this->usesStringIds() ? ['required', 'regex:/^[A-Za-z0-9_.-]{1,100}$/'] : 'required|integer|min:1',
        ], [
            'id.regex' => 'id debe ser el id del registro (letras, números, punto, guion).',
            'id.integer' => 'id debe ser un número entero.',
            'id.min' => 'id debe ser un número entero positivo.',
        ]);

        $exposed = $this->resolveResource($request, $validated['resource']);

        if ($exposed instanceof Response) {
            return $exposed;
        }

        $id = $this->usesStringIds() ? (string) $validated['id'] : (int) $validated['id'];

        return $this->withinNadota($request, $exposed, [], function (NadotaRequest $nadota) use ($exposed, $id): Response {
            $model = $this->query->find($exposed, $nadota, $id);

            if ($model === null) {
                return Response::error("Registro no encontrado o sin acceso: {$exposed->key} #{$id}.");
            }

            $fields = ($this->projector->rowProjector($exposed, $nadota, $exposed->resource->fieldsForShow($nadota, 'show', $model)))($model);
            unset($fields['id']);

            return Response::json([
                'id' => $model->getKey(),
                'label' => (string) $exposed->resource->displayLabel($model),
                'fields' => $fields,
            ]);
        });
    }

    protected function usesStringIds(): bool
    {
        return false;
    }
}
