# Filtros - Documentación Técnica

Este documento explica el flujo completo de filtros en Nadota, desde la configuración en el backend hasta la aplicación en queries.

---

## Tabla de Contenido

1. [Flujo General de Filtros](#flujo-general-de-filtros)
2. [Filtros en Index](#filtros-en-index)
3. [Filtros en Relaciones Paginadas](#filtros-en-relaciones-paginadas)
4. [Filtros MorphTo](#filtros-morphto)
5. [Estructura de Request/Response](#estructura-de-requestresponse)

---

## Flujo General de Filtros

### Backend → Frontend

```
1. Resource define campos con ->filterable()
2. ResourceIndexController genera array de filtros
3. Cada Filter::toArray() serializa a JSON
4. Frontend recibe array de filtros disponibles
5. Frontend muestra UI de filtros
```

### Frontend → Backend

```
1. Usuario selecciona filtros en UI
2. Frontend envía filters[key]=value en query params
3. FilterCriteria recibe array de valores
4. FilterCriteria aplica cada Filter::apply() al query
5. Query ejecuta y devuelve resultados filtrados
```

---

## Filtros en Index

### Configuración en Resource

```php
class PostResource extends Resource
{
    public function fields(NadotaRequest $request): array
    {
        return [
            // Campo filtrable simple
            Input::make('Title', 'title')
                ->filterable(),  // Genera TextFilter automáticamente

            // BelongsTo filtrable
            BelongsTo::make('Category', 'category', CategoryResource::class)
                ->filterable(),  // Genera SelectFilter automáticamente

            // Toggle/Boolean filtrable
            Toggle::make('Published', 'is_published')
                ->filterable(),  // Genera BooleanFilter automáticamente
        ];
    }

    // Filtros personalizados adicionales
    public function filters(NadotaRequest $request): array
    {
        return [
            new DateRangeFilter('Created At', 'created_at'),
            new CustomFilter('...'),
        ];
    }
}
```

### Obtener Filtros Disponibles

**Endpoint:**
```
GET /nadota-api/{resource}/resource/filters
```

O dentro del config completo:
```
GET /nadota-api/{resource}/resource/config
```

**Response:**
```json
{
  "filters": [
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
    },
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
    },
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
  ]
}
```

### Aplicar Filtros

**Endpoint:**
```
GET /nadota-api/{resource}/resource?filters[title]=Laravel&filters[category_id]=1&filters[is_published]=true
```

**Flujo interno:**

1. **ResourceController::index()** recibe el request
2. **IndexService::handle()** procesa la petición
3. **ApplyFiltersPipe::handle()** aplica los filtros:
   ```php
   // En ApplyFiltersPipe.php
   $filters = $resource->getFilters($request);
   $requestFilters = $request->get('filters', []);

   (new FilterCriteria($requestFilters))->apply($request, $query, $filters);
   ```
4. **FilterCriteria::apply()** itera sobre cada filtro:
   ```php
   foreach ($this->filterValues as $filterName => $value) {
       $filter = collect($filters)->first(fn($f) => $f->key() === $filterName);

       if ($filter && $value !== null) {
           $query = $filter->apply($request, $query, $value);
       }
   }
   ```
5. Cada **Filter::apply()** modifica el query:
   ```php
   // TextFilter
   $query->where('title', 'like', "%{$value}%");

   // SelectFilter
   $query->where('category_id', $value);

   // BooleanFilter
   $query->where('is_published', filter_var($value, FILTER_VALIDATE_BOOLEAN));
   ```

---

## Filtros en Relaciones Paginadas

### Configuración

```php
class AccessResource extends Resource
{
    public function fields(NadotaRequest $request): array
    {
        return [
            HasMany::make('Internals', 'internals', AccessInternalResource::class)
                ->paginated(),  // ← Habilita paginación
        ];
    }
}

class AccessInternalResource extends Resource
{
    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')
                ->filterable()   // ← Este filtro estará disponible
                ->searchable(),

            BelongsTo::make('Document Type', 'documentType', DocumentTypeResource::class)
                ->filterable(),  // ← Este también
        ];
    }
}
```

### Obtener Datos y Filtros

**Endpoint:**
```
GET /nadota-api/accesses-qr/resource/{id}/relation/internals
```

**Response:**
```json
{
  "data": [
    { "id": 1, "label": "Juan Perez" }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 25,
    "resource": "access-internal",
    "filters": [
      {
        "key": "name",
        "label": "Name",
        "component": "FilterText",
        "type": "text",
        "options": [],
        "value": "",
        "props": {}
      },
      {
        "key": "document_type_id",
        "label": "Document Type",
        "component": "FilterSelect",
        "type": "select",
        "options": [
          { "label": "DNI", "value": 1 },
          { "label": "Passport", "value": 2 }
        ],
        "value": "",
        "props": {}
      }
    ]
  },
  "links": {...}
}
```

### Aplicar Filtros en Relaciones

**Endpoint:**
```
GET /nadota-api/accesses-qr/resource/{id}/relation/internals
    ?filters[name]=Juan
    &filters[document_type_id]=1
    &page=1
    &per_page=10
```

**Flujo interno:**

1. **RelationController::index()** recibe el request
2. **RelationIndexService::handle()** procesa:
   ```php
   // Línea 70-73
   $query = $this->buildQuery($request, $query, $field, $relatedResource);
   $query = $this->applySearch($request, $query, $field, $relatedResource);
   $query = $this->applyFilters($request, $query, $field, $relatedResource);
   $query = $this->applySorting($request, $query, $field);
   ```
3. **applyFilters()** obtiene filtros del recurso relacionado:
   ```php
   // Líneas 210-240
   $filterableFields = collect($resource->fieldsForIndex($request))
       ->filter(fn($field) => $field->isFilterable());

   $filters = $filterableFields->flatMap(fn($field) => $field->filters())->all();

   $resourceFilters = $resource->filters($request);
   if (!empty($resourceFilters)) {
       $filters = array_merge($filters, $resourceFilters);
   }

   $requestFilters = $request->get('filters', []);

   (new FilterCriteria($requestFilters))->apply($request, $query, $filters);
   ```

**Importante:** Los filtros se obtienen del **recurso relacionado** (AccessInternalResource), NO del recurso padre (AccessResource).

---

## Filtros MorphTo

Los campos MorphTo requieren **dos filtros** que trabajan en conjunto:

1. **MorphTypeFilter** - Selecciona el tipo de entidad
2. **MorphEntityFilter** - Selecciona la entidad específica según el tipo

### Configuración

```php
class CommentResource extends Resource
{
    public function fields(NadotaRequest $request): array
    {
        return [
            MorphTo::make('Commentable', 'commentable')
                ->resources([
                    'post' => PostResource::class,
                    'video' => VideoResource::class,
                    'article' => ArticleResource::class,
                ])
                ->filterable(),  // ← Genera AMBOS filtros automáticamente
        ];
    }
}
```

### Generación Automática

Cuando un campo `MorphTo` tiene `->filterable()`, internamente genera:

```php
// Automático en MorphTo field
protected function generateFilters(): array
{
    $morphFilter = new MorphToFilter(
        $this->fieldData->label,
        $this->morphTypeField,  // 'commentable_type'
        $this->morphIdField,    // 'commentable_id'
        $this->morphTypes,
        $resourceKey
    );

    return $morphFilter->generateFilters();
}
```

### Respuesta de Filtros MorphTo

**Endpoint:**
```
GET /nadota-api/comments/resource/filters
```

**Response:**
```json
{
  "filters": [
    {
      "key": "commentable_type",
      "label": "Commentable - Tipo",
      "component": "FilterSelect",
      "type": "select",
      "options": [
        { "label": "Post", "value": "post" },
        { "label": "Video", "value": "video" },
        { "label": "Article", "value": "article" }
      ],
      "value": "",
      "props": {}
    },
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
  ]
}
```

### Props Clave del Filtro MorphTo

| Propiedad | Valor | Descripción |
|-----------|-------|-------------|
| `endpointTemplate` | `/nadota-api/.../morph-options/{morphType}` | Template con placeholder `{morphType}` |
| `isMorphEndpoint` | `true` | Indica que el endpoint es dinámico |
| `dependsOn` | `["commentable_type"]` | Dependencia hard del filtro de tipo |
| `filtersToSend` | `["commentable_type"]` | Filtros a incluir al cargar opciones |
| `applyToQuery` | `true` | El filtro se aplica al query final |

### Flujo Frontend para MorphTo

1. **Cargar filtros iniciales**
   ```
   GET /nadota-api/comments/resource/filters
   ```

2. **Usuario selecciona tipo** (`commentable_type = "post"`)
   - Frontend reemplaza `{morphType}` con `"post"`
   - Endpoint resultante: `/nadota-api/comments/resource/field/commentable/morph-options/post`

3. **Cargar opciones del tipo seleccionado**
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

4. **Usuario selecciona entidad** (`commentable_id = 1`)

5. **Aplicar filtros**
   ```
   GET /nadota-api/comments/resource
       ?filters[commentable_type]=post
       &filters[commentable_id]=1
   ```

### Aplicación en Query (Backend)

**Request recibido:**
```json
{
  "filters": {
    "commentable_type": "post",
    "commentable_id": 1
  }
}
```

**FilterCriteria itera sobre los filtros:**

```php
// 1. Aplica MorphTypeFilter (SelectFilter)
// En SelectFilter::apply()
$query->where('commentable_type', 'App\\Models\\Post');
// Nota: Se convierte 'post' a 'App\Models\Post'

// 2. Aplica MorphEntityFilter (DynamicSelectFilter)
// En DynamicSelectFilter::apply()
$query->where('commentable_id', 1);
```

**SQL generado:**
```sql
SELECT * FROM comments
WHERE commentable_type = 'App\\Models\\Post'
  AND commentable_id = 1
```

---

## Estructura de Request/Response

### Request - Aplicando Filtros

**Query String:**
```
?filters[title]=Laravel
&filters[category_id]=1
&filters[is_published]=true
&filters[commentable_type]=post
&filters[commentable_id]=1
&filters[created_at][from]=2025-01-01
&filters[created_at][to]=2025-12-31
```

**Array PHP recibido:**
```php
[
    'filters' => [
        'title' => 'Laravel',
        'category_id' => 1,
        'is_published' => 'true',
        'commentable_type' => 'post',
        'commentable_id' => 1,
        'created_at' => [
            'from' => '2025-01-01',
            'to' => '2025-12-31',
        ],
    ]
]
```

### Response - Filtros Disponibles

**Estructura Base:**
```json
{
  "key": "field_name",
  "label": "Field Label",
  "component": "FilterComponent",
  "type": "filterType",
  "options": [...],
  "value": "",
  "props": {...},
  "isRange": false,
  "filterKeys": {...}
}
```

**Tipos de Filtros:**

| Tipo | Component | Options | Props Especiales |
|------|-----------|---------|------------------|
| `text` | `FilterText` | `[]` | - |
| `select` | `FilterSelect` | Array de opciones | `translateLabels` |
| `boolean` | `FilterBoolean` | `[{label: "Yes", value: "true"}, ...]` | - |
| `dynamicSelect` | `FilterDynamicSelect` | `[]` | `endpoint`, `dependsOn`, `searchable`, `multiple` |
| `dateRange` | `FilterDateRange` | `[]` | - |

### Propiedades Importantes

#### `filterKeys`
Define qué claves se usan en el query string:

```json
// Filtro simple
"filterKeys": {
  "value": "title"
}

// Filtro de rango
"filterKeys": {
  "from": "created_at[from]",
  "to": "created_at[to]"
}
```

#### `props.dependsOn`
Dependencia hard - resetea cuando cambia el padre:

```json
"props": {
  "dependsOn": ["commentable_type"]
}
```

Cuando `commentable_type` cambia, `commentable_id` se resetea a `null`.

#### `props.softDependsOn`
Dependencia soft - re-filtra pero mantiene el valor si sigue siendo válido:

```json
"props": {
  "softDependsOn": ["route_id"]
}
```

#### `props.filtersToSend`
Filtros que se envían al endpoint al cargar opciones:

```json
"props": {
  "filtersToSend": ["commentable_type", "route_id"]
}
```

El frontend incluirá estos valores en la petición al endpoint.

---

## Resumen de Flujos

### Index
```
1. GET /resource/filters → Obtener filtros disponibles
2. Frontend muestra UI
3. GET /resource?filters[...]... → Aplicar filtros
4. Backend aplica FilterCriteria
5. Devuelve resultados filtrados
```

### Relaciones Paginadas
```
1. GET /resource/{id}/relation/{field} → Primera carga
2. Response incluye meta.filters
3. Frontend muestra UI con filtros
4. GET /resource/{id}/relation/{field}?filters[...]... → Aplicar
5. Backend aplica FilterCriteria al query de la relación
6. Devuelve resultados filtrados
```

### MorphTo
```
1. GET /resource/filters → Recibe MorphTypeFilter + MorphEntityFilter
2. Usuario selecciona tipo (ej: "post")
3. Frontend construye endpoint: /field/commentable/morph-options/post
4. GET morph-options/post → Carga opciones del tipo
5. Usuario selecciona entidad
6. GET /resource?filters[type]=post&filters[id]=1 → Aplica ambos
7. Backend convierte "post" → "App\Models\Post"
8. Query con WHERE type = 'App\Models\Post' AND id = 1
```

---

## Notas Técnicas

1. **Conversión de valores**: Los filtros reciben valores como strings del query string y deben convertirlos al tipo correcto (boolean, int, etc.)

2. **Placeholder en MorphTo**: El `{morphType}` es reemplazado por el frontend, NO por el backend

3. **Namespace de modelos**: MorphToFilter convierte alias (`post`) a clase completa (`App\Models\Post`)

4. **Lazy loading**: Los filtros se cargan solo cuando se necesitan (en la primera request de paginación)

5. **Cache**: Los filtros NO se cachean automáticamente - el frontend debe implementar su propio cache si lo necesita

6. **Orden de aplicación**: Los filtros se aplican en el orden que aparecen en el array, pero esto generalmente no importa ya que son condiciones AND
