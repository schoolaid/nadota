# Roadmap: ResourceEditService

## Estado Actual

El servicio `ResourceEditService` tiene implementación básica pero incompleta:

```php
// ACTUAL - Con problemas
class ResourceEditService implements ResourceEditInterface
{
    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();
        $model = $resource->getQuery($request)->findOrFail($id);
        $request->authorized('update', $model);

        $fields = $resource->fieldsForForm($request, $model);

        return response()->json([
            'data' => [
                'id' => $model->getKey(),
                'fields' => $fields,  // ❌ Raw Collection
            ],
        ], 200);
    }
}
```

## Problemas Identificados

1. **Fields sin transformar**: `$fields` es una Collection sin mapear a `toArray()`
2. **Key inconsistente**: Usa `'fields'` en vez de `'attributes'`
3. **Sin eager loading**: Relaciones no se cargan eficientemente (N+1)
4. **Sin column selection**: Carga todas las columnas innecesariamente
5. **Metadata faltante**: No incluye `key`, `title`, `permissions`, `deletedAt`
6. **Sin values resueltos**: Los campos no tienen sus valores actuales

---

## Objetivo

Alinear `ResourceEditService` con `ResourceShowService` y `ResourceCreateService` para:
- Estructura de respuesta consistente
- Campos con valores resueltos
- Relaciones cargadas eficientemente
- Metadata completa para el frontend

---

## Tareas

### Fase 1: Estructura de Respuesta ✅ Básico

- [ ] **1.1** Transformar fields con `toArray()` incluyendo model y resource
- [ ] **1.2** Cambiar key de `'fields'` a `'attributes'`
- [ ] **1.3** Agregar `key` (resource key)
- [ ] **1.4** Agregar `title` (resource title)

### Fase 2: Datos del Modelo

- [ ] **2.1** Implementar column selection para edit fields
- [ ] **2.2** Implementar eager loading de relaciones para edit
- [ ] **2.3** Agregar `deletedAt` para soft deletes

### Fase 3: Metadata y Permisos

- [ ] **3.1** Agregar `permissions` para el modelo
- [ ] **3.2** Agregar URLs relevantes (update, show, delete)

### Fase 4: Optimización

- [ ] **4.1** Crear método `fieldsForUpdate()` específico si es necesario
- [ ] **4.2** Agregar soporte para custom response resource

---

## Implementación Propuesta

```php
<?php

namespace SchoolAid\Nadota\Http\Services;

use SchoolAid\Nadota\Contracts\ResourceEditInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use Illuminate\Http\JsonResponse;

class ResourceEditService implements ResourceEditInterface
{
    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        // Build query with proper eager loading and column selection
        $eagerLoadRelations = $resource->getEagerLoadRelationsForEdit($request);
        $columns = $resource->getSelectColumnsForEdit($request);

        $model = $resource->getQuery($request)
            ->with($eagerLoadRelations)
            ->select(...$columns)
            ->findOrFail($id);

        $request->authorized('update', $model);

        // Transform fields with model values
        $fields = $resource->fieldsForForm($request, $model)
            ->map(fn($field) => $field->toArray($request, $model, $resource));

        return response()->json([
            'data' => [
                'id' => $model->getKey(),
                'key' => $resource::getKey(),
                'attributes' => $fields,
                'permissions' => $resource->getPermissionsForResource($request, $model),
                'title' => $resource->title(),
                'deletedAt' => $model->deleted_at ?? null,
            ],
        ]);
    }
}
```

---

## Métodos a Agregar en Resource

### `getEagerLoadRelationsForEdit()`

Similar a `getEagerLoadRelations()` pero filtrado por `fieldsForUpdate`:

```php
public function getEagerLoadRelationsForEdit(NadotaRequest $request): array
{
    return collect($this->fieldsForForm($request, true)) // true = update mode
        ->filter(fn($field) => $field->isRelationship())
        ->flatMap(fn($field) => $this->buildRelationConstraints($field, $request))
        ->toArray();
}
```

### `getSelectColumnsForEdit()`

Similar a `getSelectColumns()` pero para campos de edición:

```php
public function getSelectColumnsForEdit(NadotaRequest $request): array
{
    $modelClass = $this->model;
    $model = new $modelClass;
    $primaryKey = $model->getKeyName();

    $columns = collect($this->fieldsForForm($request, true))
        ->flatMap(fn($field) => $field->getColumnsForSelect($modelClass))
        ->filter()
        ->unique()
        ->values()
        ->toArray();

    // Always include primary key
    if (!in_array($primaryKey, $columns)) {
        array_unshift($columns, $primaryKey);
    }

    // Include soft delete column if applicable
    if (method_exists($model, 'getDeletedAtColumn')) {
        $deletedAt = $model->getDeletedAtColumn();
        if (!in_array($deletedAt, $columns)) {
            $columns[] = $deletedAt;
        }
    }

    return $columns;
}
```

---

## Estructura de Respuesta Final

```json
{
  "data": {
    "id": 1,
    "key": "users",
    "title": "Users",
    "deletedAt": null,
    "permissions": {
      "view": true,
      "update": true,
      "delete": true,
      "forceDelete": false,
      "restore": false
    },
    "attributes": [
      {
        "key": "name",
        "label": "Name",
        "type": "text",
        "value": "John Doe",
        "props": { ... },
        "rules": ["required", "string", "max:255"],
        "readonly": false,
        "disabled": false,
        "required": true
      },
      {
        "key": "email",
        "label": "Email",
        "type": "email",
        "value": "john@example.com",
        ...
      },
      {
        "key": "category_id",
        "label": "Category",
        "type": "belongsTo",
        "value": {
          "id": 5,
          "label": "Technology",
          "resource": "categories"
        },
        "props": {
          "urls": {
            "options": "/nadota-api/users/resource/field/category_id/options?resourceId=1"
          }
        }
      },
      {
        "key": "tags",
        "label": "Tags",
        "type": "belongsToMany",
        "value": {
          "data": [
            { "id": 1, "label": "Laravel" },
            { "id": 2, "label": "PHP" }
          ],
          "meta": { ... }
        },
        "props": {
          "urls": {
            "options": "/nadota-api/users/resource/field/tags/options?resourceId=1",
            "attach": "/nadota-api/users/resource/1/attach/tags",
            "detach": "/nadota-api/users/resource/1/detach/tags",
            "sync": "/nadota-api/users/resource/1/sync/tags"
          }
        },
        "pivotFields": [ ... ]
      }
    ]
  }
}
```

---

## Notas de Implementación

### Field.toArray() con Model

El método `toArray()` del Field ya maneja correctamente el modelo:

```php
public function toArray(NadotaRequest $request, ?Model $model = null, ?ResourceInterface $resource = null): array
{
    $data = array_merge($this->fieldData->toArray(), [
        'key' => $this->key(),
        // ... otros campos
        'props' => $this->getProps($request, $model, $resource),
    ]);

    if ($model) {
        $data['value'] = $this->resolve($request, $model, $resource);  // ✅ Resuelve valor
    }

    return $data;
}
```

### Relaciones en getProps()

Los campos de relación (BelongsTo, BelongsToMany, etc.) generan URLs con `$model` en `getProps()`:

```php
// BelongsToMany::getProps()
if ($model) {
    $props['urls']['options'] = ".../options?resourceId={$modelId}";
    $props['urls']['attach'] = ".../attach/{$fieldKey}";
    // etc.
}
```

---

## Progreso

| Fase | Tarea | Estado |
|------|-------|--------|
| 1 | Estructura de respuesta | ✅ Completado |
| 2 | Datos del modelo | ✅ Completado |
| 3 | Metadata y permisos | ✅ Completado |
| 4 | Optimización | ✅ Completado |

---

## Implementación Final (Completada)

```php
<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use SchoolAid\Nadota\Contracts\ResourceEditInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class ResourceEditService implements ResourceEditInterface
{
    public function handle(NadotaRequest $request, $id): JsonResponse
    {
        $request->prepareResource();
        $resource = $request->getResource();

        // Get fields for update form (before loading model to build query correctly)
        $fields = $resource->fieldsForForm($request, true); // true indicates update mode

        // Build optimized query with proper eager loading and column selection
        $columns = $resource->getSelectColumns($request, $fields);
        $eagerLoadRelations = $resource->getEagerLoadRelations($request, $fields);

        // Include soft delete column if model uses soft deletes
        $columns = $this->includeDeletedAtColumn($resource, $columns);

        $model = $resource->getQuery($request)
            ->with($eagerLoadRelations)
            ->select(...$columns)
            ->findOrFail($id);

        $request->authorized('update', $model);

        // Check if a custom edit response resource is defined
        $customResourceClass = $resource->getEditResponseResource();

        if ($customResourceClass && is_subclass_of($customResourceClass, JsonResource::class)) {
            return (new $customResourceClass($model))->response();
        }

        return $this->buildDefaultResponse($request, $resource, $model, $fields);
    }

    protected function buildDefaultResponse(
        NadotaRequest $request,
        $resource,
        $model,
        $fields
    ): JsonResponse {
        $attributes = $fields->map(function ($field) use ($request, $model, $resource) {
            return $field->toArray($request, $model, $resource);
        });

        return response()->json([
            'data' => [
                'id' => $model->getKey(),
                'key' => $resource::getKey(),
                'attributes' => $attributes,
                'permissions' => $resource->getPermissionsForResource($request, $model),
                'title' => $resource->title(),
                'deletedAt' => $model->deleted_at ?? null,
            ],
        ]);
    }

    protected function includeDeletedAtColumn($resource, array $columns): array
    {
        $modelClass = $resource->model;
        $model = new $modelClass;

        if (method_exists($model, 'getDeletedAtColumn')) {
            $deletedAt = $model->getDeletedAtColumn();
            if (!in_array($deletedAt, $columns) && !in_array('*', $columns)) {
                $columns[] = $deletedAt;
            }
        }

        return $columns;
    }
}
```

---

## Dependencias

- ✅ `Field::toArray()` ya soporta model y resource
- ✅ `Field::resolve()` ya obtiene valores del modelo
- ✅ Relation fields ya generan URLs en `getProps()`
- ✅ `getEagerLoadRelations()` acepta `$fields` como parámetro opcional
- ✅ `getSelectColumns()` acepta `$fields` como parámetro opcional
- ✅ Agregado `getEditResponseResource()` en Resource
- ✅ Agregado `$editResponseResource` property en Resource
