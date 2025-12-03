# API de Filtros - Nadota

Documentación completa de la API de filtros en Nadota. Esta API permite obtener la configuración de filtros disponibles y aplicarlos a las consultas de recursos.

## Tabla de Contenidos

- [Endpoint de Filtros](#endpoint-de-filtros)
- [Estructura de Respuesta](#estructura-de-respuesta)
- [Tipos de Filtros](#tipos-de-filtros)
- [Aplicar Filtros](#aplicar-filtros)
- [Filtros Dinámicos](#filtros-dinámicos)
- [Dependencias entre Filtros](#dependencias-entre-filtros)
- [Ejemplos Completos](#ejemplos-completos)
- [Códigos de Estado HTTP](#códigos-de-estado-http)

---

## Endpoint de Filtros

### Obtener Filtros Disponibles

```
GET /nadota-api/{resourceKey}/resource/filters
```

Obtiene todos los filtros disponibles para un recurso específico. Los filtros se generan automáticamente desde los fields marcados como `filterable()` y desde los filtros definidos manualmente en el método `filters()` del Resource.

**Parámetros de URL:**
- `{resourceKey}` - Clave del recurso (ej: `users`, `posts`, `products`)

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Ejemplo de Request:**
```http
GET /nadota-api/users/resource/filters
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Accept: application/json
```

**Ejemplo de Response:**
```json
[
  {
    "id": "name-filter",
    "key": "name",
    "name": "Nombre",
    "component": "FilterText",
    "type": "text",
    "field": "name",
    "options": [],
    "value": "",
    "props": {}
  },
  {
    "id": "status-filter",
    "key": "status",
    "name": "Estado",
    "component": "FilterSelect",
    "type": "select",
    "field": "status",
    "options": [
      {"label": "Activo", "value": "active"},
      {"label": "Inactivo", "value": "inactive"}
    ],
    "value": "",
    "props": {}
  }
]
```

---

## Estructura de Respuesta

Cada filtro en la respuesta tiene la siguiente estructura:

```typescript
interface Filter {
  id: string;              // ID único del filtro (slug del nombre)
  key: string;             // Clave única para identificar el filtro
  name: string;            // Nombre legible del filtro
  component: string;       // Componente frontend a usar
  type: string;            // Tipo de filtro (text, select, number, etc.)
  field: string;           // Campo de la base de datos
  options: Option[];       // Opciones disponibles (para selects)
  value: string;           // Valor por defecto
  props: FilterProps;      // Propiedades adicionales del filtro
}
```

### Opciones (Options)

Para filtros de tipo `select`, `radio`, `checkbox_list`:

```typescript
interface Option {
  label: string;  // Texto a mostrar
  value: any;     // Valor a enviar
}
```

### Props del Filtro (FilterProps)

Las props varían según el tipo de filtro:

**DynamicSelectFilter:**
```typescript
{
  endpoint: string;           // URL para obtener opciones
  valueField: string;         // Campo para el valor (default: 'id')
  labelField: string;         // Campo para la etiqueta (default: 'name')
  multiple: boolean;          // Permitir múltiples selecciones
  searchable: boolean;        // Habilitar búsqueda
  applyToQuery: boolean;     // Aplicar al query
  dependsOn?: string[];       // Dependencias hard
  softDependsOn?: string[];   // Dependencias soft
  filtersToSend?: string[];   // Filtros a enviar al endpoint
  relation?: string;         // Relación para whereHas
}
```

**DateFilter / NumberFilter:**
```typescript
{
  isRange: boolean;  // Si es filtro de rango
}
```

**BooleanFilter:**
```typescript
{
  trueValue: any;   // Valor para true
  falseValue: any;  // Valor para false
}
```

---

## Tipos de Filtros

### 1. DefaultFilter (Texto)

Filtro para campos de texto con búsqueda parcial.

**Estructura:**
```json
{
  "id": "name-filter",
  "key": "name",
  "name": "Nombre",
  "component": "FilterText",
  "type": "text",
  "field": "name",
  "options": [],
  "value": "",
  "props": {}
}
```

**Aplicación:**
```json
{
  "name": "Juan"
}
```

**SQL generado:**
```sql
WHERE name LIKE '%Juan%'
```

---

### 2. SelectFilter (Selección)

Filtro para campos con opciones predefinidas.

**Estructura:**
```json
{
  "id": "status-filter",
  "key": "status",
  "name": "Estado",
  "component": "FilterSelect",
  "type": "select",
  "field": "status",
  "options": [
    {"label": "Activo", "value": "active"},
    {"label": "Inactivo", "value": "inactive"}
  ],
  "value": "",
  "props": {}
}
```

**Aplicación - Valor único:**
```json
{
  "status": "active"
}
```

**Aplicación - Múltiples valores:**
```json
{
  "status": ["active", "pending"]
}
```

**SQL generado:**
```sql
-- Valor único
WHERE status = 'active'

-- Múltiples valores
WHERE status IN ('active', 'pending')
```

---

### 3. BooleanFilter (Booleano)

Filtro para campos booleanos/checkbox.

**Estructura:**
```json
{
  "id": "active-filter",
  "key": "active",
  "name": "Activo",
  "component": "FilterBoolean",
  "type": "boolean",
  "field": "is_active",
  "options": [
    {"label": "Sí", "value": "true"},
    {"label": "No", "value": "false"}
  ],
  "value": "",
  "props": {
    "trueValue": 1,
    "falseValue": 0
  }
}
```

**Aplicación:**
```json
{
  "active": true
}
```

**SQL generado:**
```sql
WHERE is_active = 1
```

---

### 4. DateFilter (Fecha)

Filtro para campos de fecha.

**Estructura - Filtro único:**
```json
{
  "id": "created-filter",
  "key": "created",
  "name": "Fecha de Creación",
  "component": "FilterDate",
  "type": "date",
  "field": "created_at",
  "options": [],
  "value": "",
  "props": {
    "isRange": false
  }
}
```

**Aplicación - Filtro único:**
```json
{
  "created": "2024-01-15"
}
```

**SQL generado:**
```sql
WHERE DATE(created_at) = '2024-01-15'
```

**Estructura - Filtro de rango:**
```json
{
  "id": "created-range-filter",
  "key": "created",
  "name": "Fecha de Creación",
  "component": "FilterDateRange",
  "type": "date",
  "field": "created_at",
  "options": [],
  "value": "",
  "props": {
    "isRange": true
  }
}
```

**Aplicación - Rango:**
```json
{
  "created": {
    "start": "2024-01-01",
    "end": "2024-01-31"
  }
}
```

**SQL generado:**
```sql
WHERE created_at BETWEEN '2024-01-01' AND '2024-01-31'
```

---

### 5. NumberFilter (Número)

Filtro para campos numéricos.

**Estructura - Filtro exacto:**
```json
{
  "id": "price-filter",
  "key": "price",
  "name": "Precio",
  "component": "FilterNumber",
  "type": "number",
  "field": "price",
  "options": [],
  "value": "",
  "props": {
    "isRange": false
  }
}
```

**Aplicación - Filtro exacto:**
```json
{
  "price": 100
}
```

**SQL generado:**
```sql
WHERE price = 100
```

**Aplicación - Rango:**
```json
{
  "price": {
    "start": 10,
    "end": 100
  }
}
```

**SQL generado:**
```sql
WHERE price BETWEEN 10 AND 100
```

---

### 6. DynamicSelectFilter (Select Dinámico)

Filtro para relaciones y selects que cargan opciones dinámicamente desde un endpoint.

**Estructura:**
```json
{
  "id": "user-filter",
  "key": "user",
  "name": "Usuario",
  "component": "FilterDynamicSelect",
  "type": "dynamicSelect",
  "field": "user_id",
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

**Aplicación:**
```json
{
  "user": 5
}
```

**SQL generado:**
```sql
WHERE user_id = 5

-- O con relación
WHERE EXISTS (
  SELECT 1 FROM users 
  WHERE users.id = posts.user_id 
  AND users.id = 5
)
```

---

## Aplicar Filtros

### Endpoint de Listado con Filtros

```
GET /nadota-api/{resourceKey}/resource
```

**Query Parameters:**
- `page` - Número de página (default: 1)
- `per_page` - Items por página (default: 15)
- `sort` - Campo para ordenar
- `direction` - Dirección de ordenamiento (`asc` o `desc`)
- `filters` - **JSON codificado** con los valores de los filtros
- `search` - Búsqueda general

**Ejemplo de Request:**
```http
GET /nadota-api/users/resource?page=1&per_page=20&filters=%7B%22status%22%3A%22active%22%2C%22name%22%3A%22Juan%22%7D&sort=created_at&direction=desc
```

**Ejemplo de Query Parameters (decodificado):**
```
page=1
per_page=20
filters={"status":"active","name":"Juan"}
sort=created_at
direction=desc
```

**Ejemplo de Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Juan Pérez",
      "email": "juan@example.com",
      "status": "active",
      "created_at": "2024-01-15 10:30:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100,
    "from": 1,
    "to": 20
  }
}
```

### Formato del Parámetro `filters`

El parámetro `filters` debe ser un objeto JSON codificado con las claves de los filtros y sus valores:

```json
{
  "name": "Juan",
  "status": "active",
  "price": {
    "start": 10,
    "end": 100
  },
  "created": "2024-01-15",
  "active": true,
  "user": 5
}
```

**Ejemplo en JavaScript:**
```javascript
const filters = {
  name: "Juan",
  status: "active",
  price: { start: 10, end: 100 },
  created: "2024-01-15",
  active: true,
  user: 5
};

const queryString = `filters=${encodeURIComponent(JSON.stringify(filters))}`;
// Resultado: filters=%7B%22name%22%3A%22Juan%22%2C%22status%22%3A%22active%22%2C...
```

---

## Filtros Dinámicos

Los filtros `DynamicSelectFilter` obtienen sus opciones desde un endpoint. El frontend debe hacer una petición adicional para obtener las opciones.

### Endpoint de Opciones

```
GET /nadota-api/{resourceKey}/resource/field/{fieldName}/options
```

**Parámetros de URL:**
- `{resourceKey}` - Clave del recurso relacionado
- `{fieldName}` - Nombre del field

**Query Parameters:**
- `search` - Término de búsqueda para filtrar opciones
- `limit` - Límite de resultados (default: 100)
- `filters` - Filtros adicionales a aplicar (para dependencias)

**Ejemplo de Request:**
```http
GET /nadota-api/users/resource/field/user_id/options?search=Juan&limit=20
```

**Ejemplo de Response:**
```json
{
  "success": true,
  "options": [
    {
      "value": 1,
      "label": "Juan Pérez"
    },
    {
      "value": 2,
      "label": "Juan García"
    }
  ],
  "meta": {
    "total": 2,
    "search": "Juan",
    "field_type": "belongsTo"
  }
}
```

### Opciones Paginadas

```
GET /nadota-api/{resourceKey}/resource/field/{fieldName}/options/paginated
```

**Query Parameters:**
- `search` - Término de búsqueda
- `per_page` - Items por página (default: 15)
- `page` - Número de página

**Ejemplo de Response:**
```json
{
  "success": true,
  "data": [
    {"value": 1, "label": "Opción 1"},
    {"value": 2, "label": "Opción 2"}
  ],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

---

## Dependencias entre Filtros

Los filtros pueden tener dependencias entre sí. Cuando un filtro padre cambia, los filtros dependientes se actualizan.

### Dependencias Hard (dependsOn)

Los filtros con dependencias hard se **resetean** cuando el filtro padre cambia.

**Ejemplo:**
```json
{
  "id": "city-filter",
  "key": "city",
  "name": "Ciudad",
  "component": "FilterDynamicSelect",
  "props": {
    "dependsOn": ["country"]
  }
}
```

**Comportamiento:**
1. Usuario selecciona `country: "ES"`
2. El filtro `city` se resetea y carga opciones de ciudades de España
3. Si el usuario cambia a `country: "MX"`, el filtro `city` se resetea nuevamente

### Dependencias Soft (softDependsOn)

Los filtros con dependencias soft se **re-filtran** pero mantienen el valor si sigue siendo válido.

**Ejemplo:**
```json
{
  "id": "district-filter",
  "key": "district",
  "name": "Distrito",
  "component": "FilterDynamicSelect",
  "props": {
    "softDependsOn": ["city"]
  }
}
```

**Comportamiento:**
1. Usuario selecciona `city: "Madrid"` y `district: "Centro"`
2. Si el usuario cambia a `city: "Barcelona"`, el filtro `district` se re-filtra
3. Si "Centro" existe en Barcelona, se mantiene seleccionado
4. Si no existe, se resetea

### Enviar Filtros al Endpoint

Los filtros pueden enviar otros filtros al endpoint para obtener opciones filtradas:

```json
{
  "id": "product-filter",
  "key": "product",
  "name": "Producto",
  "component": "FilterDynamicSelect",
  "props": {
    "filtersToSend": ["category", "status"]
  }
}
```

**Comportamiento:**
Cuando se solicita opciones para `product`, se envían los valores de `category` y `status` al endpoint:

```
GET /nadota-api/products/resource/field/product_id/options?filters[category]=electronics&filters[status]=active
```

---

## Ejemplos Completos

### Ejemplo 1: Filtros Básicos

**Request - Obtener filtros:**
```http
GET /nadota-api/products/resource/filters
```

**Response:**
```json
[
  {
    "id": "name-filter",
    "key": "name",
    "name": "Nombre",
    "component": "FilterText",
    "type": "text",
    "field": "name",
    "options": [],
    "value": "",
    "props": {}
  },
  {
    "id": "status-filter",
    "key": "status",
    "name": "Estado",
    "component": "FilterSelect",
    "type": "select",
    "field": "status",
    "options": [
      {"label": "Activo", "value": "active"},
      {"label": "Inactivo", "value": "inactive"}
    ],
    "value": "",
    "props": {}
  }
]
```

**Request - Aplicar filtros:**
```http
GET /nadota-api/products/resource?filters=%7B%22name%22%3A%22Laptop%22%2C%22status%22%3A%22active%22%7D
```

**Filtros aplicados:**
```json
{
  "name": "Laptop",
  "status": "active"
}
```

---

### Ejemplo 2: Filtros de Rango

**Request - Aplicar filtro de rango:**
```http
GET /nadota-api/products/resource?filters=%7B%22price%22%3A%7B%22start%22%3A100%2C%22end%22%3A500%7D%7D
```

**Filtros aplicados:**
```json
{
  "price": {
    "start": 100,
    "end": 500
  }
}
```

**SQL generado:**
```sql
WHERE price BETWEEN 100 AND 500
```

---

### Ejemplo 3: Filtro Dinámico con Dependencias

**Request - Obtener filtros:**
```http
GET /nadota-api/orders/resource/filters
```

**Response:**
```json
[
  {
    "id": "country-filter",
    "key": "country",
    "name": "País",
    "component": "FilterDynamicSelect",
    "type": "dynamicSelect",
    "field": "country_id",
    "options": [],
    "value": "",
    "props": {
      "endpoint": "/nadota-api/countries/resource/field/country_id/options",
      "searchable": true
    }
  },
  {
    "id": "city-filter",
    "key": "city",
    "name": "Ciudad",
    "component": "FilterDynamicSelect",
    "type": "dynamicSelect",
    "field": "city_id",
    "options": [],
    "value": "",
    "props": {
      "endpoint": "/nadota-api/cities/resource/field/city_id/options",
      "dependsOn": ["country"],
      "filtersToSend": ["country"]
    }
  }
]
```

**Flujo:**
1. Usuario selecciona `country: 1` (España)
2. Frontend solicita opciones de ciudades: `GET /nadota-api/cities/resource/field/city_id/options?filters[country]=1`
3. Usuario selecciona `city: 5` (Madrid)
4. Request final con ambos filtros:
```http
GET /nadota-api/orders/resource?filters=%7B%22country%22%3A1%2C%22city%22%3A5%7D
```

---

### Ejemplo 4: Múltiples Filtros Combinados

**Request:**
```http
GET /nadota-api/users/resource?filters=%7B%22name%22%3A%22Juan%22%2C%22status%22%3A%5B%22active%22%2C%22pending%22%5D%2C%22active%22%3Atrue%2C%22created%22%3A%7B%22start%22%3A%222024-01-01%22%2C%22end%22%3A%222024-01-31%22%7D%7D&page=1&per_page=20&sort=created_at&direction=desc
```

**Filtros aplicados (decodificado):**
```json
{
  "name": "Juan",
  "status": ["active", "pending"],
  "active": true,
  "created": {
    "start": "2024-01-01",
    "end": "2024-01-31"
  }
}
```

**SQL generado:**
```sql
WHERE name LIKE '%Juan%'
  AND status IN ('active', 'pending')
  AND is_active = 1
  AND created_at BETWEEN '2024-01-01' AND '2024-01-31'
ORDER BY created_at DESC
LIMIT 20 OFFSET 0
```

---

## Códigos de Estado HTTP

| Código | Descripción |
|--------|-------------|
| `200` | Éxito - Filtros obtenidos correctamente |
| `400` | Bad Request - Parámetros inválidos |
| `401` | Unauthorized - No autenticado |
| `403` | Forbidden - Sin permisos para ver el recurso |
| `404` | Not Found - Recurso no encontrado |
| `422` | Validation Error - Error de validación |
| `500` | Server Error - Error interno del servidor |

---

## Notas Importantes

1. **Codificación de Filtros**: El parámetro `filters` debe estar codificado en URL (usar `encodeURIComponent` en JavaScript).

2. **Orden de Aplicación**: Los filtros se aplican en el orden en que aparecen en la respuesta.

3. **Filtros Vacíos**: Los filtros con valores vacíos, `null` o `undefined` no se aplican a la consulta.

4. **Rendimiento**: Los filtros de rango y los filtros con `whereHas` pueden ser más costosos. Considera índices en la base de datos.

5. **Autorización**: Todos los endpoints requieren autenticación y respetan las políticas de autorización de Laravel.

6. **Caché**: Las opciones de filtros dinámicos pueden ser cacheadas en el frontend para mejorar el rendimiento.

---

## Referencias

- [Documentación de Filtros](../src/Http/Filters/README.md)
- [Documentación de Fields](../src/Http/Fields/README.md)
- [Documentación General de API](./FILTERS_AND_API.md)

