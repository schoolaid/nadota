<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;
use SchoolAid\Nadota\Mcp\Tools\DescribeAdminResourceTool;
use SchoolAid\Nadota\Mcp\Tools\GetAdminRecordTool;
use SchoolAid\Nadota\Mcp\Tools\ListAdminResourcesTool;
use SchoolAid\Nadota\Mcp\Tools\QueryAdminResourceTool;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpUser;

dataset('mcp tools', [
    ListAdminResourcesTool::class,
    DescribeAdminResourceTool::class,
    QueryAdminResourceTool::class,
    GetAdminRecordTool::class,
]);

function mcpRegistersFor(string $toolClass, McpUser $user): bool
{
    auth()->setUser($user);

    /** @var Tool $tool */
    $tool = app($toolClass);

    return $tool->eligibleForRegistration();
}

it('hides every tool from a user who reads no listed resource', function (string $toolClass) {
    expect(mcpRegistersFor($toolClass, McpUser::granted([])))->toBeFalse();
})->with('mcp tools');

it('shows every tool to a user who reads one listed resource', function (string $toolClass) {
    expect(mcpRegistersFor($toolClass, McpUser::granted(['owner'])))->toBeTrue();
})->with('mcp tools');

it('hides every tool when nothing is listed, whatever the user may read', function (string $toolClass) {
    config()->set('nadota.mcp.resources', []);

    expect(mcpRegistersFor($toolClass, McpUser::granted(['batch', 'owner'])))->toBeFalse();
})->with('mcp tools');

it('refuses a stale call from a user without access, in the server and in the tool', function () {
    $user = McpUser::granted([]);

    expect(mcpTool($user, ListAdminResourcesTool::class)->toolError())->toBe('Tool [list-admin-resources] not found.');

    auth()->setUser($user);
    $response = app(ListAdminResourcesTool::class)->handle(new Request([]));

    expect($response->isError())->toBeTrue()
        ->and((string) $response->content())->toBe('Permission denied.');
});

it('lists the visible resources with their title and search flag', function () {
    config()->set('nadota.mcp.resources.mcp-owner.search', false);

    expect(mcpTool(McpUser::granted(['batch', 'owner']), ListAdminResourcesTool::class)->toolJson())->toBe([
        'resources' => [
            ['key' => 'mcp-batch', 'title' => 'Mcp Batch Resources', 'searchable' => true],
            ['key' => 'mcp-owner', 'title' => 'Mcp Owner Resources', 'searchable' => false],
        ],
    ]);
});

it('takes a description from config', function () {
    config()->set('nadota.mcp.descriptions.query-admin-resource', 'Texto propio de la app.');

    expect(app(QueryAdminResourceTool::class)->description())->toBe('Texto propio de la app.')
        ->and(app(GetAdminRecordTool::class)->description())->toStartWith('Un registro de un resource por id');
});

it('is read-only and idempotent', function (string $toolClass) {
    $annotations = app($toolClass)->annotations();

    expect($annotations['readOnlyHint'] ?? null)->toBeTrue()
        ->and($annotations['idempotentHint'] ?? null)->toBeTrue();
})->with('mcp tools');
