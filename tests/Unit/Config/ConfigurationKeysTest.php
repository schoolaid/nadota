<?php

/*
 * Cross-checks config/nadota.php against the keys src/ actually reads.
 *
 * The two "drift" tests pass today and fail as soon as someone declares a key
 * nobody reads, or reads a key the config does not declare, beyond the gaps
 * already logged (see docs/audit/configuration.md). The skipped tests assert the
 * fixed state of each logged gap and become regression tests once it is fixed.
 */

const AUD_CONFIG_KNOWN_UNREAD = [
    'path',                 // AUD-035
    'namespace',            // AUD-035
    'export.enabled',       // AUD-014
    'export.sync_limit',    // AUD-014
    'export.chunk_size',    // AUD-014
    'fields.text.component', // CORE-007 (Input reads fields.input.component)
    'fields.*.type',        // CORE-007 (types come from the FieldType enum)
];

const AUD_CONFIG_KNOWN_UNDECLARED = [
    'prefix',      // CORE-001 (RouteServiceProvider)
    'api_prefix',  // CORE-001 (ResourceExportable)
    'fields.array.component', 'fields.code.component', 'fields.color.component', 'fields.count.component',
    'fields.date.component', 'fields.dynamic.component', 'fields.exists.component', 'fields.hasOneThrough.component',
    'fields.html.component', 'fields.json.component', 'fields.rich_text.component', 'fields.signature.component',
    'fields.time.component', 'fields.variableText.component',
    'fields.file.max_size', 'fields.image.max_size', // CORE-007
];

/** @return array<int, string> every leaf key of config/nadota.php in dot notation (lists count as leaves) */
function audDeclaredConfigKeys(): array
{
    $flatten = function (array $values, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $keys = is_array($value) && $value !== [] && ! array_is_list($value)
                ? array_merge($keys, $flatten($value, $path))
                : array_merge($keys, [$path]);
        }

        return $keys;
    };

    return $flatten(require dirname(__DIR__, 3) . '/config/nadota.php');
}

/** @return array<int, string> every nadota.* key literal read through config()/safeConfig()/Config::get() in src/ */
function audConfigKeysReadBySrc(): array
{
    $keys = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        preg_match_all('/(?:config|safeConfig|Config::get)\(\s*[\'"]nadota\.([^\'"]+)[\'"]/', file_get_contents($file->getPathname()), $matches);
        foreach ($matches[1] as $key) {
            $keys[$key] = true;
        }
    }

    return array_keys($keys);
}

/** A key read as "nadota.filters.boolean.{$key}" matches any declared key in that position. */
function audConfigKeyMatches(string $readKey, string $declaredKey): bool
{
    $pattern = preg_replace('/\\\\\{\\\\\$\w+\\\\\}/', '[A-Za-z0-9_]+', preg_quote($readKey, '/'));

    return (bool) preg_match('/^' . $pattern . '$/', $declaredKey);
}

/** @return array<int, string> */
function audUnreadDeclaredKeys(): array
{
    $reads = audConfigKeysReadBySrc();

    return array_values(array_filter(
        audDeclaredConfigKeys(),
        fn ($declared) => ! array_filter($reads, fn ($read) => audConfigKeyMatches($read, $declared))
    ));
}

/** @return array<int, string> */
function audUndeclaredReadKeys(): array
{
    $declared = audDeclaredConfigKeys();

    return array_values(array_filter(
        audConfigKeysReadBySrc(),
        fn ($read) => ! array_filter($declared, fn ($key) => audConfigKeyMatches($read, $key))
    ));
}

/** @param array<int, string> $keys */
function audWithoutKnown(array $keys, array $known): array
{
    return array_values(array_filter($keys, fn ($key) => ! array_filter($known, fn ($pattern) => fnmatch($pattern, $key))));
}

it('reads every key it declares, apart from the gaps already logged', function () {
    expect(audWithoutKnown(audUnreadDeclaredKeys(), AUD_CONFIG_KNOWN_UNREAD))->toBe([]);
});

it('declares every key it reads, apart from the gaps already logged', function () {
    expect(audWithoutKnown(audUndeclaredReadKeys(), AUD_CONFIG_KNOWN_UNDECLARED))->toBe([]);
});

it('ships config component defaults identical to the fallbacks hard-coded in the field classes', function () {
    $config = require dirname(__DIR__, 3) . '/config/nadota.php';
    $fallbacks = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src/Http/Fields', FilesystemIterator::SKIP_DOTS)) as $file) {
        preg_match_all("/safeConfig\('nadota\.fields\.(\w+)\.component',\s*'(\w+)'\)/", file_get_contents($file->getPathname()), $matches, PREG_SET_ORDER);
        foreach ($matches as [, $field, $component]) {
            $fallbacks[$field] = $component;
        }
    }

    $declared = collect($config['fields'])->map(fn ($entry) => $entry['component'])->intersectByKeys($fallbacks)->sortKeys()->all();
    $expected = collect($fallbacks)->intersectByKeys($declared)->sortKeys()->all();

    expect($declared)->toHaveCount(30)
        ->and($declared)->toBe($expected);
});

it('declares the boolean filter labels that BooleanFilter reads dynamically', function () {
    $declared = audDeclaredConfigKeys();

    expect($declared)->toContain('filters.boolean.true_label', 'filters.boolean.false_label');
});

it('reads the export.enabled, export.sync_limit and export.chunk_size keys it declares', function () {
    expect(array_intersect(audUnreadDeclaredKeys(), ['export.enabled', 'export.sync_limit', 'export.chunk_size']))->toBe([]);
})->skip('ISSUE AUD-014: the three export keys are declared and documented but nothing reads them');

it('reads the path and namespace keys, or stops declaring them', function () {
    expect(array_intersect(audUnreadDeclaredKeys(), ['path', 'namespace']))->toBe([]);
})->skip('ISSUE AUD-035: path and namespace are declared and documented with a meaning, but never read');

it('reads every fields.* entry it declares', function () {
    expect(array_values(array_filter(audUnreadDeclaredKeys(), fn ($key) => str_starts_with($key, 'fields.'))))->toBe([]);
})->skip('ISSUE CORE-007: fields.*.type and fields.text are declared but never read');

it('declares a default for every field component and size the field classes read', function () {
    expect(array_values(array_filter(audUndeclaredReadKeys(), fn ($key) => str_starts_with($key, 'fields.'))))->toBe([]);
})->skip('ISSUE CORE-007: 14 components and 2 max sizes are read with a hard-coded fallback but not declared');

it('reads the API prefix from the single key it declares (api.prefix)', function () {
    expect(array_intersect(audUndeclaredReadKeys(), ['prefix', 'api_prefix']))->toBe([]);
})->skip('ISSUE CORE-001: routes read nadota.prefix and the export URL nadota.api_prefix, neither declared');
