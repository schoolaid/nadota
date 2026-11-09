<?php

use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Mcp\RequestScope;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpBatchResource;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpUser;

it('binds a NadotaRequest of the MCP user while it runs and restores the request after', function () {
    $original = app('request');
    $user = McpUser::granted(['batch'], id: 7);

    $seen = app(RequestScope::class)->run($user, app(McpBatchResource::class), ['search' => 'x'], fn (NadotaRequest $request): array => [
        'bound' => request() === $request,
        'user' => $request->user()?->getAuthIdentifier(),
        'input' => $request->input('search'),
        'resource' => $request->getResource()::class,
    ]);

    expect($seen)->toBe(['bound' => true, 'user' => 7, 'input' => 'x', 'resource' => McpBatchResource::class])
        ->and(app('request'))->toBe($original)
        ->and(request())->toBe($original);
});

it('restores the request when Nadota throws', function () {
    $original = app('request');

    expect(fn () => app(RequestScope::class)->run(McpUser::granted([]), app(McpBatchResource::class), [], function (): never {
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class, 'boom');

    expect(app('request'))->toBe($original)->and(request())->toBe($original);
});

it('answers a generic error without the SQL and reports the exception', function () {
    $reported = [];
    app(Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (Throwable $exception) use (&$reported): void {
        $reported[] = $exception::class;
    });

    Illuminate\Support\Facades\Schema::drop('mcp_batches');

    $error = mcpTool(McpUser::granted(['batch']), SchoolAid\Nadota\Mcp\Tools\QueryAdminResourceTool::class, ['resource' => 'mcp-batch'])->toolError();

    expect($error)->toBe('No se pudo consultar mcp-batch.')
        ->and($reported)->toContain(Illuminate\Database\QueryException::class);
});
