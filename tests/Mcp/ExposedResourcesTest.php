<?php

use SchoolAid\Nadota\Mcp\Contracts\ExposureRule;
use SchoolAid\Nadota\Mcp\ExposedResource;
use SchoolAid\Nadota\Mcp\ExposedResources;
use SchoolAid\Nadota\Mcp\Tools\DescribeAdminResourceTool;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpUser;

/**
 * Rejects every resource whose entry is flagged `blocked`, and counts its calls.
 */
class McpBlockedFlagRule implements ExposureRule
{
    public static int $calls = 0;

    /** @var array<int, array<string, mixed>> */
    public static array $entries = [];

    public function check(Resource $resource, array $entry): ?string
    {
        self::$calls++;
        self::$entries[] = $entry;

        return ($entry['blocked'] ?? false) ? 'flagged as blocked' : null;
    }
}

beforeEach(function () {
    McpBlockedFlagRule::$calls = 0;
    McpBlockedFlagRule::$entries = [];
});

function mcpExposed(): ExposedResources
{
    return app(ExposedResources::class);
}

it('resolves a listed resource with an explicit policy that allows the user', function () {
    $exposed = mcpExposed()->resolve(McpUser::granted(['batch']), 'mcp-batch');

    expect($exposed)->toBeInstanceOf(ExposedResource::class)
        ->and($exposed->key)->toBe('mcp-batch')
        ->and($exposed->search)->toBeTrue()
        ->and($exposed->money)->toBe(['total' => 'currency_code']);
});

it('refuses, the same way, everything that is not listed, open or allowed', function (array $resources, string $key, array $grants) {
    /*
     * mcp-owner stays visible to the caller below, so the tool registers and its answer is
     * the gatekeeper's.
     */
    config()->set('nadota.mcp.resources', [...$resources, 'mcp-owner' => ['fields' => ['name']]]);

    expect(mcpExposed()->resolve(McpUser::granted($grants), $key))->toBeNull();

    $error = mcpTool(McpUser::granted([...$grants, 'owner']), DescribeAdminResourceTool::class, ['resource' => $key])->toolError();

    expect($error)->toBe("Resource no encontrado o sin acceso: {$key}. Usa list-admin-resources para ver los disponibles.");
})->with([
    'a Nadota resource outside the list, with permission' => [[], 'mcp-batch', ['batch']],
    'a listed model without a policy' => [['mcp-loose' => ['fields' => ['name']]], 'mcp-loose', ['batch']],
    'a listed policy without view' => [['mcp-half' => ['fields' => ['name']]], 'mcp-half', ['batch']],
    'a listed resource whose policy denies the user' => [['mcp-batch' => ['fields' => ['reference']]], 'mcp-batch', []],
    'a listed key that is not a Nadota resource' => [['mcp-nothing' => ['fields' => ['name']]], 'mcp-nothing', ['batch']],
]);

it('refuses a guest', function () {
    expect(mcpExposed()->resolve(null, 'mcp-batch'))->toBeNull()
        ->and(mcpExposed()->visibleTo(null))->toBe([]);
});

it('applies the app rules after the explicit policy, with the entry', function () {
    config()->set('nadota.mcp.rules', [McpBlockedFlagRule::class]);
    config()->set('nadota.mcp.resources.mcp-owner.blocked', true);

    $user = McpUser::granted(['batch', 'owner']);

    expect(mcpExposed()->resolve($user, 'mcp-owner'))->toBeNull()
        ->and(mcpExposed()->resolve($user, 'mcp-batch'))->not->toBeNull()
        ->and(collect(McpBlockedFlagRule::$entries)->contains(fn (array $entry): bool => ($entry['blocked'] ?? false) === true))->toBeTrue();
});

it('does not run the app rules on a resource the explicit policy already refused', function () {
    config()->set('nadota.mcp.rules', [McpBlockedFlagRule::class]);
    config()->set('nadota.mcp.resources', ['mcp-loose' => ['fields' => ['name']]]);

    mcpExposed()->resolve(McpUser::granted(['batch']), 'mcp-loose');

    expect(McpBlockedFlagRule::$calls)->toBe(0);
});

it('lists only the resources the user may read', function () {
    $keys = fn (array $grants): array => array_map(
        fn (ExposedResource $exposed): string => $exposed->key,
        mcpExposed()->visibleTo(McpUser::granted($grants, id: count($grants) + 10)),
    );

    expect($keys(['batch']))->toBe(['mcp-batch'])
        ->and($keys(['batch', 'owner']))->toBe(['mcp-batch', 'mcp-owner'])
        ->and($keys([]))->toBe([]);
});

it('memoizes visibleTo per user and per list within a request', function () {
    config()->set('nadota.mcp.rules', [McpBlockedFlagRule::class]);
    $resources = mcpExposed();
    $user = McpUser::granted(['batch', 'owner']);

    $resources->visibleTo($user);
    $resources->visibleTo($user);
    expect(McpBlockedFlagRule::$calls)->toBe(2);

    $resources->visibleTo(McpUser::granted(['batch', 'owner'], id: 2));
    expect(McpBlockedFlagRule::$calls)->toBe(4);

    config()->set('nadota.mcp.resources.mcp-owner.blocked', true);
    expect(array_map(fn (ExposedResource $exposed): string => $exposed->key, $resources->visibleTo($user)))->toBe(['mcp-batch']);
});

it('is a fresh instance per request (scoped binding)', function () {
    $first = app(ExposedResources::class);

    expect(app(ExposedResources::class))->toBe($first);

    app()->forgetScopedInstances();

    expect(app(ExposedResources::class))->not->toBe($first);
});

it('drops forbidden attributes from fields and money even when an entry lists them', function () {
    config()->set('nadota.mcp.forbidden_attributes', ['reference']);
    config()->set('nadota.mcp.resources.mcp-batch.fields', ['reference', 'api_key', 'total']);
    config()->set('nadota.mcp.resources.mcp-batch.money', ['total' => 'currency_code', 'api_key' => 'default']);

    $exposed = mcpExposed()->resolve(McpUser::granted(['batch']), 'mcp-batch');

    expect($exposed->fields)->toBe(['total'])
        ->and($exposed->money)->toBe(['total' => 'currency_code']);
});
