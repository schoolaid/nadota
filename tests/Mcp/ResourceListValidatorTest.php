<?php

use SchoolAid\Nadota\Mcp\Contracts\ExposureRule;
use SchoolAid\Nadota\Mcp\ResourceListValidator;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpUser;

class McpRejectOwnersRule implements ExposureRule
{
    public function check(Resource $resource, array $entry): ?string
    {
        return str_contains($resource::class, 'McpOwner') ? 'owners are not tenant-scoped' : null;
    }
}

/**
 * @return array<int, string>
 */
function mcpListProblems(bool $checkColumns = true): array
{
    return app(ResourceListValidator::class)->problems(McpUser::granted(['batch', 'owner']), $checkColumns);
}

/**
 * @param  array<string, mixed>  $entry
 * @return array<int, string>
 */
function mcpBatchProblems(array $entry, bool $checkColumns = true): array
{
    config()->set('nadota.mcp.resources', ['mcp-batch' => $entry]);

    return mcpListProblems($checkColumns);
}

it('passes the fixture list', function () {
    expect(mcpListProblems())->toBe([]);
});

it('reports a key that is not a resource, a missing policy and a missing view', function () {
    config()->set('nadota.mcp.resources', [
        'mcp-nothing' => ['fields' => ['name']],
        'mcp-loose' => ['fields' => ['name']],
        'mcp-half' => ['fields' => ['name']],
    ]);

    expect(mcpListProblems())->toBe([
        'mcp-nothing: not a Nadota resource',
        'mcp-loose: its model has no policy',
        'mcp-half: its policy does not define both viewAny and view',
    ]);
});

it('reports what an app rule rejects', function () {
    config()->set('nadota.mcp.rules', [McpRejectOwnersRule::class]);

    expect(mcpListProblems())->toBe(['mcp-owner: owners are not tenant-scoped']);
});

it('reports unknown, blocked and forbidden fields, and an empty list', function () {
    expect(mcpBatchProblems(['fields' => ['reference', 'refrence', 'xml', 'api_key']]))->toBe([
        'mcp-batch.refrence: not a field of the resource',
        'mcp-batch.xml: the type code is blocked',
        'mcp-batch.api_key: its attribute is forbidden',
    ])->and(mcpBatchProblems(['fields' => []]))->toBe(['mcp-batch: lists no fields']);
});

it('reports a Currency field without a currency', function () {
    expect(mcpBatchProblems(['fields' => ['reference', 'total']]))->toBe(['mcp-batch.total: is Currency and has no currency in money']);
});

it('reports money problems', function () {
    expect(mcpBatchProblems([
        'fields' => ['reference'],
        'money' => [
            'total' => 'currency_code',
            'phantom' => 'default',
            'notes' => 'no_such_column',
            'computed' => ['amount' => 'computedAmount', 'currency' => 'missingMethod'],
            'api_key' => 'default',
        ],
    ]))->toBe([
        'mcp-batch.total: is in money but not in fields',
        'mcp-batch.phantom: neither a field nor a column',
        'mcp-batch.notes: is in money but not in fields',
        'mcp-batch.notes: the currency column no_such_column does not exist',
        'mcp-batch.computed: the currency method does not exist on the model',
        'mcp-batch.api_key: its attribute is forbidden',
    ]);
});

it('skips the column checks when asked to', function () {
    expect(mcpBatchProblems(['fields' => ['reference'], 'money' => ['phantom' => 'no_such_column']], checkColumns: false))->toBe([]);
});

it('reports a search over an attribute that is not listed', function () {
    expect(mcpBatchProblems(['fields' => ['status'], 'search' => true]))->toBe(['mcp-batch: searches reference, which is not in fields']);
});

it('reports a hint of a field that is not listed', function () {
    expect(mcpBatchProblems(['fields' => ['reference'], 'hints' => ['reference' => 'ok', 'xml' => 'no']]))
        ->toBe(['mcp-batch.xml: hint of a field that is not listed']);
});

it('accepts a search over the primary key, which always leaves as id', function () {
    config()->set('nadota.mcp.resources', ['mcp-owner' => ['fields' => ['name'], 'search' => true]]);

    expect(mcpListProblems())->toBe([]);
});
