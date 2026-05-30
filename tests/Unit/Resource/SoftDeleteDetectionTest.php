<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * Covers how Resource::getUseSoftDeletes() resolves its value:
 *  - null (default) autodetects from the model's SoftDeletes trait,
 *  - an explicit true/false on the resource overrides the autodetection.
 */

class SoftDeletableDetectionModel extends Model
{
    use SoftDeletes;

    protected $table = 'soft_deletable_detection_models';

    protected $fillable = ['name'];
}

function makeResourceFor(string $modelClass, ?bool $usesSoftDeletes): Resource
{
    return new class($modelClass, $usesSoftDeletes) extends Resource
    {
        public function __construct(string $modelClass, ?bool $usesSoftDeletes)
        {
            $this->model = $modelClass;
            $this->usesSoftDeletes = $usesSoftDeletes;
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            return [];
        }
    };
}

it('autodetects soft deletes from a model using the trait', function () {
    $resource = makeResourceFor(SoftDeletableDetectionModel::class, null);

    expect($resource->getUseSoftDeletes())->toBeTrue()
        ->and($resource->usesSoftDeletes())->toBeTrue();
});

it('autodetects no soft deletes from a model without the trait', function () {
    $resource = makeResourceFor(TestModel::class, null);

    expect($resource->getUseSoftDeletes())->toBeFalse()
        ->and($resource->usesSoftDeletes())->toBeFalse();
});

it('lets an explicit false override a model that uses the trait', function () {
    $resource = makeResourceFor(SoftDeletableDetectionModel::class, false);

    expect($resource->getUseSoftDeletes())->toBeFalse();
});

it('lets an explicit true override a model without the trait', function () {
    $resource = makeResourceFor(TestModel::class, true);

    expect($resource->getUseSoftDeletes())->toBeTrue();
});
