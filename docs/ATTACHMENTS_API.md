# API de Attachments para Relaciones

Este documento describe los endpoints de la API para operaciones de attach/detach/sync en campos de relación.

## Endpoints Disponibles

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/{resource}/resource/{id}/attachable/{field}` | Obtener items disponibles para adjuntar |
| POST | `/{resource}/resource/{id}/attach/{field}` | Adjuntar items a la relación |
| POST | `/{resource}/resource/{id}/detach/{field}` | Separar items de la relación |
| POST | `/{resource}/resource/{id}/sync/{field}` | Sincronizar items (reemplazar todos) |

---

## Soporte por Tipo de Relación

| Relación | attachable | attach | detach | sync | Pivot Data |
|----------|------------|--------|--------|------|------------|
| HasMany | ✅ | ✅ | ✅ | ❌ | ❌ |
| BelongsToMany | ✅ | ✅ | ✅ | ✅ | ✅ |
| MorphToMany | ✅ | ✅ | ✅ | ✅ | ✅ |
| MorphedByMany | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## HasMany

Relación uno a muchos donde el modelo hijo tiene una FK hacia el padre.

### Comportamiento

- **attach**: Setea la FK del hijo al ID del padre
- **detach**: Setea la FK del hijo a `null`
- **sync**: No soportado (usar attach/detach individualmente)

### Configuración del Field

```php
HasMany::make('Comentarios', 'comments', CommentResource::class)
    ->attachable()                           // Habilitar attachments (default: true)
    ->attachableLimit(10)                    // Máximo de items adjuntables
    ->attachableSearchFields(['body', 'author']) // Campos para búsqueda
    ->attachableQuery(function ($query) {    // Query personalizada
        $query->where('status', 'approved');
    });
```

### GET attachable - Obtener items disponibles

```
GET /nadota-api/posts/resource/1/attachable/comments
```

**Query Parameters:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| search | string | '' | Término de búsqueda |
| per_page | int | 25 | Items por página (max: 100) |
| page | int | 1 | Número de página |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "label": "Comentario sin asignar",
      "meta": {}
    },
    {
      "id": 8,
      "label": "Otro comentario",
      "meta": {}
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 25,
    "total": 67,
    "attachable_limit": 10
  }
}
```

### POST attach - Adjuntar items

```
POST /nadota-api/posts/resource/1/attach/comments
Content-Type: application/json

{
  "items": [5, 8, 12]
}
```

**Response:**

```json
{
  "success": true,
  "message": "Items attached successfully",
  "attached": [5, 8, 12],
  "count": 3
}
```

### POST detach - Separar items

```
POST /nadota-api/posts/resource/1/detach/comments
Content-Type: application/json

{
  "items": [5, 8]
}
```

**Response:**

```json
{
  "success": true,
  "message": "Items detached successfully",
  "detached": 2
}
```

---

## BelongsToMany

Relación muchos a muchos con tabla pivot.

### Comportamiento

- **attach**: Inserta registros en la tabla pivot
- **detach**: Elimina registros de la tabla pivot
- **sync**: Reemplaza todos los registros en la tabla pivot

### Configuración del Field

```php
BelongsToMany::make('Roles', 'roles', RoleResource::class)
    ->attachable()                           // Habilitar attachments
    ->attachableLimit(5)                     // Máximo de roles
    ->withPivot(['expires_at', 'is_admin'])  // Columnas pivot permitidas
    ->pivotFields([                          // Fields para editar pivot
        DateTime::make('Expira', 'expires_at'),
        Toggle::make('Admin', 'is_admin'),
    ]);
```

### GET attachable - Obtener items disponibles

```
GET /nadota-api/users/resource/1/attachable/roles?search=admin
```

**Query Parameters:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| search | string | '' | Término de búsqueda |
| per_page | int | 25 | Items por página (max: 100) |
| page | int | 1 | Número de página |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "label": "Administrador"
    },
    {
      "id": 7,
      "label": "Super Admin"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 25,
    "total": 2,
    "attached_count": 2
  }
}
```

### POST attach - Adjuntar con pivot data

```
POST /nadota-api/users/resource/1/attach/roles
Content-Type: application/json

{
  "items": [3, 7],
  "pivot": {
    "expires_at": "2025-12-31",
    "is_admin": false
  }
}
```

**Con pivot data diferente por item:**

```json
{
  "items": [3, 7],
  "pivot": {
    "3": {
      "expires_at": "2025-12-31",
      "is_admin": true
    },
    "7": {
      "expires_at": "2026-06-30",
      "is_admin": false
    }
  }
}
```

**Response:**

```json
{
  "success": true,
  "message": "Items attached successfully",
  "attached": [3, 7],
  "count": 2
}
```

### POST detach - Separar items

```
POST /nadota-api/users/resource/1/detach/roles
Content-Type: application/json

{
  "items": [3]
}
```

**Response:**

```json
{
  "success": true,
  "message": "Items detached successfully",
  "detached": 1
}
```

### POST sync - Sincronizar (reemplazar todos)

```
POST /nadota-api/users/resource/1/sync/roles
Content-Type: application/json

{
  "items": [3, 7, 9],
  "pivot": {
    "3": { "is_admin": true },
    "7": { "is_admin": false },
    "9": { "is_admin": false }
  },
  "detaching": true
}
```

**Parámetros:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| items | array | required | IDs a sincronizar |
| pivot | object | {} | Datos pivot (mismo para todos o por ID) |
| detaching | bool | true | Si eliminar items no incluidos |

**Response:**

```json
{
  "success": true,
  "message": "Items synced successfully",
  "attached": [9],
  "detached": [5],
  "updated": [3, 7]
}
```

---

## MorphToMany

Relación polimórfica muchos a muchos (ej: tags compartidos entre posts, videos, etc).

### Comportamiento

Funciona igual que BelongsToMany pero con una tabla pivot polimórfica.

### Configuración del Field

```php
MorphToMany::make('Tags', 'tags', TagResource::class)
    ->attachable()
    ->attachableLimit(20)
    ->withPivot(['order'])
    ->pivotFields([
        Number::make('Orden', 'order'),
    ]);
```

### GET attachable

```
GET /nadota-api/posts/resource/1/attachable/tags?search=laravel
```

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 15,
      "label": "Laravel"
    },
    {
      "id": 23,
      "label": "Laravel Nova"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 25,
    "total": 2,
    "attached_count": 5
  }
}
```

### POST attach

```
POST /nadota-api/posts/resource/1/attach/tags
Content-Type: application/json

{
  "items": [15, 23],
  "pivot": {
    "order": 1
  }
}
```

### POST detach

```
POST /nadota-api/posts/resource/1/detach/tags
Content-Type: application/json

{
  "items": [15]
}
```

### POST sync

```
POST /nadota-api/posts/resource/1/sync/tags
Content-Type: application/json

{
  "items": [15, 23, 30],
  "pivot": {
    "15": { "order": 1 },
    "23": { "order": 2 },
    "30": { "order": 3 }
  }
}
```

---

## MorphedByMany

Inversa de MorphToMany (ej: desde Tag ver todos los modelos taggeados).

### Comportamiento

Funciona igual que MorphToMany. El service es el mismo.

### Configuración del Field

```php
// En TagResource
MorphedByMany::make('Posts', 'posts', PostResource::class)
    ->attachable()
    ->attachableLimit(100);
```

### Endpoints

Los mismos que MorphToMany:

```
GET  /nadota-api/tags/resource/1/attachable/posts
POST /nadota-api/tags/resource/1/attach/posts
POST /nadota-api/tags/resource/1/detach/posts
POST /nadota-api/tags/resource/1/sync/posts
```

---

## Respuestas de Error

### Field no encontrado (404)

```json
{
  "success": false,
  "message": "Field not found"
}
```

### Field no es attachable (422)

```json
{
  "success": false,
  "message": "Field is not attachable"
}
```

### Tipo de relación no soportado (422)

```json
{
  "success": false,
  "message": "Attachment not supported for this field type: belongsTo"
}
```

### Límite excedido (422)

```json
{
  "success": false,
  "message": "Attachment limit exceeded. Maximum allowed: 10",
  "current": 8,
  "limit": 10,
  "attempting": 5
}
```

### No autorizado (403)

```json
{
  "success": false,
  "message": "Unauthorized"
}
```

### Sin items para procesar (422)

```json
{
  "success": false,
  "message": "No items to attach"
}
```

### Sync no soportado (422)

```json
{
  "success": false,
  "message": "Sync operation not supported for this relation type"
}
```

---

## Permisos

Las operaciones de attachment verifican permisos del Resource:

| Operación | Permiso verificado |
|-----------|-------------------|
| attachable | Ninguno (solo lectura) |
| attach | `attach` |
| detach | `detach` |
| sync | `attach` |

Configura permisos en tu Policy:

```php
class PostPolicy
{
    public function attach(User $user, Post $post): bool
    {
        return $user->can('update', $post);
    }

    public function detach(User $user, Post $post): bool
    {
        return $user->can('update', $post);
    }
}
```

---

## Personalización de Query

Los services utilizan `optionsQuery()` del Resource relacionado para filtrar items:

```php
class RoleResource extends Resource
{
    public function optionsQuery(Builder $query, NadotaRequest $request, array $params = []): Builder
    {
        // Solo mostrar roles activos en attachable
        return $query->where('is_active', true);
    }
}
```

---

## URLs en Response de Fields

Los campos de relación incluyen URLs pre-construidas en su response:

```json
{
  "key": "roles",
  "type": "belongsToMany",
  "props": {
    "urls": {
      "options": "/nadota-api/users/resource/field/roles/options?resourceId=1",
      "attach": "/nadota-api/users/resource/1/attach/roles",
      "detach": "/nadota-api/users/resource/1/detach/roles",
      "sync": "/nadota-api/users/resource/1/sync/roles"
    }
  }
}
```

El frontend puede usar estas URLs directamente sin construirlas manualmente.

---

## Diferencias entre Options y Attachable

| Aspecto | `/field/{f}/options` | `/{id}/attachable/{f}` |
|---------|---------------------|------------------------|
| Propósito | Opciones para select | Items para adjuntar |
| Excluye relacionados | Via `resourceId` param | Automático |
| Paginación | Opcional | Siempre |
| Meta info | Básica | Incluye attached_count |

Usa `attachable` cuando necesites una UI dedicada para gestionar relaciones.
Usa `options` para selects simples en formularios.
