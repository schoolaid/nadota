<?php

use Illuminate\Contracts\Support\Arrayable;
use SchoolAid\Nadota\Http\Actions\ActionResponse;

it('builds a message response with only type and message', function () {
    $response = ActionResponse::message('Done');

    expect($response->toArray())->toBe(['type' => 'message', 'message' => 'Done'])
        ->and($response->getType())->toBe('message')
        ->and($response->getMessage())->toBe('Done')
        ->and($response->getUrl())->toBeNull()
        ->and($response->getFilename())->toBeNull()
        ->and($response->shouldOpenInNewTab())->toBeFalse()
        ->and($response->getData())->toBe([]);
});

it('builds a danger response', function () {
    expect(ActionResponse::danger('Nope')->toArray())->toBe(['type' => 'danger', 'message' => 'Nope']);
});

it('builds a redirect response with only type and url', function () {
    expect(ActionResponse::redirect('/students/1')->toArray())->toBe(['type' => 'redirect', 'url' => '/students/1']);
});

it('builds a download response with url and filename', function () {
    expect(ActionResponse::download('/storage/report.csv', 'report.csv')->toArray())
        ->toBe(['type' => 'download', 'url' => '/storage/report.csv', 'filename' => 'report.csv']);
});

it('builds an openInNewTab response with the flag set', function () {
    $response = ActionResponse::openInNewTab('https://example.com');

    expect($response->toArray())->toBe(['type' => 'openInNewTab', 'url' => 'https://example.com', 'openInNewTab' => true])
        ->and($response->shouldOpenInNewTab())->toBeTrue();
});

it('merges extra data across withData() calls and emits it under data', function () {
    $response = ActionResponse::message('Done')->withData(['processed' => 1])->withData(['skipped' => 2, 'processed' => 3]);

    expect($response->getData())->toBe(['processed' => 3, 'skipped' => 2])
        ->and($response->toArray()['data'])->toBe(['processed' => 3, 'skipped' => 2]);
});

it('omits data when it is empty', function () {
    expect(ActionResponse::message('Done')->withData([])->toArray())->not->toHaveKey('data');
});

it('accepts any type through the constructor', function () {
    expect((new ActionResponse('custom'))->toArray())->toBe(['type' => 'custom']);
});

it('is Arrayable and serializes to JSON like toArray()', function () {
    $response = ActionResponse::download('/f.csv', 'f.csv')->withData(['rows' => 2]);

    expect($response)->toBeInstanceOf(Arrayable::class)
        ->and($response)->toBeInstanceOf(JsonSerializable::class)
        ->and(json_decode(json_encode($response), true))->toBe($response->toArray());
});
