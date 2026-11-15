<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Http\Controllers\AttachmentController;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceAuthorizationService;
use SchoolAid\Nadota\Tests\Models\Note;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\Tag;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Policies\UpdateOnlyTestModelPolicy;
use SchoolAid\Nadota\Tests\Resources\AttachableTestResource;
use SchoolAid\Nadota\Tests\Resources\HasManyAttachTestResource;

/*
 * Attach/detach on one-to-many fields (HasMany, MorphMany) write the owner columns of the child.
 * They used to be on by default and to take a child from any other record: a host app's
 * h2h-batch could pull orders out of another batch, a contract the paragraphs of another one.
 * Now (AttachesOwnedChildren):
 * - off by default (`->attachable()` turns it on);
 * - attach only adopts children without an owner (`->reassignable()` to take them from others),
 *   and the attachable list and attach use the same candidates;
 * - detach is not offered, and refused, when the owner columns are NOT NULL.
 *
 * Also: a morphMany declared as HasMany (host apps do this with `attachments`) got only the id
 * written, so the row of another model ended up pointing at a record of its own type; and without
 * a record (the create form), resolving attach/detach called the policy's `update` with the class.
 */
beforeEach(function () {
    app()->bind(ResourceAuthorizationInterface::class, ResourceAuthorizationService::class);
    Gate::policy(TestModel::class, UpdateOnlyTestModelPolicy::class);
    UpdateOnlyTestModelPolicy::$canUpdate = true;
    HasManyAttachTestResource::$attachable = true;
    HasManyAttachTestResource::$reassignable = false;
    HasMany::flushOrphanableCache();
    MorphMany::flushOrphanableCache();

    Schema::create('notes', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->nullableMorphs('notable');
        $table->timestamps();
    });

    Schema::create('comments', function (Blueprint $table) {
        $table->id();
        $table->string('body');
        $table->morphs('commentable');
        $table->timestamps();
    });

    $this->user = new User();
    $this->user->id = 1;
    $this->parent = TestModel::create(['name' => 'Parent']);
    $this->other = TestModel::create(['name' => 'Other']);
});

function hasManyRequest(User $user, array $items = []): NadotaRequest
{
    $request = new NadotaRequest();
    $request->setResource(new HasManyAttachTestResource());
    $request->setUserResolver(fn () => $user);
    $request->merge(['items' => $items]);

    return $request;
}

function hasManyFieldArray(NadotaRequest $request, ?TestModel $model, string $key, $resource = null): array
{
    $resource ??= new HasManyAttachTestResource();
    $field = collect($resource->fields($request))->first(fn ($f) => $f->key() === $key);

    return $field->toArray($request, $model, $resource);
}

function attachEndpoint(string $endpoint, User $user, TestModel $parent, string $field, array $items = [])
{
    return app(AttachmentController::class)->{$endpoint}(hasManyRequest($user, $items), 'test', (string) $parent->id, $field);
}

it('is off by default on HasMany and MorphMany', function () {
    $request = hasManyRequest($this->user);
    $orphan = RelatedModel::create(['title' => 'Orphan']);
    $resource = new HasManyAttachTestResource();

    $hasMany = hasManyFieldArray($request, $this->parent, 'plainRelated')['props'];
    $morphMany = MorphMany::make('Notes', 'morphNotes')->toArray($request, $this->parent, $resource)['props'];

    foreach ([$hasMany, $morphMany] as $props) {
        expect($props['canAttach'])->toBeFalse()
            ->and($props['canDetach'])->toBeFalse()
            ->and($props['urls'])->not->toHaveKeys(['attachable', 'attach', 'detach']);
    }

    $response = attachEndpoint('attach', $this->user, $this->parent, 'plainRelated', [$orphan->id]);

    expect($response->getStatusCode())->toBe(422)
        ->and($orphan->fresh()->test_model_id)->toBeNull();
});

it('attaches a child without a parent and detaches it again', function () {
    $child = RelatedModel::create(['title' => 'Child']);

    $attached = attachEndpoint('attach', $this->user, $this->parent, 'relatedModels', [$child->id]);
    expect($attached->getStatusCode())->toBe(200)
        ->and((int) $child->fresh()->test_model_id)->toBe($this->parent->id);

    $detached = attachEndpoint('detach', $this->user, $this->parent, 'relatedModels', [$child->id]);
    expect($detached->getStatusCode())->toBe(200)
        ->and($detached->getData(true)['detached'])->toBe(1)
        ->and($child->fresh()->test_model_id)->toBeNull();
});

it('does not take a child from another parent, nor offer it', function () {
    $foreign = RelatedModel::create(['title' => 'Of other', 'test_model_id' => $this->other->id]);
    $orphan = RelatedModel::create(['title' => 'Orphan']);

    $list = attachEndpoint('attachable', $this->user, $this->parent, 'relatedModels');
    expect(collect($list->getData(true)['data'])->pluck('id')->all())->toBe([$orphan->id]);

    // One bad id rejects the whole request: the orphan is not attached either
    $response = attachEndpoint('attach', $this->user, $this->parent, 'relatedModels', [$orphan->id, $foreign->id]);
    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['rejected'])->toBe([(string) $foreign->id])
        ->and((int) $foreign->fresh()->test_model_id)->toBe($this->other->id)
        ->and($orphan->fresh()->test_model_id)->toBeNull();
});

it('takes a child from another parent when the field is reassignable', function () {
    HasManyAttachTestResource::$reassignable = true;
    $foreign = RelatedModel::create(['title' => 'Of other', 'test_model_id' => $this->other->id]);
    $own = RelatedModel::create(['title' => 'Own', 'test_model_id' => $this->parent->id]);

    $list = attachEndpoint('attachable', $this->user, $this->parent, 'relatedModels');
    expect(collect($list->getData(true)['data'])->pluck('id')->all())->toBe([$foreign->id]);

    // An id already attached is skipped, not rejected
    $response = attachEndpoint('attach', $this->user, $this->parent, 'relatedModels', [$foreign->id, $own->id]);
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['attached'])->toBe([$foreign->id])
        ->and((int) $foreign->fresh()->test_model_id)->toBe($this->parent->id);
});

it('does not attach what the attachable list would not offer', function () {
    $hidden = RelatedModel::create(['title' => 'Hidden']);

    $response = attachEndpoint('attach', $this->user, $this->parent, 'relatedModels', [$hidden->id]);

    expect($response->getStatusCode())->toBe(422)
        ->and($hidden->fresh()->test_model_id)->toBeNull();
});

it('saves each child through its model, so model events run', function () {
    $child = RelatedModel::create(['title' => 'Child', 'test_model_id' => $this->parent->id]);
    $saved = [];
    RelatedModel::saved(function ($model) use (&$saved) {
        $saved[] = $model->id;
    });

    attachEndpoint('detach', $this->user, $this->parent, 'relatedModels', [$child->id]);

    expect($saved)->toBe([$child->id]);
});

it('attaches an orphan to a MorphMany writing type and id, and detaches it', function () {
    $orphan = Note::create(['title' => 'Orphan']);
    $foreign = Note::create(['title' => 'Of a tag', 'notable_type' => Tag::class, 'notable_id' => 99]);

    $list = attachEndpoint('attachable', $this->user, $this->parent, 'morphNotes');
    expect(collect($list->getData(true)['data'])->pluck('id')->all())->toBe([$orphan->id]);

    expect(attachEndpoint('attach', $this->user, $this->parent, 'morphNotes', [$foreign->id])->getStatusCode())->toBe(422)
        ->and($foreign->fresh()->notable_type)->toBe(Tag::class);

    expect(attachEndpoint('attach', $this->user, $this->parent, 'morphNotes', [$orphan->id])->getStatusCode())->toBe(200)
        ->and($orphan->fresh()->notable_type)->toBe(TestModel::class)
        ->and((int) $orphan->fresh()->notable_id)->toBe($this->parent->id);

    expect(attachEndpoint('detach', $this->user, $this->parent, 'morphNotes', [$orphan->id])->getStatusCode())->toBe(200)
        ->and($orphan->fresh()->only(['notable_type', 'notable_id']))->toEqual(['notable_type' => null, 'notable_id' => null]);
});

it('does not offer nor run detach when the owner columns are NOT NULL', function () {
    $comment = $this->parent->comments()->create(['body' => 'Own']);
    $props = hasManyFieldArray(hasManyRequest($this->user), $this->parent, 'comments')['props'];

    expect($props['canAttach'])->toBeTrue()
        ->and($props['canDetach'])->toBeFalse()
        ->and($props['urls'])->toHaveKeys(['attachable', 'attach'])
        ->and($props['urls'])->not->toHaveKey('detach');

    $response = attachEndpoint('detach', $this->user, $this->parent, 'comments', [$comment->id]);
    expect($response->getStatusCode())->toBe(422)
        ->and((int) $comment->fresh()->commentable_id)->toBe($this->parent->id);
});

it('hides the MorphMany buttons when the policy denies them', function () {
    UpdateOnlyTestModelPolicy::$canUpdate = false;
    $props = hasManyFieldArray(hasManyRequest($this->user), $this->parent, 'morphNotes')['props'];

    expect($props['canAttach'])->toBeFalse()
        ->and($props['canDetach'])->toBeFalse()
        ->and($props['urls'])->not->toHaveKeys(['attachable', 'attach', 'detach']);
});

it('refuses attach, detach and the attachable list on a morphMany declared as HasMany', function (string $endpoint) {
    $foreign = Note::create(['title' => 'Of a tag', 'notable_type' => Tag::class, 'notable_id' => 99]);
    $own = $this->parent->notes()->create(['title' => 'Own']);
    $items = $endpoint === 'detach' ? [$own->id] : [$foreign->id];

    $response = attachEndpoint($endpoint, $this->user, $this->parent, 'notes', $items);

    expect($response->getStatusCode())->toBe(422)
        ->and($foreign->fresh()->only(['notable_type', 'notable_id']))->toEqual(['notable_type' => Tag::class, 'notable_id' => 99])
        ->and((int) $own->fresh()->notable_id)->toBe($this->parent->id);
})->with(['attach', 'detach', 'attachable']);

it('does not offer attach or detach on a morphMany declared as HasMany', function () {
    $request = hasManyRequest($this->user);
    $notes = hasManyFieldArray($request, $this->parent, 'notes');
    $related = hasManyFieldArray($request, $this->parent, 'relatedModels');

    expect($notes['props']['canAttach'])->toBeFalse()
        ->and($notes['props']['canDetach'])->toBeFalse()
        ->and($notes['props']['urls'])->not->toHaveKeys(['attachable', 'attach', 'detach'])
        ->and($related['props']['canAttach'])->toBeTrue()
        ->and($related['props']['canDetach'])->toBeTrue()
        ->and($related['props']['urls'])->toHaveKeys(['attachable', 'attach', 'detach']);
});

it('builds the create form when the policy only has update($user, $model)', function () {
    $request = hasManyRequest($this->user);

    $belongsToMany = hasManyFieldArray($request, null, 'simpleTags', new AttachableTestResource());
    $hasMany = hasManyFieldArray($request, null, 'relatedModels');
    $morphMany = hasManyFieldArray($request, null, 'morphNotes');

    expect($belongsToMany['props']['canAttach'])->toBeFalse()
        ->and($belongsToMany['props']['canDetach'])->toBeFalse()
        ->and($hasMany['props']['canAttach'])->toBeFalse()
        ->and($hasMany['props']['canDetach'])->toBeFalse()
        ->and($morphMany['props']['canAttach'])->toBeFalse()
        ->and($morphMany['props']['canDetach'])->toBeFalse();
});
