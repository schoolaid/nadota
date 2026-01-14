# Relation Fields optionsUrl Fix

## Problema Identificado

Los campos de relación `HasMany` y `MorphMany` NO incluían `optionsUrl` en `props.urls` cuando se renderizaban en la respuesta de `show`, a diferencia de `BelongsToMany` y `MorphToMany` que sí lo incluían.

## Comportamiento Anterior

### BelongsToMany ✅ (Correcto)
```json
{
  "key": "tags",
  "optionsUrl": "/nadota-api/posts/resource/field/tags/options",
  "props": {
    "urls": {
      "options": "/nadota-api/posts/resource/field/tags/options?resourceId=1",
      "attach": "/nadota-api/posts/resource/1/attach/tags",
      "detach": "/nadota-api/posts/resource/1/detach/tags",
      "sync": "/nadota-api/posts/resource/1/sync/tags"
    }
  }
}
```

### HasMany ❌ (Incorrecto - antes del fix)
```json
{
  "key": "comments",
  "optionsUrl": "/nadota-api/posts/resource/field/comments/options",
  "props": {
    "urls": {
      // ❌ NO incluía "options"
      "attachable": "/nadota-api/posts/resource/1/attachable/comments",
      "attach": "/nadota-api/posts/resource/1/attach/comments",
      "detach": "/nadota-api/posts/resource/1/detach/comments"
    }
  }
}
```

## Comportamiento Actual (Después del Fix)

### HasMany ✅
```json
{
  "key": "comments",
  "optionsUrl": "/nadota-api/posts/resource/field/comments/options",
  "props": {
    "urls": {
      "options": "/nadota-api/posts/resource/field/comments/options?resourceId=1",  // ✅ Añadido
      "attachable": "/nadota-api/posts/resource/1/attachable/comments",
      "attach": "/nadota-api/posts/resource/1/attach/comments",
      "detach": "/nadota-api/posts/resource/1/detach/comments"
    }
  }
}
```

### MorphMany ✅
```json
{
  "key": "images",
  "optionsUrl": "/nadota-api/posts/resource/field/images/options",
  "props": {
    "urls": {
      "options": "/nadota-api/posts/resource/field/images/options?resourceId=1",  // ✅ Añadido
      "attachable": "/nadota-api/posts/resource/1/attachable/images",
      "attach": "/nadota-api/posts/resource/1/attach/images",
      "detach": "/nadota-api/posts/resource/1/detach/images"
    }
  }
}
```

## Dos Niveles de optionsUrl

### Nivel Raíz (Generado por Field.php)
```json
{
  "optionsUrl": "/nadota-api/posts/resource/field/comments/options"
}
```

**Generado por:** `Field::toArray()` línea 162
**Fuente:** `RelationshipTrait::getOptionsUrl()`
**Propósito:** URL base para obtener opciones del campo (sin contexto de modelo)

### Dentro de props.urls (Generado por cada campo de relación)
```json
{
  "props": {
    "urls": {
      "options": "/nadota-api/posts/resource/field/comments/options?resourceId=1"
    }
  }
}
```

**Generado por:** Método `getProps()` de cada campo de relación
**Propósito:** URL con contexto del modelo padre (resourceId) para opciones filtradas

## Diferencias Entre URLs

### Sin Modelo (nivel raíz)
```
/nadota-api/posts/resource/field/comments/options
```
- Retorna todas las opciones disponibles
- Usada en create forms
- No tiene contexto del modelo padre

### Con Modelo (props.urls)
```
/nadota-api/posts/resource/field/comments/options?resourceId=1
```
- Retorna opciones filtradas según el modelo padre
- Usada en edit/show views
- Puede excluir items ya adjuntos (en BelongsToMany)
- Puede filtrar según el modelo padre

## Campos con Paginación

Cuando un campo tiene `->paginated()`, se añade adicionalmente `paginationUrl`:

```json
{
  "props": {
    "paginated": true,
    "urls": {
      "options": "/nadota-api/posts/resource/field/comments/options?resourceId=1",
      "paginationUrl": "/nadota-api/posts/resource/1/relation/comments"
    }
  }
}
```

**Diferencia:**
- `options`: Para selects/multiselects (attach/create forms)
- `paginationUrl`: Para listados paginados de la relación (show view)

## Campos Afectados por el Fix

| Campo | Antes | Después | Notas |
|-------|-------|---------|-------|
| `BelongsToMany` | ✅ Correcto | ✅ Sin cambios | Ya incluía `props.urls.options` |
| `MorphToMany` | ✅ Correcto | ✅ Sin cambios | Ya incluía `props.urls.options` |
| **`HasMany`** | ❌ Faltaba | ✅ **CORREGIDO** | Ahora incluye `props.urls.options` |
| **`MorphMany`** | ❌ Faltaba | ✅ **CORREGIDO** | Ahora incluye `props.urls.options` |
| `BelongsTo` | N/A | N/A | No es un campo de colección |
| `HasOne` | N/A | N/A | No es un campo de colección |
| `MorphTo` | N/A | N/A | No es un campo de colección |

## Código Modificado

### HasMany.php (líneas 486-507)

**Antes:**
```php
// Initialize URLs array
$props['urls'] = [];

// Attach/detach URLs require an existing model
if ($model) {
    $modelId = $model->getKey();
    $props['urls']['attachable'] = ...;
    $props['urls']['attach'] = ...;
    $props['urls']['detach'] = ...;
}
```

**Después:**
```php
// Options URL is always available (no model ID needed)
$props['urls'] = [
    'options' => "/{$apiPrefix}/{$resourceKey}/resource/field/{$fieldKey}/options",
];

// Attach/detach URLs require an existing model
if ($model) {
    $modelId = $model->getKey();

    // Update options URL with resource ID context
    $props['urls']['options'] = "/{$apiPrefix}/{$resourceKey}/resource/field/{$fieldKey}/options?resourceId={$modelId}";
    $props['urls']['attachable'] = ...;
    $props['urls']['attach'] = ...;
    $props['urls']['detach'] = ...;
}
```

### MorphMany.php (líneas 466-487)

Misma modificación que `HasMany`.

## Beneficios del Fix

1. **Consistencia:** Todos los campos de relación tipo colección ahora tienen el mismo comportamiento
2. **Frontend simplificado:** El frontend puede confiar en que `props.urls.options` siempre existe
3. **Mejor DX:** Los desarrolladores no necesitan usar rutas diferentes según el tipo de campo
4. **Compatibilidad:** Mantiene backward compatibility - el `optionsUrl` del nivel raíz sigue existiendo

## Uso en el Frontend

```javascript
// Antes (tenías que verificar el tipo de campo)
const optionsUrl = field.type === 'belongsToMany' || field.type === 'morphToMany'
  ? field.props?.urls?.options
  : field.optionsUrl;

// Después (uniforme para todos)
const optionsUrl = field.props?.urls?.options || field.optionsUrl;
```

## Testing

Para verificar que funciona correctamente:

```php
// En tu recurso
HasMany::make('Comments', 'comments')
    ->paginated();

// En show response
$response = get("/nadota-api/posts/resource/1");
$commentsField = collect($response['data']['attributes'])
    ->firstWhere('key', 'comments');

// Verificar que existen ambas URLs
expect($commentsField['optionsUrl'])->toBeTruthy();
expect($commentsField['props']['urls']['options'])->toBeTruthy();
expect($commentsField['props']['paginationUrl'])->toBeTruthy();
```

## Archivos Modificados

- `/src/Http/Fields/Relations/HasMany.php` (líneas 486-507)
- `/src/Http/Fields/Relations/MorphMany.php` (líneas 466-487)

## Archivos No Modificados (Ya Correctos)

- `/src/Http/Fields/Relations/BelongsToMany.php`
- `/src/Http/Fields/Relations/MorphToMany.php`
- `/src/Http/Fields/Field.php` (genera `optionsUrl` nivel raíz)
- `/src/Http/Fields/Traits/RelationshipTrait.php` (método `getOptionsUrl()`)
