<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\BelongsToOptionsStrategy;
use SchoolAid\Nadota\Tests\Models\SoftChild;
use SchoolAid\Nadota\Tests\Models\SoftParent;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\SoftChildResource;
use SchoolAid\Nadota\Tests\Resources\SoftParentResource;

/*
 * Un BelongsTo hacia un registro soft-deleted resolvía null: el form de edición recibía el campo
 * vacío, lo mandaba vacío y guardar borraba la FK. Ahora el relacionado se carga con withTrashed
 * (con su deletedAt, para que el front no enlace a un registro borrado).
 */
beforeEach(function () {
    Schema::create('soft_parents', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('soft_children', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->unsignedBigInteger('soft_parent_id')->nullable();
        $table->unsignedBigInteger('test_model_id')->nullable();
        $table->timestamps();
    });
});

function softChildWithDeletedParent(): SoftChild
{
    $parent = SoftParent::create(['name' => 'Borrado']);
    $child = SoftChild::create(['title' => 'Hijo', 'soft_parent_id' => $parent->id]);
    $parent->delete();

    return $child->fresh();
}

it('resuelve el relacionado soft-deleted con su deletedAt (carga perezosa)', function () {
    $child = softChildWithDeletedParent();
    $field = BelongsTo::make('Parent', 'softParent', SoftParentResource::class);

    $value = $field->resolve(createNadotaRequest(), $child, null);

    expect($value)->toBeArray()
        ->and($value['key'] ?? $value['id'])->toBe($child->soft_parent_id)
        ->and($value['deletedAt'])->not->toBeNull();
});

it('lo resuelve también cuando viene de la carga anticipada del resource', function () {
    $child = softChildWithDeletedParent();
    $request = createNadotaRequest();
    $resource = new SoftChildResource();
    $fields = $resource->flattenFields($request);

    $loaded = SoftChild::with($resource->getEagerLoadRelations($request, $fields))->find($child->id);
    $field = $fields->first(fn ($f) => $f instanceof BelongsTo);
    $value = $field->resolve($request, $loaded, null);

    expect($loaded->relationLoaded('softParent'))->toBeTrue()
        ->and($value)->toBeArray()
        ->and($value['key'] ?? $value['id'])->toBe($child->soft_parent_id)
        ->and($value['deletedAt'])->not->toBeNull();
});

// Un resource puede pisar getEagerLoadRelations con su propia restricción (marketaid:
// InventoryTransactionResource precarga `orderDetail` completo con `fn ($q) => $q`).
it('lo resuelve aunque la relación venga precargada sin withTrashed', function () {
    $child = softChildWithDeletedParent();
    $loaded = SoftChild::with(['softParent' => fn ($q) => $q])->find($child->id);
    $field = BelongsTo::make('Parent', 'softParent', SoftParentResource::class);

    $value = $field->resolve(createNadotaRequest(), $loaded, null);

    expect($value)->toBeArray()
        ->and($value['key'] ?? $value['id'])->toBe($child->soft_parent_id)
        ->and($value['deletedAt'])->not->toBeNull();
});

it('sigue en null si la FK apunta a un registro que no existe', function () {
    $child = SoftChild::create(['title' => 'Huérfano', 'soft_parent_id' => 999]);
    $field = BelongsTo::make('Parent', 'softParent', SoftParentResource::class);

    expect($field->resolve(createNadotaRequest(), $child->fresh(), null))->toBeNull();
});

it('no cambia los BelongsTo hacia modelos sin SoftDeletes', function () {
    $model = TestModel::factory()->create(['name' => 'Padre']);
    $child = SoftChild::create(['title' => 'Hijo', 'test_model_id' => $model->id]);
    $field = BelongsTo::make('Test Model', 'testModel')->relationAttribute('name');

    expect($field->resolve(createNadotaRequest(), $child->fresh(), null))
        ->toHaveKey('label', 'Padre');
});

// El front resuelve la etiqueta del valor guardado con filters[id]=<id>: sin el borrado, el edit
// conservaba la FK pero mostraba «Selecciona una opción».
it('las opciones por id traen el registro soft-deleted, la búsqueda normal no', function () {
    $child = softChildWithDeletedParent();
    $fetch = fn (array $params) => (new BelongsToOptionsStrategy())->fetchOptions(
        createNadotaRequest(),
        new SoftChildResource(),
        BelongsTo::make('Parent', 'softParent', SoftParentResource::class),
        $params
    );

    expect($fetch(['filters' => ['id' => (string) $child->soft_parent_id]]))->toHaveCount(1)
        ->and($fetch([]))->toHaveCount(0);
});

