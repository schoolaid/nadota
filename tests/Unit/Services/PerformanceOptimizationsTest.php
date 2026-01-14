<?php

use SchoolAid\Nadota\Http\Services\FieldOptions\OptionsCache;

it('generates consistent cache keys', function () {
    $key1 = OptionsCache::generateKey('App\Resources\UserResource', 'role', [
        'search' => 'test',
        'limit' => 10,
    ]);

    $key2 = OptionsCache::generateKey('App\Resources\UserResource', 'role', [
        'search' => 'test',
        'limit' => 10,
    ]);

    expect($key1)->toBe($key2);
});

it('generates different keys for different parameters', function () {
    $key1 = OptionsCache::generateKey('App\Resources\UserResource', 'role', [
        'search' => 'test',
    ]);

    $key2 = OptionsCache::generateKey('App\Resources\UserResource', 'role', [
        'search' => 'different',
    ]);

    expect($key1)->not->toBe($key2);
});

it('normalizes empty parameters in cache key', function () {
    $key1 = OptionsCache::generateKey('App\Resources\UserResource', 'role', [
        'search' => '',
        'limit' => 10,
    ]);

    $key2 = OptionsCache::generateKey('App\Resources\UserResource', 'role', [
        'limit' => 10,
    ]);

    expect($key1)->toBe($key2);
});

it('caches callback results', function () {
    $callCount = 0;
    $callback = function () use (&$callCount) {
        $callCount++;
        return ['result' => 'data'];
    };

    $key = 'test_key_' . uniqid();

    // First call should execute callback
    $result1 = OptionsCache::remember($key, $callback, 60);
    expect($callCount)->toBe(1);
    expect($result1)->toBe(['result' => 'data']);

    // Second call should use cache
    $result2 = OptionsCache::remember($key, $callback, 60);
    expect($callCount)->toBe(1); // Callback not called again
    expect($result2)->toBe(['result' => 'data']);
});

it('bypasses cache when TTL is null', function () {
    $callCount = 0;
    $callback = function () use (&$callCount) {
        $callCount++;
        return ['result' => 'data'];
    };

    $key = 'test_key_' . uniqid();

    // First call
    OptionsCache::remember($key, $callback, null);
    expect($callCount)->toBe(1);

    // Second call should execute callback again (no caching)
    OptionsCache::remember($key, $callback, null);
    expect($callCount)->toBe(2);
});

it('bypasses cache when TTL is zero', function () {
    $callCount = 0;
    $callback = function () use (&$callCount) {
        $callCount++;
        return ['result' => 'data'];
    };

    $key = 'test_key_' . uniqid();

    // Both calls should execute callback
    OptionsCache::remember($key, $callback, 0);
    OptionsCache::remember($key, $callback, 0);
    
    expect($callCount)->toBe(2);
});
