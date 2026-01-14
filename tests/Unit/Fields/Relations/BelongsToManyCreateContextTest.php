<?php

use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Models\TestModel;
use Illuminate\Http\Request;

it('includes createContext when canCreate is enabled', function () {
    $tagResource = new class extends Resource {
        public string $model = TestModel::class;
        public static function getKey(): string { return 'tags'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $postResource = new class extends Resource {
        public string $model = TestModel::class;
        public static function getKey(): string { return 'posts'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $field = BelongsToMany::make('Tags', 'tags', get_class($tagResource))
        ->canCreate();

    $model = TestModel::factory()->create();
    $request = Request::create('/test');

    $array = $field->toArray(createNadotaRequest([]), $model, $postResource);

    expect($array['props']['canCreate'])->toBe(true)
        ->and($array['props']['autoAttach'])->toBe(true)
        ->and($array['props']['createContext'])->toBeArray()
        ->and($array['props']['createContext']['parentResource'])->toBe('posts')
        ->and($array['props']['createContext']['parentId'])->toBe($model->id)
        ->and($array['props']['createContext']['relatedResource'])->toBe('tags')
        ->and($array['props']['createContext']['relationType'])->toBe('belongsToMany')
        ->and($array['props']['createContext']['autoAttach'])->toBe(true)
        ->and($array['props']['createContext']['attachUrl'])->toContain('/attach/tags')
        ->and($array['props']['createContext']['createUrl'])->toContain('/tags/resource/create')
        ->and($array['props']['createContext']['storeUrl'])->toContain('/tags/resource')
        ->and($array['props']['createContext']['returnUrl'])->toContain('/posts/' . $model->id);
});

it('does not include createContext when canCreate is disabled', function () {
    $tagResource = new class extends Resource {
        public string $model = TestModel::class;
        public static function getKey(): string { return 'tags'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $postResource = new class extends Resource {
        public string $model = TestModel::class;
        public static function getKey(): string { return 'posts'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $field = BelongsToMany::make('Tags', 'tags', get_class($tagResource));
    // canCreate is false by default

    $model = TestModel::factory()->create();

    $array = $field->toArray(createNadotaRequest([]), $model, $postResource);

    expect($array['props']['canCreate'])->toBe(false)
        ->and($array['props'])->not->toHaveKey('createContext');
});

it('allows disabling autoAttach', function () {
    $tagResource = new class extends Resource {
        public string $model = TestModel::class;
        public static function getKey(): string { return 'tags'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $postResource = new class extends Resource {
        public string $model = TestModel::class;
        public static function getKey(): string { return 'posts'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $field = BelongsToMany::make('Tags', 'tags', get_class($tagResource))
        ->canCreate()
        ->autoAttach(false);

    $model = TestModel::factory()->create();

    $array = $field->toArray(createNadotaRequest([]), $model, $postResource);

    expect($array['props']['autoAttach'])->toBe(false)
        ->and($array['props']['createContext']['autoAttach'])->toBe(false);
});

it('createContext includes all necessary URLs', function () {
    $tagResource = new class extends Resource {
        public string $model = TestModel::class;
        public static function getKey(): string { return 'tags'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $postResource = new class extends Resource {
        public string $model = TestModel::class;
        public static function getKey(): string { return 'posts'; }
        public function fields(NadotaRequest $request): array { return []; }
    };

    $field = BelongsToMany::make('Tags', 'tags', get_class($tagResource))
        ->canCreate();

    $model = TestModel::factory()->create();

    $array = $field->toArray(createNadotaRequest([]), $model, $postResource);

    $createContext = $array['props']['createContext'];

    expect($createContext)->toHaveKey('attachUrl')
        ->and($createContext)->toHaveKey('createUrl')
        ->and($createContext)->toHaveKey('storeUrl')
        ->and($createContext)->toHaveKey('returnUrl')
        ->and($createContext['attachUrl'])->toBeString()
        ->and($createContext['createUrl'])->toBeString()
        ->and($createContext['storeUrl'])->toBeString()
        ->and($createContext['returnUrl'])->toBeString();
});
