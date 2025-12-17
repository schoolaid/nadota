# API Routes - Nadota

Este documento describe todas las rutas disponibles en la API de Nadota.

**Prefijo base:** `/{prefix}` (default: `/nadota-api`)

---

## Menú

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/menu` | `menu` | Obtener estructura del menú de navegación |

---

## Recursos

Todas las rutas de recursos usan el prefijo: `/{resourceKey}/resource`

Donde `{resourceKey}` es el identificador del resource (ej: `users`, `posts`, `comments`).

### Configuración y Metadatos

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/config` | `resource.config` | Configuración completa del resource |
| GET | `/info` | `resource.info` | Información básica del resource |
| GET | `/fields` | `resource.fields` | Lista de campos del resource |
| GET | `/filters` | `resource.filters` | Filtros disponibles |
| GET | `/lens` | `resource.lens` | Configuración de lentes |

### CRUD Principal

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/` | `resource.index` | Listar recursos (paginado) |
| GET | `/data` | `resource.compact` | Listar recursos en formato compacto |
| GET | `/create` | `resource.create` | Obtener campos para creación |
| POST | `/` | `resource.store` | Crear nuevo recurso |
| GET | `/{id}` | `resource.show` | Ver detalle de un recurso |
| GET | `/{id}/edit` | `resource.edit` | Obtener campos para edición |
| PUT | `/{id}` | `resource.update` | Actualizar recurso completo |
| PATCH | `/{id}` | `resource.patch` | Actualizar recurso parcialmente |
| DELETE | `/{id}` | `resource.destroy` | Eliminar recurso (soft delete si aplica) |
| DELETE | `/{id}/force` | `resource.forceDelete` | Eliminar permanentemente |
| POST | `/{id}/restore` | `resource.restore` | Restaurar recurso eliminado |

### Permisos

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/{id}/permissions` | `resource.permissions` | Obtener permisos del usuario sobre el recurso |

**Response:**
```json
{
  "view": true,
  "update": true,
  "delete": true,
  "forceDelete": false,
  "restore": false,
  "attach": true,
  "detach": true
}
```

---

## Options (Selectores)

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/options` | `resource.options` | Opciones del resource para selects |
| GET | `/field/{fieldName}/options` | `resource.field.options` | Opciones de un campo específico |
| GET | `/field/{fieldName}/options/paginated` | `resource.field.options.paginated` | Opciones paginadas |
| GET | `/field/{fieldName}/morph-options/{morphType}` | `resource.field.morph.options` | Opciones para campos MorphTo |

### Query Parameters para Options

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `search` | string | `''` | Término de búsqueda |
| `limit` | int | `15` | Máximo de resultados (max: 100) |
| `exclude` | array | `[]` | IDs a excluir |
| `resourceId` | int | `null` | ID del modelo padre (auto-excluye relacionados) |
| `orderBy` | string | `null` | Campo para ordenar |
| `orderDirection` | string | `'asc'` | Dirección: `asc` o `desc` |

**Response:**
```json
{
  "success": true,
  "data": [
    { "value": 1, "label": "Opción 1" },
    { "value": 2, "label": "Opción 2" }
  ]
}
```

---

## Actions (Acciones)

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/actions` | `resource.actions` | Listar acciones disponibles |
| GET | `/actions/{actionKey}/fields` | `resource.actions.fields` | Campos de una acción |
| POST | `/actions/{actionKey}` | `resource.actions.execute` | Ejecutar acción |

### Request para Ejecutar Acción

```json
{
  "resources": [1, 2, 3],
  "field_name": "value"
}
```

---

## Attachments (Relaciones Many-to-Many)

Para relaciones `HasMany`, `BelongsToMany`, `MorphToMany`, `MorphedByMany`.

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/{id}/attachable/{field}` | `resource.attachable` | Obtener items disponibles para adjuntar |
| POST | `/{id}/attach/{field}` | `resource.attach` | Adjuntar items a la relación |
| POST | `/{id}/detach/{field}` | `resource.detach` | Separar items de la relación |
| POST | `/{id}/sync/{field}` | `resource.sync` | Sincronizar items (solo BelongsToMany/MorphToMany) |

### Soporte por Tipo de Relación

| Relación | attachable | attach | detach | sync | Pivot Data |
|----------|------------|--------|--------|------|------------|
| HasMany | ✅ | ✅ | ✅ | ❌ | ❌ |
| BelongsToMany | ✅ | ✅ | ✅ | ✅ | ✅ |
| MorphToMany | ✅ | ✅ | ✅ | ✅ | ✅ |
| MorphedByMany | ✅ | ✅ | ✅ | ✅ | ✅ |

### Request: Attach

```json
{
  "items": [1, 2, 3],
  "pivot": {
    "role": "admin",
    "expires_at": "2025-12-31"
  }
}
```

### Request: Detach

```json
{
  "items": [1, 2]
}
```

### Request: Sync

```json
{
  "items": [1, 2, 3],
  "pivot": {
    "1": { "role": "admin" },
    "2": { "role": "user" },
    "3": { "role": "guest" }
  },
  "detaching": true
}
```

### Response: Attach/Detach

```json
{
  "success": true,
  "message": "Items attached successfully",
  "attached": [1, 2, 3],
  "count": 3
}
```

### Response: Sync

```json
{
  "success": true,
  "message": "Items synced successfully",
  "attached": [3],
  "detached": [4, 5],
  "updated": [1, 2]
}
```

> Ver [ATTACHMENTS_API.md](./ATTACHMENTS_API.md) para documentación detallada.

---

## Relaciones Paginadas

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/{id}/relation/{field}` | `resource.relation.index` | Obtener items de una relación paginados |

### Query Parameters

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `page` | int | `1` | Número de página |
| `per_page` | int | `15` | Items por página |
| `search` | string | `''` | Término de búsqueda |
| `sort_by` | string | `null` | Campo para ordenar |
| `sort_direction` | string | `'desc'` | Dirección del ordenamiento |

**Response:**
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  }
}
```

---

## Action Events (Auditoría)

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/{id}/action-events` | `resource.action-events` | Historial de acciones sobre el recurso |

### Query Parameters

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `page` | int | `1` | Número de página |
| `per_page` | int | `15` | Items por página |

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "action": "create",
      "user": { "id": 1, "name": "Admin" },
      "changes": { "name": [null, "New Name"] },
      "created_at": "2025-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 5
  }
}
```

---

## Errores Comunes

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```

### 404 Not Found
```json
{
  "message": "Resource not found"
}
```

### 422 Validation Error
```json
{
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required."],
    "email": ["The email must be a valid email address."]
  }
}
```

### 500 Server Error
```json
{
  "message": "Failed to create resource",
  "error": "Error message details"
}
```

---

## Ejemplos de Uso

### Listar usuarios con filtros

```
GET /nadota-api/users/resource?page=1&per_page=25&sort_by=created_at&sort_direction=desc
```

### Crear un post

```
POST /nadota-api/posts/resource
Content-Type: application/json

{
  "title": "Mi nuevo post",
  "content": "Contenido del post...",
  "category_id": 5
}
```

### Actualizar un usuario

```
PUT /nadota-api/users/resource/1
Content-Type: application/json

{
  "name": "Nuevo Nombre",
  "email": "nuevo@email.com"
}
```

### Obtener opciones para un select de categorías

```
GET /nadota-api/posts/resource/field/category_id/options?search=tech&limit=10
```

### Adjuntar tags a un post (BelongsToMany)

```
POST /nadota-api/posts/resource/1/attach/tags
Content-Type: application/json

{
  "items": [1, 2, 3]
}
```

### Sincronizar roles de un usuario con datos pivot

```
POST /nadota-api/users/resource/1/sync/roles
Content-Type: application/json

{
  "items": [1, 2],
  "pivot": {
    "1": { "expires_at": "2025-12-31" },
    "2": { "expires_at": "2026-06-30" }
  }
}
```

---

## Configuración

El prefijo de la API se configura en `config/nadota.php`:

```php
return [
    'api' => [
        'prefix' => 'nadota-api',
    ],
];
```
