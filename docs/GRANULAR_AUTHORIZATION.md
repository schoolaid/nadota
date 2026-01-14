# Autorización Granular por Campo en Nadota

## Introducción

El sistema de autorización de Nadota ahora soporta **autorización granular por campo** para operaciones de attach/detach. Esto te permite controlar permisos de manera más específica, permitiendo diferentes reglas de autorización según qué campo se está adjuntando o desacoplando.

## Problema que Resuelve

### Antes (Sistema Genérico)

Con el sistema anterior, si definías un método `attach()` en tu policy, se aplicaba a **todos** los campos attachables del recurso:

```php
// SchoolPolicy.php
public function attach(User $user, School $school): bool
{
    return $user->isAdmin(); // Aplica para TODOS los campos
}
```

Esto significaba que:
- Si permites `attach`, permite adjuntar grades, students, teachers, etc.
- No puedes diferenciar entre diferentes tipos de relaciones
- No puedes validar items específicos o datos pivot

### Ahora (Sistema Granular)

Ahora puedes definir métodos específicos por campo:

```php
// SchoolPolicy.php

// Solo super admin puede adjuntar grades
public function attachGrades(User $user, School $school, array $context = []): bool
{
    return $user->isSuperAdmin();
}

// Usuarios normales pueden adjuntar students a su propia escuela
public function attachStudents(User $user, School $school, array $context = []): bool
{
    if ($user->isSuperAdmin()) {
        return true;
    }

    return $user->school_id == $school->id;
}
```

## Cómo Funciona

### Prioridad de Métodos

El sistema verifica los métodos en este orden:

1. **Método específico por campo** (ej: `attachGrades`, `detachStudents`)
2. **Método genérico** (ej: `attach`, `detach`)
3. **Fallback permisivo** (si no existe ninguno, autoriza por defecto)

### Nomenclatura de Métodos

Los métodos específicos siguen este patrón:

```
{acción} + {Ucfirst(nombreDelCampo)}
```

Ejemplos:
- Campo `grades` → `attachGrades()`, `detachGrades()`
- Campo `students` → `attachStudents()`, `detachStudents()`
- Campo `teachers` → `attachTeachers()`, `detachTeachers()`
- Campo `schoolYears` → `attachSchoolYears()`, `detachSchoolYears()`

### Contexto Disponible

Todos los métodos de policy reciben tres parámetros:

```php
public function attachGrades(User $user, School $school, array $context = []): bool
{
    // $user - Usuario autenticado
    // $school - Modelo padre (la escuela siendo editada)
    // $context - Array con información adicional
}
```

El array `$context` contiene:

```php
[
    'field' => 'grades',              // Nombre del campo
    'items' => [1, 2, 3],             // IDs de items a adjuntar/desacoplar
    'pivot' => [                       // Datos pivot (solo para attach/sync)
        'permission_text' => 'Admin',
        'active' => true
    ],
    'detaching' => true                // Solo para sync - si desacopla items no incluidos
]
```

## Ejemplos de Uso

### Ejemplo 1: Autorización Simple por Campo

```php
class SchoolPolicy extends BasePolicy
{
    // Método genérico - aplica cuando no hay método específico
    public function attach(User $user, School $school, array $context = []): bool
    {
        return $user->isAdmin();
    }

    // Método específico - solo super admin puede adjuntar grades
    public function attachGrades(User $user, School $school, array $context = []): bool
    {
        return $user->isSuperAdmin();
    }

    // Método específico - usuarios pueden adjuntar students a su escuela
    public function attachStudents(User $user, School $school, array $context = []): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Solo su propia escuela
        return $user->school_id == $school->id;
    }
}
```

### Ejemplo 2: Validación de Cantidad de Items

```php
public function attachStudents(User $user, School $school, array $context = []): bool
{
    if ($user->isSuperAdmin()) {
        return true; // Sin límite para super admin
    }

    $items = $context['items'] ?? [];

    // Usuarios regulares solo pueden adjuntar máximo 10 estudiantes a la vez
    if (count($items) > 10) {
        return false;
    }

    return $user->school_id == $school->id;
}
```

### Ejemplo 3: Validación de Items Específicos

```php
public function detachTeachers(User $user, School $school, array $context = []): bool
{
    if ($user->isSuperAdmin()) {
        return true;
    }

    $items = $context['items'] ?? [];

    // No permitir desacoplar ciertos profesores críticos
    $protectedTeachers = [1, 2, 3]; // IDs de profesores protegidos

    if (array_intersect($items, $protectedTeachers)) {
        return false;
    }

    return $user->school_id == $school->id;
}
```

### Ejemplo 4: Validación de Datos Pivot

```php
public function attachGrades(User $user, School $school, array $context = []): bool
{
    if ($user->isSuperAdmin()) {
        return true;
    }

    $pivot = $context['pivot'] ?? [];

    // Solo super admin puede establecer ciertos permisos en el pivot
    if (isset($pivot['permission_text']) && $pivot['permission_text'] === 'SuperAdmin') {
        return false;
    }

    return true;
}
```

### Ejemplo 5: Validación Basada en el Modelo

```php
public function attachStudents(User $user, School $school, array $context = []): bool
{
    if ($user->isSuperAdmin()) {
        return true;
    }

    // Solo permitir si la escuela está activa
    if (!$school->is_active) {
        return false;
    }

    // Solo su propia escuela
    if ($user->school_id != $school->id) {
        return false;
    }

    $items = $context['items'] ?? [];

    // Verificar límite de capacidad de la escuela
    $currentStudents = $school->students()->count();
    $maxCapacity = $school->max_students;

    if (($currentStudents + count($items)) > $maxCapacity) {
        return false;
    }

    return true;
}
```

### Ejemplo 6: Autorización Asimétrica

```php
class SchoolPolicy extends BasePolicy
{
    // Fácil adjuntar students
    public function attachStudents(User $user, School $school, array $context = []): bool
    {
        return $user->school_id == $school->id;
    }

    // Difícil desacoplar students - requiere permiso especial
    public function detachStudents(User $user, School $school, array $context = []): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Requiere permiso específico para desacoplar
        return $user->hasPermission('school.students.detach');
    }
}
```

## Acciones Soportadas

Las siguientes acciones soportan autorización granular:

| Acción | Método Genérico | Ejemplo Específico | Contexto Incluye |
|--------|----------------|-------------------|------------------|
| Attach | `attach()` | `attachGrades()` | field, items, pivot |
| Detach | `detach()` | `detachStudents()` | field, items |
| Sync | `attach()`* | `attachGrades()` | field, items, pivot, detaching |

\* Sync usa el permiso `attach` por defecto

## Matriz de Autorización

```
Request: POST /nadota-api/schools/1/attach/grades
                    ↓
AttachmentController::attach()
                    ↓
1. Busca el modelo padre (School con id=1)
2. Obtiene el field instance (grades)
3. Construye contexto:
   {
     'field': 'grades',
     'items': [1, 2, 3],
     'pivot': {...}
   }
                    ↓
$resource->authorizedTo($request, 'attach', $model, $context)
                    ↓
ResourceAuthorizationService::authorizedTo()
                    ↓
4. Busca policy de School
5. Verifica si existe attachGrades()
   ✓ SI → Llama a attachGrades($user, $school, $context)
   ✗ NO → Llama a attach($user, $school, $context)
                    ↓
6. Si no existe ninguno → Autoriza (true)
7. Si existe pero retorna false → Rechaza (403)
8. Si existe y retorna true → Continúa operación
```

## Integración con Recursos

No necesitas cambiar nada en tus recursos. El sistema funciona automáticamente:

```php
// En tu Resource
class SchoolResource extends Resource
{
    public function fields(NadotaRequest $request): array
    {
        return [
            // Simplemente define tus campos attachables
            BelongsToMany::make('Grades', 'grades')
                ->attachable(),

            HasMany::make('Students', 'students')
                ->attachable(),

            BelongsToMany::make('Teachers', 'teachers')
                ->attachable(),
        ];
    }
}
```

## Testing

Puedes testear la autorización granular creando policies de prueba:

```php
it('authorizes attach based on field', function () {
    $policy = new class {
        public function attachGrades($user, $model, array $context = []): bool
        {
            return $context['field'] === 'grades';
        }
    };

    Gate::policy(School::class, get_class($policy));

    $result = $authService->authorizedTo($request, 'attach', [
        'field' => 'grades',
        'items' => [1, 2, 3]
    ]);

    expect($result)->toBeTrue();
});
```

## Backward Compatibility

El sistema es 100% compatible con código existente:

- Si no defines métodos específicos, funciona como antes
- Si solo defines `attach()` genérico, aplica a todos los campos
- Si defines métodos específicos, tienen prioridad pero no rompen el código existente

## Best Practices

### 1. Usa Métodos Específicos para Casos Especiales

```php
// ❌ Evita validaciones complejas en el método genérico
public function attach(User $user, School $school, array $context = []): bool
{
    $field = $context['field'] ?? null;

    if ($field === 'grades') {
        return $user->isSuperAdmin();
    } elseif ($field === 'students') {
        return $user->school_id == $school->id;
    }
    // ...
}

// ✅ Mejor: usa métodos específicos
public function attachGrades(User $user, School $school, array $context = []): bool
{
    return $user->isSuperAdmin();
}

public function attachStudents(User $user, School $school, array $context = []): bool
{
    return $user->school_id == $school->id;
}
```

### 2. Proporciona Fallback Genérico

```php
// Define un método genérico como fallback
public function attach(User $user, School $school, array $context = []): bool
{
    // Regla general: usuarios pueden adjuntar a su propia escuela
    return $user->school_id == $school->id;
}

// Sobrescribe para casos específicos
public function attachGrades(User $user, School $school, array $context = []): bool
{
    // Regla más restrictiva para grades
    return $user->isSuperAdmin();
}
```

### 3. Valida el Contexto de Manera Segura

```php
public function attachStudents(User $user, School $school, array $context = []): bool
{
    // ✅ Usa ?? para valores por defecto seguros
    $items = $context['items'] ?? [];
    $pivot = $context['pivot'] ?? [];

    // ✅ Valida tipos
    if (!is_array($items)) {
        return false;
    }

    // Tu lógica aquí
}
```

### 4. Mantén la Lógica Simple

```php
// ❌ Evita lógica muy compleja en la policy
public function attachStudents(User $user, School $school, array $context = []): bool
{
    // Query compleja
    $availableSlots = DB::table('schools')
        ->join('students', ...)
        ->where(...)
        ->count();

    // Muchas validaciones
    // ...
}

// ✅ Mejor: mueve la lógica compleja a un servicio
public function attachStudents(User $user, School $school, array $context = []): bool
{
    if ($user->isSuperAdmin()) {
        return true;
    }

    // Lógica simple y clara
    return app(SchoolCapacityService::class)
        ->canAddStudents($school, $context['items'] ?? []);
}
```

## Debugging

Para debugging, puedes inspeccionar el contexto en tu policy:

```php
public function attachGrades(User $user, School $school, array $context = []): bool
{
    \Log::info('Authorization context', [
        'user_id' => $user->id,
        'school_id' => $school->id,
        'field' => $context['field'] ?? null,
        'items_count' => count($context['items'] ?? []),
        'pivot' => $context['pivot'] ?? null,
    ]);

    return $user->isSuperAdmin();
}
```

## FAQ

### ¿Puedo usar esto para otras acciones además de attach/detach?

Actualmente, la autorización granular está implementada solo para:
- `attach`
- `detach`
- `sync` (usa el permiso `attach`)

### ¿Qué pasa si no defino ningún método en la policy?

El sistema autoriza por defecto (fallback permisivo). Esto es útil para prototipos pero deberías definir policies para producción.

### ¿Puedo validar los items individuales antes de adjuntarlos?

Sí, el contexto incluye los IDs de los items. Puedes cargarlos y validarlos:

```php
public function attachStudents(User $user, School $school, array $context = []): bool
{
    $items = $context['items'] ?? [];

    // Cargar los estudiantes
    $students = Student::whereIn('id', $items)->get();

    // Validar cada uno
    foreach ($students as $student) {
        if ($student->is_suspended) {
            return false; // No permitir estudiantes suspendidos
        }
    }

    return true;
}
```

### ¿Funciona con MorphToMany y otras relaciones polimórficas?

Sí, funciona con todas las relaciones attachables:
- BelongsToMany
- HasMany
- MorphMany
- MorphToMany

### ¿Puedo hacer que sync use un permiso diferente a attach?

Actualmente sync usa el permiso `attach`. Si necesitas un permiso separado, puedes verificarlo en tu método:

```php
public function attach(User $user, School $school, array $context = []): bool
{
    $isSyncing = isset($context['detaching']);

    if ($isSyncing && !$user->hasPermission('sync.grades')) {
        return false;
    }

    return true;
}
```

## Resumen

La autorización granular por campo te da control total sobre qué usuarios pueden adjuntar/desacoplar qué tipos de relaciones. Es especialmente útil cuando:

- Tienes múltiples relaciones attachables con diferentes niveles de sensibilidad
- Necesitas validar items específicos o datos pivot
- Quieres limitar cantidades o tipos de adjuntos
- Tienes reglas de negocio complejas por tipo de relación

El sistema es backward compatible, fácil de usar, y se integra perfectamente con el sistema de policies de Laravel.
