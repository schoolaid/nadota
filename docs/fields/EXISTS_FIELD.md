# Exists Field

## Descripción

El campo `Exists` permite mostrar si una relación tiene registros asociados o no. Es un campo **computed/read-only** que usa la función `withExists()` de Laravel para agregar una columna virtual al query.

## Características

- ✅ Campo computed (no se almacena en base de datos)
- ✅ Read-only (no se puede editar)
- ✅ Mostrado en index y detail por defecto
- ✅ Oculto en creation y update forms
- ✅ Soporta filtros usando `whereHas()`/`whereDoesntHave()`
- ✅ Soporta constraints para verificar condiciones específicas

## Uso Básico

```php
use SchoolAid\Nadota\Http\Fields\Exists;

class StudentResource extends Resource
{
    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name'),

            // Verificar si el estudiante tiene una familia asignada
            Exists::make('Has Family', 'family'),

            // Verificar si tiene formularios completados
            Exists::make('Has Filled Forms', 'filledForms'),
        ];
    }
}
```

## Cómo Funciona

### 1. Nombre del Atributo

El campo automáticamente genera el nombre del atributo agregando `_exists` al nombre de la relación:

```php
Exists::make('Has Profile', 'profile')
// Atributo generado: profile_exists

Exists::make('Has Comments', 'comments')
// Atributo generado: comments_exists
```

### 2. Laravel's withExists()

Internamente, Nadota usa `withExists()` de Laravel para cargar el resultado:

```php
// El campo configura applyInIndexQuery = true
// En el query de index, se ejecuta:
$query->withExists('family'); // Agrega 'family_exists' virtual column

// SQL generado:
// SELECT students.*,
//        EXISTS(SELECT * FROM families WHERE families.id = students.family_id) as family_exists
// FROM students
```

### 3. Resolución de Valor

El campo resuelve el valor del atributo virtual:

```php
public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
{
    return (bool) ($model->family_exists ?? false);
}
```

## Agregar Filtros

Para habilitar filtrado por existencia de relación, usa `->filterable()`:

```php
Exists::make('Has Family', 'family')
    ->filterable();
```

### Cómo Funcionan los Filtros

Cuando marcas el campo como `filterable()`, se genera automáticamente un `ExistsFilter` que usa `whereHas()`:

```php
// Frontend envía:
GET /nadota-api/students?filters[family_exists]=true

// Backend aplica:
$query->whereHas('family'); // Filtra estudiantes CON familia

// O si es false:
$query->whereDoesntHave('family'); // Filtra estudiantes SIN familia
```

**SQL Generado:**
```sql
-- Para filters[family_exists]=true
SELECT * FROM students
WHERE EXISTS (
    SELECT * FROM families
    WHERE families.id = students.family_id
)

-- Para filters[family_exists]=false
SELECT * FROM students
WHERE NOT EXISTS (
    SELECT * FROM families
    WHERE families.id = students.family_id
)
```

## Constraints (Condiciones Específicas)

Puedes agregar condiciones a la verificación de existencia:

```php
// Verificar si tiene comentarios ACTIVOS
Exists::make('Has Active Comments', 'comments')
    ->constraint(fn($query) => $query->where('active', true))
    ->filterable();

// Verificar si tiene formularios completados ESTE AÑO
Exists::make('Has Recent Forms', 'filledForms')
    ->constraint(fn($query) => $query->whereYear('created_at', date('Y')))
    ->filterable();
```

**Cuando se filtra:**
```php
// Con constraint, se ejecuta:
$query->whereHas('comments', function ($q) {
    $q->where('active', true);
});

// SQL generado:
SELECT * FROM students
WHERE EXISTS (
    SELECT * FROM comments
    WHERE comments.student_id = students.id
    AND comments.active = 1
)
```

## Ejemplos Completos

### Ejemplo 1: Sistema de Tareas

```php
class StudentResource extends Resource
{
    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name'),

            // Mostrar si tiene tareas pendientes
            Exists::make('Has Pending Tasks', 'tasks')
                ->constraint(fn($q) => $q->where('status', 'pending'))
                ->filterable(),

            // Mostrar si tiene tareas completadas
            Exists::make('Has Completed Tasks', 'tasks')
                ->constraint(fn($q) => $q->where('status', 'completed'))
                ->filterable(),
        ];
    }
}
```

### Ejemplo 2: Sistema de Inscripciones

```php
class SchoolResource extends Resource
{
    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name'),

            // Verificar si tiene estudiantes activos
            Exists::make('Has Active Students', 'students')
                ->constraint(fn($q) => $q->where('status', 'active'))
                ->filterable(),

            // Verificar si tiene profesores certificados
            Exists::make('Has Certified Teachers', 'teachers')
                ->constraint(fn($q) => $q->whereNotNull('certification_date'))
                ->filterable(),
        ];
    }
}
```

### Ejemplo 3: Sistema de Auditoría

```php
class UserResource extends Resource
{
    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Email', 'email'),

            // Verificar si tiene actividad reciente (últimos 30 días)
            Exists::make('Recent Activity', 'activities')
                ->constraint(fn($q) => $q->where('created_at', '>=', now()->subDays(30)))
                ->filterable(),

            // Verificar si tiene sesiones activas
            Exists::make('Active Sessions', 'sessions')
                ->constraint(fn($q) => $q->whereNull('logged_out_at'))
                ->filterable(),
        ];
    }
}
```

## Personalización de Visibilidad

Puedes controlar dónde se muestra el campo:

```php
// Solo en index
Exists::make('Has Comments', 'comments')
    ->onlyOnIndex();

// Solo en detail
Exists::make('Has Profile', 'profile')
    ->onlyOnDetail();

// En index y detail (default)
Exists::make('Has Family', 'family')
    ->showOnIndex()
    ->showOnDetail();
```

## Integración con ApplyFieldsPipe

El campo Exists está configurado para cargarse automáticamente:

```php
// En el constructor de Exists:
$this->applyInIndexQuery = true;  // Se carga en índice
$this->applyInShowQuery = true;   // Se carga en show/detail
```

Esto significa que `ApplyFieldsPipe` automáticamente agregará `withExists('relation')` al query.

## API del Campo

### Métodos

| Método | Descripción | Ejemplo |
|--------|-------------|---------|
| `make(string $name, string $relation)` | Constructor | `Exists::make('Has Comments', 'comments')` |
| `constraint(Closure $callback)` | Agregar condición | `->constraint(fn($q) => $q->where('active', true))` |
| `filterable()` | Habilitar filtro | `->filterable()` |
| `getExistsRelation()` | Obtener nombre relación | `$field->getExistsRelation()` → 'comments' |
| `getExistsConstraint()` | Obtener constraint | `$field->getExistsConstraint()` |

### Propiedades

| Propiedad | Valor Default | Descripción |
|-----------|--------------|-------------|
| `computed` | `true` | Campo computed, no fillable |
| `readonly` | `true` | No editable |
| `showOnIndex` | `true` | Mostrar en lista |
| `showOnDetail` | `true` | Mostrar en detail |
| `showOnCreation` | `false` | Ocultar en create |
| `showOnUpdate` | `false` | Ocultar en update |
| `applyInIndexQuery` | `true` | Cargar en index |
| `applyInShowQuery` | `true` | Cargar en show |

## Consideraciones Importantes

### ⚠️ No es un Contador

Si necesitas contar registros, usa un campo Computed con `withCount()`:

```php
// ❌ MAL - Exists solo dice si existe o no (true/false)
Exists::make('Comments Count', 'comments');

// ✅ BIEN - Usa un campo computed con withCount
Computed::make('Comments Count', function ($model) {
    return $model->comments_count;
})->applyInQuery(fn($query) => $query->withCount('comments'));
```

### ⚠️ Rendimiento

`withExists()` es eficiente porque usa subconsultas EXISTS:

```sql
-- Muy eficiente - se detiene en el primer match
SELECT EXISTS (SELECT * FROM comments WHERE student_id = 1)

-- vs COUNT que cuenta todos
SELECT COUNT(*) FROM comments WHERE student_id = 1
```

### ⚠️ Filtros en Columnas Virtuales

El campo Exists **NO** hace WHERE directo en la columna virtual. En su lugar:

```php
// ❌ ESTO FALLARÁ (columna no existe en DB)
WHERE family_exists = 1

// ✅ ExistsFilter usa whereHas() correctamente
WHERE EXISTS (SELECT * FROM families WHERE ...)
```

## Troubleshooting

### Error: Column not found 'xxx_exists'

**Problema:** Intentas filtrar pero obtienes error SQL.

**Solución:** Asegúrate de marcar el campo como `filterable()`:

```php
// ❌ Sin filterable - genera error al filtrar
Exists::make('Has Family', 'family');

// ✅ Con filterable - funciona correctamente
Exists::make('Has Family', 'family')
    ->filterable();
```

### El campo siempre muestra false

**Problema:** El valor siempre es false aunque la relación existe.

**Causa:** El query no está cargando el `withExists()`.

**Solución:** Verifica que:
1. El campo tiene `applyInIndexQuery = true` (default)
2. ApplyFieldsPipe está en el pipeline de index
3. El nombre de la relación es correcto

### El filtro no funciona

**Problema:** El filtro no afecta los resultados.

**Solución:** Verifica que:
1. Marcaste el campo como `->filterable()`
2. El nombre de la relación existe en el modelo
3. El request incluye `filters[relation_exists]=true/false`

## Resumen

El campo `Exists` es ideal para:
- ✅ Mostrar indicadores de existencia de relaciones
- ✅ Filtrar por presencia/ausencia de relaciones
- ✅ Verificar relaciones con condiciones específicas
- ✅ Optimizar queries usando EXISTS en lugar de JOIN/COUNT

**NO es ideal para:**
- ❌ Contar registros (usa `withCount()`)
- ❌ Mostrar información detallada de la relación (usa campos de relación)
- ❌ Editar relaciones (es read-only)
