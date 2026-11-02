<?php

use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphToMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\Attachments\BelongsToManyAttachmentService;
use SchoolAid\Nadota\Http\Services\Attachments\HasManyAttachmentService;
use SchoolAid\Nadota\Http\Services\Attachments\MorphToManyAttachmentService;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\Tag;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;

// MorphManyAttachmentService is not covered: the test models have no morphMany
// relation, and its tracking code is the same as HasManyAttachmentService's.

beforeEach(function () {
    config()->set('nadota.action_events.enabled', true);
    config()->set('nadota.action_events.queue', false);
    config()->set('nadota.action_events.dispatch_events', false);
    config()->set('nadota.action_events.system_user_id', null);
});

function attachmentRequest(array $data, bool $withResource = true): NadotaRequest
{
    $request = new NadotaRequest();
    $request->merge($data);

    if ($withResource) {
        $request->setResource(new TestResource());
    }

    return $request;
}

// ── BelongsToMany ────────────────────────────────────────────────────────────

it('records an attach event with the attached ids', function () {
    $model = TestModel::create(['name' => 'Parent']);
    $tag = Tag::create(['name' => 'T1']);

    (new BelongsToManyAttachmentService())->attach(
        attachmentRequest(['items' => [$tag->id]]),
        $model,
        BelongsToMany::make('Tags', 'simpleTags')
    );

    $event = ActionEvent::where('name', 'attach')->sole();

    expect($event->status)->toBe('finished')
        ->and($event->model_type)->toBe(TestModel::class)
        ->and((string) $event->model_id)->toBe((string) $model->id)
        ->and($event->actionable_type)->toBe(TestResource::class)
        ->and($event->fields)->toBe(['relation' => 'simpleTags'])
        ->and($event->changes)->toEqual(['attached' => [$tag->id]])
        ->and($event->original)->toBeNull();
});

it('records only the ids that were not already attached', function () {
    $model = TestModel::create(['name' => 'Parent']);
    [$attached, $fresh] = [Tag::create(['name' => 'T1']), Tag::create(['name' => 'T2'])];
    $model->simpleTags()->attach($attached->id);

    (new BelongsToManyAttachmentService())->attach(
        attachmentRequest(['items' => [$attached->id, $fresh->id]]),
        $model,
        BelongsToMany::make('Tags', 'simpleTags')
    );

    expect(ActionEvent::where('name', 'attach')->sole()->changes)
        ->toEqual(['attached' => [$fresh->id]]);
});

it('records a detach event with only the ids that were attached', function () {
    $model = TestModel::create(['name' => 'Parent']);
    [$attached, $other] = [Tag::create(['name' => 'T1']), Tag::create(['name' => 'T2'])];
    $model->simpleTags()->attach($attached->id);

    (new BelongsToManyAttachmentService())->detach(
        attachmentRequest(['items' => [$attached->id, $other->id, 999]]),
        $model,
        BelongsToMany::make('Tags', 'simpleTags')
    );

    $event = ActionEvent::where('name', 'detach')->sole();

    expect($event->fields)->toBe(['relation' => 'simpleTags'])
        ->and($event->changes)->toEqual(['detached' => [$attached->id]])
        ->and($model->simpleTags()->count())->toBe(0);
});

it('records a sync event with the changes and the ids attached before', function () {
    $model = TestModel::create(['name' => 'Parent']);
    [$kept, $removed, $added] = [
        Tag::create(['name' => 'Kept']),
        Tag::create(['name' => 'Removed']),
        Tag::create(['name' => 'Added']),
    ];
    $model->simpleTags()->attach([$kept->id, $removed->id]);

    (new BelongsToManyAttachmentService())->sync(
        attachmentRequest(['items' => [$kept->id, $added->id]]),
        $model,
        BelongsToMany::make('Tags', 'simpleTags')
    );

    $event = ActionEvent::where('name', 'sync')->sole();

    expect($event->changes)->toEqual([
            'attached' => [$added->id],
            'detached' => [$removed->id],
            'updated' => [],
        ])
        ->and($event->original['attached_before'])->toEqualCanonicalizing([$kept->id, $removed->id]);
});

// ── MorphToMany ──────────────────────────────────────────────────────────────

it('records an attach event on a MorphToMany relation', function () {
    $model = TestModel::create(['name' => 'Parent']);
    $tag = Tag::create(['name' => 'T1']);

    (new MorphToManyAttachmentService())->attach(
        attachmentRequest(['items' => [$tag->id]]),
        $model,
        MorphToMany::make('Tags', 'tags')
    );

    $event = ActionEvent::where('name', 'attach')->sole();

    expect($event->fields)->toBe(['relation' => 'tags'])
        ->and($event->changes)->toEqual(['attached' => [$tag->id]])
        ->and($model->tags()->pluck('tags.id')->all())->toBe([$tag->id]);
});

// ── HasMany ──────────────────────────────────────────────────────────────────

it('records an attach event on a HasMany relation', function () {
    $model = TestModel::create(['name' => 'Parent']);
    $related = RelatedModel::create(['title' => 'Orphan']);

    (new HasManyAttachmentService())->attach(
        attachmentRequest(['items' => [$related->id]]),
        $model,
        HasMany::make('Related', 'relatedModels')
    );

    $event = ActionEvent::where('name', 'attach')->sole();

    expect($event->fields)->toBe(['relation' => 'relatedModels'])
        ->and($event->changes)->toEqual(['attached' => [$related->id]])
        ->and($related->fresh()->test_model_id)->toBe($model->id);
});

it('records only the ids that belonged to the HasMany relation on detach', function () {
    $model = TestModel::create(['name' => 'Parent']);
    $other = TestModel::create(['name' => 'Other parent']);
    $owned = RelatedModel::create(['title' => 'Owned', 'test_model_id' => $model->id]);
    $foreign = RelatedModel::create(['title' => 'Foreign', 'test_model_id' => $other->id]);

    (new HasManyAttachmentService())->detach(
        attachmentRequest(['items' => [$owned->id, $foreign->id, 999]]),
        $model,
        HasMany::make('Related', 'relatedModels')
    );

    expect(ActionEvent::where('name', 'detach')->sole()->changes)
        ->toEqual(['detached' => [$owned->id]])
        ->and($owned->fresh()->test_model_id)->toBeNull()
        ->and($foreign->fresh()->test_model_id)->toBe($other->id);
});

// ── Guards ───────────────────────────────────────────────────────────────────

it('records nothing when action events are disabled', function () {
    config()->set('nadota.action_events.enabled', false);

    $model = TestModel::create(['name' => 'Parent']);
    $tag = Tag::create(['name' => 'T1']);

    (new BelongsToManyAttachmentService())->attach(
        attachmentRequest(['items' => [$tag->id]]),
        $model,
        BelongsToMany::make('Tags', 'simpleTags')
    );

    $this->assertDatabaseCount('action_events', 0);
    expect($model->simpleTags()->count())->toBe(1);
});

it('still attaches but records nothing when the request has no resource', function () {
    $model = TestModel::create(['name' => 'Parent']);
    $tag = Tag::create(['name' => 'T1']);

    $response = (new BelongsToManyAttachmentService())->attach(
        attachmentRequest(['items' => [$tag->id]], withResource: false),
        $model,
        BelongsToMany::make('Tags', 'simpleTags')
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($model->simpleTags()->count())->toBe(1);
    $this->assertDatabaseCount('action_events', 0);
});
