# Filtros Disponibles en Nadota

Este documento describe todos los filtros disponibles en el sistema Nadota para filtrar recursos en las consultas.

## Tabla de Contenidos

- [Clase Base Filter](#clase-base-filter)
- [Filtros Disponibles](#filtros-disponibles)
  - [DynamicSelectFilter](#dynamicselectfilter)
  - [DefaultFilter](#defaultfilter)
  - [SelectFilter](#selectfilter)
  - [BooleanFilter](#booleanfilter)
  - [DateFilter](#datefilter)
  - [NumberFilter](#numberfilter)
  - [RangeFilter](#rangefilter)
- [Uso Automático desde Fields](#uso-automático-desde-fields)
- [Crear Filtros Personalizados](#crear-filtros-personalizados)

---

## Clase Base Filter

Todos los filtros extienden de la clase abstracta `Filter` que proporciona la funcionalidad base.

### Propiedades

- `name`: Nombre del filtro (para mostrar en la UI)
- `type`: Tipo del filtro (text, number, date, etc.)
- `component`: Componente frontend a usar (FilterText, FilterSelect, etc.)
- `field`: Campo de la base de datos a filtrar
- `key`: Clave única del filtro (generada automáticamente)

### Métodos Principales

- `apply(NadotaRequest $request, $query, $value)`: Método abstracto que aplica el filtro a la consulta
- `resources(NadotaRequest $request)`: Retorna las opciones disponibles para el filtro
- `toArray($request)`: Convierte el filtro a array para la respuesta JSON

---

## Filtros Disponibles

### DynamicSelectFilter

Filtro especializado para selects dinámicos que obtiene opciones desde un endpoint. Ideal para relaciones `belongsTo` y otros casos donde las opciones se cargan dinámicamente.

**Uso Automático desde Field (belongsTo):**
```php
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;

BelongsTo::make('Usuario', 'user_id', 'user')
    ->relatedModel(User::class)
    ->resource(UserResource::class)
    ->filterable(); // Genera DynamicSelectFilter automáticamente
```

**Uso Manual en Resource:**
```php
use SchoolAid\Nadota\Http\Filters\DynamicSelectFilter;

public function filters(NadotaRequest $request): array
{
    return [
        DynamicSelectFilter::make('Usuario', 'user_id', 'dynamicSelect')
            ->resourceKey('users')
            ->relation('user')
            ->searchable(true)
            ->multiple(false),
    ];
}
```

**Comportamiento:**
- Obtiene opciones dinámicamente desde el endpoint `/field/{fieldName}/options`
- Soporta búsqueda en el select
- Puede filtrar por relación usando `whereHas`
- Componente: `FilterDynamicSelect`
- Ideal para: `belongsTo` y otros selects dinámicos

**Opciones Avanzadas:**
```php
DynamicSelectFilter::make('Categoría', 'category_id', 'dynamicSelect')
    ->endpoint('/api/custom/endpoint')  // Endpoint personalizado
    ->valueField('id')                  // Campo para el valor (default: 'id')
    ->labelField('name')                // Campo para la etiqueta (default: 'name')
    ->multiple(true)                    // Permitir múltiples selecciones
    ->searchable(true)                  // Habilitar búsqueda
    ->withDefault(1)                    // Valor por defecto
    ->dependsOn(['status'])             // Dependencias hard (resetea al cambiar)
    ->softDependsOn(['type'])           // Dependencias soft (re-filtra pero mantiene valor)
    ->filtersToSend(['status', 'type']) // Filtros a enviar al endpoint
    ->relation('category')              // Relación para whereHas
    ->applyToQuery(true);               // Aplicar al query (default: true)
```

**Ejemplo de consulta SQL:**
```sql
-- Filtro directo
WHERE user_id = 1

-- Filtro con relación
WHERE EXISTS (
    SELECT 1 FROM users 
    WHERE users.id = posts.user_id 
    AND users.id = 1
)

-- Múltiples valores
WHERE user_id IN (1, 2, 3)
```

**Estructura de respuesta JSON:**
```json
{
  "id": "user-filter",
  "key": "user",
  "name": "Usuario",
  "component": "FilterDynamicSelect",
  "type": "dynamicSelect",
  "field": "user_id",
  "endpoint": "/nadota-api/users/resource/field/user_id/options",
  "options": [],
  "value": "",
  "props": {
    "endpoint": "/nadota-api/users/resource/field/user_id/options",
    "valueField": "id",
    "labelField": "name",
    "multiple": false,
    "searchable": true,
    "applyToQuery": true,
    "relation": "user"
  }
}
```

**Métodos disponibles:**
- `endpoint(string $endpoint)`: Define endpoint personalizado
- `valueField(string $field)`: Campo para el valor (default: 'id')
- `labelField(string $field)`: Campo para la etiqueta (default: 'name')
- `multiple(bool $multiple)`: Permitir múltiples selecciones
- `searchable(bool $searchable)`: Habilitar búsqueda
- `default(mixed $default)`: Valor por defecto
- `dependsOn(array $dependencies)`: Dependencias hard
- `softDependsOn(array $dependencies)`: Dependencias soft
- `filtersToSend(array $filters)`: Filtros a enviar al endpoint
- `relation(string $relation)`: Relación para whereHas
- `applyToQuery(bool $apply)`: Aplicar al query
- `resourceKey(string $key)`: Clave del resource para construir endpoint

---

### DefaultFilter

Filtro básico para campos de texto que realiza búsquedas con `LIKE`.

**Uso:**
```php
use SchoolAid\Nadota\Http\Filters\DefaultFilter;

DefaultFilter::make('Nombre', 'name', 'text');
```

**Comportamiento:**
- Aplica búsqueda con `LIKE '%valor%'` (búsqueda parcial)
- Componente: `FilterText`
- Ideal para: `text`, `textarea`, `email`, `url`, `password`

**Ejemplo de consulta SQL:**
```sql
WHERE name LIKE '%valor%'
```

---

### SelectFilter

Filtro para campos de selección con opciones predefinidas.

**Uso:**
```php
use SchoolAid\Nadota\Http\Filters\SelectFilter;

SelectFilter::make('Estado', 'status', 'select')
    ->options([
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'pending' => 'Pendiente'
    ]);
```

**Comportamiento:**
- Si el valor es un array, usa `whereIn()`
- Si el valor es único, usa `where()` con igualdad exacta
- Componente: `FilterSelect`
- Ideal para: `select`, `radio`, `checkbox_list`

**Ejemplo de consulta SQL:**
```sql
-- Valor único
WHERE status = 'active'

-- Múltiples valores
WHERE status IN ('active', 'pending')
```

---

### BooleanFilter

Filtro para campos booleanos (checkbox, toggle).

**Uso:**
```php
use SchoolAid\Nadota\Http\Filters\BooleanFilter;

BooleanFilter::make('Activo', 'is_active', 'boolean')
    ->trueValue(1)
    ->falseValue(0);
```

**Comportamiento:**
- Maneja valores: `true`, `false`, `'true'`, `'false'`, `1`, `0`, `'1'`, `'0'`
- Componente: `FilterBoolean`
- Ideal para: `checkbox`, `boolean`, `toggle`
- Opciones por defecto: "Sí" / "No"

**Ejemplo de consulta SQL:**
```sql
WHERE is_active = 1  -- para true
WHERE is_active = 0  -- para false
```

**Métodos:**
- `trueValue(mixed $value)`: Define el valor para `true`
- `falseValue(mixed $value)`: Define el valor para `false`

---

### DateFilter

Filtro para campos de fecha y fecha/hora.

**Uso - Filtro único:**
```php
use SchoolAid\Nadota\Http\Filters\DateFilter;

DateFilter::make('Fecha de Creación', 'created_at', 'date');
```

**Uso - Filtro de rango:**
```php
DateFilter::make('Fecha de Creación', 'created_at', 'date')
    ->range(true);
```

**Comportamiento:**
- **Modo único**: Usa `whereDate()` para igualdad exacta
- **Modo rango**: Usa `whereBetween()`, `>=` o `<=` según los valores proporcionados
- Componente: `FilterDate` (único) o `FilterDateRange` (rango)
- Ideal para: `date`, `datetime`

**Ejemplo de consulta SQL:**
```sql
-- Modo único
WHERE DATE(created_at) = '2024-01-15'

-- Modo rango
WHERE created_at BETWEEN '2024-01-01' AND '2024-01-31'
WHERE created_at >= '2024-01-01'  -- solo start
WHERE created_at <= '2024-01-31'  -- solo end
```

**Formato de valor para rango:**
```json
{
  "start": "2024-01-01",
  "end": "2024-01-31"
}
```

**Métodos:**
- `range(bool $isRange = true)`: Activa/desactiva el modo rango

---

### NumberFilter

Filtro para campos numéricos.

**Uso - Filtro exacto:**
```php
use SchoolAid\Nadota\Http\Filters\NumberFilter;

NumberFilter::make('Precio', 'price', 'number');
```

**Uso - Filtro de rango:**
```php
NumberFilter::make('Precio', 'price', 'number')
    ->range(true);
```

**Comportamiento:**
- **Modo exacto**: Usa `where()` con igualdad exacta
- **Modo rango**: Usa `whereBetween()`, `>=` o `<=` según los valores proporcionados
- Componente: `FilterNumber` (exacto) o `FilterNumberRange` (rango)
- Ideal para: `number`

**Ejemplo de consulta SQL:**
```sql
-- Modo exacto
WHERE price = 100

-- Modo rango
WHERE price BETWEEN 10 AND 100
WHERE price >= 10   -- solo start
WHERE price <= 100  -- solo end
```

**Formato de valor para rango:**
```json
{
  "start": 10,
  "end": 100
}
```

**Métodos:**
- `range(bool $isRange = true)`: Activa/desactiva el modo rango

---

### RangeFilter

Filtro genérico de rango para cualquier tipo de campo.

**Uso:**
```php
use SchoolAid\Nadota\Http\Filters\RangeFilter;

RangeFilter::make('Rango de Precio', 'price', 'number', 'FilterRangeNumber');
```

**Comportamiento:**
- Aplica filtro de rango genérico con `whereBetween()`, `>=` o `<=`
- Componente: Personalizado (se debe especificar)
- Útil para casos especiales donde los filtros específicos no cubren la necesidad

**Formato de valor:**
```json
{
  "start": valor_inicio,
  "end": valor_fin
}
```

---

## Uso Automático desde Fields

Los filtros se configuran automáticamente cuando marcas un field como `filterable()`:

```php
use SchoolAid\Nadota\Http\Fields\Text;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Checkbox;
use SchoolAid\Nadota\Http\Fields\DateTime;

// Texto -> DefaultFilter
Text::make('Nombre', 'name')->filterable();

// Número -> NumberFilter (exacto)
Number::make('Precio', 'price')->filterable();

// Número con rango -> NumberFilter (rango)
Number::make('Precio', 'price')->filterableRange();

// Select -> SelectFilter (obtiene opciones automáticamente)
Select::make('Estado', 'status')
    ->options(['active' => 'Activo', 'inactive' => 'Inactivo'])
    ->filterable();

// Checkbox -> BooleanFilter (obtiene trueValue/falseValue automáticamente)
Checkbox::make('Activo', 'is_active')
    ->trueValue(1)
    ->falseValue(0)
    ->filterable();

// Fecha -> DateFilter (único)
DateTime::make('Fecha', 'created_at')->filterable();

// Fecha con rango -> DateFilter (rango)
DateTime::make('Fecha', 'created_at')->filterableRange();
```

### Mapeo Automático de Fields a Filtros

| Tipo de Field | Filtro Asignado | Modo por Defecto |
|--------------|-----------------|------------------|
| `belongsTo` | `DynamicSelectFilter` | Select dinámico con endpoint |
| `text`, `textarea`, `email`, `url`, `password` | `DefaultFilter` | Búsqueda parcial |
| `number` | `NumberFilter` | Exacto |
| `date`, `datetime` | `DateFilter` | Único |
| `checkbox`, `boolean` | `BooleanFilter` | Sí/No |
| `select`, `radio`, `checkbox_list` | `SelectFilter` | Selección |
| `hasMany`, `hasOne`, etc. | ❌ Sin filtro | Excluidos |

---

## Crear Filtros Personalizados

Para crear un filtro personalizado, extiende la clase `Filter`:

```php
<?php

namespace App\Filters;

use SchoolAid\Nadota\Http\Filters\Filter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class CustomStatusFilter extends Filter
{
    public string $name = 'Estado Personalizado';
    public string $component = 'FilterCustomStatus';
    
    public function apply(NadotaRequest $request, $query, $value)
    {
        // Lógica personalizada de filtrado
        if ($value === 'active') {
            return $query->where('status', 'active')
                        ->where('deleted_at', null);
        }
        
        return $query->where('status', $value);
    }
    
    public function resources(NadotaRequest $request): array
    {
        return [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'archived' => 'Archivado'
        ];
    }
    
    public function props(): array
    {
        return array_merge(parent::props(), [
            'customProp' => 'valor personalizado'
        ]);
    }
}
```

**Uso en Resource:**
```php
public function filters(NadotaRequest $request): array
{
    return [
        CustomStatusFilter::make(),
    ];
}
```

---

## Estructura de Respuesta JSON

Todos los filtros se serializan a un formato estándar:

```json
{
  "id": "nombre-del-filtro",
  "key": "nombredelfiltro",
  "name": "Nombre del Filtro",
  "component": "FilterText",
  "type": "text",
  "field": "campo_db",
  "options": [
    {
      "label": "Opción 1",
      "value": "opcion1"
    }
  ],
  "value": "",
  "props": {
    "isRange": false,
    "trueValue": 1,
    "falseValue": 0
  }
}
```

---

## Notas Importantes

1. **Relaciones**: Los fields de tipo `belongsTo` generan automáticamente un `DynamicSelectFilter` cuando se marca como `filterable()`. Otras relaciones (`hasMany`, `hasOne`, etc.) deben configurarse manualmente en el método `filters()` del Resource usando `DynamicSelectFilter` u otros filtros según sea necesario.

2. **Opciones Automáticas**: Los filtros `SelectFilter` y `BooleanFilter` intentan obtener automáticamente las opciones y valores del field usando reflexión.

3. **Componentes Frontend**: Cada filtro tiene un componente frontend asociado que debe estar implementado en el frontend (Vue, React, etc.).

4. **Rendimiento**: Los filtros de rango pueden ser más costosos en términos de rendimiento para grandes volúmenes de datos. Considera índices en la base de datos.

---

## Ejemplos Completos

### Ejemplo 1: Resource con Filtros Automáticos

```php
use SchoolAid\Nadota\Http\Fields\Text;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;

public function fields(NadotaRequest $request): array
{
    return [
        Text::make('Nombre', 'name')->filterable(),
        Number::make('Precio', 'price')->filterableRange(),
        Select::make('Categoría', 'category_id')
            ->options([1 => 'Electrónica', 2 => 'Ropa'])
            ->filterable(),
        BelongsTo::make('Usuario', 'user_id', 'user')
            ->relatedModel(User::class)
            ->resource(UserResource::class)
            ->filterable(), // Genera BelongsToFilter automáticamente
    ];
}
```

### Ejemplo 2: Resource con Filtros Personalizados

```php
use SchoolAid\Nadota\Http\Filters\SelectFilter;
use SchoolAid\Nadota\Http\Filters\DateFilter;

public function filters(NadotaRequest $request): array
{
    return [
        SelectFilter::make('Estado', 'status')
            ->options([
                'active' => 'Activo',
                'inactive' => 'Inactivo'
            ]),
        DateFilter::make('Fecha de Creación', 'created_at')
            ->range(true),
    ];
}
```

---

## Soporte

Para más información sobre el sistema de filtros, consulta:
- [Documentación de Fields](../Fields/README.md)
- [Documentación de API](../../../docs/FILTERS_AND_API.md)

