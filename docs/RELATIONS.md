# Relations - Nadota

## Resumen de Arquitectura

### Sistema Unificado de Consultas

Tanto `index` como `show` usan el mismo sistema para:

1. **Selección de columnas** (`getSelectColumns`)
   - Cada field define sus columnas via `getColumnsForSelect()`
   - BelongsTo resuelve FK desde Eloquent relationship
   - MorphTo resuelve type + id columns

2. **Eager Loading** (`getEagerLoadRelations`)
   - Cada field define columnas relacionadas via `getRelatedColumns()`
   - Soporta constraints de columnas en eager loading

3. **RelationResource** (`src/Http/Resources/RelationResource.php`)
   - Clase unificada para formatear datos de relaciones
   - Métodos: `formatItem()` (1:1), `formatCollection()` (1:N)
   - Soporta: fields, permissions, pivot data, label resolver

---

## Relaciones Implementadas

### BelongsTo (1:1 inversa)
```php
BelongsTo::make('Usuario', 'user', UserResource::class)
    ->foreignKey('custom_user_id')     // FK personalizada
    ->displayAttribute('name')          // Atributo para label
    ->withFields()                      // Incluir fields en respuesta (default: false)
    ->fields([...])                     // Fields personalizados
    ->exceptFields(['password'])        // Excluir fields
```

### HasOne
```php
HasOne::make('Perfil', 'profile', ProfileResource::class)
    ->withFields()
    ->fields([...])
    ->displayAttribute('full_name')
```

### HasMany
```php
HasMany::make('Comentarios', 'comments', CommentResource::class)
    ->limit(10)
    ->orderBy('created_at', 'desc')
    ->paginated()
    ->fields([...])
    ->exceptFields([...])
```

### BelongsToMany
```php
BelongsToMany::make('Roles', 'roles', RoleResource::class)
    ->withPivot(['expires_at', 'is_admin'])    // Columnas pivot
    ->pivotFields([                             // Fields para forms
        Date::make('Expira', 'expires_at'),
        Boolean::make('Admin', 'is_admin'),
    ])
    ->withTimestamps()                          // Timestamps del pivot
    ->limit(20)
    ->orderBy('name', 'asc')
```

### MorphTo (polimórfica inversa)
```php
MorphTo::make('Comentable', 'commentable', [
    'post' => PostResource::class,
    'video' => VideoResource::class,
])
    ->withFields()
    ->displayAttribute('title')
```

### MorphOne (1:1 polimórfica)
```php
MorphOne::make('Imagen', 'image', ImageResource::class)
    ->withFields()
    ->displayAttribute('filename')
```

### MorphMany (1:N polimórfica)
```php
MorphMany::make('Comentarios', 'comments', CommentResource::class)
    ->limit(10)
    ->orderBy('created_at', 'desc')
    ->fields([...])
```

### MorphToMany (N:N polimórfica)
```php
MorphToMany::make('Etiquetas', 'tags', TagResource::class)
    ->withPivot(['order'])
    ->pivotFields([
        Number::make('Orden', 'order'),
    ])
    ->withTimestamps()
    ->limit(20)
```

### MorphedByMany (inversa de MorphToMany)
```php
// En TagResource: obtener todos los Posts/Videos que tienen este tag
MorphedByMany::make('Posts', 'posts', PostResource::class)
    ->withPivot(['order'])
    ->limit(10)
```

### HasManyThrough (1:N a través de intermedia)
```php
// Country -> Users -> Posts
HasManyThrough::make('Posts del País', 'posts', PostResource::class)
    ->limit(10)
    ->orderBy('created_at', 'desc')
```

### HasOneThrough (1:1 a través de intermedia)
```php
// Mechanic -> Car -> Owner
HasOneThrough::make('Dueño', 'carOwner', OwnerResource::class)
    ->withFields()
```

---

## Configuración por Defecto

| Relación | showOnIndex | showOnDetail | showOnCreate | showOnUpdate | withFields |
|----------|-------------|--------------|--------------|--------------|------------|
| BelongsTo | true | true | true | true | false |
| HasOne | false | true | false | false | false |
| HasMany | false | true | false | false | N/A |
| BelongsToMany | false | true | true | true | N/A |
| MorphTo | true | true | true | true | false |
| MorphOne | false | true | false | false | false |
| MorphMany | false | true | false | false | N/A |
| MorphToMany | false | true | true | true | N/A |
| MorphedByMany | false | true | false | false | N/A |
| HasManyThrough | false | true | false | false | N/A |
| HasOneThrough | false | true | false | false | false |

---

## Respuesta JSON

### BelongsTo / HasOne / MorphTo (sin withFields)
```json
{
  "key": 1,
  "label": "John Doe",
  "resource": "users"
}
```

### BelongsTo / HasOne / MorphTo (con withFields)
```json
{
  "key": 1,
  "label": "John Doe",
  "resource": "users",
  "fields": [
    { "key": "name", "value": "John Doe", ... },
    { "key": "email", "value": "john@example.com", ... }
  ]
}
```

### HasMany / BelongsToMany
```json
{
  "data": [
    {
      "key": 1,
      "label": "Item 1",
      "resource": "items",
      "fields": [...],
      "pivot": { "order": 1 }  // Solo BelongsToMany con withPivot
    }
  ],
  "meta": {
    "total": 10,
    "hasMore": true,
    "resource": "items"
  }
}
```

---

## Archivos Clave

```
src/
├── Http/
│   ├── Fields/
│   │   ├── Relations/
│   │   │   ├── BelongsTo.php
│   │   │   ├── BelongsToMany.php
│   │   │   ├── HasMany.php
│   │   │   ├── HasManyThrough.php
│   │   │   ├── HasOne.php
│   │   │   ├── HasOneThrough.php
│   │   │   ├── MorphedByMany.php
│   │   │   ├── MorphMany.php
│   │   │   ├── MorphOne.php
│   │   │   ├── MorphTo.php
│   │   │   └── MorphToMany.php
│   │   └── Traits/
│   │       ├── BuildsSelectColumns.php      # getSelectColumns()
│   │       ├── ManagesRelationLoading.php   # getEagerLoadRelations()
│   │       └── RelationshipTrait.php
│   ├── Resources/
│   │   └── RelationResource.php             # Formateo unificado
│   └── Services/
│       ├── Pipes/
│       │   └── BuildQueryPipe.php           # Usa getSelectColumns + getEagerLoadRelations
│       └── ResourceShowService.php          # Usa getSelectColumns + getEagerLoadRelations
```

---

## Paginación de Relaciones

Las relaciones multi-registro pueden configurarse para cargarse de forma paginada en lugar de cargar todos los registros en el show.

### Relaciones que soportan paginación

| Relación | Soporta `->paginated()` |
|----------|:-----------------------:|
| HasMany | ✅ |
| BelongsToMany | ✅ |
| MorphMany | ✅ |
| MorphToMany | ✅ |
| MorphedByMany | ✅ |
| HasManyThrough | ✅ |
| BelongsTo | ❌ |
| HasOne | ❌ |
| HasOneThrough | ❌ |
| MorphOne | ❌ |
| MorphTo | ❌ |

### Configuración

```php
HasMany::make('Comentarios', 'comments', CommentResource::class)
    ->paginated()              // Habilita paginación
    ->limit(25)                // Items por página (default: 10)
    ->orderBy('created_at', 'desc')
    ->withFields()             // Incluir fields en response
    ->fields([...])            // Fields personalizados
    ->exceptFields(['user']);  // Excluir fields
```

### Comportamiento

Cuando un campo está configurado con `->paginated()`:

1. **NO se carga en el show** - El campo se excluye del eager loading
2. **El frontend debe llamar al endpoint de paginación** para obtener los datos
3. **El campo incluye `paginationUrl` en sus props** con la URL del endpoint

### Endpoint de Paginación

```http
GET /nadota-api/{resourceKey}/resource/{id}/relation/{field}
```

**Parámetros:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `page` | int | 1 | Página actual |
| `per_page` | int | 15 | Items por página |
| `search` | string | - | Búsqueda en `searchableAttributes` del resource |
| `sort_field` | string | - | Campo de ordenamiento |
| `sort_direction` | string | `desc` | Dirección: `asc` o `desc` |

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "label": "Comentario 1",
      "resource": "comments",
      "deletedAt": null,
      "fields": [...],
      "pivot": {...}
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 25,
    "total": 120,
    "from": 1,
    "to": 25,
    "resource": "comments",
    "relation_type": "hasMany",
    "has_pivot": false
  },
  "links": {
    "first": "...?page=1",
    "last": "...?page=5",
    "prev": null,
    "next": "...?page=2"
  }
}
```

### Props del Field (cuando paginated=true)

```json
{
  "props": {
    "paginated": true,
    "paginationUrl": "/nadota-api/posts/resource/1/relation/comments",
    "limit": 25,
    "orderBy": "created_at",
    "orderDirection": "desc",
    "relationType": "hasMany",
    "resource": "comments"
  }
}
```

---

## Pendiente (Futuro)

- [x] ~~**Paginación de Relaciones** - Endpoint dedicado para cargar relaciones paginadas~~
- [ ] **Inline Create** - Crear relacionados desde el mismo form
- [ ] **Searchable Relations** - Búsqueda async para selects con muchos registros
