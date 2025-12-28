# Nadota API Routes

Base URL: `/nadota-api`

---

## Resource Configuration

### GET /{resource}/resource/config
Obtiene toda la configuración del resource en una sola llamada. Recomendado para inicialización.

**Response:**
```json
{
  "resource": {
    "key": "users",
    "label": "Users",
    "canCreate": true,
    "softDeletes": false,
    "perPage": 15,
    "allowedPerPage": [15, 25, 50, 100],
    "components": {},
    "search": {
      "key": "search",
      "enabled": true
    },
    "selection": {
      "enabled": true,
      "mode": "multiple"
    }
  },
  "fields": [...],
  "filters": [...],
  "actions": [...],
  "sections": {
    "detail": [...],
    "create": [...],
    "update": [...]
  },
  "export": {
    "enabled": true,
    "formats": ["csv"],
    "syncLimit": 1000,
    "columns": [...]
  }
}
```

### GET /{resource}/resource/info
Obtiene información básica del resource.

**Response:**
```json
{
  "data": {
    "key": "users",
    "title": "Users",
    "description": "Manage system users",
    "perPage": 15,
    "allowedPerPage": [15, 25, 50, 100],
    "allowedSoftDeletes": false,
    "canCreate": true,
    "components": {},
    "search": {
      "key": "search",
      "enabled": true
    },
    "selection": {...},
    "export": {...}
  }
}
```

### GET /{resource}/resource/fields
Obtiene los campos visibles en el index.

**Response:**
```json
{
  "data": [
    {
      "key": "name",
      "label": "Name",
      "attribute": "name",
      "component": "FieldText",
      "sortable": true,
      "filterable": false
    }
  ]
}
```

### GET /{resource}/resource/filters
Obtiene los filtros disponibles.

**Response:**
```json
{
  "data": [
    {
      "key": "status",
      "label": "Status",
      "component": "FilterSelect",
      "type": "select",
      "options": [
        { "label": "Active", "value": "active" },
        { "label": "Inactive", "value": "inactive" }
      ]
    }
  ]
}
```

---

## CRUD Operations

### GET /{resource}/resource
Lista los registros del resource con paginación.

**Query Parameters:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Página actual |
| `perPage` | int | Registros por página |
| `search` | string | Término de búsqueda |
| `sortBy` | string | Campo para ordenar |
| `sortDirection` | string | `asc` o `desc` |
| `filters[field]` | mixed | Filtros dinámicos |
| `trashed` | string | `with`, `only`, `without` (soft deletes) |

**Response:**
```json
{
  "data": [...],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

### GET /{resource}/resource/create
Obtiene los campos para el formulario de creación.

**Response:**
```json
{
  "data": {
    "key": "users",
    "title": "Users",
    "attributes": [
      {
        "key": "name",
        "label": "Name",
        "attribute": "name",
        "component": "FieldText",
        "value": null,
        "rules": ["required", "string", "max:255"]
      }
    ]
  }
}
```

### POST /{resource}/resource
Crea un nuevo registro.

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com"
}
```

**Response (201):**
```json
{
  "message": "Resource created successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

### GET /{resource}/resource/{id}
Obtiene un registro específico (detail view).

**Response:**
```json
{
  "data": {
    "id": 1,
    "key": "users",
    "title": "Users",
    "attributes": [...],
    "permissions": {
      "update": true,
      "delete": true,
      "restore": false,
      "forceDelete": false
    },
    "deletedAt": null,
    "tools": [...],
    "actionEventsUrl": "/nadota-api/users/resource/1/action-events"
  }
}
```

### GET /{resource}/resource/{id}/edit
Obtiene los campos para el formulario de edición.

**Response:**
```json
{
  "data": {
    "id": 1,
    "key": "users",
    "title": "Users",
    "attributes": [...],
    "permissions": {...},
    "deletedAt": null
  }
}
```

### PUT /{resource}/resource/{id}
Actualiza un registro.

**Request Body:**
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com"
}
```

**Response (200):**
```json
{
  "message": "Resource updated successfully",
  "data": {...}
}
```

### PATCH /{resource}/resource/{id}
Actualización parcial de un registro (mismo comportamiento que PUT).

### DELETE /{resource}/resource/{id}
Elimina un registro (soft delete si está habilitado).

**Response (200):**
```json
{
  "message": "Resource deleted successfully"
}
```

### DELETE /{resource}/resource/{id}/force
Elimina permanentemente un registro.

**Response (200):**
```json
{
  "message": "Resource force deleted successfully"
}
```

### POST /{resource}/resource/{id}/restore
Restaura un registro eliminado (soft delete).

**Response (200):**
```json
{
  "message": "Resource restored successfully"
}
```

### GET /{resource}/resource/{id}/permissions
Obtiene los permisos del usuario para un registro específico.

**Response:**
```json
{
  "data": {
    "view": true,
    "update": true,
    "delete": true,
    "restore": false,
    "forceDelete": false
  }
}
```

---

## Actions

### GET /{resource}/resource/actions
Obtiene las acciones disponibles para el resource.

**Response:**
```json
{
  "data": [
    {
      "key": "send-email",
      "name": "Send Email",
      "confirmText": "Are you sure?",
      "confirmButtonText": "Send",
      "cancelButtonText": "Cancel",
      "destructive": false
    }
  ]
}
```

### GET /{resource}/resource/actions/{actionKey}/fields
Obtiene los campos del formulario de una acción.

**Response:**
```json
{
  "data": {
    "key": "send-email",
    "name": "Send Email",
    "fields": [
      {
        "key": "subject",
        "label": "Subject",
        "component": "FieldText"
      }
    ]
  }
}
```

### POST /{resource}/resource/actions/{actionKey}
Ejecuta una acción sobre registros seleccionados.

**Request Body:**
```json
{
  "resources": [1, 2, 3],
  "subject": "Hello"
}
```

**Response:**
```json
{
  "message": "Action executed successfully",
  "data": {...}
}
```

---

## Relations

### GET /{resource}/resource/{id}/relation/{field}
Obtiene registros paginados de una relación HasMany/BelongsToMany.

**Query Parameters:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Página actual |
| `perPage` | int | Registros por página |
| `search` | string | Búsqueda en la relación |

**Response:**
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "total": 50
  }
}
```

---

## Attachments (BelongsToMany/MorphToMany)

### GET /{resource}/resource/{id}/attachable/{field}
Obtiene registros disponibles para adjuntar.

**Query Parameters:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `search` | string | Filtrar opciones |
| `except` | array | IDs a excluir |

**Response:**
```json
{
  "data": [
    { "value": 1, "label": "Option 1" },
    { "value": 2, "label": "Option 2" }
  ]
}
```

### POST /{resource}/resource/{id}/attach/{field}
Adjunta registros a una relación.

**Request Body:**
```json
{
  "resources": [1, 2, 3],
  "pivot": {
    "role": "editor"
  }
}
```

### POST /{resource}/resource/{id}/detach/{field}
Desvincula registros de una relación.

**Request Body:**
```json
{
  "resources": [1, 2]
}
```

### POST /{resource}/resource/{id}/sync/{field}
Sincroniza una relación (reemplaza todos los registros).

**Request Body:**
```json
{
  "resources": [1, 2, 3]
}
```

---

## Field Options

### GET /{resource}/resource/field/{fieldName}/options
Obtiene opciones para un campo de relación (BelongsTo, etc).

**Query Parameters:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `search` | string | Filtrar opciones |
| `except` | array | IDs a excluir |
| `dependsOn[field]` | mixed | Valores de campos dependientes |

**Response:**
```json
{
  "data": [
    { "value": 1, "label": "Option 1" },
    { "value": 2, "label": "Option 2" }
  ]
}
```

### GET /{resource}/resource/field/{fieldName}/options/paginated
Opciones paginadas para campos con muchos registros.

**Query Parameters:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Página actual |
| `perPage` | int | Opciones por página |
| `search` | string | Filtrar opciones |

**Response:**
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "total": 100
  }
}
```

### GET /{resource}/resource/field/{fieldName}/morph-options/{morphType}
Obtiene opciones para un campo MorphTo según el tipo seleccionado.

**Response:**
```json
{
  "data": [
    { "value": 1, "label": "Post #1" },
    { "value": 2, "label": "Post #2" }
  ]
}
```

---

## Resource Options

### GET /{resource}/resource/options
Obtiene registros del resource como opciones (para selects en otros resources).

**Query Parameters:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `search` | string | Filtrar opciones |
| `ids` | array | Obtener opciones específicas por ID |

**Response:**
```json
{
  "data": [
    { "value": 1, "label": "User 1" },
    { "value": 2, "label": "User 2" }
  ]
}
```

---

## Export

### GET /{resource}/resource/export
Descarga los datos del resource en el formato especificado.

**Query Parameters:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `format` | string | Formato de export (`csv`) |
| `columns[]` | array | Columnas específicas a exportar |
| `filename` | string | Nombre del archivo (sin extensión) |
| `search` | string | Aplicar búsqueda |
| `sortBy` | string | Campo para ordenar |
| `sortDirection` | string | `asc` o `desc` |
| `filters[field]` | mixed | Aplicar filtros |

**Response:** Archivo binario para descarga.

**Headers de respuesta:**
```
Content-Type: text/csv; charset=UTF-8
Content-Disposition: attachment; filename="users_2024-01-15.csv"
```

### GET /{resource}/resource/export/config
Obtiene la configuración de export del resource.

**Response:**
```json
{
  "data": {
    "enabled": true,
    "formats": ["csv"],
    "syncLimit": 1000,
    "columns": [
      { "key": "name", "label": "Name" },
      { "key": "email", "label": "Email" }
    ]
  }
}
```

---

## Action Events

### GET /{resource}/resource/{id}/action-events
Obtiene el historial de acciones realizadas sobre un registro.

**Query Parameters:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `page` | int | Página actual |
| `perPage` | int | Eventos por página |

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "action": "created",
      "user": {
        "id": 1,
        "name": "Admin"
      },
      "changes": {
        "name": { "old": null, "new": "John" }
      },
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {...}
}
```

---

## Compact Data

### GET /{resource}/resource/data
Obtiene datos compactos del resource (sin paginación).

**Query Parameters:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `fields` | string | Campos separados por coma (`id,name,email`) |

**Response:**
```json
[
  { "id": 1, "name": "John", "email": "john@example.com" },
  { "id": 2, "name": "Jane", "email": "jane@example.com" }
]
```

---

## Sections (Layout Configuration)

Las secciones definen cómo agrupar campos en las vistas detail/create/update.

**Estructura en /config:**
```json
{
  "sections": {
    "detail": [
      {
        "type": "default",
        "fieldKeys": ["email", "phone"]
      },
      {
        "type": "section",
        "title": "Personal Info",
        "icon": "user",
        "description": "Basic user information",
        "collapsible": true,
        "collapsed": false,
        "fieldKeys": ["name", "birth_date"]
      }
    ],
    "create": [...],
    "update": [...]
  }
}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `type` | string | `default` (sin título) o `section` |
| `title` | string | Título de la sección |
| `icon` | string | Icono de la sección |
| `description` | string | Descripción opcional |
| `collapsible` | boolean | Si se puede colapsar |
| `collapsed` | boolean | Si inicia colapsada |
| `fieldKeys` | array | Keys de los campos en esta sección |

---

## Error Responses

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
  "message": "Resource not found."
}
```

### 422 Validation Error
```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."],
    "name": ["The name must be at least 3 characters."]
  }
}
```

### 500 Server Error
```json
{
  "message": "Server error",
  "error": "Error details..."
}
```
