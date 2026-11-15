<?php

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Json;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * The form submits a Json field as text (multipart). The persist service used to assign that
 * text to the attribute as is: an `array` cast encoded it a second time, so the model read back a
 * string (and Json::resolve() hid it by decoding again for the panel); rules saw the text, not the
 * structure; and text that is not JSON was saved over the previous value. Now Json decodes the
 * text before validation (normalizeInput), rejects text that is not JSON, and stores the decoded
 * value once.
 */
function makeJsonResource(bool $required = false): Resource
{
    return new class($required) extends Resource
    {
        public string $model = TestModel::class;

        public function __construct(private bool $required)
        {
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            // Like marketaid's webhook endpoint: the url rule reads `metadata.allow_http`.
            $allowsHttp = new class implements DataAwareRule, ValidationRule
            {
                private array $data = [];

                public function setData(array $data): static
                {
                    $this->data = $data;

                    return $this;
                }

                public function validate(string $attribute, mixed $value, Closure $fail): void
                {
                    $metadata = $this->data['metadata'] ?? null;
                    $allowed = is_array($metadata) && ($metadata['allow_http'] ?? null) === true;

                    if (str_starts_with((string) $value, 'http:') && ! $allowed) {
                        $fail('https required');
                    }
                }
            };

            $metadata = Json::make('Metadata', 'metadata');

            return [
                Input::make('Name', 'name')->required()->rules([$allowsHttp]),
                $this->required ? $metadata->required() : $metadata->nullable(),
                Json::make('Description', 'description')->nullable(),
            ];
        }
    };
}

function jsonRequest(array $input, bool $required = false): NadotaRequest
{
    $request = new NadotaRequest();
    $request->merge($input);
    $request->setResource(makeJsonResource($required));

    return $request;
}

function rawColumn(int $id, string $column): mixed
{
    return DB::table('test_models')->where('id', $id)->value($column);
}

it('stores the JSON text of an array-cast attribute once, not as an encoded string', function () {
    $response = (new ResourceStoreService())->handle(jsonRequest([
        'name' => 'New',
        'metadata' => "{\r\n  \"allow_http\": true,\r\n  \"skip\": [3]\r\n}",
    ]));

    $model = TestModel::where('name', 'New')->first();

    expect($response->getStatusCode())->toBe(201)
        ->and($model->metadata)->toBe(['allow_http' => true, 'skip' => [3]])
        ->and(json_decode(rawColumn($model->id, 'metadata'), true))->toBe(['allow_http' => true, 'skip' => [3]]);
});

it('lets data-aware rules read the decoded structure', function () {
    $response = (new ResourceStoreService())->handle(jsonRequest([
        'name' => 'http://example.com',
        'metadata' => '{"allow_http": true}',
    ]));

    expect($response->getStatusCode())->toBe(201);
});

it('rejects text that is not JSON and keeps the stored value', function (string $text) {
    $model = TestModel::create(['name' => 'Original', 'metadata' => ['keep' => true]]);

    $response = (new ResourceUpdateService())->handle(jsonRequest(['name' => 'Original', 'metadata' => $text]), $model->id);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey('metadata')
        ->and($model->fresh()->metadata)->toBe(['keep' => true]);
})->with(['truncated object' => '{"allow_http": tru', 'bare word' => 'hola']);

it('clears the attribute with empty text', function () {
    $model = TestModel::create(['name' => 'Original', 'metadata' => ['keep' => true]]);

    $response = (new ResourceUpdateService())->handle(jsonRequest(['name' => 'Original', 'metadata' => '  ']), $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and(rawColumn($model->id, 'metadata'))->toBeNull();
});

it('still fails `required` with empty text', function () {
    $response = (new ResourceStoreService())->handle(jsonRequest(['name' => 'New', 'metadata' => ''], required: true));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey('metadata');
});

it('accepts JSON scalars and lists', function (string $text, mixed $stored) {
    $model = TestModel::create(['name' => 'Original']);

    $response = (new ResourceUpdateService())->handle(jsonRequest(['name' => 'Original', 'metadata' => $text]), $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and($model->fresh()->metadata)->toBe($stored);
})->with([
    'list' => ['[1, 2]', [1, 2]],
    'string literal' => ['"hola"', 'hola'],
    'number' => ['5', 5],
]);

it('stores JSON text in a column without a cast', function () {
    $model = TestModel::create(['name' => 'Original']);

    $response = (new ResourceUpdateService())->handle(jsonRequest([
        'name' => 'Original',
        'description' => "{\n  \"a\": \"ñ/1\"\n}",
    ]), $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and(rawColumn($model->id, 'description'))->toBe('{"a":"ñ/1"}');
});

it('accepts an already decoded value (JSON request body)', function () {
    $response = (new ResourceStoreService())->handle(jsonRequest(['name' => 'New', 'metadata' => ['a' => 1]]));

    expect($response->getStatusCode())->toBe(201)
        ->and(TestModel::where('name', 'New')->first()->metadata)->toBe(['a' => 1]);
});

it('does not touch the attribute when the input is not sent', function () {
    $model = TestModel::create(['name' => 'Original', 'metadata' => ['keep' => true]]);

    $response = (new ResourceUpdateService())->handle(jsonRequest(['name' => 'Renamed']), $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and($model->fresh()->metadata)->toBe(['keep' => true]);
});

it('sends only string rules to the frontend', function () {
    $field = Json::make('Metadata', 'metadata')->nullable();

    expect($field->toArray(new NadotaRequest())['rules'])->toBe(['nullable']);
});

it('fill() decodes the text once and leaves the attribute alone when the key is not sent', function () {
    $field = Json::make('Metadata', 'metadata');
    $model = new TestModel(['metadata' => ['keep' => true]]);

    $field->fill(new NadotaRequest(), $model);
    expect($model->metadata)->toBe(['keep' => true]);

    $request = new NadotaRequest();
    $request->merge(['metadata' => '{"a": 1}']);
    $field->fill($request, $model);
    expect($model->metadata)->toBe(['a' => 1])
        ->and($model->getAttributes()['metadata'])->toBe('{"a":1}');
});
