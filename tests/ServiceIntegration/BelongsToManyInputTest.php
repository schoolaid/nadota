<?php

use Illuminate\Validation\ValidationException;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\Attachments\BelongsToManyAttachmentService;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\Tag;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TagResource;

/**
 * The form syncs a BelongsToMany with the ids it sends, and attach/sync take ids from the request.
 * None of them checked the ids: one that does not exist ended in a 500 (foreign key), and one the
 * related resource does not offer (`optionsQuery()`) was attached anyway. Now the ids must be
 * options of the field or already attached (a record that stopped being an option can stay).
 *
 * The edit form also sends back the whole list it received: with `limit()` or `paginated()` it got
 * a cut list, and saving detached the rest. The edit payload now always carries every item.
 */
function btmResource(?BelongsToMany $field = null): Resource
{
    return new class($field) extends Resource
    {
        public string $model = TestModel::class;

        public function __construct(private ?BelongsToMany $field)
        {
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            return [
                Input::make('Name', 'name'),
                $this->field ?? BelongsToMany::make('Tags', 'simpleTags', TagResource::class),
            ];
        }
    };
}

function btmRequest(array $input, ?BelongsToMany $field = null): NadotaRequest
{
    $request = new NadotaRequest();
    $request->merge($input);
    $request->setResource(btmResource($field));

    return $request;
}

function tagIds(TestModel $model): array
{
    return $model->simpleTags()->orderBy('tags.id')->pluck('tags.id')->all();
}

beforeEach(function () {
    $this->visible = Tag::create(['name' => 'visible']);
    $this->other = Tag::create(['name' => 'other']);
    $this->hidden = Tag::create(['name' => 'hidden tag']);
    $this->model = TestModel::create(['name' => 'Original']);
});

describe('form sync', function () {
    it('syncs ids that are options of the field', function () {
        $response = (new ResourceUpdateService())->handle(btmRequest(['name' => 'Renamed', 'simpleTags' => [$this->visible->id, (string) $this->other->id]]), $this->model->id);

        expect($response->getStatusCode())->toBe(200)
            ->and(tagIds($this->model))->toBe([$this->visible->id, $this->other->id]);
    });

    it('rejects an id the related resource does not offer, and saves nothing', function () {
        $this->model->simpleTags()->attach($this->visible->id);

        expect(fn () => (new ResourceUpdateService())->handle(btmRequest(['name' => 'Renamed', 'simpleTags' => [$this->visible->id, $this->hidden->id]]), $this->model->id))
            ->toThrow(ValidationException::class);

        expect(tagIds($this->model))->toBe([$this->visible->id])
            ->and($this->model->fresh()->name)->toBe('Original');
    });

    it('answers 422 for an id that does not exist instead of a 500', function () {
        try {
            (new ResourceUpdateService())->handle(btmRequest(['name' => 'Original', 'simpleTags' => [999999]]), $this->model->id);
            $this->fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('simpleTags')
                ->and($e->status)->toBe(422);
        }

        expect(tagIds($this->model))->toBe([]);
    });

    it('keeps an attached record that stopped being an option', function () {
        $this->model->simpleTags()->attach($this->hidden->id);

        $response = (new ResourceUpdateService())->handle(btmRequest(['name' => 'Original', 'simpleTags' => [$this->hidden->id, $this->visible->id]]), $this->model->id);

        expect($response->getStatusCode())->toBe(200)
            ->and(tagIds($this->model))->toBe([$this->visible->id, $this->hidden->id]);
    });

    it('checks the ids of items sent with pivot data', function () {
        expect(fn () => (new ResourceUpdateService())->handle(btmRequest(['name' => 'Original', 'simpleTags' => [['id' => $this->hidden->id]]]), $this->model->id))
            ->toThrow(ValidationException::class);
    });

    it('rejects the id on create and creates no record', function () {
        expect(fn () => (new ResourceStoreService())->handle(btmRequest(['name' => 'New', 'simpleTags' => [$this->hidden->id]])))
            ->toThrow(ValidationException::class);

        expect(TestModel::where('name', 'New')->exists())->toBeFalse();
    });

    it('still clears the relation with an empty list', function () {
        $this->model->simpleTags()->attach([$this->visible->id, $this->hidden->id]);

        $response = (new ResourceUpdateService())->handle(btmRequest(['name' => 'Original', 'simpleTags' => '[]']), $this->model->id);

        expect($response->getStatusCode())->toBe(200)
            ->and(tagIds($this->model))->toBe([]);
    });
});

describe('attach and sync endpoints', function () {
    function btmAttachRequest(array $input): NadotaRequest
    {
        return btmRequest($input, BelongsToMany::make('Tags', 'simpleTags', TagResource::class)->attachable());
    }

    function btmField(): BelongsToMany
    {
        return BelongsToMany::make('Tags', 'simpleTags', TagResource::class)->attachable();
    }

    it('attaches options of the field', function () {
        $response = (new BelongsToManyAttachmentService())->attach(btmAttachRequest(['items' => [$this->visible->id]]), $this->model, btmField());

        expect($response->getStatusCode())->toBe(200)
            ->and(tagIds($this->model))->toBe([$this->visible->id]);
    });

    it('rejects ids that are not options or do not exist', function (string $which) {
        $id = $which === 'hidden' ? $this->hidden->id : 999999;

        $response = (new BelongsToManyAttachmentService())->attach(btmAttachRequest(['items' => [$this->visible->id, $id]]), $this->model, btmField());

        expect($response->getStatusCode())->toBe(422)
            ->and($response->getData(true)['rejected'])->toBe([$id])
            ->and(tagIds($this->model))->toBe([]);
    })->with(['hidden', 'missing']);

    it('rejects on sync an id that is not an option, but keeps attached ones', function () {
        $this->model->simpleTags()->attach($this->hidden->id);
        $service = new BelongsToManyAttachmentService();

        $kept = $service->sync(btmAttachRequest(['items' => [$this->hidden->id, $this->visible->id]]), $this->model, btmField());
        expect($kept->getStatusCode())->toBe(200)
            ->and(tagIds($this->model))->toBe([$this->visible->id, $this->hidden->id]);

        $this->model->simpleTags()->detach($this->hidden->id);
        $rejected = $service->sync(btmAttachRequest(['items' => [$this->hidden->id]]), $this->model, btmField());
        expect($rejected->getStatusCode())->toBe(422)
            ->and(tagIds($this->model))->toBe([$this->visible->id]);
    });

    it('lists as attachable only options that are not attached, through attachableQuery() too', function () {
        $this->model->simpleTags()->attach($this->visible->id);
        $field = btmField()->attachableQuery(fn ($query) => $query->where('name', '!=', 'other'));
        $extra = Tag::create(['name' => 'extra']);

        $response = (new BelongsToManyAttachmentService())->getAttachableItems(btmAttachRequest([]), $this->model, $field);

        expect(collect($response->getData(true)['data'])->pluck('id')->all())->toBe([$extra->id]);
    });

    it('sends the attachable url so the show dialog offers only what is not attached', function () {
        $request = btmAttachRequest([]);
        $props = btmField()->toArray($request, $this->model, $request->getResource())['props'];

        expect($props['urls'])->toHaveKey('attachable')
            ->and($props['urls']['attachable'])->toEndWith("/resource/{$this->model->id}/attachable/simpleTags");
    });
});

describe('edit payload', function () {
    beforeEach(function () {
        $this->model->simpleTags()->attach([$this->visible->id, $this->other->id, $this->hidden->id]);
    });

    it('carries every attached item even with limit() or paginated()', function (BelongsToMany $field) {
        $request = btmRequest(['action' => 'update'], $field);

        $value = $field->resolve($request, $this->model->fresh(), $request->getResource());

        expect($value['data'])->toHaveCount(3)
            ->and($value['meta']['hasMore'])->toBeFalse();
    })->with([
        'limit' => fn () => BelongsToMany::make('Tags', 'simpleTags', TagResource::class)->limit(1),
        'paginated' => fn () => BelongsToMany::make('Tags', 'simpleTags', TagResource::class)->paginated(),
    ]);

    it('keeps limit() on the detail view', function () {
        $field = BelongsToMany::make('Tags', 'simpleTags', TagResource::class)->limit(1);
        $request = btmRequest([], $field);

        $value = $field->resolve($request, $this->model->fresh(), $request->getResource());

        expect($value['data'])->toHaveCount(1)
            ->and($value['meta']['hasMore'])->toBeTrue();
    });
});

describe('options with resourceId', function () {
    it('leaves out the records already attached even when the pivot has its own id column', function () {
        // test_model_tag has an `id`: the unqualified pluck('id') was ambiguous, the error was
        // swallowed and nothing was excluded.
        $this->model->simpleTags()->attach($this->visible->id);
        $service = app(\SchoolAid\Nadota\Http\Services\FieldOptionsService::class);
        $method = new ReflectionMethod($service, 'getAttachedIds');

        $ids = $method->invoke($service, btmResource(), BelongsToMany::make('Tags', 'simpleTags', TagResource::class), $this->model->id);

        expect($ids)->toBe([$this->visible->id]);
    });
});

describe('a link the related model\'s global scopes hide', function () {
    // A record linked before a scope hid it (another tenant's, say): the edit form sends it back,
    // so it must count as attached, not as a new id that is not an option.
    beforeEach(function () {
        $this->scopedOut = Tag::create(['name' => 'scoped out']);
        $this->model->simpleTags()->attach($this->scopedOut->id);
        Tag::addGlobalScope('hideScopedOut', fn ($query) => $query->where('tags.name', '!=', 'scoped out'));
    });

    afterEach(function () {
        Tag::clearBootedModels();
    });

    it('is kept when the edit form sends it back', function () {
        $response = (new ResourceUpdateService())->handle(btmRequest(['name' => 'Renamed', 'simpleTags' => [$this->scopedOut->id, $this->visible->id]]), $this->model->id);

        expect($response->getStatusCode())->toBe(200)
            ->and($this->model->simpleTags()->withoutGlobalScopes()->orderBy('tags.id')->pluck('tags.id')->all())
            ->toBe([$this->visible->id, $this->scopedOut->id]);
    });

    it('is not rejected by sync', function () {
        $field = BelongsToMany::make('Tags', 'simpleTags', TagResource::class)->attachable();
        $request = btmRequest(['items' => [$this->scopedOut->id, $this->visible->id]], $field);

        $response = (new BelongsToManyAttachmentService())->sync($request, $this->model, $field);

        expect($response->getStatusCode())->toBe(200);
    });

    it('is still rejected once it is no longer linked', function () {
        $this->model->simpleTags()->withoutGlobalScopes()->detach($this->scopedOut->id);

        expect(fn () => (new ResourceUpdateService())->handle(btmRequest(['name' => 'Renamed', 'simpleTags' => [$this->scopedOut->id]]), $this->model->id))
            ->toThrow(ValidationException::class);
    });
});

describe('rejectIdsUsing()', function () {
    afterEach(function () {
        BelongsToMany::rejectIdsUsing(null);
    });

    it('lets the application decide the rejected ids, on the form and on attach', function () {
        $seen = [];
        BelongsToMany::rejectIdsUsing(function (BelongsToMany $field, $request, $model, array $ids, Closure $default) use (&$seen) {
            $seen[] = $field->getRelation();

            return [];
        });

        $form = (new ResourceUpdateService())->handle(btmRequest(['name' => 'Renamed', 'simpleTags' => [$this->hidden->id]]), $this->model->id);
        $field = BelongsToMany::make('Tags', 'simpleTags', TagResource::class)->attachable();
        $attach = (new BelongsToManyAttachmentService())->attach(btmRequest(['items' => [$this->other->id]], $field), $this->model, $field);

        expect($form->getStatusCode())->toBe(200)
            ->and($attach->getStatusCode())->toBe(200)
            ->and(tagIds($this->model))->toBe([$this->other->id, $this->hidden->id])
            ->and($seen)->toBe(['simpleTags', 'simpleTags']);
    });

    it('hands the callback the default check', function () {
        BelongsToMany::rejectIdsUsing(fn ($field, $request, $model, array $ids, Closure $default) => $default());

        expect(fn () => (new ResourceUpdateService())->handle(btmRequest(['name' => 'Renamed', 'simpleTags' => [$this->hidden->id]]), $this->model->id))
            ->toThrow(ValidationException::class);
    });
});
