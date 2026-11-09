<?php

use Illuminate\Support\Str;
use SchoolAid\Nadota\Mcp\Tools\GetAdminRecordTool;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpBatch;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpOwner;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpUser;

class McpStringIdRecordTool extends GetAdminRecordTool
{
    protected function usesStringIds(): bool
    {
        return true;
    }
}

function mcpRecord(int|string $id, array $grants = ['batch', 'owner']): Laravel\Mcp\Server\Testing\TestResponse
{
    return mcpTool(McpUser::granted($grants), GetAdminRecordTool::class, ['resource' => 'mcp-batch', 'id' => $id]);
}

it('returns the detail fields, the label and the relation as {id, label}', function () {
    $owner = McpOwner::create(['name' => 'Ana']);
    $batch = McpBatch::create(['reference' => 'G-1', 'owner_id' => $owner->id, 'notes' => 'Nota', 'xml' => '<x/>', 'api_key' => 'sk']);

    $record = mcpRecord($batch->id)->toolJson();

    expect($record['id'])->toBe($batch->id)
        ->and($record['label'])->toBe('Lote G-1')
        ->and($record['fields']['owner_id'])->toBe(['id' => $owner->id, 'label' => 'Ana'])
        ->and($record['fields']['notes'])->toBe('Nota')
        ->and($record['fields'])->not->toHaveKey('xml')
        ->and($record['fields'])->not->toHaveKey('api_key')
        ->and($record['fields'])->not->toHaveKey('id');
});

it('keeps the foreign key of a relation whose record is gone', function () {
    $batch = McpBatch::create(['reference' => 'G-2', 'owner_id' => 999]);

    expect(mcpRecord($batch->id)->toolJson()['fields']['owner_id'])->toBe(['id' => 999, 'label' => null]);
});

it('cuts long text to 1000 characters', function () {
    $batch = McpBatch::create(['reference' => 'G-3', 'notes' => Str::repeat('a', 1500)]);

    expect(mb_strlen(mcpRecord($batch->id)->toolJson()['fields']['notes']))->toBe(1001);
});

it('answers the same for a missing record and one the policy hides', function () {
    $sealed = McpBatch::create(['reference' => 'G-4', 'status' => 'sealed']);

    expect(mcpRecord($sealed->id)->toolError())->toBe("Registro no encontrado o sin acceso: mcp-batch #{$sealed->id}.")
        ->and(mcpRecord(404)->toolError())->toBe('Registro no encontrado o sin acceso: mcp-batch #404.');
});

it('takes positive integer ids unless a subclass uses string ids', function () {
    expect(mcpRecord(0)->toolError())->toBe('id debe ser un número entero positivo.')
        ->and(app(GetAdminRecordTool::class)->toArray()['inputSchema']['properties']['id']['type'])->toBe('integer')
        ->and(app(McpStringIdRecordTool::class)->toArray()['inputSchema']['properties']['id']['type'])->toBe('string');
});

it('does not eager load the relations of fields that do not leave, nor the show with', function () {
    $batch = McpBatch::create(['reference' => 'G-5']);

    expect(mcpRecord($batch->id)->toolJson()['fields'])->not->toHaveKey('logs');
});
