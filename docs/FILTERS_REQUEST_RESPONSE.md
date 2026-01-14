# Filtros - Request/Response

Resumen ejecutivo de qué se envía al frontend y qué se recibe del frontend para filtros.

---

## 📤 Backend → Frontend (Response)

### Endpoint para Index
```
GET /nadota-api/{resource}/resource/filters
```

### Endpoint para Relaciones Paginadas
```
GET /nadota-api/{resource}/resource/{id}/relation/{field}
```
Los filtros vienen en `meta.filters` de la respuesta.

### Estructura JSON

```json
{
  "key": "field_name",           // ← Clave única del filtro
  "label": "Field Label",         // ← Etiqueta para mostrar
  "component": "FilterText",      // ← Componente frontend
  "type": "text",                 // ← Tipo de filtro
  "options": [],                  // ← Opciones (si es select)
  "value": "",                    // ← Valor por defecto
  "props": {},                    // ← Props adicionales
  "isRange": false,               // ← Si es rango (de/hasta)
  "filterKeys": {                 // ← Mapeo de claves
    "value": "field_name"
  }
}
```

### Ejemplo: Filtro de Texto

```json
{
  "key": "title",
  "label": "Title",
  "component": "FilterText",
  "type": "text",
  "options": [],
  "value": "",
  "props": {},
  "isRange": false,
  "filterKeys": {
    "value": "title"
  }
}
```

**Frontend debe enviar:**
```
filters[title]=valor
```

### Ejemplo: Filtro Select (BelongsTo)

```json
{
  "key": "category_id",
  "label": "Category",
  "component": "FilterSelect",
  "type": "select",
  "options": [
    { "label": "News", "value": 1 },
    { "label": "Blog", "value": 2 }
  ],
  "value": "",
  "props": {},
  "isRange": false,
  "filterKeys": {
    "value": "category_id"
  }
}
```

**Frontend debe enviar:**
```
filters[category_id]=1
```

### Ejemplo: Filtro Boolean

```json
{
  "key": "is_published",
  "label": "Published",
  "component": "FilterBoolean",
  "type": "boolean",
  "options": [
    { "label": "Yes", "value": "true" },
    { "label": "No", "value": "false" }
  ],
  "value": "",
  "props": {},
  "isRange": false,
  "filterKeys": {
    "value": "is_published"
  }
}
```

**Frontend debe enviar:**
```
filters[is_published]=true    // o "false"
```

### Ejemplo: Filtro de Rango (Fecha)

```json
{
  "key": "created_at",
  "label": "Created At",
  "component": "FilterDateRange",
  "type": "dateRange",
  "options": [],
  "value": "",
  "props": {},
  "isRange": true,
  "filterKeys": {
    "from": "created_at[from]",
    "to": "created_at[to]"
  }
}
```

**Frontend debe enviar:**
```
filters[created_at][from]=2025-01-01
filters[created_at][to]=2025-12-31
```

### Ejemplo: MorphTo (DOS filtros)

**Filtro 1: Tipo**
```json
{
  "key": "commentable_type",
  "label": "fields.commentable_type",
  "component": "FilterSelect",
  "type": "select",
  "options": [
    { "label": "Post", "value": "post" },
    { "label": "Video", "value": "video" }
  ],
  "value": "",
  "props": {}
}
```

**Nota sobre labels**: Si el campo usa un translation key (contiene `.`), el filtro de tipo usa el patrón `{base}.{morphTypeField}`. Por ejemplo:
- Campo: `fields.commentable` → Tipo: `fields.commentable_type`
- Campo: `resources.forms.targetable` → Tipo: `resources.forms.targetable_type`
- Campo sin puntos: `Commentable` → Tipo: `Commentable` (sin cambios)

Esto permite que el frontend traduzca correctamente sin texto concatenado hardcodeado.

**Filtro 2: Entidad**
```json
{
  "key": "commentable_id",
  "label": "Commentable",
  "component": "FilterDynamicSelect",
  "type": "dynamicSelect",
  "endpoint": "/nadota-api/comments/resource/field/commentable/morph-options/{morphType}",
  "options": [],
  "value": "",
  "props": {
    "endpoint": "/nadota-api/comments/resource/field/commentable/morph-options/{morphType}",
    "endpointTemplate": "/nadota-api/comments/resource/field/commentable/morph-options/{morphType}",
    "isMorphEndpoint": true,
    "valueField": "id",
    "labelField": "name",
    "multiple": false,
    "searchable": true,
    "dependsOn": ["commentable_type"],
    "filtersToSend": ["commentable_type"],
    "applyToQuery": true
  }
}
```

**Flujo Frontend:**

1. Usuario selecciona tipo: `commentable_type = "post"`
2. Frontend reemplaza `{morphType}` → `/morph-options/post`
3. GET `/nadota-api/comments/resource/field/commentable/morph-options/post`
4. Recibe opciones: `[{id: 1, name: "Post 1"}, ...]`
5. Usuario selecciona entidad: `commentable_id = 1`
6. Envía ambos filtros al backend

**Frontend debe enviar:**
```
filters[commentable_type]=post
filters[commentable_id]=1
```

---

## 📥 Frontend → Backend (Request)

### Formato de Query String

```
GET /nadota-api/{resource}/resource?filters[key1]=value1&filters[key2]=value2
```

### Ejemplos de Query Strings

**Filtros simples:**
```
?filters[title]=Laravel
&filters[category_id]=1
&filters[is_published]=true
```

**Filtros de rango:**
```
?filters[created_at][from]=2025-01-01
&filters[created_at][to]=2025-12-31
```

**Filtros MorphTo:**
```
?filters[commentable_type]=post
&filters[commentable_id]=1
```

**Múltiples filtros combinados:**
```
?filters[title]=Laravel
&filters[category_id]=1
&filters[is_published]=true
&filters[commentable_type]=post
&filters[commentable_id]=1
&filters[created_at][from]=2025-01-01
&filters[created_at][to]=2025-12-31
```

### Estructura PHP Recibida

```php
// Request::get('filters')
[
    'title' => 'Laravel',
    'category_id' => '1',        // String del query param
    'is_published' => 'true',     // String del query param
    'commentable_type' => 'post',
    'commentable_id' => '1',
    'created_at' => [
        'from' => '2025-01-01',
        'to' => '2025-12-31',
    ],
]
```

**Nota:** Todos los valores vienen como strings. Cada filtro debe convertir al tipo correcto.

---

## 🔄 Conversión de Valores

### Backend convierte automáticamente:

| Filtro | Recibe | Convierte a | Ejemplo |
|--------|--------|-------------|---------|
| TextFilter | `"Laravel"` | String | `WHERE title LIKE '%Laravel%'` |
| SelectFilter | `"1"` | Mismo tipo | `WHERE category_id = 1` |
| BooleanFilter | `"true"` | Boolean | `WHERE is_published = 1` |
| DateRangeFilter | `["from" => "2025-01-01", "to" => "..."]` | Fechas | `WHERE created_at BETWEEN ...` |
| MorphTypeFilter | `"post"` | Clase completa | `WHERE type = 'App\Models\Post'` |
| MorphEntityFilter | `"1"` | Integer | `WHERE id = 1` |

---

## 📋 Casos Especiales

### DynamicSelectFilter con Dependencias

**Response:**
```json
{
  "key": "stop_id",
  "props": {
    "endpoint": "/nadota-api/routes/resource/field/stop/options",
    "dependsOn": ["route_id"],
    "filtersToSend": ["route_id"]
  }
}
```

**Comportamiento Frontend:**

1. Cuando `route_id` cambia → resetea `stop_id` a null
2. Al cargar opciones → envía `route_id` en el endpoint:
   ```
   GET /nadota-api/routes/resource/field/stop/options?filters[route_id]=5
   ```

**Frontend debe enviar al aplicar:**
```
filters[route_id]=5
filters[stop_id]=10
```

### Filtros con `applyToQuery: false`

```json
{
  "key": "aux_filter",
  "props": {
    "applyToQuery": false
  }
}
```

Este filtro NO se aplica al query SQL. Se usa solo para cargar opciones de otros filtros.

**Frontend:** Puede enviarlo, pero el backend lo ignora en el query final.

---

## 🎯 Resumen Rápido

### Lo que Backend ENVÍA

```json
{
  "filters": [
    {
      "key": "...",           // ← Usar en filters[key]
      "label": "...",         // ← Mostrar en UI
      "component": "...",     // ← Componente a usar
      "type": "...",          // ← Tipo de filtro
      "options": [...],       // ← Opciones (si aplica)
      "props": {
        "endpoint": "...",           // ← Endpoint dinámico
        "dependsOn": [...],          // ← Dependencias hard
        "filtersToSend": [...],      // ← Filtros a enviar al endpoint
        "isMorphEndpoint": true      // ← Es morph (reemplazar {morphType})
      }
    }
  ]
}
```

### Lo que Backend RECIBE

```
GET /resource?filters[key1]=value1&filters[key2]=value2
```

Internamente:
```php
$request->get('filters') => [
    'key1' => 'value1',  // String
    'key2' => 'value2',  // String
]
```

### Mapeo

| `key` en Response | `filters[key]` en Request | Aplica en Query |
|-------------------|---------------------------|-----------------|
| `"title"` | `filters[title]=valor` | `WHERE title LIKE '%valor%'` |
| `"category_id"` | `filters[category_id]=1` | `WHERE category_id = 1` |
| `"is_published"` | `filters[is_published]=true` | `WHERE is_published = 1` |
| `"commentable_type"` | `filters[commentable_type]=post` | `WHERE commentable_type = 'App\Models\Post'` |
| `"commentable_id"` | `filters[commentable_id]=1` | `WHERE commentable_id = 1` |
| `"created_at"` | `filters[created_at][from]=...` | `WHERE created_at >= '...'` |
| `"created_at"` | `filters[created_at][to]=...` | `AND created_at <= '...'` |

---

## 🧪 Ejemplo Completo de Flujo

### 1. Cargar Página Index

**Request:**
```
GET /nadota-api/comments/resource/config
```

**Response (filtros):**
```json
{
  "filters": [
    {
      "key": "commentable_type",
      "label": "fields.commentable_type",
      "component": "FilterSelect",
      "options": [
        { "label": "Post", "value": "post" },
        { "label": "Video", "value": "video" }
      ]
    },
    {
      "key": "commentable_id",
      "label": "fields.commentable",
      "component": "FilterDynamicSelect",
      "props": {
        "endpointTemplate": "/nadota-api/comments/resource/field/commentable/morph-options/{morphType}",
        "isMorphEndpoint": true,
        "dependsOn": ["commentable_type"]
      }
    }
  ]
}
```

### 2. Usuario Selecciona Tipo

Usuario selecciona `"post"` en el filtro de tipo.

Frontend construye endpoint:
```
/nadota-api/comments/resource/field/commentable/morph-options/post
```

**Request:**
```
GET /nadota-api/comments/resource/field/commentable/morph-options/post
```

**Response:**
```json
{
  "options": [
    { "id": 1, "name": "Post Title 1" },
    { "id": 2, "name": "Post Title 2" }
  ]
}
```

### 3. Usuario Selecciona Entidad y Aplica

Usuario selecciona Post con `id=1` y hace clic en "Filtrar".

**Request:**
```
GET /nadota-api/comments/resource?filters[commentable_type]=post&filters[commentable_id]=1
```

**SQL Generado:**
```sql
SELECT * FROM comments
WHERE commentable_type = 'App\\Models\\Post'
  AND commentable_id = 1
```

**Response:**
```json
{
  "data": [
    { "id": 5, "content": "Great post!" },
    { "id": 8, "content": "Thanks!" }
  ],
  "meta": {...}
}
```
