<?php

namespace SchoolAid\Nadota\Mcp\Tools;

use Closure;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Mcp\ExposedResource;
use SchoolAid\Nadota\Mcp\ExposedResources;
use SchoolAid\Nadota\Mcp\RequestScope;
use Throwable;

/**
 * Base of the MCP read tools over Nadota resources (requires laravel/mcp). They register only if
 * the gatekeeper lets at least one resource through for the user, and handle() goes through it
 * again: an agent holding a stale tools/list does not skip it.
 *
 * An app registers the tools (or subclasses of them) in its Laravel\Mcp\Server. The description
 * of each can be replaced in `config('nadota.mcp.descriptions.<tool name>')`.
 */
abstract class NadotaTool extends Tool
{
    public function __construct(
        protected readonly ExposedResources $resources,
        protected readonly RequestScope $scope,
    ) {
    }

    public function description(): string
    {
        $override = config('nadota.mcp.descriptions.'.$this->name());

        return is_string($override) && $override !== '' ? $override : parent::description();
    }

    public function shouldRegister(Request $request): bool
    {
        return $this->resources->visibleTo($request->user()) !== [];
    }

    protected function resolveResource(Request $request, string $key): ExposedResource|Response
    {
        return $this->resources->resolve($request->user(), $key)
            ?? Response::error("Resource no encontrado o sin acceso: {$key}. Usa list-admin-resources para ver los disponibles.");
    }

    /**
     * Runs $callback with the user's NadotaRequest. If Nadota throws, the exception is reported
     * once the MCP request is back in the container (so the app's exception handler and logging
     * see the MCP request), and the agent gets an error without the SQL.
     *
     * @param  array<string, mixed>  $input
     * @param  Closure(NadotaRequest): Response  $callback
     */
    protected function withinNadota(Request $request, ExposedResource $exposed, array $input, Closure $callback): Response
    {
        try {
            return $this->scope->run($request->user(), $exposed->resource, $input, $callback);
        } catch (Throwable $exception) {
            report($exception);

            return Response::error("No se pudo consultar {$exposed->key}.");
        }
    }
}
