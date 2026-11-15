<?php

use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Code;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * A Code field over a JSON-cast attribute (marketaid's registration-request `data`) got the form's
 * text assigned as is: the `array` cast encoded it a second time and the model read back a string.
 * Code now decodes the text before validation when the model casts the attribute to JSON (like
 * Json), and leaves plain code (XML, PHP…) in a text column untouched.
 */
function makeCodeResource(): Resource
{
    return new class extends Resource
    {
        public string $model = TestModel::class;

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name')->required(),
                Code::make('Metadata', 'metadata')->json()->nullable(),
                Code::make('Description', 'description')->xml()->nullable(),
            ];
        }
    };
}

function codeRequest(array $input): NadotaRequest
{
    $request = new NadotaRequest();
    $request->merge($input);
    $request->setResource(makeCodeResource());

    return $request;
}

it('stores the JSON text of an array-cast attribute once', function () {
    $model = TestModel::create(['name' => 'Original', 'metadata' => ['first_name' => 'Edwin']]);

    $response = (new ResourceUpdateService())->handle(codeRequest(['name' => 'Original', 'metadata' => "{\n  \"first_name\": \"Ana\"\n}"]), $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and($model->fresh()->metadata)->toBe(['first_name' => 'Ana'])
        ->and(json_decode(DB::table('test_models')->where('id', $model->id)->value('metadata'), true))->toBe(['first_name' => 'Ana']);
});

it('rejects text that is not JSON for an array-cast attribute and keeps the stored value', function (string $text) {
    $model = TestModel::create(['name' => 'Original', 'metadata' => ['keep' => true]]);

    $response = (new ResourceUpdateService())->handle(codeRequest(['name' => 'Original', 'metadata' => $text]), $model->id);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey('metadata')
        ->and($model->fresh()->metadata)->toBe(['keep' => true]);
})->with(['what the old form sent' => '[object Object]', 'bare word' => 'hola']);

it('keeps plain code in a text column as text', function () {
    $xml = "<?xml version=\"1.0\"?>\n<dte>1</dte>";

    $response = (new ResourceStoreService())->handle(codeRequest(['name' => 'New', 'description' => $xml]));

    expect($response->getStatusCode())->toBe(201)
        ->and(TestModel::where('name', 'New')->value('description'))->toBe($xml);
});

it('does not decode outside a save (no model)', function () {
    expect(Code::make('Metadata', 'metadata')->json()->normalizeInput('{"a":1}'))->toBe('{"a":1}');
});
