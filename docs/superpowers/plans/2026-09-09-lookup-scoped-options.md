# Lookup Fields y Opciones Acotadas — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir un field de formulario que no se persiste (`Lookup`) y un mecanismo declarativo (`scopedBy()`) para que cualquier field acote su query de opciones con el valor de otro field del mismo formulario.

**Architecture:** Un trait `ScopesOptions` en la clase base `Field` guarda las declaraciones en `OptionScopeDTO`; una clase sin estado `OptionScopeResolver` las traduce a cláusulas `where` sobre el query de opciones, leyendo los valores del parámetro `scope[]` del request. La columna siempre viene de la declaración del servidor, nunca del request. El resolver se engancha en los dos puntos donde se construyen queries de opciones: `AbstractOptionsStrategy` y `FieldOptionsService::getPaginatedOptions()`.

**Tech Stack:** PHP 8.2+, Laravel 11/12, Pest 2 sobre Orchestra Testbench, SQLite en memoria.

**Spec:** `docs/superpowers/specs/2026-09-08-lookup-scoped-options-design.md`

## Global Constraints

- Namespace raíz `SchoolAid\Nadota`; imports siempre con ruta absoluta de namespace.
- Comentarios y docblocks del código y de `docs/` **en inglés**, igual que el resto del paquete. Este plan y el spec están en español por ser documentos de trabajo.
- Comparaciones de scope **exactas** (`=` / `whereIn`), nunca `like`. Es la regresión que motiva todo el trabajo.
- La columna a filtrar sale **siempre** de la declaración en el resource; el request solo aporta el valor.
- Sin cambios en `store`/`update` ni en la validación de persistencia: fuera de alcance.
- Ejecutar tests con `./vendor/bin/pest <ruta>`; la suite completa con `composer test`.
- **La suite tiene 105 fallos preexistentes**, ajenos a este trabajo: 92 en `tests/Unit/Fields/*` y 13 en `tests/ServiceIntegration/` (este directorio no estaba declarado en `phpunit.xml` y nunca se había ejecutado; la Task 4 lo añade a los testsuites). Son tests desactualizados respecto al código: son tests desactualizados respecto al código (esperan `fieldData->type` como enum y nombres de componente antiguos como `field-hidden`). El criterio de verificación NO es "`composer test` pasa", sino: **los archivos de test de la tarea pasan al 100% y el número de fallos de la suite sigue siendo 105**. No arreglar esos tests: está fuera de alcance.
- Cada tarea termina con commit propio.

---

### Task 1: Declaración de scopes (`OptionScopeDTO` + `ScopesOptions`)

**Files:**
- Create: `src/Http/Fields/DataTransferObjects/OptionScopeDTO.php`
- Create: `src/Http/Fields/Traits/ScopesOptions.php`
- Modify: `src/Http/Fields/Field.php:28-38` (bloque de `use` de traits)
- Test: `tests/Unit/Fields/Traits/ScopesOptionsTest.php`

**Interfaces:**
- Consumes: nada.
- Produces:
  - `OptionScopeDTO::__construct(string $field, ?string $column = null, ?Closure $callback = null, bool $optional = false)`, propiedades públicas del mismo nombre, `usesCallback(): bool`, `toArray(): array{field: string, optional: bool}`.
  - `Field::scopedBy(string $field, string|Closure|null $target = null, bool $optional = false): static`
  - `Field::getOptionScopes(): array<string, OptionScopeDTO>`
  - `Field::hasOptionScopes(): bool`

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Unit/Fields/Traits/ScopesOptionsTest.php`:

```php
<?php

use Illuminate\Database\Eloquent\Builder;
use SchoolAid\Nadota\Http\Fields\Input;

it('has no option scopes by default', function () {
    $field = Input::make('Student', 'student_id');

    expect($field->hasOptionScopes())->toBeFalse()
        ->and($field->getOptionScopes())->toBe([]);
});

it('infers the column from the observed field name', function () {
    $field = Input::make('Student', 'student_id')->scopedBy('grade');

    $scope = $field->getOptionScopes()['grade'];

    expect($scope->field)->toBe('grade')
        ->and($scope->column)->toBe('grade_id')
        ->and($scope->callback)->toBeNull()
        ->and($scope->optional)->toBeFalse()
        ->and($scope->usesCallback())->toBeFalse();
});

it('snake cases a camel cased field name when inferring the column', function () {
    $field = Input::make('Student', 'student_id')->scopedBy('schoolYear');

    expect($field->getOptionScopes()['schoolYear']->column)->toBe('school_year_id');
});

it('accepts an explicit column name', function () {
    $field = Input::make('Student', 'student_id')->scopedBy('grade', 'current_grade_id');

    expect($field->getOptionScopes()['grade']->column)->toBe('current_grade_id');
});

it('accepts a closure and leaves the column null', function () {
    $callback = fn (Builder $query, $value) => $query->where('grade_id', $value);

    $field = Input::make('Student', 'student_id')->scopedBy('grade', $callback);

    $scope = $field->getOptionScopes()['grade'];

    expect($scope->column)->toBeNull()
        ->and($scope->callback)->toBe($callback)
        ->and($scope->usesCallback())->toBeTrue();
});

it('marks a scope as optional when asked', function () {
    $field = Input::make('Student', 'student_id')
        ->scopedBy('campus', 'campus_id', optional: true);

    expect($field->getOptionScopes()['campus']->optional)->toBeTrue();
});

it('accumulates scopes on different fields', function () {
    $field = Input::make('Student', 'student_id')
        ->scopedBy('grade', 'grade_id')
        ->scopedBy('campus', 'campus_id');

    expect(array_keys($field->getOptionScopes()))->toBe(['grade', 'campus'])
        ->and($field->hasOptionScopes())->toBeTrue();
});

it('replaces a previous declaration on the same field', function () {
    $field = Input::make('Student', 'student_id')
        ->scopedBy('grade', 'grade_id')
        ->scopedBy('grade', 'other_grade_id');

    expect($field->getOptionScopes())->toHaveCount(1)
        ->and($field->getOptionScopes()['grade']->column)->toBe('other_grade_id');
});

it('is chainable', function () {
    $field = Input::make('Student', 'student_id');

    expect($field->scopedBy('grade'))->toBe($field);
});
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `./vendor/bin/pest tests/Unit/Fields/Traits/ScopesOptionsTest.php`
Expected: FAIL con `Call to undefined method ...Input::hasOptionScopes()`

- [ ] **Step 3: Crear el DTO**

`src/Http/Fields/DataTransferObjects/OptionScopeDTO.php`:

```php
<?php

namespace SchoolAid\Nadota\Http\Fields\DataTransferObjects;

use Closure;

/**
 * A single option-scope declaration: how one field's value constrains
 * another field's options query.
 */
class OptionScopeDTO
{
    public function __construct(
        public string $field,
        public ?string $column = null,
        public ?Closure $callback = null,
        public bool $optional = false,
    ) {
    }

    /**
     * Whether this scope is applied through a closure instead of a column.
     */
    public function usesCallback(): bool
    {
        return $this->callback !== null;
    }

    /**
     * The frontend-facing shape. The column is deliberately omitted:
     * it is server-side configuration and must never reach the client.
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'optional' => $this->optional,
        ];
    }
}
```

- [ ] **Step 4: Crear el trait**

`src/Http/Fields/Traits/ScopesOptions.php`:

```php
<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use Closure;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Http\Fields\DataTransferObjects\OptionScopeDTO;

/**
 * Constrains a field's options query with the value of another field
 * in the same form.
 *
 * The observed field does not need to be persisted: a Lookup field, or any
 * field marked ->virtual(), works as the source of the value.
 */
trait ScopesOptions
{
    /**
     * Declared option scopes, keyed by the observed field name.
     *
     * @var array<string, OptionScopeDTO>
     */
    protected array $optionScopes = [];

    /**
     * Constrain this field's options by the value of another field.
     *
     * @param string $field The observed field's key in the form.
     * @param string|Closure|null $target A column name on the related model, or
     *        fn(Builder $query, mixed $value): Builder. Defaults to the observed
     *        field name in snake_case with an `_id` suffix.
     * @param bool $optional When false (the default), an observed field with no
     *        value makes the options query return nothing.
     * @return static
     */
    public function scopedBy(string $field, string|Closure|null $target = null, bool $optional = false): static
    {
        $this->optionScopes[$field] = new OptionScopeDTO(
            field: $field,
            column: $target instanceof Closure ? null : ($target ?? Str::snake($field) . '_id'),
            callback: $target instanceof Closure ? $target : null,
            optional: $optional,
        );

        return $this;
    }

    /**
     * @return array<string, OptionScopeDTO>
     */
    public function getOptionScopes(): array
    {
        return $this->optionScopes;
    }

    public function hasOptionScopes(): bool
    {
        return $this->optionScopes !== [];
    }
}
```

- [ ] **Step 5: Registrar el trait en `Field`**

En `src/Http/Fields/Field.php`, dentro del bloque de imports agrupados `use SchoolAid\Nadota\Http\Fields\Traits\{...}` (línea 11), añadir `ScopesOptions` a la lista, y en el cuerpo de la clase (líneas 28-38) añadir la línea manteniendo el orden alfabético existente:

```php
    use RelationshipTrait;
    use ScopesOptions;
    use SearchableTrait;
```

- [ ] **Step 6: Ejecutar el test para verificar que pasa**

Run: `./vendor/bin/pest tests/Unit/Fields/Traits/ScopesOptionsTest.php`
Expected: PASS, 9 tests

- [ ] **Step 7: Ejecutar la suite completa para verificar que no se rompió nada**

Run: `composer test`
Expected: la línea final sigue diciendo `105 failed`; los tests nuevos aparecen entre los que pasan. Cualquier fallo nuevo es tuyo.

- [ ] **Step 8: Commit**

```bash
git add src/Http/Fields/DataTransferObjects/OptionScopeDTO.php \
        src/Http/Fields/Traits/ScopesOptions.php \
        src/Http/Fields/Field.php \
        tests/Unit/Fields/Traits/ScopesOptionsTest.php
git commit -m "feat: add scopedBy() declaration for option scopes"
```

---

### Task 2: Contrato JSON hacia el frontend

**Files:**
- Modify: `src/Http/Fields/DataTransferObjects/DependencyDTO.php`
- Modify: `src/Http/Fields/Traits/ScopesOptions.php`
- Test: `tests/Unit/Fields/Traits/ScopesOptionsTest.php` (añadir casos)

**Interfaces:**
- Consumes: `Field::scopedBy()` de Task 1; `DependsOnTrait::dependsOn()`, `::clearOnDependencyChange()`, `::getDependencyDTO()` (ya existentes en `Field`).
- Produces: `DependencyDTO::addOptionScope(string $field, bool $optional = false): static` y la clave `options.scope` en `Field::getDependencyConfig()`, como lista de `{field, optional}`.

- [ ] **Step 1: Escribir los tests que fallan**

Añadir al final de `tests/Unit/Fields/Traits/ScopesOptionsTest.php`:

```php
it('registers the observed field as a dependency', function () {
    $field = Input::make('Student', 'student_id')->scopedBy('grade');

    expect($field->hasDependencies())->toBeTrue()
        ->and($field->getDependsOnFields())->toBe(['grade']);
});

it('clears its value when a dependency changes', function () {
    $field = Input::make('Student', 'student_id')->scopedBy('grade');

    expect($field->getDependencyConfig()['clearOnChange'])->toBeTrue();
});

it('serializes scopes under options.scope', function () {
    $field = Input::make('Student', 'student_id')
        ->scopedBy('grade')
        ->scopedBy('campus', 'campus_id', optional: true);

    expect($field->getDependencyConfig()['options']['scope'])->toBe([
        ['field' => 'grade', 'optional' => false],
        ['field' => 'campus', 'optional' => true],
    ]);
});

it('does not leak the column name to the frontend', function () {
    $field = Input::make('Student', 'student_id')->scopedBy('grade', 'secret_column');

    expect(json_encode($field->getDependencyConfig()))->not->toContain('secret_column');
});

it('serializes a replaced scope only once', function () {
    $field = Input::make('Student', 'student_id')
        ->scopedBy('grade', 'grade_id')
        ->scopedBy('grade', 'other_grade_id', optional: true);

    expect($field->getDependencyConfig()['options']['scope'])->toBe([
        ['field' => 'grade', 'optional' => true],
    ]);
});

it('keeps cascadeFrom and scopes side by side under options', function () {
    $field = Input::make('Student', 'student_id')
        ->cascadeFrom('country_id')
        ->scopedBy('grade');

    $options = $field->getDependencyConfig()['options'];

    expect($options['cascadeFrom'])->toBe('country_id')
        ->and($options['scope'])->toBe([
            ['field' => 'grade', 'optional' => false],
        ]);
});
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `./vendor/bin/pest tests/Unit/Fields/Traits/ScopesOptionsTest.php`
Expected: FAIL — `hasDependencies()` devuelve `false` y `getDependencyConfig()` no tiene clave `options`

- [ ] **Step 3: Añadir el almacenamiento y la serialización en `DependencyDTO`**

En `src/Http/Fields/DataTransferObjects/DependencyDTO.php`, añadir la propiedad junto a las demás (después de `public ?array $options = null;`):

```php
    /**
     * Option scopes declared via ScopesOptions::scopedBy(), keyed by observed field.
     *
     * @var array<string, array{field: string, optional: bool}>
     */
    public array $optionScopes = [];
```

Añadir el método junto a `setCascadeFrom()`:

```php
    /**
     * Register an option scope. Re-declaring the same field replaces it.
     */
    public function addOptionScope(string $field, bool $optional = false): static
    {
        $this->optionScopes[$field] = [
            'field' => $field,
            'optional' => $optional,
        ];

        return $this;
    }
```

En `hasDependencies()`, añadir la condición al final de la expresión:

```php
            || $this->compute !== null
            || $this->optionScopes !== [];
```

En `toArray()`, **después** del bloque `if ($this->options !== null) { $data['options'] = $this->options; }` y antes del bloque de `compute`, añadir:

```php
        if ($this->optionScopes !== []) {
            $data['options'] = array_merge(
                $data['options'] ?? [],
                ['scope' => array_values($this->optionScopes)]
            );
        }
```

El `array_merge` es deliberado: permite que `cascadeFrom()`/`optionsFromEndpoint()` y `scopedBy()` coexistan en el mismo field sin pisarse.

- [ ] **Step 4: Añadir los efectos colaterales en `scopedBy()`**

En `src/Http/Fields/Traits/ScopesOptions.php`, dentro de `scopedBy()`, antes del `return $this;`:

```php
        $this->dependsOn($field);
        $this->clearOnDependencyChange();
        $this->getDependencyDTO()->addOptionScope($field, $optional);
```

Y añadir al docblock del trait, debajo de la descripción:

```php
 * Requires DependsOnTrait on the same class: declaring a scope registers the
 * observed field as a dependency so the frontend re-fetches options when it
 * changes, and clears this field's value so a stale selection is not submitted.
```

- [ ] **Step 5: Ejecutar los tests para verificar que pasan**

Run: `./vendor/bin/pest tests/Unit/Fields/Traits/ScopesOptionsTest.php`
Expected: PASS, 15 tests

- [ ] **Step 6: Ejecutar la suite completa**

Run: `composer test`
Expected: la línea final sigue diciendo `105 failed`; los tests nuevos aparecen entre los que pasan. Cualquier fallo nuevo es tuyo.. Prestar atención a los tests existentes de `dependsOn`, que comparten el `DependencyDTO`.

- [ ] **Step 7: Commit**

```bash
git add src/Http/Fields/DataTransferObjects/DependencyDTO.php \
        src/Http/Fields/Traits/ScopesOptions.php \
        tests/Unit/Fields/Traits/ScopesOptionsTest.php
git commit -m "feat: serialize option scopes in the field dependency payload"
```

---

### Task 3: `OptionScopeResolver`

**Files:**
- Create: `src/Http/Services/FieldOptions/OptionScopeResolver.php`
- Test: `tests/ServiceIntegration/OptionScopeResolverTest.php`

**Interfaces:**
- Consumes: `Field::hasOptionScopes()`, `Field::getOptionScopes()`, `OptionScopeDTO` (Task 1).
- Produces: `OptionScopeResolver::apply(Builder $query, Field $field, array $values): ?Builder` — devuelve el builder, o `null` cuando un scope estricto no trae valor.

Este test toca SQLite de verdad (por eso vive en `ServiceIntegration/`): comprobar que el filtrado es exacto exige ejecutar el query.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/ServiceIntegration/OptionScopeResolverTest.php`:

```php
<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Services\FieldOptions\OptionScopeResolver;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * Owners 5, 15 and 51 exist so that an exact match on 5 can be told apart
 * from a LIKE '%5%', which would also match 15 and 51.
 */
function seedOwnersAndItems(): void
{
    foreach ([5, 15, 51] as $ownerId) {
        $owner = new TestModel(['name' => "Owner {$ownerId}"]);
        $owner->id = $ownerId;
        $owner->save();

        RelatedModel::query()->create([
            'title' => "Item for owner {$ownerId}",
            'test_model_id' => $ownerId,
        ]);
    }
}

function scopedField(string|Closure|null $target = 'test_model_id', bool $optional = false)
{
    return Input::make('Item', 'item_id')->scopedBy('owner', $target, $optional);
}

it('returns the query untouched when no scopes are declared', function () {
    seedOwnersAndItems();

    $query = RelatedModel::query();

    $result = (new OptionScopeResolver())->apply($query, Input::make('Item', 'item_id'), []);

    expect($result)->not->toBeNull()
        ->and($result->count())->toBe(3);
});

it('filters by an exact column match, not a partial one', function () {
    seedOwnersAndItems();

    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField(),
        ['owner' => 5]
    );

    expect($result->pluck('title')->all())->toBe(['Item for owner 5']);
});

it('uses whereIn for array values', function () {
    seedOwnersAndItems();

    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField(),
        ['owner' => [5, 51]]
    );

    expect($result->pluck('title')->all())
        ->toBe(['Item for owner 5', 'Item for owner 51']);
});

it('returns null when a strict scope has no value', function () {
    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField(),
        []
    );

    expect($result)->toBeNull();
});

it('treats empty string, empty array and the string null as no value', function ($value) {
    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField(),
        ['owner' => $value]
    );

    expect($result)->toBeNull();
})->with([[''], [[]], ['null'], [null]]);

it('skips an optional scope with no value', function () {
    seedOwnersAndItems();

    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        scopedField('test_model_id', optional: true),
        []
    );

    expect($result)->not->toBeNull()
        ->and($result->count())->toBe(3);
});

it('applies a closure scope', function () {
    seedOwnersAndItems();

    $field = scopedField(
        fn ($query, $value) => $query->whereHas(
            'testModel',
            fn ($owner) => $owner->where('name', "Owner {$value}")
        )
    );

    $result = (new OptionScopeResolver())->apply(RelatedModel::query(), $field, ['owner' => 15]);

    expect($result->pluck('title')->all())->toBe(['Item for owner 15']);
});

it('ignores request keys that were not declared as scopes', function () {
    seedOwnersAndItems();

    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        Input::make('Item', 'item_id'),
        ['title' => 'Item for owner 5', 'test_model_id' => 5]
    );

    expect($result->count())->toBe(3);
});

it('applies several scopes as AND', function () {
    seedOwnersAndItems();

    $field = Input::make('Item', 'item_id')
        ->scopedBy('owner', 'test_model_id')
        ->scopedBy('name', 'title');

    $result = (new OptionScopeResolver())->apply(
        RelatedModel::query(),
        $field,
        ['owner' => 5, 'name' => 'Item for owner 51']
    );

    expect($result->count())->toBe(0);
});
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `./vendor/bin/pest tests/ServiceIntegration/OptionScopeResolverTest.php`
Expected: FAIL con `Class "SchoolAid\Nadota\Http\Services\FieldOptions\OptionScopeResolver" not found`

- [ ] **Step 3: Escribir la implementación**

`src/Http/Services/FieldOptions/OptionScopeResolver.php`:

```php
<?php

namespace SchoolAid\Nadota\Http\Services\FieldOptions;

use Illuminate\Database\Eloquent\Builder;
use SchoolAid\Nadota\Http\Fields\Field;

/**
 * Applies a field's declared option scopes to its options query.
 *
 * Only scopes declared on the server are applied, and the column always comes
 * from that declaration: the request supplies the value and nothing else.
 */
class OptionScopeResolver
{
    /**
     * @param array<string, mixed> $values Raw scope values from the request (scope[...]).
     * @return Builder|null Null when a strict scope has no value, meaning the query is
     *                      unsatisfiable and must not be executed as it stands.
     */
    public function apply(Builder $query, Field $field, array $values): ?Builder
    {
        if (! $field->hasOptionScopes()) {
            return $query;
        }

        foreach ($field->getOptionScopes() as $key => $scope) {
            $value = $values[$key] ?? null;

            if ($this->isMissing($value)) {
                if ($scope->optional) {
                    continue;
                }

                return null;
            }

            if ($scope->usesCallback()) {
                $query = call_user_func($scope->callback, $query, $value) ?? $query;
                continue;
            }

            is_array($value)
                ? $query->whereIn($scope->column, $value)
                : $query->where($scope->column, '=', $value);
        }

        return $query;
    }

    /**
     * A scope value is missing when it is null, an empty string, an empty array,
     * or the literal string "null" that query strings produce.
     */
    protected function isMissing(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || $value === 'null';
    }
}
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

Run: `./vendor/bin/pest tests/ServiceIntegration/OptionScopeResolverTest.php`
Expected: PASS, 12 tests (el caso con dataset cuenta 4)

- [ ] **Step 5: Commit**

```bash
git add src/Http/Services/FieldOptions/OptionScopeResolver.php \
        tests/ServiceIntegration/OptionScopeResolverTest.php
git commit -m "feat: add OptionScopeResolver for scoped option queries"
```

---

### Task 4: Enganche en el pipeline de strategies

**Files:**
- Modify: `src/Http/Services/FieldOptions/Traits/SearchesOptions.php` (método `getCommonParams`)
- Modify: `src/Http/Services/FieldOptions/Strategies/AbstractOptionsStrategy.php:135-145`
- Modify: `src/Http/Services/FieldOptionsService.php` (array `$params` en `getFieldOptions`, ~línea 125)
- Create: `tests/Resources/RelatedModelResource.php`
- Test: `tests/ServiceIntegration/ScopedFieldOptionsTest.php`

**Interfaces:**
- Consumes: `OptionScopeResolver::apply()` (Task 3), `Field::scopedBy()` (Task 1).
- Produces: el parámetro `scope` disponible en `getCommonParams()` y honrado por `BelongsToOptionsStrategy::fetchOptions()` y por el resto de strategies que heredan de `AbstractOptionsStrategy`.

- [ ] **Step 1: Crear el resource de pruebas para el modelo relacionado**

`tests/Resources/RelatedModelResource.php`:

```php
<?php

namespace SchoolAid\Nadota\Tests\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\RelatedModel;

class RelatedModelResource extends Resource
{
    public string $model = RelatedModel::class;

    /**
     * Options search reads this resource property, not the fields' ->searchable()
     * flags: SearchesOptions::applyResourceSearch() only consults
     * getSearchableAttributes(). Leaving it empty makes every search match.
     */
    protected array $searchableAttributes = ['title'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Title', 'title')->searchable(),
        ];
    }
}
```

- [ ] **Step 2: Escribir el test que falla**

Crear `tests/ServiceIntegration/ScopedFieldOptionsTest.php`:

```php
<?php

use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\BelongsToOptionsStrategy;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\RelatedModelResource;

/**
 * Owners 5, 15 and 51 exist so an exact match on 5 can be told apart from a
 * LIKE '%5%', which the generic filters[] channel would produce.
 */
function seedScopedOptions(): void
{
    foreach ([5, 15, 51] as $ownerId) {
        $owner = new TestModel(['name' => "Owner {$ownerId}"]);
        $owner->id = $ownerId;
        $owner->save();

        RelatedModel::query()->create([
            'title' => "Item for owner {$ownerId}",
            'test_model_id' => $ownerId,
        ]);
    }
}

function fetchScopedOptions(BelongsTo $field, array $params = []): array
{
    return (new BelongsToOptionsStrategy())->fetchOptions(
        createNadotaRequest(),
        createTestResource(),
        $field,
        $params
    );
}

function scopedItemField(): BelongsTo
{
    return BelongsTo::make('Item', 'item', RelatedModelResource::class)
        ->scopedBy('owner', 'test_model_id');
}

it('returns every option when the field declares no scopes', function () {
    seedScopedOptions();

    $options = fetchScopedOptions(
        BelongsTo::make('Item', 'item', RelatedModelResource::class)
    );

    expect($options)->toHaveCount(3);
});

it('filters options by the exact scope value', function () {
    seedScopedOptions();

    $options = fetchScopedOptions(scopedItemField(), ['scope' => ['owner' => 5]]);

    expect($options)->toHaveCount(1)
        ->and($options[0]['label'])->toBe('Item for owner 5');
});

it('returns no options when a strict scope has no value', function () {
    seedScopedOptions();

    $options = fetchScopedOptions(scopedItemField());

    expect($options)->toBe([]);
});

it('returns every option when an optional scope has no value', function () {
    seedScopedOptions();

    $field = BelongsTo::make('Item', 'item', RelatedModelResource::class)
        ->scopedBy('owner', 'test_model_id', optional: true);

    expect(fetchScopedOptions($field))->toHaveCount(3);
});

it('ignores scope keys the field did not declare', function () {
    seedScopedOptions();

    $options = fetchScopedOptions(
        BelongsTo::make('Item', 'item', RelatedModelResource::class),
        ['scope' => ['title' => 'Item for owner 5']]
    );

    expect($options)->toHaveCount(3);
});

it('combines a scope with a search term', function () {
    seedScopedOptions();

    RelatedModel::query()->create([
        'title' => 'Another item for owner 5',
        'test_model_id' => 5,
    ]);

    $options = fetchScopedOptions(scopedItemField(), [
        'scope' => ['owner' => 5],
        'search' => 'Another',
    ]);

    expect($options)->toHaveCount(1)
        ->and($options[0]['label'])->toBe('Another item for owner 5');
});

it('reads the scope from the request when it is not passed in params', function () {
    seedScopedOptions();

    $request = createNadotaRequest(['scope' => ['owner' => 51]]);

    $options = (new BelongsToOptionsStrategy())->fetchOptions(
        $request,
        createTestResource(),
        scopedItemField()
    );

    expect($options)->toHaveCount(1)
        ->and($options[0]['label'])->toBe('Item for owner 51');
});
```

- [ ] **Step 3: Ejecutar el test para verificar que falla**

Run: `./vendor/bin/pest tests/ServiceIntegration/ScopedFieldOptionsTest.php`
Expected: FAIL — "filters options by the exact scope value" devuelve 3 opciones en vez de 1, porque el scope todavía no se aplica.

- [ ] **Step 4: Propagar `scope` en `getCommonParams()`**

En `src/Http/Services/FieldOptions/Traits/SearchesOptions.php`, dentro del array que devuelve `getCommonParams()`, después de la entrada `'filters'`:

```php
            'scope' => $params['scope'] ?? $request->get('scope', []),
```

- [ ] **Step 5: Aplicar el resolver en `AbstractOptionsStrategy`**

En `src/Http/Services/FieldOptions/Strategies/AbstractOptionsStrategy.php`, añadir el import:

```php
use SchoolAid\Nadota\Http\Services\FieldOptions\OptionScopeResolver;
```

En `buildAndExecuteQuery()`, justo **después** del bloque `if (method_exists($field, 'hasOptionsScope') && $field->hasOptionsScope()) { ... }` y **antes** del bloque de `$filters`:

```php
        // Apply declared option scopes. Values arrive as scope[...] in the request;
        // the columns come from the field's own declaration.
        $scoped = (new OptionScopeResolver())->apply($query, $field, $commonParams['scope'] ?? []);

        if ($scoped === null) {
            // A strict scope has no value: no options, and no trip to the database.
            return collect();
        }

        $query = $scoped;
```

- [ ] **Step 6: Propagar `scope` desde el servicio**

En `src/Http/Services/FieldOptionsService.php`, en `getFieldOptions()`, dentro del `array_merge` que construye `$params`, después de la entrada `'filters'`:

```php
            'scope' => $request->get('scope', []),
```

- [ ] **Step 7: Ejecutar el test para verificar que pasa**

Run: `./vendor/bin/pest tests/ServiceIntegration/ScopedFieldOptionsTest.php`
Expected: PASS, 7 tests

- [ ] **Step 8: Ejecutar la suite completa**

Run: `composer test`
Expected: la línea final sigue diciendo `105 failed`; los tests nuevos aparecen entre los que pasan. Cualquier fallo nuevo es tuyo.

- [ ] **Step 9: Commit**

```bash
git add src/Http/Services/FieldOptions/Traits/SearchesOptions.php \
        src/Http/Services/FieldOptions/Strategies/AbstractOptionsStrategy.php \
        src/Http/Services/FieldOptionsService.php \
        tests/Resources/RelatedModelResource.php \
        tests/ServiceIntegration/ScopedFieldOptionsTest.php
git commit -m "feat: apply option scopes in the field options strategies"
```

---

### Task 5: Enganche en el endpoint paginado

**Files:**
- Modify: `src/Http/Services/FieldOptionsService.php:246-260` (método `getPaginatedOptions`)
- Create: `tests/Resources/ScopedOptionsResource.php`
- Test: `tests/ServiceIntegration/ScopedPaginatedOptionsTest.php`

**Interfaces:**
- Consumes: `OptionScopeResolver::apply()` (Task 3), `RelatedModelResource` (Task 4).
- Produces: nada nuevo hacia otras tareas.

`getPaginatedOptions()` no usa el sistema de strategies: construye su propio query. Por eso necesita su propio enganche.

- [ ] **Step 1: Crear el resource padre de pruebas**

`tests/Resources/ScopedOptionsResource.php`:

```php
<?php

namespace SchoolAid\Nadota\Tests\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

class ScopedOptionsResource extends Resource
{
    public string $model = TestModel::class;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name'),

            BelongsTo::make('Item', 'item', RelatedModelResource::class)
                ->scopedBy('owner', 'test_model_id'),
        ];
    }
}
```

- [ ] **Step 2: Escribir el test que falla**

Crear `tests/ServiceIntegration/ScopedPaginatedOptionsTest.php`:

```php
<?php

use SchoolAid\Nadota\Http\Services\FieldOptionsService;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\ScopedOptionsResource;

beforeEach(function () {
    // ResourceManager discovers resources from the filesystem, which does not work
    // for the test namespace, so the registry is seeded directly.
    $property = new ReflectionProperty(ResourceManager::class, 'resources');
    $property->setAccessible(true);
    $property->setValue(null, collect([
        'scoped-options' => [
            'class' => ScopedOptionsResource::class,
            'model' => TestModel::class,
        ],
    ]));

    foreach ([5, 15, 51] as $ownerId) {
        $owner = new TestModel(['name' => "Owner {$ownerId}"]);
        $owner->id = $ownerId;
        $owner->save();

        RelatedModel::query()->create([
            'title' => "Item for owner {$ownerId}",
            'test_model_id' => $ownerId,
        ]);
    }
});

function paginatedOptions(array $query = []): array
{
    return (new FieldOptionsService(new ResourceManager()))->getPaginatedOptions(
        createNadotaRequest($query),
        'scoped-options',
        'item'
    );
}

it('paginates only the options matching the scope', function () {
    $response = paginatedOptions(['scope' => ['owner' => 5]]);

    expect($response['success'])->toBeTrue()
        ->and($response['data'])->toHaveCount(1)
        ->and($response['data'][0]['label'])->toBe('Item for owner 5')
        ->and($response['meta']['total'])->toBe(1);
});

it('returns an empty page with full meta when a strict scope has no value', function () {
    $response = paginatedOptions();

    expect($response['success'])->toBeTrue()
        ->and($response['data'])->toBe([])
        ->and($response['meta']['total'])->toBe(0)
        ->and($response['meta']['last_page'])->toBe(1)
        ->and($response['meta'])->toHaveKeys([
            'current_page', 'per_page', 'total', 'last_page', 'from', 'to',
        ]);
});

it('matches the scope value exactly', function () {
    $response = paginatedOptions(['scope' => ['owner' => 5]]);

    expect(collect($response['data'])->pluck('label')->all())
        ->toBe(['Item for owner 5']);
});
```

- [ ] **Step 3: Ejecutar el test para verificar que falla**

Run: `./vendor/bin/pest tests/ServiceIntegration/ScopedPaginatedOptionsTest.php`
Expected: FAIL — devuelve las 3 opciones y `total` 3 en vez de 1.

- [ ] **Step 4: Aplicar el resolver en `getPaginatedOptions()`**

En `src/Http/Services/FieldOptionsService.php`, añadir el import:

```php
use SchoolAid\Nadota\Http\Services\FieldOptions\OptionScopeResolver;
```

En `getPaginatedOptions()`, justo **después** del bloque `if (method_exists($fieldResourceInstance, 'optionsQuery')) { ... }` y **antes** del bloque `if (!empty($filters))`:

```php
        // Apply declared option scopes. When a strict scope has no value the query is
        // made unsatisfiable rather than short-circuited, so the paginator still builds
        // the meta block the frontend expects.
        $scoped = (new OptionScopeResolver())->apply($query, $field, $request->get('scope', []));

        if ($scoped === null) {
            $query->whereRaw('1 = 0');
        } else {
            $query = $scoped;
        }
```

- [ ] **Step 5: Ejecutar el test para verificar que pasa**

Run: `./vendor/bin/pest tests/ServiceIntegration/ScopedPaginatedOptionsTest.php`
Expected: PASS, 3 tests

- [ ] **Step 6: Ejecutar la suite completa**

Run: `composer test`
Expected: la línea final sigue diciendo `105 failed`; los tests nuevos aparecen entre los que pasan. Cualquier fallo nuevo es tuyo.

- [ ] **Step 7: Commit**

```bash
git add src/Http/Services/FieldOptionsService.php \
        tests/Resources/ScopedOptionsResource.php \
        tests/ServiceIntegration/ScopedPaginatedOptionsTest.php
git commit -m "feat: apply option scopes in the paginated options endpoint"
```

---

### Task 6: El field `Lookup`

**Files:**
- Create: `src/Http/Fields/Lookup.php`
- Modify: `src/Http/Fields/Enums/FieldType.php`
- Modify: `config/nadota.php:76-79` (insertar tras la entrada `select`)
- Test: `tests/Unit/Fields/LookupTest.php`
- Test: `tests/ServiceIntegration/LookupOptionsTest.php`

**Interfaces:**
- Consumes: `Field` y su `virtual()`, `onlyOnForms()`, `resource()`; `FieldType`; `DefaultOptionsStrategy` (existente); `Field::scopedBy()` (Task 1); el enganche del resolver (Task 4).
- Produces: `Lookup::make(string $label, string $attribute): static`, tipo serializado `lookup`, servido por `DefaultOptionsStrategy` sin registrar ninguna strategy nueva.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Unit/Fields/LookupTest.php`:

```php
<?php

use SchoolAid\Nadota\Http\Fields\Lookup;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\RelatedModelResource;

it('can be instantiated', function () {
    $field = Lookup::make('Grade', 'grade');

    expect($field)->toBeInstanceOf(Lookup::class)
        ->and($field->getName())->toBe('Grade')
        ->and($field->getAttribute())->toBe('grade')
        ->and($field->getType())->toBe('lookup');
});

it('is virtual without being told', function () {
    $field = Lookup::make('Grade', 'grade');

    expect($field->isVirtual())->toBeTrue()
        ->and($field->shouldSkipFill())->toBeTrue();
});

it('contributes no columns to the select clause', function () {
    $field = Lookup::make('Grade', 'grade');

    expect($field->getColumnsForSelect(TestModel::class))->toBe([]);
});

it('never fills the model', function () {
    $field = Lookup::make('Grade', 'grade');
    $model = new TestModel();

    $field->fill(createNadotaRequest(['grade' => 7]), $model);

    expect($model->getAttributes())->toBe([]);
});

it('resolves to null', function () {
    $model = new TestModel(['name' => 'Ada']);
    $model->grade = 7;

    $field = Lookup::make('Grade', 'grade');

    expect($field->resolve(createNadotaRequest(), $model, createTestResource()))->toBeNull();
});

it('is shown on forms only', function () {
    $field = Lookup::make('Grade', 'grade');
    $request = createNadotaRequest();

    expect($field->isShowOnIndex($request, null))->toBeFalse()
        ->and($field->isShowOnDetail($request, null))->toBeFalse()
        ->and($field->isShowOnCreation($request, null))->toBeTrue()
        ->and($field->isShowOnUpdate($request, null))->toBeTrue();
});

it('is not a relationship field', function () {
    $field = Lookup::make('Grade', 'grade')->resource(RelatedModelResource::class);

    expect($field->isRelationship())->toBeFalse();
});

it('exposes an options url once a resource is set', function () {
    $field = Lookup::make('Grade', 'grade')->resource(RelatedModelResource::class);

    $payload = $field->toArray(createNadotaRequest(), null, createTestResource());

    expect($payload['optionsUrl'])->toContain('/field/grade/options')
        ->and($payload['type'])->toBe('lookup');
});

it('can be the source of another field option scope', function () {
    $lookup = Lookup::make('Grade', 'grade');
    $student = Lookup::make('Student', 'student')->scopedBy('grade', 'grade_id');

    expect($lookup->hasOptionScopes())->toBeFalse()
        ->and($student->getOptionScopes()['grade']->column)->toBe('grade_id');
});
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `./vendor/bin/pest tests/Unit/Fields/LookupTest.php`
Expected: FAIL con `Class "SchoolAid\Nadota\Http\Fields\Lookup" not found`

- [ ] **Step 3: Añadir el caso al enum**

En `src/Http/Fields/Enums/FieldType.php`, añadir junto a los demás casos:

```php
    case LOOKUP = 'lookup';
```

- [ ] **Step 4: Crear el field**

`src/Http/Fields/Lookup.php`:

```php
<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

/**
 * A form-only field whose value is never persisted.
 *
 * Its options come from a Nadota Resource, and its value exists to be consumed
 * by another field's scopedBy(), narrowing that field's options.
 *
 * Lookup fields still take part in validation: marking one ->required() will
 * make store/update require the value even though nothing is written with it.
 */
class Lookup extends Field
{
    public function __construct(string $label, string $attribute)
    {
        parent::__construct(
            $label,
            $attribute,
            FieldType::LOOKUP->value,
            static::safeConfig('nadota.fields.lookup.component', 'FieldLookup')
        );

        $this->virtual();
        $this->onlyOnForms();
    }

    /**
     * Lookup fields hold no model state.
     */
    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        return null;
    }

    /**
     * Lookup fields are never persisted. The value is read straight from the
     * request by the option scopes that depend on it.
     */
    public function fill(Request $request, Model $model): void
    {
        // Intentionally empty.
    }
}
```

- [ ] **Step 5: Añadir la entrada de configuración**

En `config/nadota.php`, inmediatamente después del bloque `'select' => [...]` (líneas 76-79):

```php
        'lookup' => [
            'type' => 'lookup',
            'component' => 'FieldLookup'
        ],
```

- [ ] **Step 6: Ejecutar el test para verificar que pasa**

Run: `./vendor/bin/pest tests/Unit/Fields/LookupTest.php`
Expected: PASS, 9 tests

- [ ] **Step 7: Escribir el test de integración del pipeline de opciones**

El spec afirma que un `Lookup` se sirve por `DefaultOptionsStrategy` sin cambios y que puede encadenarse con otro. Nada lo verifica todavía.

Crear `tests/ServiceIntegration/LookupOptionsTest.php`:

```php
<?php

use SchoolAid\Nadota\Http\Fields\Lookup;
use SchoolAid\Nadota\Http\Services\FieldOptions\Strategies\DefaultOptionsStrategy;
use SchoolAid\Nadota\Tests\Models\RelatedModel;
use SchoolAid\Nadota\Tests\Models\TestModel;
use SchoolAid\Nadota\Tests\Resources\RelatedModelResource;

beforeEach(function () {
    foreach ([5, 15, 51] as $ownerId) {
        $owner = new TestModel(['name' => "Owner {$ownerId}"]);
        $owner->id = $ownerId;
        $owner->save();

        RelatedModel::query()->create([
            'title' => "Item for owner {$ownerId}",
            'test_model_id' => $ownerId,
        ]);
    }
});

function fetchLookupOptions(Lookup $field, array $params = []): array
{
    return (new DefaultOptionsStrategy())->fetchOptions(
        createNadotaRequest(),
        createTestResource(),
        $field,
        $params
    );
}

it('serves lookup options from its resource without a dedicated strategy', function () {
    $field = Lookup::make('Item', 'item')->resource(RelatedModelResource::class);

    expect((new DefaultOptionsStrategy())->canHandle($field))->toBeTrue()
        ->and(fetchLookupOptions($field))->toHaveCount(3);
});

it('narrows a lookup with a scope, so lookups can cascade', function () {
    $field = Lookup::make('Item', 'item')
        ->resource(RelatedModelResource::class)
        ->scopedBy('owner', 'test_model_id');

    $options = fetchLookupOptions($field, ['scope' => ['owner' => 15]]);

    expect($options)->toHaveCount(1)
        ->and($options[0]['label'])->toBe('Item for owner 15');
});

it('returns nothing for a scoped lookup with no value', function () {
    $field = Lookup::make('Item', 'item')
        ->resource(RelatedModelResource::class)
        ->scopedBy('owner', 'test_model_id');

    expect(fetchLookupOptions($field))->toBe([]);
});
```

- [ ] **Step 8: Ejecutar el test de integración**

Run: `./vendor/bin/pest tests/ServiceIntegration/LookupOptionsTest.php`
Expected: PASS, 3 tests

- [ ] **Step 9: Ejecutar la suite completa**

Run: `composer test`
Expected: la línea final sigue diciendo `105 failed`; los tests nuevos aparecen entre los que pasan. Cualquier fallo nuevo es tuyo.

- [ ] **Step 10: Commit**

```bash
git add src/Http/Fields/Lookup.php \
        src/Http/Fields/Enums/FieldType.php \
        config/nadota.php \
        tests/Unit/Fields/LookupTest.php \
        tests/ServiceIntegration/LookupOptionsTest.php
git commit -m "feat: add Lookup field for non-persisted form values"
```

---

### Task 7: Documentación

**Files:**
- Create: `docs/fields/lookup.md`
- Modify: `docs/fields/README.md`
- Modify: `docs/fields/depends-on.md` (sección "Dynamic options")
- Modify: `docs/fields/relation-fields.md`

**Interfaces:**
- Consumes: la API pública de las tareas 1-6.
- Produces: nada de código.

- [ ] **Step 1: Escribir la página de documentación**

Crear `docs/fields/lookup.md`. Contenido completo:

````markdown
# Lookup Fields and Scoped Options

A `Lookup` field appears in a form, is validated, and is **never written to the
database**. Its only job is to hold a value that other fields use to narrow their
own options — a grade that filters the list of students, a campus that filters the
list of classrooms.

## Quick start

```php
use SchoolAid\Nadota\Http\Fields\Lookup;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;

public function fields(NadotaRequest $request): array
{
    return [
        Lookup::make('Grade', 'grade')
            ->resource(GradeResource::class)
            ->searchable()
            ->helpText('Narrows the students you can pick'),

        BelongsTo::make('Student', 'student', StudentResource::class)
            ->scopedBy('grade', 'grade_id'),
    ];
}
```

Picking a grade re-fetches the student options, constrained to that grade. The
grade itself never reaches the model.

## The Lookup field

| Behaviour | Detail |
| --------- | ------ |
| Persistence | Never. The field is virtual and skips fill entirely. |
| Visibility | Forms only. There is no stored value to show on index or detail. |
| Options | Served by the field options endpoint, from the Resource given to `->resource()`. Search, ordering and `displayLabel()` all work as they do for relation fields. |
| Validation | Applies as usual. A `->required()` Lookup makes store and update require the value, even though nothing is written with it. |

Any field can play the same role: `Select::make('Grade', 'grade')->virtual()->options([...])`
works as a scope source with static options. `Lookup` is the Resource-backed variant.

## Scoping options with `scopedBy()`

```php
public function scopedBy(
    string $field,
    string|Closure|null $target = null,
    bool $optional = false
): static
```

Available on every field.

- `$field` — the observed field's key in the form.
- `$target` — a column on the related model, or `fn(Builder $query, mixed $value): Builder`.
  Defaults to the observed field name in snake_case with an `_id` suffix.
- `$optional` — when `false` (the default), an observed field with no value makes the
  options query return nothing.

```php
// Column by convention: 'grade' => 'grade_id'
->scopedBy('grade')

// Explicit column
->scopedBy('grade', 'current_grade_id')

// Closure, for a link through an intermediate relation
->scopedBy('grade', fn ($query, $value) => $query->whereHas(
    'enrollments', fn ($q) => $q->where('grade_id', $value)
))

// Optional: with no value, it does not narrow anything
->scopedBy('campus', 'campus_id', optional: true)

// Several scopes are applied as AND
->scopedBy('grade', 'grade_id')->scopedBy('campus', 'campus_id')
```

Scopes are keyed by the observed field. Declaring one twice on the same field
replaces the earlier declaration.

Declaring a scope also registers the observed field with `dependsOn()` and turns on
`clearOnDependencyChange()`, so changing the grade clears the selected student
instead of submitting a stale one. Note that `clearOnChange` is a single flag on the
field: it applies to every dependency of that field, not just the scoped one.

## Cascading further

Because `scopedBy()` lives on the base field, a `Lookup` can itself be scoped by
another one:

```php
Lookup::make('Level', 'level')->resource(LevelResource::class),

Lookup::make('Grade', 'grade')
    ->resource(GradeResource::class)
    ->scopedBy('level', 'level_id'),

BelongsTo::make('Student', 'student', StudentResource::class)
    ->scopedBy('grade', 'grade_id'),
```

## Wire format

The field's payload tells the client which values to send with its options request:

```json
{
  "key": "student",
  "optionsUrl": "/nadota-api/enrollments/resource/field/student/options",
  "dependencies": {
    "fields": ["grade"],
    "options": {
      "scope": [
        { "field": "grade", "optional": false }
      ]
    },
    "clearOnChange": true
  }
}
```

The client sends each value under `scope[<field>]`:

```
GET /nadota-api/enrollments/resource/field/student/options?scope[grade]=5&search=ana
```

`optional` is included so the client can disable the dependent control until the
observed field has a value.

## Why not `filters[]`

The generic `filters[]` parameter turns a scalar into `where(column, 'like', '%5%')`,
which for a foreign key also matches 5, 15, 51 and 105. It also accepts any column
name from the client unless the target resource defines `getAllowedOptionsFilters()`.

Option scopes are the opposite: the column comes from the server-side declaration and
the request only supplies the value, compared with `=` (or `whereIn` for arrays). Any
`scope[]` key the field did not declare is ignored.

## What scopes do not do

Scopes constrain the **options query only**. They are not enforced on store or update:
a request that posts a student from another grade is saved. `clearOnDependencyChange`
covers the realistic mistake — a stale selection after changing the grade — but if you
need a hard guarantee, add your own validation rule on the relation field.
````

- [ ] **Step 2: Enlazar desde el catálogo de fields**

`docs/fields/README.md` es un catálogo de tablas por categoría. Añadir una fila a la tabla de la sección `### Special`, después de la fila de `CustomComponent`:

```markdown
| `Lookup` | Form-only value that narrows another field's options (no DB column). | [lookup.md](lookup.md) |
```

- [ ] **Step 3: Enlazar desde `depends-on.md`**

En `docs/fields/depends-on.md`, al final de la sección "Dynamic options", añadir:

```markdown
`cascadeFrom()` and `optionsFromEndpoint()` are frontend-only contracts: the backend
serializes them and the client does the rest. When you want the **server** to narrow
the options query — with the column fixed server-side and the request supplying only
the value — use `scopedBy()` instead. See [Lookup Fields and Scoped Options](lookup.md).
```

- [ ] **Step 4: Enlazar desde `relation-fields.md`**

En `docs/fields/relation-fields.md`, añadir al final del documento:

```markdown
## Narrowing options from another form field

A relation field's options can be constrained by the value of another field in the
same form, without that field being persisted. See
[Lookup Fields and Scoped Options](lookup.md).
```

- [ ] **Step 5: Verificar que los enlaces relativos resuelven**

Run: `ls docs/fields/lookup.md docs/fields/README.md docs/fields/depends-on.md docs/fields/relation-fields.md`
Expected: los cuatro archivos existen

- [ ] **Step 6: Ejecutar la suite completa una última vez**

Run: `composer test`
Expected: la línea final sigue diciendo `105 failed`; los tests nuevos aparecen entre los que pasan. Cualquier fallo nuevo es tuyo.

- [ ] **Step 7: Commit**

```bash
git add docs/fields/lookup.md docs/fields/README.md \
        docs/fields/depends-on.md docs/fields/relation-fields.md
git commit -m "docs: document Lookup fields and scoped options"
```
