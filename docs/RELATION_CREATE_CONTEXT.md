# Relation Fields - createContext Validation

Este documento explica la validación de `createContext` en los campos de relación y qué campos deben incluirlo.

---

## ✅ HasMany - YA IMPLEMENTADO

### Características
- Relación uno-a-muchos
- El modelo relacionado tiene una FK hacia el padre
- Permite crear nuevos hijos directamente

### createContext incluye:
```json
{
  "parentResource": "posts",
  "parentId": 1,
  "relatedResource": "comments",
  "foreignKey": "post_id",
  "prefill": { "post_id": 1 },
  "lock": ["post_id"],
  "returnUrl": "/resources/posts/1",
  "createUrl": "/nadota-api/comments/resource/create",
  "storeUrl": "/nadota-api/comments/resource"
}
```

### Flujo:
1. Usuario está en Post #1
2. Hace clic en "Create Comment"
3. Frontend abre formulario de Comment
4. Campo `post_id` viene pre-llenado con `1` y bloqueado
5. Guarda el nuevo Comment
6. Regresa al Post #1

**Estado**: ✅ Implementado y funcionando

---

## ✅ BelongsToMany - IMPLEMENTADO AHORA

### Características
- Relación muchos-a-muchos
- Usa tabla pivot
- Permite attach/detach de elementos existentes
- **NUEVO**: Permite crear nuevos elementos y auto-attacharlos

### createContext incluye:
```json
{
  "parentResource": "posts",
  "parentId": 1,
  "relatedResource": "tags",
  "relationType": "belongsToMany",
  "autoAttach": true,
  "attachUrl": "/nadota-api/posts/resource/1/attach/tags",
  "returnUrl": "/resources/posts/1",
  "createUrl": "/nadota-api/tags/resource/create",
  "storeUrl": "/nadota-api/tags/resource"
}
```

### Diferencias con HasMany:
- ❌ NO tiene `foreignKey` (usa tabla pivot)
- ❌ NO tiene `prefill` (no hay FK que pre-llenar)
- ❌ NO tiene `lock` (no hay campos que bloquear)
- ✅ Tiene `relationType: "belongsToMany"`
- ✅ Tiene `autoAttach: true` (para hacer attach automático después de crear)
- ✅ Tiene `attachUrl` (para hacer el attach)

### Flujo:
1. Usuario está en Post #1
2. Hace clic en "Create Tag"
3. Frontend abre formulario de Tag
4. Guarda el nuevo Tag (ej: Tag #5)
5. Frontend hace POST a `/nadota-api/posts/resource/1/attach/tags` con `{ ids: [5] }`
6. Regresa al Post #1 con el nuevo Tag attachado

### Uso:
```php
BelongsToMany::make('Tags', 'tags', TagResource::class)
    ->canCreate()           // Habilita creación
    ->autoAttach(true)      // Auto-attach después de crear (default: true)
    ->withPivot(['order'])  // Incluye columnas pivot si es necesario
```

**Estado**: ✅ Implementado

---

## ❌ HasManyThrough - NO NECESITA createContext

### Características
- Relación a través de una tabla intermedia
- **Solo lectura** (read-only)
- No permite modificar los elementos relacionados

### Ejemplo:
```
Country -> User -> Post
```
No puedes crear Posts directamente desde Country porque la relación pasa por User.

### Por qué NO necesita createContext:
1. Es read-only (método `fill()` vacío)
2. La creación debe hacerse en el resource intermedio
3. No tiene sentido crear elementos a través de 2 saltos

**Estado**: ❌ No implementado (y no debería implementarse)

---

## Comparación

| Campo | createContext | Razón |
|-------|--------------|-------|
| **HasMany** | ✅ Sí | Crea hijos con FK pre-llenada |
| **BelongsToMany** | ✅ Sí (opcional) | Crea y auto-attach elementos |
| **HasManyThrough** | ❌ No | Solo lectura, relación indirecta |
| **MorphMany** | ✅ Debería | Similar a HasMany pero polimórfico |
| **BelongsTo** | ❌ No | Usa select, no crea en contexto |

---

## Implementación Backend

### BelongsToMany con createContext

```php
// En BelongsToMany.php se agregaron:

// Propiedades
protected bool $canCreate = false;
protected bool $autoAttach = true;

// Métodos
public function canCreate(bool $canCreate = true): static
public function autoAttach(bool $autoAttach = true): static

// Props para frontend
$props = [
    'canCreate' => $this->canCreate,
    'autoAttach' => $this->autoAttach,
    'createContext' => [
        'parentResource' => $resourceKey,
        'parentId' => $modelId,
        'relatedResource' => $relatedResourceKey,
        'relationType' => 'belongsToMany',
        'autoAttach' => $this->autoAttach,
        'attachUrl' => "/{$apiPrefix}/{$resourceKey}/resource/{$modelId}/attach/{$fieldKey}",
        'returnUrl' => "/{$frontendPrefix}/{$resourceKey}/{$modelId}",
        'createUrl' => "/{$apiPrefix}/{$relatedResourceKey}/resource/create",
        'storeUrl' => "/{$apiPrefix}/{$relatedResourceKey}/resource",
    ],
];
```

---

## Implementación Frontend (recomendado)

### Para BelongsToMany con autoAttach:

```typescript
// Después de crear el nuevo elemento
const newItem = await createRelatedItem(data);

// Si autoAttach está habilitado
if (createContext.autoAttach && createContext.attachUrl) {
  await fetch(createContext.attachUrl, {
    method: 'POST',
    body: JSON.stringify({ ids: [newItem.id] }),
  });
}

// Regresar a la vista padre
router.push(createContext.returnUrl);
```

---

## Resumen

### ✅ Implementados:
- **HasMany**: `createContext` con `foreignKey`, `prefill`, `lock`
- **BelongsToMany**: `createContext` con `autoAttach`, `attachUrl`

### ❌ No necesarios:
- **HasManyThrough**: Es read-only

### 📝 Pendientes (futuro):
- **MorphMany**: Similar a HasMany pero con campos polimórficos
- **MorphToMany**: Similar a BelongsToMany pero con pivot polimórfico

---

## Testing

Para probar BelongsToMany con createContext:

```php
// En tu Resource
BelongsToMany::make('Tags', 'tags', TagResource::class)
    ->canCreate()  // Habilita el botón "Create Tag"
    ->autoAttach() // Auto-attach después de crear
```

El frontend recibirá:
```json
{
  "canCreate": true,
  "autoAttach": true,
  "createContext": {
    "parentResource": "posts",
    "parentId": 1,
    "relatedResource": "tags",
    "relationType": "belongsToMany",
    "autoAttach": true,
    "attachUrl": "/nadota-api/posts/resource/1/attach/tags",
    "returnUrl": "/resources/posts/1",
    "createUrl": "/nadota-api/tags/resource/create",
    "storeUrl": "/nadota-api/tags/resource"
  }
}
```
