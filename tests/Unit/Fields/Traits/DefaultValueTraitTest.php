<?php

use SchoolAid\Nadota\Http\Fields\Input;

it('field has no default value by default', function () {
    $field = Input::make('Name', 'name');
    
    expect($field->hasDefault())->toBeFalse();
});

it('can set default value', function () {
    $field = Input::make('Status', 'status')
        ->default('active');
    
    expect($field->hasDefault())->toBeTrue();
});

it('resolves default value when model attribute is empty', function () {
    $model = createTestModel(['name' => null]);
    $field = Input::make('Name', 'name')
        ->default('Default Name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('Default Name');
});

it('uses model value when available instead of default', function () {
    $model = createTestModel(['name' => 'Actual Name']);
    $field = Input::make('Name', 'name')
        ->default('Default Name');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('Actual Name');
});

it('can use callback for default value', function () {
    $model = createTestModel(['name' => null]);
    $field = Input::make('Created At', 'created_at')
        ->default(function ($request, $model, $resource) {
            return now()->format('Y-m-d');
        });
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe(now()->format('Y-m-d'));
});

it('can use model attribute as default', function () {
    $model = createTestModel(['email' => 'test@example.com', 'name' => null]);
    $field = Input::make('Name', 'name')
        ->defaultFromAttribute('email');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('test@example.com');
});

it('can resolve nested default values', function () {
    $model = createTestModel(['metadata' => ['title' => 'Test Title'], 'name' => null]);
    $field = Input::make('Name', 'name')
        ->defaultFromAttribute('metadata.title');
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe('Test Title');
});

it('handles null default gracefully', function () {
    $model = createTestModel(['name' => null]);
    $field = Input::make('Name', 'name')
        ->default(null);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeNull();
});

it('resolves boolean default values correctly', function () {
    $model = createTestModel(['is_active' => null]);
    $field = Input::make('Active', 'is_active')
        ->default(true);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBeTrue();
});

it('resolves array default values correctly', function () {
    $model = createTestModel(['metadata' => null]);
    $defaultArray = ['key' => 'value'];
    $field = Input::make('Metadata', 'metadata')
        ->default($defaultArray);
    
    $request = createNadotaRequest();
    $value = $field->resolve($request, $model, null);
    
    expect($value)->toBe($defaultArray);
});
it('takes a callback through defaultUsing()', function () {
    $field = Input::make('Code', 'code')->defaultUsing(fn ($request) => 'from-' . $request->get('prefix'));

    expect($field->hasDefault())->toBeTrue()
        ->and($field->resolveDefault(createNadotaRequest(['prefix' => 'req'])))->toBe('from-req');
});

it('applies a defaultWhen value only while the condition holds', function () {
    $received = null;
    $field = Input::make('Status', 'status')->defaultWhen(function (...$args) use (&$received) {
        $received = $args;

        return $args[0]->get('kind') === 'new';
    }, 'draft');

    $model = createTestModel();
    $resource = createTestResource();
    $request = createNadotaRequest(['kind' => 'new']);

    expect($field->resolveDefault($request, $model, $resource))->toBe('draft')
        ->and($received)->toBe([$request, $model, $resource])
        ->and($field->resolveDefault(createNadotaRequest(['kind' => 'old'])))->toBeNull();
});

it('prefers defaultFromAttribute over a static default when a model is given', function () {
    $field = Input::make('Name', 'name')->default('static')->defaultFromAttribute('email');

    expect($field->resolveDefault(createNadotaRequest(), createTestModel(['email' => 'a@b.c'])))->toBe('a@b.c')
        ->and($field->resolveDefault(createNadotaRequest()))->toBe('static');
});

it('returns null for an attribute path that leads nowhere', function (string $path) {
    $model = createTestModel(['name' => 'Ana', 'metadata' => ['title' => 'T']]);

    expect(Input::make('X', 'x')->defaultFromAttribute($path)->resolveDefault(createNadotaRequest(), $model))->toBeNull();
})->with(['missing', 'metadata.missing', 'name.length', 'metadata.title.deeper']);

it('exposes the configured default and source attribute', function () {
    $field = Input::make('Name', 'name')->default('x')->defaultFromAttribute('email');

    expect($field->getDefault())->toBe('x')
        ->and($field->getDefaultFromAttribute())->toBe('email');
});

it('sends the resolved default to the create form', function () {
    $array = Input::make('Status', 'status')->default(fn () => 'draft')->toArray(createNadotaRequest());

    expect($array['default'])->toBe('draft');
});

it('sends a null default when none is configured', function () {
    expect(Input::make('Status', 'status')->toArray(createNadotaRequest())['default'])->toBeNull();
});

it('treats a string default as a literal even when it names a PHP function', function (string $literal) {
    $field = Input::make('Kind', 'kind')->default($literal);

    expect($field->resolveDefault(createNadotaRequest()))->toBe($literal);
})->with(['date', 'time', 'file', 'max'])
    ->skip('ISSUE TRT-004: is_callable() on the default calls date()/time()/file() with (request, item, resource)');

it('passes the request, model and resource to a default callback on resolve', function () {
    // Moved from tests/ServiceIntegration/DefaultValueTraitIntegrationTest.php.
    $received = [];
    $model = createTestModel(['name' => null]);
    $resource = createTestResource();
    $request = createNadotaRequest();

    Input::make('Name', 'name')
        ->default(function (...$args) use (&$received) {
            $received = $args;

            return 'callback_value';
        })
        ->resolve($request, $model, $resource);

    expect($received)->toBe([$request, $model, $resource]);
});
