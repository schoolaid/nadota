<?php

use Illuminate\Support\Facades\Lang;
use SchoolAid\Nadota\Mcp\Labels;
use SchoolAid\Nadota\Mcp\Tools\DescribeAdminResourceTool;
use SchoolAid\Nadota\Tests\Mcp\Fixtures\McpUser;

/*
| Labels that are translation keys of the panel's frontend: an app keeps them in one group
| (`global.` in SchoolAid), so the agent would get the bare key without the prefix.
*/

beforeEach(function (): void {
    Lang::addLines([
        'global.Reference' => 'Referencia',
        'global.Abierto' => 'Abierta',
        'global.nested.key' => 'Anidada',
    ], app()->getLocale());
});

it('tries each prefix that has the key, then the bare key, then the label as written', function () {
    $labels = app(Labels::class);

    expect($labels->translate('Reference'))->toBe('Reference');

    config()->set('nadota.mcp.translation_prefixes', ['missing.', 'global.']);

    expect($labels->translate('Reference'))->toBe('Referencia')
        ->and($labels->translate('nested.key'))->toBe('Anidada')
        ->and($labels->translate('Total'))->toBe('Total')
        ->and($labels->translate(''))->toBe('');
});

it('never hands over a translation group as a label', function () {
    config()->set('nadota.mcp.translation_prefixes', ['global.']);

    expect(app(Labels::class)->translate('global'))->toBe('global')
        ->and(app(Labels::class)->translate('nested'))->toBe('nested');
});

it('translates the field and option labels describe-admin-resource returns', function () {
    config()->set('nadota.mcp.translation_prefixes', ['global.']);

    $describe = mcpTool(McpUser::granted(['batch']), DescribeAdminResourceTool::class, ['resource' => 'mcp-batch'])->toolJson();
    $fields = collect($describe['fields'])->keyBy('key');

    expect($fields['reference']['label'])->toBe('Referencia')
        ->and(collect($fields['status']['options'])->firstWhere('value', 'open')['label'])->toBe('Abierta')
        ->and($describe['search']['fields'])->toBe(['Referencia']);
});
