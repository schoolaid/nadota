<?php

use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

beforeEach(function () {
    $this->tagResource = new class extends Resource {
        public string $model = TestModel::class;
        public static function getKey(): string { return 'tags'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $this->postResource = new class extends Resource {
        public string $model = TestModel::class;
        public static function getKey(): string { return 'posts'; }
        public function fields(NadotaRequest $request): array { return []; }
    };
});

it('includes createContext when canCreate is enabled', function () {
    $model = TestModel::factory()->create();

    $props = BelongsToMany::make('Tags', 'tags', get_class($this->tagResource))->canCreate()
        ->toArray(createNadotaRequest(), $model, $this->postResource)['props'];

    expect($props['canCreate'])->toBeTrue()
        ->and($props['autoAttach'])->toBeTrue()
        ->and($props['createContext'])->toBe([
            'parentResource' => 'posts',
            'parentId' => $model->id,
            'relatedResource' => 'tags',
            'relationType' => 'belongsToMany',
            'autoAttach' => true,
            'attachUrl' => "/nadota-api/posts/resource/{$model->id}/attach/tags",
            'returnUrl' => "/resources/posts/{$model->id}",
            'createUrl' => '/nadota-api/tags/resource/create',
            'storeUrl' => '/nadota-api/tags/resource',
        ]);
});

it('does not include createContext when canCreate is disabled', function () {
    $model = TestModel::factory()->create();

    $props = BelongsToMany::make('Tags', 'tags', get_class($this->tagResource))
        ->toArray(createNadotaRequest(), $model, $this->postResource)['props'];

    expect($props['canCreate'])->toBeFalse()
        ->and($props)->not->toHaveKey('createContext');
});

it('does not include createContext without a parent model', function () {
    $props = BelongsToMany::make('Tags', 'tags', get_class($this->tagResource))->canCreate()
        ->toArray(createNadotaRequest(), null, $this->postResource)['props'];

    expect($props['canCreate'])->toBeTrue()
        ->and($props)->not->toHaveKey('createContext');
});

it('allows disabling autoAttach', function () {
    $model = TestModel::factory()->create();

    $props = BelongsToMany::make('Tags', 'tags', get_class($this->tagResource))->canCreate()->autoAttach(false)
        ->toArray(createNadotaRequest(), $model, $this->postResource)['props'];

    expect($props['autoAttach'])->toBeFalse()
        ->and($props['createContext']['autoAttach'])->toBeFalse();
});

it('reports canCreate false when the related resource denies create', function () {
    app()->bind(ResourceAuthorizationInterface::class, fn () => new class implements ResourceAuthorizationInterface {
        public function setModel($model): self { return $this; }
        public function authorizedTo(NadotaRequest $request, string $action, array $context = []): bool
        {
            return $action !== 'create';
        }
    });
    $model = TestModel::factory()->create();

    $props = BelongsToMany::make('Tags', 'tags', get_class($this->tagResource))->canCreate()
        ->toArray(createNadotaRequest(), $model, $this->postResource)['props'];

    expect($props['canCreate'])->toBeFalse()
        ->and($props)->not->toHaveKey('createContext')
        ->and($props['canAttach'])->toBeTrue();
});
