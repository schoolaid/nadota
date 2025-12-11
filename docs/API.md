# Nadota API - Documentación para Frontend

Documentación completa de la API REST de Nadota para implementaciones frontend.

## Tabla de Contenidos

- [Información General](#información-general)
- [Autenticación](#autenticación)
- [Endpoints de Navegación](#endpoints-de-navegación)
- [Endpoints de Configuración de Recursos](#endpoints-de-configuración-de-recursos)
- [Endpoints CRUD](#endpoints-crud)
- [Endpoints de Relaciones](#endpoints-de-relaciones)
- [Endpoints de Acciones](#endpoints-de-acciones)
- [Endpoints de Opciones de Campos](#endpoints-de-opciones-de-campos)
- [Parámetros de Query Comunes](#parámetros-de-query-comunes)
- [Códigos de Estado HTTP](#códigos-de-estado-http)
- [Ejemplos de Implementación](#ejemplos-de-implementación)

---

## Información General

### Base URL

```
/nadota-api
```

### Formato de Respuesta

Todas las respuestas son en formato JSON.

### Headers Requeridos

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

Para uploads de archivos:
```http
Content-Type: multipart/form-data
```

---

## Autenticación

Todos los endpoints requieren autenticación. El token debe enviarse en el header `Authorization`.

```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

---

## Endpoints de Navegación

### Obtener Menú

```http
GET /nadota-api/menu
```

Retorna la estructura del menú de navegación con todos los recursos disponibles.

**Response:**
```json
{
  "sections": [
    {
      "label": "Usuarios",
      "icon": "users",
      "items": [
        {
          "key": "users",
          "label": "Usuarios",
          "icon": "user",
          "path": "/resources/users"
        }
      ]
    }
  ]
}
```

---

## Endpoints de Configuración de Recursos

### Obtener Configuración Completa

```http
GET /nadota-api/{resourceKey}/resource/config
```

Retorna toda la configuración necesaria para renderizar un recurso (info, fields, filters, actions).

**Response:**
```json
{
  "resource": {
    "key": "users",
    "label": "Usuarios",
    "canCreate": true,
    "softDeletes": false,
    "perPage": 15,
    "allowedPerPage": [10, 15, 25, 50, 100],
    "components": {
      "index": "ResourceIndex",
      "show": "ResourceShow",
      "create": "ResourceCreate",
      "update": "ResourceUpdate",
      "delete": "ResourceDelete"
    },
    "search": {
      "key": "search",
      "enabled": true
    },
    "selection": {
      "showRowCheckbox": false,
      "showSelectAll": false
    }
  },
  "fields": [
    {
      "label": "Nombre",
      "attribute": "name",
      "type": "text",
      "component": "FieldInput",
      "sortable": true,
      "filterable": true,
      "searchable": true,
      "showOnIndex": true,
      "showOnDetail": true,
      "showOnCreation": true,
      "showOnUpdate": true
    }
  ],
  "filters": [
    {
      "id": "name-filter",
      "key": "name",
      "name": "Nombre",
      "component": "FilterText",
      "type": "text"
    }
  ],
  "actions": [
    {
      "key": "send-email",
      "name": "Enviar Email",
      "destructive": false,
      "standalone": false,
      "showOnIndex": true,
      "showOnDetail": true
    }
  ]
}
```

### Obtener Información del Recurso

```http
GET /nadota-api/{resourceKey}/resource/info
```

**Response:**
```json
{
  "key": "users",
  "label": "Usuarios",
  "canCreate": true,
  "softDeletes": false
}
```

### Obtener Campos

```http
GET /nadota-api/{resourceKey}/resource/fields
```

Retorna los campos configurados para el index.

**Response:**
```json
{
  "data": [
    {
      "label": "Nombre",
      "attribute": "name",
      "type": "text",
      "component": "FieldInput",
      "sortable": true,
      "filterable": true,
      "props": {}
    }
  ]
}
```

### Obtener Filtros

```http
GET /nadota-api/{resourceKey}/resource/filters
```

Ver [Documentación de Filtros](./FILTERS_API.md) para detalles completos.

---

## Endpoints CRUD

### Listar Recursos (Index)

```http
GET /nadota-api/{resourceKey}/resource
```

**Query Parameters:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `page` | int | 1 | Número de página |
| `perPage` | int | 15 | Items por página |
| `sort` | string | - | Campo para ordenar |
| `direction` | string | asc | Dirección (asc/desc) |
| `search` | string | - | Búsqueda global |
| `filters` | json | - | Filtros (JSON codificado) |
| `trashed` | string | - | `only` o `with` para soft deletes |

**Ejemplo:**
```http
GET /nadota-api/users/resource?page=1&perPage=20&sort=created_at&direction=desc&search=juan&filters={"status":"active"}
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "attributes": [
        {
          "label": "Nombre",
          "attribute": "name",
          "value": "Juan Pérez",
          "type": "text"
        }
      ],
      "deletedAt": null,
      "permissions": {
        "view": true,
        "update": true,
        "delete": true,
        "forceDelete": false,
        "restore": false
      }
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 20,
    "to": 20,
    "total": 100
  }
}
```

> **Nota:** Las actions no se incluyen en cada item del index. Usa `GET /config` o `GET /actions` para obtenerlas una sola vez.

### Obtener Datos para Crear

```http
GET /nadota-api/{resourceKey}/resource/create
```

Retorna los campos con valores por defecto para el formulario de creación.

**Response:**
```json
{
  "fields": [
    {
      "label": "Nombre",
      "attribute": "name",
      "type": "text",
      "value": "",
      "rules": ["required", "string", "max:255"],
      "showOnCreation": true
    }
  ]
}
```

### Crear Recurso

```http
POST /nadota-api/{resourceKey}/resource
```

**Request Body:**
```json
{
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "status": "active"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Resource created successfully",
  "data": {
    "id": 1,
    "name": "Juan Pérez",
    "email": "juan@example.com"
  }
}
```

**Response (422 Validation Error):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

### Ver Recurso (Show)

```http
GET /nadota-api/{resourceKey}/resource/{id}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "attributes": [
      {
        "label": "Nombre",
        "attribute": "name",
        "value": "Juan Pérez",
        "type": "text"
      }
    ],
    "permissions": {
      "view": true,
      "update": true,
      "delete": true
    },
    "title": "Usuarios",
    "tools": [],
    "deletedAt": null
  }
}
```

> **Nota:** Las actions no se incluyen en el show. Usa `GET /actions?context=detail` para obtenerlas.

### Obtener Datos para Editar

```http
GET /nadota-api/{resourceKey}/resource/{id}/edit
```

Retorna los campos con valores actuales para el formulario de edición.

**Response:**
```json
{
  "data": {
    "id": 1,
    "fields": [
      {
        "label": "Nombre",
        "attribute": "name",
        "value": "Juan Pérez",
        "type": "text",
        "rules": ["required", "string"],
        "showOnUpdate": true
      }
    ]
  }
}
```

### Actualizar Recurso

```http
PUT /nadota-api/{resourceKey}/resource/{id}
```

o

```http
PATCH /nadota-api/{resourceKey}/resource/{id}
```

**Request Body:**
```json
{
  "name": "Juan García",
  "email": "juan.garcia@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Resource updated successfully",
  "data": {
    "id": 1,
    "name": "Juan García"
  }
}
```

### Eliminar Recurso

```http
DELETE /nadota-api/{resourceKey}/resource/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "Resource deleted successfully"
}
```

### Eliminar Permanentemente (Force Delete)

```http
DELETE /nadota-api/{resourceKey}/resource/{id}/force
```

Solo disponible para recursos con soft deletes habilitado.

**Response:**
```json
{
  "success": true,
  "message": "Resource permanently deleted"
}
```

### Restaurar Recurso

```http
POST /nadota-api/{resourceKey}/resource/{id}/restore
```

Solo disponible para recursos con soft deletes habilitado.

**Response:**
```json
{
  "success": true,
  "message": "Resource restored successfully"
}
```

---

## Endpoints de Relaciones

### Obtener Elementos Relacionados (Paginados)

```http
GET /nadota-api/{resourceKey}/resource/{id}/relation/{field}
```

Endpoint para obtener registros paginados de relaciones multi-registro. Se usa cuando un campo de relación está configurado con `->paginated()`.

**Relaciones soportadas:**
- `HasMany`
- `BelongsToMany`
- `MorphMany`
- `MorphToMany`
- `MorphedByMany`
- `HasManyThrough`

**Query Parameters:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `page` | int | 1 | Número de página |
| `per_page` | int | 15 | Items por página |
| `search` | string | - | Término de búsqueda (busca en `searchableAttributes` del resource relacionado) |
| `sort_field` | string | - | Campo por el cual ordenar (si no se especifica, usa el `orderBy` del field) |
| `sort_direction` | string | `desc` | Dirección del ordenamiento: `asc` o `desc` |

**Ejemplo de Request:**
```http
GET /nadota-api/schools/resource/1/relation/students?page=2&per_page=25&search=juan&sort_field=name&sort_direction=asc
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "label": "Juan Pérez",
      "resource": "students",
      "deletedAt": null,
      "fields": [
        {"key": "name", "value": "Juan Pérez", "type": "text"},
        {"key": "email", "value": "juan@example.com", "type": "email"}
      ],
      "pivot": {
        "enrolled_at": "2024-01-15",
        "status": "active"
      }
    },
    {
      "id": 2,
      "label": "María García",
      "resource": "students",
      "deletedAt": null,
      "fields": [...],
      "pivot": {...}
    }
  ],
  "meta": {
    "current_page": 2,
    "last_page": 5,
    "per_page": 25,
    "total": 120,
    "from": 26,
    "to": 50,
    "resource": "students",
    "relation_type": "belongsToMany",
    "has_pivot": true
  },
  "links": {
    "first": "/nadota-api/schools/resource/1/relation/students?page=1",
    "last": "/nadota-api/schools/resource/1/relation/students?page=5",
    "prev": "/nadota-api/schools/resource/1/relation/students?page=1",
    "next": "/nadota-api/schools/resource/1/relation/students?page=3"
  }
}
```

**Notas:**
- El campo `fields` solo aparece si el field está configurado con `->withFields()`
- El campo `pivot` solo aparece en relaciones con tabla pivot (`BelongsToMany`, `MorphToMany`, `MorphedByMany`) y si están configuradas con `->withPivot([...])`
- Cuando un field usa `->paginated()`, NO se carga en el show del recurso padre. El frontend debe llamar a este endpoint para obtener los datos.

**Configuración del Field:**
```php
// En el Resource
HasMany::make('Estudiantes', 'students', StudentResource::class)
    ->paginated()           // Habilita paginación (excluye del show)
    ->withFields()          // Incluye fields en response
    ->limit(25)             // Default per_page
    ->orderBy('name', 'asc');

BelongsToMany::make('Roles', 'roles', RoleResource::class)
    ->paginated()
    ->withPivot(['assigned_at', 'expires_at'])
    ->withTimestamps();
```

---

### Obtener Elementos Attachables

```http
GET /nadota-api/{resourceKey}/resource/{id}/attachable/{field}
```

Retorna elementos que pueden ser adjuntados a una relación.

**Query Parameters:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `search` | string | - | Búsqueda |
| `perPage` | int | 15 | Items por página |

**Response:**
```json
{
  "data": [
    {
      "value": 1,
      "label": "Producto A"
    }
  ],
  "meta": {
    "total": 100
  }
}
```

### Adjuntar Relación

```http
POST /nadota-api/{resourceKey}/resource/{id}/attach/{field}
```

**Request Body:**
```json
{
  "resources": [1, 2, 3],
  "pivot": {
    "quantity": 5
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Resources attached successfully"
}
```

### Desadjuntar Relación

```http
POST /nadota-api/{resourceKey}/resource/{id}/detach/{field}
```

**Request Body:**
```json
{
  "resources": [1, 2]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Resources detached successfully"
}
```

---

## Endpoints de Acciones

### Listar Acciones Disponibles

```http
GET /nadota-api/{resourceKey}/resource/actions
```

**Query Parameters:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `context` | string | index | `index` o `detail` |

**Response:**
```json
{
  "actions": [
    {
      "key": "send-email",
      "name": "Enviar Email",
      "fields": [],
      "showOnIndex": true,
      "showOnDetail": true,
      "destructive": false,
      "standalone": false,
      "confirmText": null,
      "confirmButtonText": "Run Action",
      "cancelButtonText": "Cancel"
    },
    {
      "key": "delete-selected",
      "name": "Eliminar Seleccionados",
      "fields": [],
      "showOnIndex": true,
      "showOnDetail": false,
      "destructive": true,
      "standalone": false,
      "confirmText": "¿Estás seguro de que deseas eliminar estos elementos?",
      "confirmButtonText": "Eliminar",
      "cancelButtonText": "Cancelar"
    }
  ]
}
```

### Obtener Campos de una Acción

```http
GET /nadota-api/{resourceKey}/resource/actions/{actionKey}/fields
```

**Response:**
```json
{
  "fields": [
    {
      "label": "Asunto",
      "attribute": "subject",
      "type": "text",
      "rules": ["required"]
    },
    {
      "label": "Mensaje",
      "attribute": "message",
      "type": "textarea",
      "rules": ["required"]
    }
  ]
}
```

### Ejecutar Acción

```http
POST /nadota-api/{resourceKey}/resource/actions/{actionKey}
```

**Request Body:**
```json
{
  "resources": [1, 2, 3],
  "subject": "Notificación",
  "message": "Este es el mensaje"
}
```

**Tipos de Response:**

#### Mensaje de Éxito
```json
{
  "type": "message",
  "message": "Email enviado a 3 usuarios."
}
```

#### Mensaje de Error
```json
{
  "type": "danger",
  "message": "Error al enviar el email."
}
```

#### Redirección
```json
{
  "type": "redirect",
  "url": "/resources/users/1"
}
```

#### Descarga
```json
{
  "type": "download",
  "url": "/storage/exports/report.pdf",
  "filename": "report.pdf"
}
```

#### Abrir en Nueva Pestaña
```json
{
  "type": "openInNewTab",
  "url": "https://external-service.com/report",
  "openInNewTab": true
}
```

### Propiedades de Acciones

| Propiedad | Tipo | Descripción |
|-----------|------|-------------|
| `key` | string | Identificador único de la acción |
| `name` | string | Nombre para mostrar |
| `fields` | array | Campos del formulario de la acción |
| `showOnIndex` | bool | Mostrar en la lista |
| `showOnDetail` | bool | Mostrar en el detalle |
| `destructive` | bool | Es una acción destructiva (roja) |
| `standalone` | bool | Puede ejecutarse sin seleccionar recursos |
| `confirmText` | string|null | Texto de confirmación |
| `confirmButtonText` | string | Texto del botón de confirmar |
| `cancelButtonText` | string | Texto del botón de cancelar |

---

## Endpoints de Opciones de Campos

### Obtener Opciones de Campo

```http
GET /nadota-api/{resourceKey}/resource/field/{fieldName}/options
```

Para campos de tipo select, belongsTo, etc.

**Query Parameters:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `search` | string | - | Búsqueda |
| `limit` | int | 100 | Límite de resultados |
| `filters` | json | - | Filtros adicionales |

**Response:**
```json
{
  "success": true,
  "options": [
    {"value": 1, "label": "Opción A"},
    {"value": 2, "label": "Opción B"}
  ],
  "meta": {
    "total": 2,
    "field_type": "belongsTo"
  }
}
```

### Obtener Opciones Paginadas

```http
GET /nadota-api/{resourceKey}/resource/field/{fieldName}/options/paginated
```

**Query Parameters:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `search` | string | - | Búsqueda |
| `page` | int | 1 | Número de página |
| `perPage` | int | 15 | Items por página |

**Response:**
```json
{
  "success": true,
  "data": [
    {"value": 1, "label": "Opción A"}
  ],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

### Obtener Opciones de Campo Morph

```http
GET /nadota-api/{resourceKey}/resource/field/{fieldName}/morph-options/{morphType}
```

Para campos MorphTo, obtiene las opciones del tipo de morph seleccionado.

**Response:**
```json
{
  "success": true,
  "options": [
    {"value": 1, "label": "Post #1"},
    {"value": 2, "label": "Post #2"}
  ]
}
```

---

## Parámetros de Query Comunes

### Paginación

```
?page=1&perPage=20
```

### Ordenamiento

```
?sort=created_at&direction=desc
```

### Búsqueda

```
?search=término
```

### Filtros

```
?filters={"campo":"valor","rango":{"start":0,"end":100}}
```

**Nota:** El valor de `filters` debe estar codificado en URL.

```javascript
const filters = { status: "active", price: { start: 10, end: 100 } };
const encoded = encodeURIComponent(JSON.stringify(filters));
// ?filters=%7B%22status%22%3A%22active%22...
```

### Soft Deletes

```
?trashed=only   // Solo eliminados
?trashed=with   // Incluir eliminados
```

---

## Códigos de Estado HTTP

| Código | Descripción |
|--------|-------------|
| `200` | OK - Solicitud exitosa |
| `201` | Created - Recurso creado |
| `204` | No Content - Eliminación exitosa |
| `400` | Bad Request - Parámetros inválidos |
| `401` | Unauthorized - No autenticado |
| `403` | Forbidden - Sin permisos |
| `404` | Not Found - Recurso no encontrado |
| `422` | Validation Error - Error de validación |
| `500` | Server Error - Error interno |

---

## Ejemplos de Implementación

### TypeScript - Interfaces

```typescript
// Tipos base
interface Resource {
  key: string;
  label: string;
  canCreate: boolean;
  softDeletes: boolean;
  perPage: number;
  allowedPerPage: number[];
}

interface Field {
  label: string;
  attribute: string;
  type: string;
  component: string;
  value?: any;
  props?: Record<string, any>;
  rules?: string[];
  sortable?: boolean;
  filterable?: boolean;
  showOnIndex?: boolean;
  showOnDetail?: boolean;
  showOnCreation?: boolean;
  showOnUpdate?: boolean;
}

interface Action {
  key: string;
  name: string;
  fields: Field[];
  showOnIndex: boolean;
  showOnDetail: boolean;
  destructive: boolean;
  standalone: boolean;
  confirmText: string | null;
  confirmButtonText: string;
  cancelButtonText: string;
}

interface ActionResponse {
  type: 'message' | 'danger' | 'redirect' | 'download' | 'openInNewTab';
  message?: string;
  url?: string;
  filename?: string;
  openInNewTab?: boolean;
}

interface PaginatedResponse<T> {
  data: T[];
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
  };
}
```

### JavaScript - Cliente API

```javascript
class NadotaClient {
  constructor(baseUrl, token) {
    this.baseUrl = baseUrl;
    this.token = token;
  }

  async request(endpoint, options = {}) {
    const response = await fetch(`${this.baseUrl}${endpoint}`, {
      ...options,
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...options.headers,
      },
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Request failed');
    }

    return response.json();
  }

  // Configuración
  async getConfig(resourceKey) {
    return this.request(`/${resourceKey}/resource/config`);
  }

  // CRUD
  async index(resourceKey, params = {}) {
    const query = new URLSearchParams();

    if (params.page) query.set('page', params.page);
    if (params.perPage) query.set('perPage', params.perPage);
    if (params.sort) query.set('sort', params.sort);
    if (params.direction) query.set('direction', params.direction);
    if (params.search) query.set('search', params.search);
    if (params.filters) {
      query.set('filters', JSON.stringify(params.filters));
    }

    return this.request(`/${resourceKey}/resource?${query}`);
  }

  async show(resourceKey, id) {
    return this.request(`/${resourceKey}/resource/${id}`);
  }

  async create(resourceKey, data) {
    return this.request(`/${resourceKey}/resource`, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async update(resourceKey, id, data) {
    return this.request(`/${resourceKey}/resource/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async destroy(resourceKey, id) {
    return this.request(`/${resourceKey}/resource/${id}`, {
      method: 'DELETE',
    });
  }

  // Acciones
  async getActions(resourceKey, context = 'index') {
    return this.request(`/${resourceKey}/resource/actions?context=${context}`);
  }

  async executeAction(resourceKey, actionKey, resources, fields = {}) {
    return this.request(`/${resourceKey}/resource/actions/${actionKey}`, {
      method: 'POST',
      body: JSON.stringify({ resources, ...fields }),
    });
  }

  // Opciones de campos
  async getFieldOptions(resourceKey, fieldName, search = '') {
    const query = search ? `?search=${encodeURIComponent(search)}` : '';
    return this.request(`/${resourceKey}/resource/field/${fieldName}/options${query}`);
  }
}

// Uso
const client = new NadotaClient('/nadota-api', 'your-token');

// Obtener configuración
const config = await client.getConfig('users');

// Listar con filtros
const users = await client.index('users', {
  page: 1,
  perPage: 20,
  filters: { status: 'active' },
  sort: 'created_at',
  direction: 'desc',
});

// Ejecutar acción
const result = await client.executeAction('users', 'send-email', [1, 2, 3], {
  subject: 'Hola',
  message: 'Mensaje de prueba',
});

if (result.type === 'message') {
  console.log('Éxito:', result.message);
} else if (result.type === 'danger') {
  console.error('Error:', result.message);
} else if (result.type === 'redirect') {
  window.location.href = result.url;
}
```

### Vue 3 - Composable

```javascript
// useResource.js
import { ref, computed } from 'vue';

export function useResource(resourceKey) {
  const config = ref(null);
  const items = ref([]);
  const loading = ref(false);
  const meta = ref({});
  const error = ref(null);

  const client = new NadotaClient('/nadota-api', getToken());

  async function loadConfig() {
    loading.value = true;
    try {
      config.value = await client.getConfig(resourceKey);
    } catch (e) {
      error.value = e.message;
    } finally {
      loading.value = false;
    }
  }

  async function loadItems(params = {}) {
    loading.value = true;
    try {
      const response = await client.index(resourceKey, params);
      items.value = response.data;
      meta.value = response.meta;
    } catch (e) {
      error.value = e.message;
    } finally {
      loading.value = false;
    }
  }

  async function executeAction(actionKey, selectedIds, fields = {}) {
    loading.value = true;
    try {
      return await client.executeAction(resourceKey, actionKey, selectedIds, fields);
    } catch (e) {
      error.value = e.message;
      throw e;
    } finally {
      loading.value = false;
    }
  }

  return {
    config,
    items,
    loading,
    meta,
    error,
    loadConfig,
    loadItems,
    executeAction,
  };
}
```

---

## Referencias

- [Documentación de Filtros](./FILTERS_API.md)
- [Documentación de Campos](./FIELDS.md)
- [Documentación de Relaciones](./RELATIONS.md)
- [Respuestas de Archivos](./API_RESPONSES.md)
