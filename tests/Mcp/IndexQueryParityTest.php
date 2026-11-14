<?php

use Illuminate\Support\Carbon;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceIndexService;
use SchoolAid\Nadota\Mcp\Tools\QueryAdminResourceTool;
use SchoolAid\Nadota\Mcp\RequestScope;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpBatch;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpBatchResource;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpUser;

/**
 * The ids the panel's index returns for the same input. If Nadota changes what it filters or how
 * it sorts, these tests turn red together with the pipes. Every row has its own created_at: on a
 * tie the panel leaves the order to the database and the tool breaks it by primary key (pinned in
 * QueryAdminResourceToolTest), so ties have no parity to check.
 *
 * @param  array<string, mixed>  $input
 * @return array<int, int>
 */
function mcpPanelIds(array $input): array
{
    return app(RequestScope::class)->run(McpUser::granted(['batch']), app(McpBatchResource::class), $input, function (NadotaRequest $request): array {
        $index = app(ResourceIndexService::class)->handle($request);

        return collect($index->response($request)->getData(true)['data'])->pluck('id')->all();
    });
}

/**
 * @param  array<string, mixed>  $arguments
 * @return array<int, int>
 */
function mcpToolIds(array $arguments): array
{
    $result = mcpTool(McpUser::granted(['batch']), QueryAdminResourceTool::class, ['resource' => 'mcp-batch', 'perPage' => 50, ...$arguments])->toolJson();

    return array_column($result['data'], 'id');
}

beforeEach(function () {
    foreach (['ALFA-3', 'BETA-1', 'ALFA-1', 'GAMA-2', 'ALFA-2', 'BETA-2'] as $index => $reference) {
        McpBatch::create([
            'reference' => $reference,
            'is_open' => $index % 2 === 0,
            'created_at' => Carbon::parse('2026-04-01')->addDays($index),
        ]);
    }
});

it('matches the panel with the default sort', function () {
    expect(mcpToolIds([]))->toBe(mcpPanelIds(['perPage' => 50]));
});

it('matches the panel with a search', function () {
    expect(mcpToolIds(['search' => 'alfa']))->toBe(mcpPanelIds([app(McpBatchResource::class)->getSearchKey() => 'alfa', 'perPage' => 50]))
        ->and(mcpToolIds(['search' => 'alfa']))->toHaveCount(3);
});

it('matches the panel with a filter of an index field', function () {
    expect(mcpToolIds(['filters' => ['is_open' => true]]))->toBe(mcpPanelIds(['filters' => ['is_open' => true], 'perPage' => 50]));
});

it('matches the panel with a sort', function () {
    expect(mcpToolIds(['sortField' => 'reference', 'sortDirection' => 'asc']))
        ->toBe(mcpPanelIds(['sortField' => 'reference', 'sortDirection' => 'asc', 'perPage' => 50]));
});
