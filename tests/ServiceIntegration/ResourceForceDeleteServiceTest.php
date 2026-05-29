<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceForceDeleteService;
use SchoolAid\Nadota\Resource;

/**
 * Integration tests for ResourceForceDeleteService (permanent deletion).
 *
 * These lock in the contract that the force delete endpoint:
 *  - permanently removes a soft-deleted record (it disappears even from withTrashed),
 *  - records a `forceDelete` action event,
 *  - rejects resources that do not use soft deletes with a 400,
 *  - bubbles up a not-found error for unknown ids.
 *
 * They are also a regression guard for the previously missing
 * beforeForceDelete/performForceDelete/afterForceDelete hooks on Resource,
 * whose absence made the service fatal on every call.
 */

class ForceDeleteTestModel extends Model
{
    use SoftDeletes;

    protected $table = 'force_delete_test_models';

    protected $fillable = ['name'];
}

beforeEach(function () {
    Schema::create('force_delete_test_models', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->softDeletes();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('force_delete_test_models');
});

function makeForceDeleteResource(bool $usesSoftDeletes = true): Resource
{
    return new class($usesSoftDeletes) extends Resource
    {
        public string $model = ForceDeleteTestModel::class;

        public function __construct(bool $usesSoftDeletes)
        {
            $this->usesSoftDeletes = $usesSoftDeletes;
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name'),
            ];
        }
    };
}

function makeForceDeleteRequest(Resource $resource): NadotaRequest
{
    $request = new NadotaRequest();
    $request->setResource($resource);

    return $request;
}

it('permanently deletes a soft-deleted record', function () {
    $model = ForceDeleteTestModel::create(['name' => 'Doomed']);
    $model->delete();

    expect(ForceDeleteTestModel::withTrashed()->count())->toBe(1);

    $request = makeForceDeleteRequest(makeForceDeleteResource(true));

    $response = (new ResourceForceDeleteService())->handle($request, $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['message'])->toBe('Resource permanently deleted')
        ->and(ForceDeleteTestModel::withTrashed()->count())->toBe(0);
});

it('permanently deletes a record that was never soft-deleted', function () {
    $model = ForceDeleteTestModel::create(['name' => 'Alive']);

    $request = makeForceDeleteRequest(makeForceDeleteResource(true));

    $response = (new ResourceForceDeleteService())->handle($request, $model->id);

    expect($response->getStatusCode())->toBe(200)
        ->and(ForceDeleteTestModel::withTrashed()->count())->toBe(0);
});

it('records a forceDelete action event', function () {
    $model = ForceDeleteTestModel::create(['name' => 'Doomed']);
    $model->delete();

    $request = makeForceDeleteRequest(makeForceDeleteResource(true));

    (new ResourceForceDeleteService())->handle($request, $model->id);

    expect(DB::table('action_events')->where('name', 'forceDelete')->count())->toBe(1);
});

it('returns 400 when the resource does not use soft deletes', function () {
    $model = ForceDeleteTestModel::create(['name' => 'Keep']);

    $request = makeForceDeleteRequest(makeForceDeleteResource(false));

    $response = (new ResourceForceDeleteService())->handle($request, $model->id);

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getData(true)['message'])->toBe('This resource does not support force delete')
        ->and(ForceDeleteTestModel::withTrashed()->count())->toBe(1);
});

it('throws a not-found error when the record does not exist', function () {
    $request = makeForceDeleteRequest(makeForceDeleteResource(true));

    (new ResourceForceDeleteService())->handle($request, 999);
})->throws(ModelNotFoundException::class);
