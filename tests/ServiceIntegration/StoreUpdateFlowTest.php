<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\Comment;
use SchoolAid\Nadota\Tests\Models\Tag;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\TestResource;

/**
 * Store and update through the real persist pipeline for fields that write in
 * afterSave() (BelongsToMany) or set two columns (MorphTo).
 */

function flowResource(string $model, array $fields, bool $failAfterSave = false): Resource
{
    return new class($model, $fields, $failAfterSave) extends Resource
    {
        public function __construct(string $model, private array $fieldList, private bool $failAfterSave)
        {
            $this->model = $model;
            parent::__construct();
        }

        public function fields(NadotaRequest $request): array
        {
            return $this->fieldList;
        }

        public function afterStore(Model $model, NadotaRequest $request): void
        {
            if ($this->failAfterSave) {
                throw new RuntimeException('afterStore failed');
            }
        }
    };
}

function flowRequest(Resource $resource, array $input): NadotaRequest
{
    $request = new NadotaRequest();
    $request->merge($input);
    $request->setResource($resource);

    return $request;
}

function commentResource(): Resource
{
    return flowResource(Comment::class, [
        Input::make('Body', 'body'),
        MorphTo::make('Commentable', 'commentable', ['test' => TestResource::class]),
    ]);
}

it('syncs a BelongsToMany field when the record is created', function () {
    $tags = Tag::factory()->count(2)->create();
    $resource = flowResource(TestModel::class, [
        Input::make('Name', 'name'),
        BelongsToMany::make('Tags', 'simpleTags'),
    ]);

    $response = (new ResourceStoreService())->handle(
        flowRequest($resource, ['name' => 'A', 'simpleTags' => $tags->pluck('id')->all()])
    );

    expect($response->getStatusCode())->toBe(201)
        ->and(TestModel::sole()->simpleTags()->pluck('tags.id')->sort()->values()->all())
        ->toBe($tags->pluck('id')->sort()->values()->all());
});

it('rolls back the BelongsToMany sync with the record when the store fails', function () {
    $tag = Tag::factory()->create();
    $resource = flowResource(TestModel::class, [
        Input::make('Name', 'name'),
        BelongsToMany::make('Tags', 'simpleTags'),
    ], failAfterSave: true);

    $response = (new ResourceStoreService())->handle(
        flowRequest($resource, ['name' => 'A', 'simpleTags' => [$tag->id]])
    );

    expect($response->getStatusCode())->toBe(500)
        ->and(TestModel::count())->toBe(0);
    $this->assertDatabaseCount('test_model_tag', 0);
});

describe('MorphTo', function () {
    beforeEach(function () {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->string('body')->nullable();
            $table->string('commentable_type')->nullable();
            $table->unsignedBigInteger('commentable_id')->nullable();
            $table->timestamps();
        });
    });

    afterEach(function () {
        Schema::dropIfExists('comments');
    });

    it('writes the morph type and id on update', function () {
        $parent = TestModel::create(['name' => 'Parent']);
        $comment = Comment::create(['body' => 'Hi']);

        $response = (new ResourceUpdateService())->handle(
            flowRequest(commentResource(), ['body' => 'Hi', 'commentable_type' => 'test', 'commentable_id' => $parent->id]),
            $comment->id
        );

        $comment->refresh();

        expect($response->getStatusCode())->toBe(200)
            ->and($comment->commentable_type)->toBe(TestModel::class)
            ->and((int) $comment->commentable_id)->toBe($parent->id)
            ->and($comment->commentable->is($parent))->toBeTrue();
    });

    it('clears the morph columns when the update sends no type', function () {
        $parent = TestModel::create(['name' => 'Parent']);
        $comment = Comment::create(['body' => 'Hi', 'commentable_type' => TestModel::class, 'commentable_id' => $parent->id]);

        (new ResourceUpdateService())->handle(
            flowRequest(commentResource(), ['body' => 'Hi', 'commentable_type' => null, 'commentable_id' => null]),
            $comment->id
        );

        $comment->refresh();

        expect($comment->commentable_type)->toBeNull()
            ->and($comment->commentable_id)->toBeNull();
    });
});
