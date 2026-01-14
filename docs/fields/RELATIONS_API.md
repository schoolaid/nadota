# Relations API - Frontend Documentation

Documentacion para consumir campos de relacion desde el frontend.

---

## Navegacion Rapida

| [HasMany](#hasmany) | [HasOne](#hasone) | [MorphMany](#morphmany) | [MorphOne](#morphone) |
| [BelongsToMany](#belongstomany) | [MorphToMany](#morphtomany) | [createContext](#createcontext) |

---

## Tipos de Relacion

| Tipo | Descripcion | URLs | createContext |
|------|-------------|------|---------------|
| **HasMany** | 1:N - Un padre tiene muchos hijos | `attachable`, `attach`, `detach` | Si |
| **HasOne** | 1:1 - Un padre tiene un hijo | `create`, `show` | Si |
| **MorphMany** | 1:N polimorfico | `attachable`, `attach`, `detach` | Si |
| **MorphOne** | 1:1 polimorfico | `create`, `show` | Si |
| **BelongsToMany** | N:N con tabla pivot | `options`, `attach`, `detach`, `sync` | No |
| **MorphToMany** | N:N polimorfico con pivot | `options`, `attach`, `detach`, `sync` | No |

---

## HasMany

Relacion uno a muchos. El modelo padre tiene muchos hijos.

### Respuesta JSON

```json
{
  "key": "internals",
  "label": "Internos",
  "attribute": "",
  "type": "hasMany",
  "component": "field-has-many",
  "value": {
    "data": [
      {
        "id": 1,
        "label": "Juan Perez",
        "attributes": { "name": "Juan Perez", "document": "12345678" }
      },
      {
        "id": 2,
        "label": "Maria Garcia",
        "attributes": { "name": "Maria Garcia", "document": "87654321" }
      }
    ],
    "meta": {
      "total": 2,
      "hasMore": false
    }
  },
  "props": {
    "limit": 10,
    "paginated": false,
    "orderBy": null,
    "orderDirection": "desc",
    "relationType": "hasMany",
    "resource": "access-internal",
    "canCreate": false,
    "attachment": {
      "enabled": true,
      "searchFields": ["id"],
      "limit": null,
      "showCountOnIndex": false,
      "buttonLabel": null,
      "displayFields": []
    },
    "urls": {
      "attachable": "/nadota-api/accesses-qr/resource/2/attachable/internals",
      "attach": "/nadota-api/accesses-qr/resource/2/attach/internals",
      "detach": "/nadota-api/accesses-qr/resource/2/detach/internals"
    },
    "createContext": {
      "parentResource": "accesses-qr",
      "parentId": 2,
      "relatedResource": "access-internal",
      "foreignKey": "access_id",
      "prefill": {
        "access_id": 2
      },
      "lock": ["access_id"],
      "returnUrl": "/resources/accesses-qr/2",
      "createUrl": "/nadota-api/access-internal/resource/create",
      "storeUrl": "/nadota-api/access-internal/resource"
    }
  }
}
```

### Props HasMany

| Propiedad | Tipo | Descripcion |
|-----------|------|-------------|
| `limit` | number\|null | Limite de items a mostrar |
| `paginated` | boolean | Si usa paginacion |
| `orderBy` | string\|null | Campo de ordenamiento |
| `orderDirection` | string | Direccion de orden (`asc`/`desc`) |
| `relationType` | string | Siempre `"hasMany"` |
| `resource` | string | Key del recurso relacionado |
| `canCreate` | boolean | Si permite crear desde este campo |
| `attachment` | object | Configuracion de attachments |
| `urls` | object | URLs de la API |
| `createContext` | object | Contexto para crear items relacionados |

### URLs HasMany

| URL | Metodo | Descripcion |
|-----|--------|-------------|
| `attachable` | GET | Buscar items para adjuntar |
| `attach` | POST | Adjuntar un item existente |
| `detach` | POST | Desadjuntar un item |
| `paginationUrl` | GET | Cargar mas items (si `paginated: true`) |

### Paginacion (HasMany con `paginated: true`)

Cuando un campo HasMany tiene `paginated: true`, se incluye una URL `paginationUrl` que permite cargar los items relacionados de forma paginada con soporte para filtros, busqueda y ordenamiento.

**Endpoint:**
```
GET /nadota-api/{resource}/resource/{id}/relation/{field}
```

**Parametros de Query:**

| Parametro | Tipo | Descripcion |
|-----------|------|-------------|
| `page` | number | Numero de pagina (default: 1) |
| `per_page` | number | Items por pagina (default: 15) |
| `search` | string | Busqueda en campos searchable del recurso relacionado |
| `filters[{field}]` | any | Filtros por campo (usa los filtros del recurso relacionado) |
| `sort_field` | string | Campo para ordenar |
| `sort_direction` | string | Direccion de orden (`asc`/`desc`) |

**Ejemplo de Request:**
```
GET /nadota-api/accesses-qr/resource/2/relation/internals?page=1&per_page=10&search=Juan&filters[document_type_id]=5&sort_field=name&sort_direction=asc
```

**Ejemplo de Response:**
```json
{
  "data": [
    {
      "id": 1,
      "label": "Juan Perez",
      "attributes": { "name": "Juan Perez", "document": "12345678" }
    },
    {
      "id": 2,
      "label": "Juan Garcia",
      "attributes": { "name": "Juan Garcia", "document": "87654321" }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 25,
    "from": 1,
    "to": 10,
    "resource": "access-internal",
    "relation_type": "hasMany",
    "has_pivot": false,
    "filters": [
      {
        "key": "document_type_id",
        "label": "Tipo de Documento",
        "component": "FilterSelect",
        "type": "select",
        "options": [
          { "value": 1, "label": "DNI" },
          { "value": 2, "label": "Pasaporte" }
        ]
      },
      {
        "key": "name",
        "label": "Nombre",
        "component": "FilterText",
        "type": "text"
      }
    ]
  },
  "links": {
    "first": "/nadota-api/accesses-qr/resource/2/relation/internals?page=1",
    "last": "/nadota-api/accesses-qr/resource/2/relation/internals?page=3",
    "prev": null,
    "next": "/nadota-api/accesses-qr/resource/2/relation/internals?page=2"
  }
}
```

**Notas:**
- Los filtros disponibles dependen de los campos marcados como `filterable()` en el recurso relacionado
- La busqueda usa los campos marcados como `searchable()` en el recurso relacionado
- El ordenamiento por defecto puede estar definido en el campo usando `orderBy()` y `orderDirection()`

**Configuración de Filtros:**
Los filtros disponibles se incluyen automáticamente en `meta.filters` de la respuesta. Estos filtros provienen de:
1. Campos del recurso relacionado marcados con `->filterable()`
2. Filtros definidos en el método `filters()` del recurso relacionado

**Ejemplo en el Recurso Relacionado:**
```php
class AccessInternalResource extends Resource
{
    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')
                ->filterable()  // ← Genera un filtro de texto
                ->searchable(),

            BelongsTo::make('Document Type', 'documentType', DocumentTypeResource::class)
                ->filterable(),  // ← Genera un filtro de select
        ];
    }

    public function filters(NadotaRequest $request): array
    {
        return [
            // Filtros personalizados adicionales
            new DateRangeFilter('Created At', 'created_at'),
        ];
    }
}
```

El frontend recibirá todos estos filtros en `meta.filters` al hacer la primera petición al endpoint de paginación.

---

## HasOne

Relacion uno a uno. El modelo padre tiene un solo hijo.

### Respuesta JSON

```json
{
  "key": "provider",
  "label": "Proveedor",
  "type": "hasOne",
  "component": "field-has-one",
  "value": {
    "id": 5,
    "label": "ACME Corp",
    "attributes": { "name": "ACME Corp", "rut": "12345678-9" }
  },
  "props": {
    "relationType": "hasOne",
    "resource": "access-provider",
    "urls": {
      "create": "/nadota-api/access-provider/resource/create",
      "show": "/nadota-api/access-provider/resource"
    },
    "createContext": {
      "parentResource": "accesses-qr",
      "parentId": 2,
      "relatedResource": "access-provider",
      "foreignKey": "access_id",
      "prefill": {
        "access_id": 2
      },
      "lock": ["access_id"],
      "returnUrl": "/resources/accesses-qr/2",
      "createUrl": "/nadota-api/access-provider/resource/create",
      "storeUrl": "/nadota-api/access-provider/resource"
    }
  }
}
```

### Props HasOne

| Propiedad | Tipo | Descripcion |
|-----------|------|-------------|
| `relationType` | string | Siempre `"hasOne"` |
| `resource` | string | Key del recurso relacionado |
| `urls` | object | URLs de la API |
| `createContext` | object | Contexto para crear el item relacionado |

---

## MorphMany

Relacion uno a muchos polimorfica. Multiples modelos pueden tener muchos del mismo tipo.

### Respuesta JSON

```json
{
  "key": "comments",
  "label": "Comentarios",
  "type": "morphMany",
  "component": "field-morph-many",
  "value": {
    "data": [
      { "id": 1, "label": "Comentario 1" },
      { "id": 2, "label": "Comentario 2" }
    ],
    "meta": { "total": 2, "hasMore": false }
  },
  "props": {
    "relationType": "morphMany",
    "resource": "comments",
    "isPolymorphic": true,
    "urls": {
      "attachable": "/nadota-api/posts/resource/5/attachable/comments",
      "attach": "/nadota-api/posts/resource/5/attach/comments",
      "detach": "/nadota-api/posts/resource/5/detach/comments"
    },
    "createContext": {
      "parentResource": "posts",
      "parentId": 5,
      "relatedResource": "comments",
      "morphType": "commentable_type",
      "morphId": "commentable_id",
      "morphClass": "App\\Models\\Post",
      "prefill": {
        "commentable_type": "App\\Models\\Post",
        "commentable_id": 5
      },
      "lock": ["commentable_type", "commentable_id"],
      "returnUrl": "/resources/posts/5",
      "createUrl": "/nadota-api/comments/resource/create",
      "storeUrl": "/nadota-api/comments/resource",
      "isPolymorphic": true
    }
  }
}
```

### Props MorphMany

| Propiedad | Tipo | Descripcion |
|-----------|------|-------------|
| `relationType` | string | Siempre `"morphMany"` |
| `resource` | string | Key del recurso relacionado |
| `isPolymorphic` | boolean | Siempre `true` |
| `urls` | object | URLs de la API |
| `createContext` | object | Contexto para crear (incluye morph keys) |

---

## MorphOne

Relacion uno a uno polimorfica.

### Respuesta JSON

```json
{
  "key": "image",
  "label": "Imagen",
  "type": "morphOne",
  "component": "field-morph-one",
  "value": {
    "id": 10,
    "label": "profile.jpg",
    "attributes": { "path": "/images/profile.jpg" }
  },
  "props": {
    "relationType": "morphOne",
    "resource": "images",
    "isPolymorphic": true,
    "urls": {
      "create": "/nadota-api/images/resource/create",
      "show": "/nadota-api/images/resource"
    },
    "createContext": {
      "parentResource": "users",
      "parentId": 1,
      "relatedResource": "images",
      "morphType": "imageable_type",
      "morphId": "imageable_id",
      "morphClass": "App\\Models\\User",
      "prefill": {
        "imageable_type": "App\\Models\\User",
        "imageable_id": 1
      },
      "lock": ["imageable_type", "imageable_id"],
      "returnUrl": "/resources/users/1",
      "createUrl": "/nadota-api/images/resource/create",
      "storeUrl": "/nadota-api/images/resource",
      "isPolymorphic": true
    }
  }
}
```

---

## BelongsToMany

Relacion muchos a muchos con tabla pivot. No tiene `createContext` porque relaciona items existentes.

### Respuesta JSON

```json
{
  "key": "roles",
  "label": "Roles",
  "type": "belongsToMany",
  "component": "field-belongs-to-many",
  "value": [
    { "id": 1, "label": "Admin", "pivot": { "assigned_at": "2025-01-15" } },
    { "id": 2, "label": "Editor", "pivot": { "assigned_at": "2025-01-10" } }
  ],
  "props": {
    "relationType": "belongsToMany",
    "resource": "roles",
    "urls": {
      "options": "/nadota-api/users/resource/field/roles/options?resourceId=1",
      "attach": "/nadota-api/users/resource/1/attach/roles",
      "detach": "/nadota-api/users/resource/1/detach/roles",
      "sync": "/nadota-api/users/resource/1/sync/roles"
    }
  }
}
```

### URLs BelongsToMany

| URL | Metodo | Body | Descripcion |
|-----|--------|------|-------------|
| `options` | GET | - | Obtener opciones disponibles |
| `attach` | POST | `{ "id": 1, "pivot": {...} }` | Adjuntar item |
| `detach` | POST | `{ "id": 1 }` | Desadjuntar item |
| `sync` | POST | `{ "ids": [1, 2, 3] }` | Sincronizar todos |
| `paginationUrl` | GET | - | Cargar items paginados (si `paginated: true`) |

### Paginacion (BelongsToMany con `paginated: true`)

Las relaciones BelongsToMany tambien soportan paginacion con filtros, busqueda y ordenamiento usando el mismo endpoint que HasMany:

**Endpoint:**
```
GET /nadota-api/{resource}/resource/{id}/relation/{field}
```

**Parametros:**
Los mismos que HasMany (ver seccion anterior)

**Diferencias con HasMany:**
- La respuesta puede incluir datos de la tabla pivot en `pivot`:
  ```json
  {
    "id": 1,
    "label": "Admin",
    "pivot": { "assigned_at": "2025-01-15", "expires_at": "2026-01-15" }
  }
  ```
- `meta.has_pivot` sera `true` si el campo tiene columnas pivot configuradas
- Los filtros NO se aplican a columnas pivot, solo a columnas del modelo relacionado
- `meta.filters` incluye los filtros disponibles (igual que HasMany)

**Ejemplo de Response con Pivot:**
```json
{
  "data": [
    {
      "id": 1,
      "label": "Admin",
      "pivot": { "assigned_at": "2025-01-15", "expires_at": "2026-01-15" }
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 5,
    "resource": "roles",
    "has_pivot": true,
    "filters": [
      {
        "key": "name",
        "label": "Role Name",
        "component": "FilterText",
        "type": "text"
      },
      {
        "key": "is_active",
        "label": "Active",
        "component": "FilterBoolean",
        "type": "boolean"
      }
    ]
  }
}
```

---

## MorphToMany

Relacion muchos a muchos polimorfica. Similar a BelongsToMany pero con morph.

### Respuesta JSON

```json
{
  "key": "tags",
  "label": "Tags",
  "type": "morphToMany",
  "component": "field-morph-to-many",
  "value": [
    { "id": 1, "label": "Laravel" },
    { "id": 2, "label": "PHP" }
  ],
  "props": {
    "relationType": "morphToMany",
    "resource": "tags",
    "isPolymorphic": true,
    "urls": {
      "options": "/nadota-api/posts/resource/field/tags/options?resourceId=5",
      "attach": "/nadota-api/posts/resource/5/attach/tags",
      "detach": "/nadota-api/posts/resource/5/detach/tags",
      "sync": "/nadota-api/posts/resource/5/sync/tags"
    }
  }
}
```

---

## createContext

El objeto `createContext` proporciona toda la informacion necesaria para que el frontend pueda:

1. **Preseleccionar** el campo de relacion (BelongsTo/MorphTo) al crear un item relacionado
2. **Bloquear** el campo para que no sea editable
3. **Redirigir** al usuario de vuelta al padre despues de crear

### Estructura para HasMany/HasOne

```typescript
interface CreateContext {
  // Informacion del padre
  parentResource: string;      // Key del recurso padre
  parentId: number;            // ID del modelo padre

  // Informacion del relacionado
  relatedResource: string;     // Key del recurso a crear
  foreignKey: string;          // Nombre de la FK (ej: "access_id")

  // Valores para el formulario
  prefill: Record<string, any>; // { "access_id": 2 }
  lock: string[];               // ["access_id"]

  // URLs
  returnUrl: string;           // A donde volver despues de crear
  createUrl: string;           // GET - Obtener form de creacion
  storeUrl: string;            // POST - Guardar el nuevo item
}
```

### Estructura para MorphMany/MorphOne

```typescript
interface CreateContextPolymorphic extends CreateContext {
  // Campos morph adicionales
  morphType: string;           // Nombre de la columna type (ej: "commentable_type")
  morphId: string;             // Nombre de la columna id (ej: "commentable_id")
  morphClass: string;          // Clase del modelo padre (ej: "App\\Models\\Post")
  isPolymorphic: true;

  // prefill incluye ambos campos
  prefill: {
    "commentable_type": "App\\Models\\Post",
    "commentable_id": 5
  };

  // lock incluye ambos campos
  lock: ["commentable_type", "commentable_id"];
}
```

---

## Uso en Frontend

### Cargar Relacion Paginada con Filtros

```typescript
interface PaginatedRelationResponse {
  data: Array<{
    id: number;
    label: string;
    attributes?: Record<string, any>;
    pivot?: Record<string, any>;
  }>;
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    resource: string;
    filters: Array<{
      key: string;
      label: string;
      component: string;
      type: string;
      options?: any[];
    }>;
  };
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
}

async function loadPaginatedRelation(
  paginationUrl: string,
  options?: {
    page?: number;
    perPage?: number;
    search?: string;
    filters?: Record<string, any>;
    sortField?: string;
    sortDirection?: 'asc' | 'desc';
  }
): Promise<PaginatedRelationResponse> {
  const params = new URLSearchParams();

  if (options?.page) params.append('page', String(options.page));
  if (options?.perPage) params.append('per_page', String(options.perPage));
  if (options?.search) params.append('search', options.search);
  if (options?.sortField) params.append('sort_field', options.sortField);
  if (options?.sortDirection) params.append('sort_direction', options.sortDirection);

  // Agregar filtros
  if (options?.filters) {
    Object.entries(options.filters).forEach(([key, value]) => {
      params.append(`filters[${key}]`, String(value));
    });
  }

  const url = `${paginationUrl}?${params.toString()}`;
  const response = await fetch(url);
  return response.json();
}

// Uso
const result = await loadPaginatedRelation(
  '/nadota-api/accesses-qr/resource/2/relation/internals',
  {
    page: 1,
    perPage: 10,
    search: 'Juan',
    filters: {
      document_type_id: 5,
      is_active: true
    },
    sortField: 'name',
    sortDirection: 'asc'
  }
);

// Los filtros disponibles estan en result.meta.filters
console.log('Available filters:', result.meta.filters);
```

### Componente React con Filtros Dinamicos

```tsx
import React, { useState, useEffect } from 'react';

interface Filter {
  key: string;
  label: string;
  component: string;
  type: string;
  options?: any[];
}

interface PaginatedRelationProps {
  paginationUrl: string;
}

export function PaginatedRelation({ paginationUrl }: PaginatedRelationProps) {
  const [data, setData] = useState([]);
  const [meta, setMeta] = useState(null);
  const [filters, setFilters] = useState<Filter[]>([]);
  const [activeFilters, setActiveFilters] = useState({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, [activeFilters]);

  async function loadData(page = 1) {
    setLoading(true);
    const result = await loadPaginatedRelation(paginationUrl, {
      page,
      filters: activeFilters
    });

    setData(result.data);
    setMeta(result.meta);

    // Los filtros solo se cargan en la primera request
    if (result.meta.filters && filters.length === 0) {
      setFilters(result.meta.filters);
    }

    setLoading(false);
  }

  function handleFilterChange(filterKey: string, value: any) {
    setActiveFilters(prev => ({
      ...prev,
      [filterKey]: value
    }));
  }

  return (
    <div>
      {/* Renderizar filtros dinamicamente */}
      <div className="filters">
        {filters.map(filter => (
          <FilterComponent
            key={filter.key}
            filter={filter}
            value={activeFilters[filter.key]}
            onChange={(value) => handleFilterChange(filter.key, value)}
          />
        ))}
      </div>

      {/* Renderizar datos */}
      {loading ? (
        <div>Cargando...</div>
      ) : (
        <div>
          {data.map(item => (
            <div key={item.id}>{item.label}</div>
          ))}
        </div>
      )}

      {/* Paginacion */}
      {meta && (
        <Pagination
          currentPage={meta.current_page}
          lastPage={meta.last_page}
          onPageChange={loadData}
        />
      )}
    </div>
  );
}
```

### Crear Item Relacionado (HasMany)

```typescript
async function createRelatedItem(field: RelationField) {
  const { createContext } = field.props;

  if (!createContext) return;

  // 1. Obtener el formulario de creacion
  const formResponse = await fetch(createContext.createUrl);
  const formData = await formResponse.json();

  // 2. Preseleccionar y bloquear campos
  const fields = formData.data.fields.map(f => {
    // Si el campo esta en prefill, establecer valor
    if (createContext.prefill[f.attribute]) {
      f.value = createContext.prefill[f.attribute];
    }

    // Si el campo esta en lock, deshabilitarlo
    if (createContext.lock.includes(f.attribute)) {
      f.disabled = true;
      f.readonly = true;
    }

    return f;
  });

  // 3. Mostrar formulario con campos modificados
  showCreateForm(fields, createContext);
}

async function submitRelatedItem(formData: any, createContext: CreateContext) {
  // Asegurar que los campos prellenados esten en el request
  const data = {
    ...formData,
    ...createContext.prefill
  };

  const response = await fetch(createContext.storeUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });

  if (response.ok) {
    // Redirigir al padre
    window.location.href = createContext.returnUrl;
  }
}
```

### Crear Item Polimorfico (MorphMany)

```typescript
async function createPolymorphicItem(field: RelationField) {
  const { createContext } = field.props;

  if (!createContext?.isPolymorphic) return;

  // El prefill ya incluye morphType y morphId
  // { "commentable_type": "App\\Models\\Post", "commentable_id": 5 }

  const formResponse = await fetch(createContext.createUrl);
  const formData = await formResponse.json();

  // Buscar el campo MorphTo en el formulario
  const fields = formData.data.fields.map(f => {
    // El campo MorphTo puede tener diferentes nombres
    // Buscar por el morphType o morphId
    if (f.attribute === createContext.morphType ||
        f.attribute === createContext.morphId ||
        f.type === 'morphTo') {

      // Preseleccionar el valor completo del morph
      f.value = {
        type: createContext.morphClass,
        id: createContext.parentId
      };

      // Bloquear el campo
      f.disabled = true;
      f.readonly = true;
    }

    return f;
  });

  showCreateForm(fields, createContext);
}
```

### Componente React de Ejemplo

```tsx
import React from 'react';

interface CreateContextProps {
  createContext: {
    parentResource: string;
    parentId: number;
    relatedResource: string;
    foreignKey?: string;
    morphType?: string;
    morphId?: string;
    morphClass?: string;
    prefill: Record<string, any>;
    lock: string[];
    returnUrl: string;
    createUrl: string;
    storeUrl: string;
    isPolymorphic?: boolean;
  };
}

export function CreateRelatedButton({ createContext }: CreateContextProps) {
  const handleCreate = () => {
    // Construir URL con query params para prefill
    const params = new URLSearchParams();

    // Agregar prefill como query params
    Object.entries(createContext.prefill).forEach(([key, value]) => {
      params.append(`prefill[${key}]`, String(value));
    });

    // Agregar lock como query params
    createContext.lock.forEach(field => {
      params.append('lock[]', field);
    });

    // Agregar returnUrl
    params.append('returnUrl', createContext.returnUrl);

    // Navegar al formulario de creacion
    const url = `${createContext.createUrl}?${params.toString()}`;
    window.location.href = url;
  };

  return (
    <button onClick={handleCreate}>
      Crear {createContext.relatedResource}
    </button>
  );
}
```

### Componente Vue de Ejemplo

```vue
<template>
  <button @click="handleCreate" class="create-related-btn">
    Crear {{ createContext.relatedResource }}
  </button>
</template>

<script setup>
import { useRouter } from 'vue-router';

const props = defineProps({
  createContext: {
    type: Object,
    required: true
  }
});

const router = useRouter();

function handleCreate() {
  const { createContext } = props;

  // Guardar contexto en sessionStorage para usar en el form
  sessionStorage.setItem('createContext', JSON.stringify(createContext));

  // Navegar al formulario
  router.push({
    path: `/resources/${createContext.relatedResource}/create`,
    query: {
      fromRelation: true,
      parentResource: createContext.parentResource,
      parentId: createContext.parentId
    }
  });
}
</script>
```

---

## Flujo Completo

```
1. Usuario ve detalle de Access (id: 2)
   GET /nadota-api/accesses-qr/resource/2

2. API devuelve campo HasMany "internals" con createContext
   {
     "createContext": {
       "foreignKey": "access_id",
       "prefill": { "access_id": 2 },
       "lock": ["access_id"],
       ...
     }
   }

3. Usuario hace clic en "Crear Internal"
   Frontend usa createContext para navegar

4. GET /nadota-api/access-internal/resource/create
   Frontend recibe formulario

5. Frontend aplica prefill y lock al campo "access_id"
   - Valor: 2
   - Deshabilitado: true

6. Usuario llena el resto del formulario y envia

7. POST /nadota-api/access-internal/resource
   Body: { "name": "Juan", "document": "123", "access_id": 2 }

8. Redireccion a returnUrl
   /resources/accesses-qr/2
```

---

## Ver Tambien

- [Fields README](./README.md) - Documentacion general de campos
- [Custom Fields](./CUSTOM_FIELDS.md) - Crear campos personalizados
