# Filtros para Relaciones MorphTo

Este documento explica cómo funcionan los filtros para relaciones `MorphTo` y cómo implementarlos en el frontend.

## Problema

Las relaciones `MorphTo` requieren dos filtros:
1. **Filtro de Tipo**: Seleccionar el tipo de entidad (ej: `Post`, `Video`, `Article`)
2. **Filtro de Entidad**: Seleccionar la entidad específica según el tipo elegido

El segundo filtro depende del primero y debe cambiar su endpoint dinámicamente.

## Solución Propuesta

Se generan **dos filtros separados** que trabajan juntos usando el sistema de dependencias:

### 1. Filtro de Tipo (MorphTypeFilter)

Un `SelectFilter` simple con las opciones de tipos disponibles.

**Estructura:**
```json
{
  "id": "commentable-type-filter",
  "key": "commentable_type",
  "name": "Comentable - Tipo",
  "component": "FilterSelect",
  "type": "select",
  "field": "commentable_type",
  "options": [
    {"label": "Post", "value": "post"},
    {"label": "Video", "value": "video"},
    {"label": "Article", "value": "article"}
  ],
  "value": "",
  "props": {}
}
```

### 2. Filtro de Entidad (MorphEntityFilter)

Un `DynamicSelectFilter` que depende del tipo seleccionado y cambia su endpoint dinámicamente.

**Estructura:**
```json
{
  "id": "commentable-filter",
  "key": "commentable",
  "name": "Comentable",
  "component": "FilterDynamicSelect",
  "type": "dynamicSelect",
  "field": "commentable_id",
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

## Implementación en el Frontend

### Paso 1: Detectar Filtros Morph

Cuando recibas los filtros, identifica cuáles son filtros morph:

```javascript
const filters = response.data; // Array de filtros

// Identificar filtros morph
const morphFilters = filters.filter(filter => 
  filter.props?.isMorphEndpoint === true
);

morphFilters.forEach(morphFilter => {
  // Encontrar el filtro de tipo relacionado
  const typeField = morphFilter.props.dependsOn?.[0];
  const typeFilter = filters.find(f => f.field === typeField);
  
  // Configurar dependencia
  setupMorphFilter(morphFilter, typeFilter);
});
```

### Paso 2: Construir Endpoint Dinámico

Cuando el usuario selecciona un tipo, construye el endpoint reemplazando el placeholder:

```javascript
function getMorphEndpoint(filter, selectedType) {
  if (!filter.props?.endpointTemplate) {
    return filter.props.endpoint;
  }
  
  // Reemplazar {morphType} con el tipo seleccionado
  return filter.props.endpointTemplate.replace('{morphType}', selectedType);
}

// Ejemplo:
const endpoint = getMorphEndpoint(morphFilter, 'post');
// Resultado: "/nadota-api/comments/resource/field/commentable/morph-options/post"
```

### Paso 3: Manejar Cambios de Tipo

Cuando el tipo cambia, resetea el filtro de entidad y carga nuevas opciones:

```javascript
function setupMorphFilter(morphFilter, typeFilter) {
  // Escuchar cambios en el filtro de tipo
  typeFilter.onChange = (selectedType) => {
    if (!selectedType) {
      // Si no hay tipo seleccionado, limpiar el filtro de entidad
      morphFilter.value = null;
      morphFilter.options = [];
      return;
    }
    
    // Construir endpoint con el tipo seleccionado
    const endpoint = getMorphEndpoint(morphFilter, selectedType);
    
    // Cargar opciones desde el nuevo endpoint
    loadMorphOptions(morphFilter, endpoint);
    
    // Resetear el valor del filtro de entidad
    morphFilter.value = null;
  };
}

async function loadMorphOptions(filter, endpoint) {
  try {
    const response = await fetch(endpoint);
    const data = await response.json();
    
    filter.options = data.options || [];
  } catch (error) {
    console.error('Error loading morph options:', error);
    filter.options = [];
  }
}
```

### Paso 4: Aplicar Filtros

Al aplicar los filtros, envía ambos valores:

```javascript
const filtersToApply = {
  commentable_type: 'post',      // Tipo seleccionado
  commentable_id: 123            // ID de la entidad seleccionada
};

// Enviar en la petición
const queryString = `filters=${encodeURIComponent(JSON.stringify(filtersToApply))}`;
```

## Ejemplo Completo (Vue.js)

```vue
<template>
  <div class="morph-filters">
    <!-- Filtro de Tipo -->
    <FilterSelect
      v-model="typeValue"
      :filter="typeFilter"
      @change="onTypeChange"
    />
    
    <!-- Filtro de Entidad (solo visible si hay tipo seleccionado) -->
    <FilterDynamicSelect
      v-if="typeValue"
      v-model="entityValue"
      :filter="entityFilter"
      :endpoint="computedEndpoint"
    />
  </div>
</template>

<script>
export default {
  props: {
    typeFilter: Object,
    entityFilter: Object,
  },
  
  data() {
    return {
      typeValue: null,
      entityValue: null,
    };
  },
  
  computed: {
    computedEndpoint() {
      if (!this.typeValue || !this.entityFilter.props?.endpointTemplate) {
        return null;
      }
      
      return this.entityFilter.props.endpointTemplate
        .replace('{morphType}', this.typeValue);
    }
  },
  
  methods: {
    onTypeChange(value) {
      this.typeValue = value;
      // Resetear entidad cuando cambia el tipo
      this.entityValue = null;
      
      // Cargar opciones si hay tipo seleccionado
      if (value && this.computedEndpoint) {
        this.loadOptions();
      }
    },
    
    async loadOptions() {
      try {
        const response = await fetch(this.computedEndpoint);
        const data = await response.json();
        this.entityFilter.options = data.options || [];
      } catch (error) {
        console.error('Error loading options:', error);
      }
    }
  }
};
</script>
```

## Ejemplo Completo (React)

```jsx
import { useState, useEffect } from 'react';

function MorphFilters({ typeFilter, entityFilter }) {
  const [typeValue, setTypeValue] = useState(null);
  const [entityValue, setEntityValue] = useState(null);
  const [options, setOptions] = useState([]);

  const endpoint = typeValue && entityFilter.props?.endpointTemplate
    ? entityFilter.props.endpointTemplate.replace('{morphType}', typeValue)
    : null;

  useEffect(() => {
    if (endpoint) {
      loadOptions();
    } else {
      setOptions([]);
    }
  }, [endpoint]);

  const loadOptions = async () => {
    try {
      const response = await fetch(endpoint);
      const data = await response.json();
      setOptions(data.options || []);
    } catch (error) {
      console.error('Error loading options:', error);
      setOptions([]);
    }
  };

  const handleTypeChange = (value) => {
    setTypeValue(value);
    setEntityValue(null); // Reset entity when type changes
  };

  return (
    <div className="morph-filters">
      <FilterSelect
        filter={typeFilter}
        value={typeValue}
        onChange={handleTypeChange}
      />
      
      {typeValue && (
        <FilterDynamicSelect
          filter={entityFilter}
          value={entityValue}
          onChange={setEntityValue}
          endpoint={endpoint}
          options={options}
        />
      )}
    </div>
  );
}
```

## Uso en Resource

### Automático desde Field

```php
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;

MorphTo::make('Comentable', 'commentable')
    ->resources([
        'post' => PostResource::class,
        'video' => VideoResource::class,
        'article' => ArticleResource::class,
    ])
    ->filterable(); // Genera automáticamente los dos filtros
```

### Manual en Resource

```php
use SchoolAid\Nadota\Http\Filters\MorphToFilter;

public function filters(NadotaRequest $request): array
{
    $morphFilter = new MorphToFilter(
        'Comentable',
        'commentable_type',
        'commentable_id',
        [
            'post' => [
                'model' => Post::class,
                'label' => 'Post',
                'resource' => PostResource::class,
            ],
            'video' => [
                'model' => Video::class,
                'label' => 'Video',
                'resource' => VideoResource::class,
            ],
        ],
        'comments' // resource key
    );

    return $morphFilter->generateFilters();
}
```

## Estructura de Respuesta de la API

Cuando se aplican los filtros, la API espera:

```json
{
  "commentable_type": "post",
  "commentable_id": 123
}
```

Y genera la siguiente consulta SQL:

```sql
WHERE commentable_type = 'App\\Models\\Post'
  AND commentable_id = 123
```

## Ventajas de esta Solución

1. **Flexible**: Reutiliza filtros existentes (`SelectFilter` y `DynamicSelectFilter`)
2. **Mantenible**: Usa el sistema de dependencias ya implementado
3. **Escalable**: Fácil agregar más tipos morph
4. **Frontend-friendly**: El frontend tiene control total sobre cuándo cargar opciones
5. **Consistente**: Sigue los mismos patrones que otros filtros dinámicos

## Notas Importantes

1. **Placeholder en Endpoint**: El endpoint usa `{morphType}` como placeholder que debe ser reemplazado por el frontend.

2. **Dependencia Hard**: El filtro de entidad tiene dependencia hard del tipo, por lo que se resetea cuando cambia el tipo.

3. **Endpoint Dinámico**: El endpoint se construye en tiempo de ejecución en el frontend, no en el backend.

4. **Validación**: El frontend debe validar que hay un tipo seleccionado antes de intentar cargar opciones.

5. **Caché**: Considera cachear las opciones por tipo para mejorar el rendimiento.

