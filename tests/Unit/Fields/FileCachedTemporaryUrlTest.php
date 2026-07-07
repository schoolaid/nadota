<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Tests\Models\TestModel;

beforeEach(function () {
    Carbon::setTestNow('2026-07-07 10:00:00');

    Storage::fake('s3')->buildTemporaryUrlsUsing(
        fn ($path, $expiration) => 'https://s3.test/'.$path.'?expires='.$expiration->getTimestamp()
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeCachedTemporaryUrlField(): File
{
    return File::make('Document', 'name')
        ->disk('s3')
        ->cachedTemporaryUrl();
}

it('serves the cached temporary url while the signature is still valid', function () {
    $field = makeCachedTemporaryUrlField();
    $model = TestModel::factory()->make(['name' => 'notes/featured-images/test.png']);
    $request = createNadotaRequest();

    $firstUrl = $field->resolve($request, $model, null)['url'];

    Carbon::setTestNow(now()->addMinutes(10));

    $secondUrl = $field->resolve($request, $model, null)['url'];

    expect($secondUrl)->toBe($firstUrl);
});

it('regenerates the temporary url instead of serving an expired signature', function () {
    $field = makeCachedTemporaryUrlField();
    $model = TestModel::factory()->make(['name' => 'notes/featured-images/test.png']);
    $request = createNadotaRequest();

    $firstUrl = $field->resolve($request, $model, null)['url'];

    Carbon::setTestNow(now()->addMinutes(31));

    $secondUrl = $field->resolve($request, $model, null)['url'];

    expect($secondUrl)->not->toBe($firstUrl);

    parse_str(parse_url($secondUrl, PHP_URL_QUERY), $query);
    expect((int) $query['expires'])->toBeGreaterThan(now()->getTimestamp());
});

it('namespaces the cache key instead of using the raw file path', function () {
    $field = makeCachedTemporaryUrlField();
    $model = TestModel::factory()->make(['name' => 'notes/featured-images/test.png']);
    $request = createNadotaRequest();

    $field->resolve($request, $model, null);

    expect(Cache::has('notes/featured-images/test.png'))->toBeFalse();
});
