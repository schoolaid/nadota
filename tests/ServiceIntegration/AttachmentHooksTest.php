<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\Attachments\BelongsToManyAttachmentService;
use SchoolAid\Nadota\Http\Services\Attachments\HasManyAttachmentService;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\Tag;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;

beforeEach(function () {
    config()->set('nadota.action_events.enabled', true);
    config()->set('nadota.action_events.queue', false);
    config()->set('nadota.action_events.dispatch_events', false);
});

/**
 * A TestResource that records every attachment hook call and can be told to
 * throw from one of them.
 */
function hookedResource(?string $throwIn = null): TestResource
{
    return new class($throwIn) extends TestResource {
        public array $calls = [];

        public function __construct(private ?string $throwIn)
        {
            parent::__construct();
        }

        private function record(string $hook, Model $model, Field $field, array $payload): void
        {
            $this->calls[] = [$hook, $model->getKey(), $field->getRelation(), $payload];

            if ($hook === $this->throwIn) {
                throw ValidationException::withMessages(['items' => "Blocked in {$hook}"]);
            }
        }

        public function beforeAttach(Model $model, Field $field, array $ids, NadotaRequest $request): void { $this->record('beforeAttach', $model, $field, $ids); }
        public function afterAttach(Model $model, Field $field, array $ids, NadotaRequest $request): void { $this->record('afterAttach', $model, $field, $ids); }
        public function beforeDetach(Model $model, Field $field, array $ids, NadotaRequest $request): void { $this->record('beforeDetach', $model, $field, $ids); }
        public function afterDetach(Model $model, Field $field, array $ids, NadotaRequest $request): void { $this->record('afterDetach', $model, $field, $ids); }
        public function beforeSync(Model $model, Field $field, array $ids, NadotaRequest $request): void { $this->record('beforeSync', $model, $field, $ids); }
        public function afterSync(Model $model, Field $field, array $changes, NadotaRequest $request): void { $this->record('afterSync', $model, $field, $changes); }
    };
}

function hookRequest(TestResource $resource, array $items): NadotaRequest
{
    $request = new NadotaRequest();
    $request->merge(['items' => $items]);
    $request->setResource($resource);

    return $request;
}

it('calls beforeAttach and afterAttach with the ids being attached', function () {
    $model = TestModel::create(['name' => 'Parent']);
    [$attached, $fresh] = [Tag::create(['name' => 'T1']), Tag::create(['name' => 'T2'])];
    $model->simpleTags()->attach($attached->id);
    $resource = hookedResource();

    (new BelongsToManyAttachmentService())->attach(
        hookRequest($resource, [$attached->id, $fresh->id]),
        $model,
        BelongsToMany::make('Tags', 'simpleTags')
    );

    expect($resource->calls)->toBe([
        ['beforeAttach', $model->id, 'simpleTags', [$fresh->id]],
        ['afterAttach', $model->id, 'simpleTags', [$fresh->id]],
    ]);
});

it('calls beforeDetach and afterDetach with the ids that were attached', function () {
    $model = TestModel::create(['name' => 'Parent']);
    $owned = RelatedModel::create(['title' => 'Owned', 'test_model_id' => $model->id]);
    $resource = hookedResource();

    (new HasManyAttachmentService())->detach(
        hookRequest($resource, [$owned->id, 999]),
        $model,
        HasMany::make('Related', 'relatedModels')
    );

    expect($resource->calls)->toBe([
        ['beforeDetach', $model->id, 'relatedModels', [$owned->id]],
        ['afterDetach', $model->id, 'relatedModels', [$owned->id]],
    ]);
});

it('calls beforeSync with the requested ids and afterSync with the changes', function () {
    $model = TestModel::create(['name' => 'Parent']);
    [$kept, $removed, $added] = [Tag::create(['name' => 'K']), Tag::create(['name' => 'R']), Tag::create(['name' => 'A'])];
    $model->simpleTags()->attach([$kept->id, $removed->id]);
    $resource = hookedResource();

    (new BelongsToManyAttachmentService())->sync(
        hookRequest($resource, [$kept->id, $added->id]),
        $model,
        BelongsToMany::make('Tags', 'simpleTags')
    );

    expect($resource->calls)->toBe([
        ['beforeSync', $model->id, 'simpleTags', [$kept->id, $added->id]],
        ['afterSync', $model->id, 'simpleTags', ['attached' => [$added->id], 'detached' => [$removed->id], 'updated' => []]],
    ]);
});

it('aborts the attach when beforeAttach throws', function () {
    $model = TestModel::create(['name' => 'Parent']);
    $tag = Tag::create(['name' => 'T1']);

    expect(fn () => (new BelongsToManyAttachmentService())->attach(
        hookRequest(hookedResource('beforeAttach'), [$tag->id]),
        $model,
        BelongsToMany::make('Tags', 'simpleTags')
    ))->toThrow(ValidationException::class);

    expect($model->simpleTags()->count())->toBe(0);
    $this->assertDatabaseCount('action_events', 0);
});

it('rolls the attach back when afterAttach throws', function () {
    $model = TestModel::create(['name' => 'Parent']);
    $related = RelatedModel::create(['title' => 'Orphan']);

    expect(fn () => (new HasManyAttachmentService())->attach(
        hookRequest(hookedResource('afterAttach'), [$related->id]),
        $model,
        HasMany::make('Related', 'relatedModels')
    ))->toThrow(ValidationException::class);

    expect($related->fresh()->test_model_id)->toBeNull();
    $this->assertDatabaseCount('action_events', 0);
});

it('rolls the sync back when afterSync throws', function () {
    $model = TestModel::create(['name' => 'Parent']);
    [$old, $new] = [Tag::create(['name' => 'Old']), Tag::create(['name' => 'New'])];
    $model->simpleTags()->attach($old->id);

    expect(fn () => (new BelongsToManyAttachmentService())->sync(
        hookRequest(hookedResource('afterSync'), [$new->id]),
        $model,
        BelongsToMany::make('Tags', 'simpleTags')
    ))->toThrow(ValidationException::class);

    expect($model->simpleTags()->pluck('tags.id')->all())->toBe([$old->id]);
});
