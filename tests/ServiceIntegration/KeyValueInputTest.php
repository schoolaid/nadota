<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\KeyValue;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * KeyValue::fill() never ran (FLD-002): the persist service assigned the input as is. The form
 * sends an empty KeyValue as '{}', which an `array` cast stored as the string "{}" (and JSON text
 * encoded twice); readonly and hidden keys could be overwritten and keys outside the schema kept.
 */
function makeKeyValueResource(?callable $configure = null): Resource
{
    return new class($configure) extends Resource
    {
        public string $model = TestModel::class;

        public function __construct(private $configure)
        {
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            $field = KeyValue::make('Metadata', 'metadata')->nullable();

            return [Input::make('Name', 'name')->required(), $this->configure ? ($this->configure)($field) : $field];
        }
    };
}

function keyValueRequest(array $input, ?callable $configure = null): NadotaRequest
{
    $request = new NadotaRequest();
    $request->merge($input);
    $request->setResource(makeKeyValueResource($configure));

    return $request;
}

it('stores an emptied KeyValue as null, not as the string "{}"', function (string $empty) {
    $model = TestModel::create(['name' => 'Original', 'metadata' => ['si' => 'Sí']]);

    $response = (new ResourceUpdateService())->handle(keyValueRequest(['name' => 'Original', 'metadata' => $empty]), $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and(DB::table('test_models')->where('id', $model->id)->value('metadata'))->toBeNull();
})->with(['{}', '[]', '']);

it('stores the pairs once, sent as fields or as JSON text', function (mixed $sent) {
    $response = (new ResourceStoreService())->handle(keyValueRequest(['name' => 'New', 'metadata' => $sent]));

    expect($response->getStatusCode())->toBe(201)
        ->and(TestModel::where('name', 'New')->first()->metadata)->toBe(['si' => 'Sí']);
})->with(['fields' => [['si' => 'Sí']], 'JSON text' => ['{"si":"Sí"}']]);

it('keeps readonly keys and drops keys outside the schema', function () {
    $configure = fn (KeyValue $field) => $field->schema(['theme' => ['type' => 'text'], 'locale' => ['type' => 'text']])->readonlyKeys('locale');
    $model = TestModel::create(['name' => 'Original', 'metadata' => ['theme' => 'light', 'locale' => 'es']]);

    $response = (new ResourceUpdateService())->handle(keyValueRequest([
        'name' => 'Original',
        'metadata' => ['theme' => 'dark', 'locale' => 'en', 'admin' => '1'],
    ], $configure), $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and($model->fresh()->metadata)->toBe(['theme' => 'dark', 'locale' => 'es']);
});

it('leaves the stored pairs alone when the request does not send the key', function () {
    $model = TestModel::create(['name' => 'Original', 'metadata' => ['si' => 'Sí']]);

    $response = (new ResourceUpdateService())->handle(keyValueRequest(['name' => 'Renamed']), $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and($model->fresh()->metadata)->toBe(['si' => 'Sí']);
});
