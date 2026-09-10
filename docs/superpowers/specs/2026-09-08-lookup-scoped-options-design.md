# Lookup fields y opciones acotadas (`scopedBy`)

- **Fecha:** 2026-09-08
- **Estado:** aprobado, pendiente de plan de implementación
- **Rama:** `docs/rework-documentation`

## 1. Problema

En un formulario hace falta un control que **no se persiste** y cuyo único trabajo es **acotar las opciones de otro field**. Caso motivador: en el form de un resource con `BelongsTo` a Student, elegir un Grado para que el selector de Alumno solo ofrezca los alumnos de ese grado. El Grado nunca llega al modelo.

### Qué existe hoy

| Pieza | Estado |
| ----- | ------ |
| `Field::virtual()` (`src/Http/Fields/Field.php:606`) | Ya resuelve "no se persiste": marca `skipFill`, honrado en `AbstractResourcePersistService.php:201`, y `getColumnsForSelect()` devuelve `[]` (`Field.php:634`). |
| `DependsOnTrait` — `dependsOn()`, `cascadeFrom()`, `optionsFromEndpoint()` | Existe, pero es **contrato de frontend puro**: se serializa en `dependencies` y el backend nunca lo lee. |
| `filters[]` en el endpoint de options (`SearchesOptions::applyFilters`) | Existe, con whitelist **opcional** (`getAllowedOptionsFilters()`). |
| `optionsScope(callable)` (`RelationshipTrait.php:167`) | Recibe **solo** `$query`. No ve el request ni el valor de otro field del formulario. |
| `RelationshipTrait` incluido en la clase base `Field` (`Field.php:34`) | `resource()`, `getOptionsUrl()`, `optionsScope()` y `optionsLimit()` están disponibles en **cualquier** field, no solo en los de relación. |

### El hueco

No hay forma de declarar *"este valor virtual restringe las opciones de aquel field"* y que el backend lo aplique. La única ruta actual es que el frontend mande `filters[grade_id]=5`, con cuatro problemas:

1. `applyFilters` traduce un escalar a `where('grade_id', 'like', '%5%')` → **matchea 5, 15, 51 y 105**. Roto para claves foráneas.
2. No sabe hacer `whereHas`, así que no sirve cuando el vínculo va por una relación intermedia (inscripciones, períodos).
3. Sin `getAllowedOptionsFilters()` definido en el resource destino, el cliente puede filtrar por **cualquier** columna del modelo relacionado.
4. Nada garantiza que el valor enviado corresponda a un scope real: es un canal opaco.

## 2. Decisiones de alcance

| Decisión | Elección |
| -------- | -------- |
| ¿Hasta dónde llega la restricción? | **Solo el query de opciones.** No se toca `store`/`update` ni la validación de persistencia. |
| ¿Qué tan expresiva? | **Columna + closure de escape.** La columna cubre el caso declarativo; el closure cubre `whereHas` y cualquier caso raro. |
| ¿Qué forma tiene el field virtual? | **Clase nueva dedicada** (`Lookup`), desacoplada del modelo por construcción. |
| ¿Sin valor, qué devuelve? | **Vacío (estricto) por defecto**, con `optional: true` para invertirlo. |
| ¿Por dónde viaja el valor? | **Espacio propio `scope[]`**, no `filters[]`. |

### Consecuencia aceptada

Al no validar en persistencia, un `POST` directo con un `student_id` de otro grado se guarda igual. Es una decisión consciente: `clearOnDependencyChange` cubre el error humano real, y `OptionScopeResolver` deja la restricción declarada y consultable, así que añadir validación más adelante no exigiría rediseñar nada.

## 3. Diseño

### 3.1 Superficie pública

```php
public function fields(NadotaRequest $request): array
{
    return [
        Lookup::make('Grado', 'grade')
            ->resource(GradeResource::class)
            ->searchable()
            ->help('Filtra los alumnos disponibles'),

        BelongsTo::make('Alumno', 'student', StudentResource::class)
            ->scopedBy('grade', 'grade_id'),
    ];
}
```

Dos piezas con una responsabilidad cada una: `Lookup` **produce** un valor que no se guarda; `scopedBy()` lo **consume** para acotar sus opciones. No se conocen entre sí más allá del key `'grade'`.

Variantes:

```php
// Columna por convención: Str::snake('grade') . '_id' => 'grade_id'
->scopedBy('grade')

// Escape hatch: relación intermedia
->scopedBy('grade', fn ($query, $value) => $query->whereHas(
    'enrollments', fn ($q) => $q->where('grade_id', $value)
))

// Filtro opcional: sin valor, no acota
->scopedBy('campus', 'campus_id', optional: true)

// Varios scopes: se aplican en AND
->scopedBy('grade', 'grade_id')->scopedBy('campus', 'campus_id')
```

`scopedBy()` no exige un `Lookup` al otro lado: consume el valor de **cualquier** field del formulario. Un `Select::make('Grade', 'grade')->virtual()->options([...])` con opciones estáticas funciona igual. `Lookup` es la variante respaldada por un Resource.

### 3.2 `Lookup` — `src/Http/Fields/Lookup.php`

Extiende `Field`. Virtual **de nacimiento**, no por flag opcional que alguien pueda olvidar.

```php
public function __construct(string $label, string $attribute)
{
    parent::__construct(
        $label,
        $attribute,
        FieldType::LOOKUP->value,
        static::safeConfig('nadota.fields.lookup.component', 'FieldLookup')
    );

    $this->virtual();      // marca virtual + skipFill
    $this->onlyOnForms();  // no hay nada que mostrar en index/detail
}
```

| Aspecto | Comportamiento | Origen |
| ------- | -------------- | ------ |
| `virtual` / `skipFill` | `true` desde el constructor | `Field::virtual()` |
| `getColumnsForSelect()` | `[]` | heredado; el padre ya corta en `virtual` (`Field.php:634`) |
| `fill()` | override a no-op explícito | defensa redundante frente a `skipFill` |
| `resolve()` | `null`, nunca toca el modelo | override |
| `isRelationship` | `false` | por defecto en `RelationshipTrait` |
| Visibilidad | solo formularios | `onlyOnForms()` (`VisibilityTrait.php:197`) |
| `optionsUrl` | generado | `RelationshipTrait::getOptionsUrl()`, emitido en `Field::toArray()` (`Field.php:186`) |
| Endpoint de opciones | servido sin cambios | `DefaultOptionsStrategy::canHandle()` acepta cualquier field con resource; `FieldOptionsService::findField()` (línea 513) lo encuentra vía `flattenFields` |

`isRelationship = false` lo mantiene fuera de eager loading, exports (`ResourceExportable.php:92`) y `getAttachedIds()`.

Cambios asociados:

- `FieldType::LOOKUP = 'lookup'` en `src/Http/Fields/Enums/FieldType.php`.
- Entrada `'lookup' => ['type' => 'lookup', 'component' => 'FieldLookup']` en `config/nadota.php`.

**Validación:** `Lookup` participa en la validación como cualquier field virtual (comportamiento vigente). Un `->required()` hará que el `store` exija el valor aunque no se persista. Es intencional y queda documentado.

### 3.3 `ScopesOptions` — `src/Http/Fields/Traits/ScopesOptions.php`

Trait propio, usado por la clase base `Field` junto a los demás (`Field.php:28-38`). No se anida dentro de `RelationshipTrait`.

Consecuencia buscada: **un `Lookup` también puede ser `scopedBy` de otro**, lo que da cascadas de N niveles (Nivel → Grado → Alumno) sin código adicional.

```php
public function scopedBy(
    string $field,
    string|Closure|null $target = null,
    bool $optional = false
): static
```

- `$target` **null** → columna por convención `Str::snake($field) . '_id'` (la misma que ya usa `BelongsTo` para inferir su FK).
- `$target` **string** → nombre de columna. `where($col, '=', $valor)`, o `whereIn` si el valor llega como array. Comparación **exacta**, nunca `like`.
- `$target` **Closure** → `fn(Builder $query, mixed $valor): Builder`.

Efectos colaterales automáticos, para que la declaración sea completa en una línea:

1. `dependsOn($field)` — el frontend ya sabe observar ese field.
2. `clearOnDependencyChange()` — al cambiar de grado se limpia el alumno seleccionado. Sin esto se guardaría un alumno del grado anterior, que es justo el fallo que el field pretende evitar.
3. Registra el scope en el `DependencyDTO` para que se serialice.

> **Nota:** `clearOnChange` en `DependencyDTO` es un flag global del field, no por dependencia. Declarar un `scopedBy` activa el limpiado ante **cualquier** dependencia de ese field. Es el comportamiento deseado en los casos previstos, pero queda registrado como limitación conocida.

Accesores: `getOptionScopes(): array<string, OptionScopeDTO>` y `hasOptionScopes(): bool`.

Los scopes se guardan **indexados por el nombre del field observado**. Declarar dos veces sobre el mismo field reemplaza la declaración anterior en vez de acumular dos condiciones; declarar sobre fields distintos las acumula y se aplican en AND.

El trait depende de métodos de `DependsOnTrait` (`dependsOn`, `clearOnDependencyChange`, `getDependencyDTO`); ambos conviven en `Field`, así que el acoplamiento se documenta en el docblock del trait.

### 3.4 `OptionScopeDTO` — `src/Http/Fields/DataTransferObjects/OptionScopeDTO.php`

Sigue la convención de `DependencyDTO` y `FieldDTO`.

```php
public function __construct(
    public string $field,
    public ?string $column = null,
    public ?Closure $callback = null,
    public bool $optional = false,
) {}
```

`column` y `callback` son mutuamente excluyentes: si se pasó un `Closure`, `column` queda `null`.

### 3.5 `OptionScopeResolver` — `src/Http/Services/FieldOptions/OptionScopeResolver.php`

Clase con un solo propósito, para no engordar `AbstractOptionsStrategy` y poder probarla aislada.

Sin estado y sin dependencias, así que se instancia directamente (`new OptionScopeResolver()`) en ambos puntos de enganche. No se registra en el contenedor: las strategies se construyen a mano en `FieldOptionsService::registerDefaultStrategies()` y no reciben inyección.

```php
/**
 * @return Builder|null  null cuando un scope estricto no trae valor
 *                       (la consulta es insatisfacible y no debe ejecutarse)
 */
public function apply(Builder $query, Field $field, array $values): ?Builder
```

Devuelve el builder (posiblemente reemplazado por un closure que retorne uno nuevo) o `null`.

Reglas:

1. Si el field no declara scopes, devuelve el query intacto.
2. Solo se aplican los scopes **declarados en el servidor**. Cualquier key extra presente en `scope[]` del request se ignora en silencio.
3. La columna sale **siempre** de la declaración; el request solo aporta el **valor**. Por construcción no hay filtrado por columnas arbitrarias, sin depender de `getAllowedOptionsFilters()`.
4. Valor ausente (`null`, `''`, `[]`, o la cadena `'null'`): con `optional: true` se salta ese scope; en estricto devuelve `null`.
5. Con valor: closure → `$callback($query, $valor)`; columna → `whereIn` si es array, `where(col, '=', valor)` si es escalar.

### 3.6 Puntos de enganche

El resolver se aplica en **dos** sitios, porque `getPaginatedOptions()` no usa el sistema de strategies: construye su propio query duplicando la lógica.

**a) `AbstractOptionsStrategy::buildAndExecuteQuery()`** — justo después del bloque de `optionsScope` de field-level (~línea 139):

```php
$scoped = (new OptionScopeResolver())->apply($query, $field, $commonParams['scope'] ?? []);

if ($scoped === null) {
    return collect();   // sin viaje a la base de datos
}

$query = $scoped;
```

`SearchesOptions::getCommonParams()` añade `'scope' => $params['scope'] ?? $request->get('scope', [])`.

**b) `FieldOptionsService::getPaginatedOptions()`** — después de `$query = $fieldModel::query();` (~línea 247):

```php
$scoped = (new OptionScopeResolver())->apply($query, $field, $request->get('scope', []));

if ($scoped === null) {
    $query->whereRaw('1 = 0');
} else {
    $query = $scoped;
}
```

Aquí se hace insatisfacible el query en vez de cortar, para que el paginador real produzca el `meta` con la forma exacta que el frontend ya consume (`current_page`, `per_page`, `total`, `last_page`, `from`, `to`). Cuesta una consulta trivial y evita fabricar a mano una respuesta paginada falsa. `whereRaw('1 = 0')` es portable entre SQLite, MySQL y PostgreSQL.

**c) `FieldOptionsService::getFieldOptions()`** — añadir `'scope' => $request->get('scope', [])` al array `$params` (~línea 125).

No se refactoriza la duplicación entera entre `getFieldOptions` y `getPaginatedOptions`: queda fuera del encargo. El resolver compartido es la mejora acotada que sí corresponde hacer aquí.

### 3.7 Contrato JSON y wire

`DependencyDTO` gana `addOptionScope(string $field, bool $optional)`, que emite dentro del bloque `options` ya existente:

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

Cada entrada es un objeto y no una cadena suelta porque el frontend necesita `optional` para decidir si deshabilita el selector mientras no haya valor, que es el comportamiento estricto acordado.

Lectura para el frontend: *"al pedir mis opciones, manda `scope[<field>]` con el valor actual de ese field"*.

```
GET /nadota-api/enrollments/resource/field/student/options?scope[grade]=5&search=ana
```

No reutiliza `filters[]` a propósito: ese canal aplica `where(col, 'like', '%5%')`, que para una FK matchea 5, 15, 51 y 105.

## 4. Archivos afectados

**Nuevos**

- `src/Http/Fields/Lookup.php`
- `src/Http/Fields/Traits/ScopesOptions.php`
- `src/Http/Fields/DataTransferObjects/OptionScopeDTO.php`
- `src/Http/Services/FieldOptions/OptionScopeResolver.php`
- `docs/fields/lookup.md`
- `tests/Unit/Fields/LookupTest.php`
- `tests/Unit/Fields/Traits/ScopesOptionsTest.php`
- `tests/ServiceIntegration/OptionScopeIntegrationTest.php`

**Modificados**

- `src/Http/Fields/Field.php` — `use ScopesOptions;`
- `src/Http/Fields/Enums/FieldType.php` — `case LOOKUP`
- `src/Http/Fields/DataTransferObjects/DependencyDTO.php` — `addOptionScope()` y su serialización
- `src/Http/Services/FieldOptions/Strategies/AbstractOptionsStrategy.php` — enganche del resolver
- `src/Http/Services/FieldOptions/Traits/SearchesOptions.php` — `scope` en `getCommonParams()`
- `src/Http/Services/FieldOptionsService.php` — `scope` en `getFieldOptions()` y enganche en `getPaginatedOptions()`
- `config/nadota.php` — entrada `fields.lookup`
- `docs/fields/depends-on.md` y `docs/fields/relation-fields.md` — enlaces cruzados
- `docs/fields/README.md` — índice

La documentación pública (`docs/fields/lookup.md` y los enlaces cruzados) se escribe **en inglés**, para no romper la consistencia del resto de `docs/`. Este spec queda en español por ser documento de trabajo interno.

## 5. Plan de pruebas

**Unit — `tests/Unit/Fields/LookupTest.php`**

- Es virtual y `skipFill` sin llamar a `virtual()`.
- `getColumnsForSelect()` devuelve `[]`.
- `fill()` no modifica el modelo.
- Ausente de index y detail, presente en creación y actualización.
- `optionsUrl` bien formado con el prefijo de API configurado.
- El tipo serializado es `lookup`.

**Unit — `tests/Unit/Fields/Traits/ScopesOptionsTest.php`**

- `scopedBy('grade')` infiere la columna `grade_id`.
- `scopedBy('grade', 'custom_col')` respeta la columna dada.
- `scopedBy('grade', $closure)` guarda el callback y deja `column` en `null`.
- Declarar un scope llama a `dependsOn()` y activa `clearOnChange`.
- Serialización: `dependencies.options.scope` contiene `{field, optional}`.
- Varios `scopedBy` sobre fields distintos se acumulan; dos sobre el mismo field, el segundo reemplaza al primero.

**Integración — `tests/ServiceIntegration/OptionScopeIntegrationTest.php`**

- Scope con valor filtra de forma **exacta**: con grados `5`, `15` y `51` presentes, `scope[grade]=5` devuelve solo los del grado 5. Es la regresión que el camino de `filters[]` no supera.
- Scope estricto sin valor → `options` vacío.
- Scope `optional: true` sin valor → devuelve todo.
- Closure con `whereHas` sobre relación intermedia.
- Key presente en `scope[]` pero **no declarada** en el field → ignorada (no filtra, no rompe).
- Valor array → `whereIn`.
- Endpoint paginado con scope estricto sin valor → `data` vacío y `meta.total = 0` con la forma completa.
- Un `Lookup` que a su vez es `scopedBy` de otro (cascada de tres niveles).

## 6. Fuera de alcance

- Validación en `store`/`update` de que el valor elegido pertenezca al scope.
- Paths declarativos tipo `'enrollments.grade_id'` traducidos a `whereHas` — los cubre el closure.
- Refactor de la duplicación entre `getFieldOptions()` y `getPaginatedOptions()`.
- El componente `FieldLookup` del frontend, que vive en otro repositorio. Aquí solo se define el contrato JSON.

## 7. Riesgos y notas

| Riesgo | Mitigación |
| ------ | ---------- |
| `clearOnChange` es global al field, no por dependencia | Documentado como limitación conocida; el efecto es el deseado en los casos previstos. |
| El frontend debe implementar el envío de `scope[]` | El contrato queda cerrado en §3.7; hasta que se implemente, un field con scope estricto devolverá lista vacía, que es un fallo visible y no silencioso. |
| Doble punto de enganche fácil de desincronizar | El resolver es una única clase compartida; las pruebas de integración cubren ambos endpoints. |
| `Lookup` con `->required()` bloquea el `store` | Comportamiento heredado de los fields virtuales; se documenta en `docs/fields/lookup.md`. |
