<?php

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use SchoolAid\Nadota\Mcp\ExposedResource;
use SchoolAid\Nadota\Mcp\Tools\QueryAdminResourceTool;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpBatch;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpOwner;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpUser;

/**
 * An app subclass with an extra argument, as MarketAid does with branchOfficeId.
 */
class McpLaneQueryTool extends QueryAdminResourceTool
{
    public static ?int $lane = null;

    protected function extraSchema(JsonSchema $schema): array
    {
        return ['lane' => $schema->integer()->description('Carril.')];
    }

    protected function extraRules(): array
    {
        return ['lane' => 'nullable|integer|min:1'];
    }

    protected function beforeQuery(Request $request, ExposedResource $exposed, array $validated): ?Response
    {
        if (($validated['lane'] ?? null) === 99) {
            return Response::error('Carril 99 fuera de alcance.');
        }

        self::$lane = $validated['lane'] ?? null;

        return null;
    }
}

/**
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>|null
 */
function mcpQuery(array $arguments, array $grants = ['batch', 'owner']): ?array
{
    return mcpTool(McpUser::granted($grants), QueryAdminResourceTool::class, ['resource' => 'mcp-batch', ...$arguments])->toolJson();
}

/**
 * @param  array<string, mixed>  $arguments
 */
function mcpQueryError(array $arguments): ?string
{
    return mcpTool(McpUser::granted(['batch', 'owner']), QueryAdminResourceTool::class, ['resource' => 'mcp-batch', ...$arguments])->toolError();
}

/**
 * @param  array<string, mixed>|null  $result
 * @return array<int, mixed>
 */
function mcpIds(?array $result): array
{
    return array_column($result['data'] ?? [], 'id');
}

it('pages 20 rows by default, newest first, with the pagination envelope', function () {
    foreach (range(1, 21) as $index) {
        McpBatch::create(['reference' => "R-{$index}", 'created_at' => Carbon::parse('2026-01-01')->addDays($index)]);
    }

    $first = mcpQuery([]);

    expect($first['pagination'])->toBe(['total' => 21, 'perPage' => 20, 'page' => 1, 'lastPage' => 2])
        ->and($first['data'])->toHaveCount(20)
        ->and($first['data'][0]['reference'])->toBe('R-21')
        ->and(mcpIds(mcpQuery(['page' => 2])))->toBe([1]);
});

it('caps perPage at 50 and rejects 0', function (int $perPage) {
    expect(mcpQueryError(['perPage' => $perPage]))->toBe('perPage va de 1 a 50.');
})->with([0, 51]);

it('breaks created_at ties by primary key, in the sort direction', function () {
    $at = Carbon::parse('2026-03-01 10:00:00');

    foreach (range(1, 5) as $index) {
        McpBatch::create(['reference' => "T-{$index}", 'created_at' => $at]);
    }

    expect(mcpIds(mcpQuery(['perPage' => 2])))->toBe([5, 4])
        ->and(mcpIds(mcpQuery(['perPage' => 2, 'page' => 2])))->toBe([3, 2])
        ->and(mcpIds(mcpQuery(['sortField' => 'created_at', 'sortDirection' => 'asc', 'perPage' => 2])))->toBe([1, 2]);
});

it('projects index fields only, with money, enums, booleans and ISO dates', function () {
    McpBatch::create([
        'reference' => 'P-1', 'total' => '1234.50', 'currency_code' => 'USD', 'status' => 'processed',
        'is_open' => true, 'notes' => 'solo en el detalle', 'xml' => '<x/>', 'api_key' => 'sk-123',
        'closed_on' => '2026-02-03', 'starts_at' => '07:15:00', 'created_at' => '2026-02-03 08:30:00',
    ]);

    $row = mcpQuery([])['data'][0];

    expect($row)->toBe([
        'id' => 1,
        'reference' => 'P-1',
        'total' => ['amount' => 1234.5, 'currency' => 'USD'],
        'status' => ['value' => 'processed', 'label' => 'Procesado'],
        'is_open' => true,
        'closed_on' => '2026-02-03',
        'starts_at' => '07:15:00',
        'created_at' => Carbon::parse('2026-02-03 08:30:00')->toIso8601String(),
    ]);
});

it('never projects a blocked type or a forbidden attribute, even when the entry lists them', function () {
    config()->set('nadota.mcp.resources.mcp-batch.fields', ['reference', 'xml', 'api_key']);
    McpBatch::create(['reference' => 'S-1', 'xml' => '<secret/>', 'api_key' => 'sk-live']);

    $row = mcpQuery([])['data'][0];

    expect($row)->toBe(['id' => 1, 'reference' => 'S-1'])
        ->and(json_encode(mcpQuery([])))->not->toContain('sk-live')->not->toContain('secret');
});

it('falls back to the default currency when the row has none', function () {
    McpBatch::create(['reference' => 'C-1', 'total' => 5]);

    expect(mcpQuery([])['data'][0]['total'])->toEqual(['amount' => 5, 'currency' => 'GTQ']);
});

it('computes money with the model methods', function () {
    config()->set('nadota.mcp.resources.mcp-batch.money', ['total' => ['amount' => 'computedAmount', 'currency' => 'computedCurrency']]);
    McpBatch::create(['reference' => 'M-1', 'total' => 7, 'currency_code' => 'USD']);

    expect(mcpQuery([])['data'][0]['total'])->toEqual(['amount' => 14, 'currency' => 'EUR']);
});

it('searches the searchable attributes', function () {
    McpBatch::create(['reference' => 'ALFA-1']);
    McpBatch::create(['reference' => 'BETA-1']);

    expect(array_column(mcpQuery(['search' => 'alfa'])['data'], 'reference'))->toBe(['ALFA-1']);
});

it('rejects a search on a resource whose entry does not expose it', function () {
    config()->set('nadota.mcp.resources.mcp-batch.search', false);

    expect(mcpQueryError(['search' => 'x']))->toBe('mcp-batch no tiene búsqueda; usa filters (ver describe-admin-resource).');
});

it('rejects an unknown filter or sortField with the valid ones', function () {
    expect(mcpQueryError(['filters' => ['xml' => 'a']]))
        ->toBe('Filtro desconocido en mcp-batch: xml. Válidos: reference, status, is_open, owner_id, closed_on, created_at.')
        ->and(mcpQueryError(['sortField' => 'status']))
        ->toBe('sortField inválido en mcp-batch: status. Válidos: reference, total, created_at.');
});

it('rejects sortDirection without sortField', function () {
    expect(mcpQueryError(['sortDirection' => 'asc']))->toBe('sortDirection va con sortField (ver defaultSort en describe-admin-resource).');
});

it('filters a Status exactly, by one value or a list, and rejects a partial value', function () {
    McpBatch::create(['reference' => 'O', 'status' => 'open']);
    McpBatch::create(['reference' => 'P', 'status' => 'processed']);
    McpBatch::create(['reference' => 'S', 'status' => 'sealed']);

    expect(array_column(mcpQuery(['filters' => ['status' => 'open']])['data'], 'reference'))->toBe(['O'])
        ->and(array_column(mcpQuery(['filters' => ['status' => ['open', 'sealed']]])['data'], 'reference'))->toEqualCanonicalizing(['O', 'S'])
        ->and(mcpQueryError(['filters' => ['status' => 'proc']]))
        ->toBe('Valor inválido para el filtro status (oneOf): uno de: open, processed, sealed (o una lista de ellos).');
});

it('filters a boolean and rejects a value Nadota would ignore', function () {
    McpBatch::create(['reference' => 'Y', 'is_open' => true]);
    McpBatch::create(['reference' => 'N', 'is_open' => false]);

    expect(array_column(mcpQuery(['filters' => ['is_open' => true]])['data'], 'reference'))->toBe(['Y'])
        ->and(mcpQueryError(['filters' => ['is_open' => 'yes']]))->toBe('Valor inválido para el filtro is_open (boolean): true o false.');
});

it('filters by a detail-only relation through the foreign key', function () {
    $owner = McpOwner::create(['name' => 'Ana']);
    McpBatch::create(['reference' => 'A', 'owner_id' => $owner->id]);
    McpBatch::create(['reference' => 'B', 'owner_id' => $owner->id + 1]);

    expect(array_column(mcpQuery(['filters' => ['owner_id' => $owner->id]])['data'], 'reference'))->toBe(['A'])
        ->and(array_column(mcpQuery(['filters' => ['owner_id' => $owner->id + 1]])['data'], 'reference'))->toBe(['B'])
        ->and(mcpQueryError(['filters' => ['owner_id' => 0]]))->toBe('Valor inválido para el filtro owner_id (relationId): un id entero positivo.');
});

it('filters a date range by whole days, inclusive', function () {
    McpBatch::create(['reference' => 'D1', 'closed_on' => '2026-05-01']);
    McpBatch::create(['reference' => 'D2', 'closed_on' => '2026-05-15']);
    McpBatch::create(['reference' => 'D3', 'closed_on' => '2026-05-31']);

    expect(array_column(mcpQuery(['filters' => ['closed_on' => ['from' => '2026-05-15', 'to' => '2026-05-31']]])['data'], 'reference'))->toEqualCanonicalizing(['D2', 'D3'])
        ->and(array_column(mcpQuery(['filters' => ['closed_on' => ['to' => '2026-05-01']]])['data'], 'reference'))->toBe(['D1']);
});

it('rejects a malformed date range', function (mixed $value) {
    expect(mcpQueryError(['filters' => ['closed_on' => $value]]))->toStartWith('Valor inválido para el filtro closed_on (dateRange)');
})->with([
    'reversed' => [['from' => '2026-05-31', 'to' => '2026-05-01']],
    'empty' => [[]],
    'not a date' => [['from' => '2026-02-30']],
    'unknown key' => [['since' => '2026-05-01']],
    'a string' => ['2026-05-01'],
]);

it('lets an app subclass add an argument and stop the query before it runs', function () {
    McpBatch::create(['reference' => 'L-1']);
    $user = McpUser::granted(['batch']);

    expect(McpLaneQueryTool::class)->not->toBe(QueryAdminResourceTool::class);

    $ok = (new ReflectionClass(McpLaneQueryTool::class))->newInstance(...array_map(
        fn (ReflectionParameter $parameter) => app($parameter->getType()->getName()),
        (new ReflectionMethod(McpLaneQueryTool::class, '__construct'))->getParameters(),
    ));

    auth()->setUser($user);

    $response = $ok->handle(new Request(['resource' => 'mcp-batch', 'lane' => 3]));
    expect($response->isError())->toBeFalse()->and(McpLaneQueryTool::$lane)->toBe(3);

    $stopped = $ok->handle(new Request(['resource' => 'mcp-batch', 'lane' => 99]));
    expect($stopped->isError())->toBeTrue()->and((string) $stopped->content())->toBe('Carril 99 fuera de alcance.');

    expect(array_keys($ok->toArray()['inputSchema']['properties']))->toContain('lane');
});

it('shows a Time field as text and does not offer its date filter', function () {
    $describe = mcpTool(McpUser::granted(['batch']), SchoolAid\Nadota\Mcp\Tools\DescribeAdminResourceTool::class, ['resource' => 'mcp-batch'])->toolJson();

    expect(collect($describe['fields'])->firstWhere('key', 'starts_at')['type'])->toBe('time')
        ->and(array_column($describe['filters'], 'key'))->not->toContain('starts_at');
});
