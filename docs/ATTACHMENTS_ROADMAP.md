# Roadmap: Sistema de Attachments para Relaciones

Este documento describe el plan de implementación para el sistema completo de attach/detach/sync en todas las relaciones.

## Estado Actual

| Relación | ManagesAttachments | Service | Controller | Rutas | Estado |
|----------|-------------------|---------|------------|-------|--------|
| HasMany | ✅ | ✅ HasManyAttachmentService | ✅ | ✅ | **Completo** |
| BelongsToMany | ✅ | ✅ BelongsToManyAttachmentService | ✅ | ✅ | **Completo** |
| MorphToMany | ✅ | ✅ MorphToManyAttachmentService | ✅ | ✅ | **Completo** |
| MorphedByMany | ✅ | ✅ (usa MorphToMany) | ✅ | ✅ | **Completo** |

---

## Fase 1: Infraestructura Base

### 1.1 Crear Contrato Base para Attachment Services
- [ ] Crear `AttachmentServiceInterface`
- [ ] Definir métodos: `attach()`, `detach()`, `sync()`, `getAttachableItems()`

### 1.2 Crear Clase Base Abstracta
- [ ] Crear `AbstractAttachmentService`
- [ ] Implementar lógica común (validación, permisos, respuestas)

**Archivos:**
- `src/Http/Services/Attachments/Contracts/AttachmentServiceInterface.php`
- `src/Http/Services/Attachments/AbstractAttachmentService.php`

---

## Fase 2: BelongsToMany Attachment Service

### 2.1 Crear BelongsToManyAttachmentService
- [ ] Implementar `getAttachableItems()` - items no relacionados
- [ ] Implementar `attach()` - usa `$relation->attach($ids, $pivotData)`
- [ ] Implementar `detach()` - usa `$relation->detach($ids)`
- [ ] Implementar `sync()` - usa `$relation->sync($ids)`
- [ ] Soporte para datos pivot en attach/sync

### 2.2 Agregar ManagesAttachments a BelongsToMany
- [ ] Agregar trait `ManagesAttachments`
- [ ] Agregar métodos específicos para pivot data

### 2.3 Actualizar URLs en BelongsToMany
- [ ] Verificar que URLs apunten a rutas correctas

**Archivos:**
- `src/Http/Services/Attachments/BelongsToManyAttachmentService.php`
- `src/Http/Fields/Relations/BelongsToMany.php` (modificar)

---

## Fase 3: MorphToMany Attachment Service

### 3.1 Crear MorphToManyAttachmentService
- [ ] Extender/reutilizar lógica de BelongsToMany
- [ ] Manejar morph type en queries
- [ ] Implementar `attach()`, `detach()`, `sync()`

### 3.2 Agregar ManagesAttachments a MorphToMany
- [ ] Agregar trait
- [ ] Configurar para relaciones polimórficas

### 3.3 MorphedByMany
- [ ] Evaluar si necesita service separado o reutiliza MorphToMany

**Archivos:**
- `src/Http/Services/Attachments/MorphToManyAttachmentService.php`
- `src/Http/Fields/Relations/MorphToMany.php` (modificar)
- `src/Http/Fields/Relations/MorphedByMany.php` (modificar)

---

## Fase 4: Controller y Rutas

### 4.1 Actualizar AttachmentController
- [ ] Agregar casos para `belongsToMany`, `morphToMany`, `morphedByMany`
- [ ] Inyectar nuevos services
- [ ] Implementar método `sync()`

### 4.2 Agregar Ruta Sync
- [ ] Agregar `POST /{id}/sync/{field}` en routes/api.php

### 4.3 Refactorizar switch a Strategy/Factory (opcional)
- [ ] Considerar patrón factory para seleccionar service por tipo

**Archivos:**
- `src/Http/Controllers/AttachmentController.php` (modificar)
- `routes/api.php` (modificar)

---

## Fase 5: Validación y Pivot Data

### 5.1 Validación de Pivot Fields
- [ ] Validar datos pivot según `pivotFields()` definidos en el field
- [ ] Usar reglas de validación de cada pivot field

### 5.2 Request para Attachments
- [ ] Crear `AttachmentRequest` con validación dinámica
- [ ] Soportar estructura: `{ items: [id], pivot: { field: value } }`

**Archivos:**
- `src/Http/Requests/AttachmentRequest.php` (nuevo)

---

## Fase 6: Hooks y Eventos

### 6.1 Hooks en Resource
- [ ] `beforeAttach($model, $field, $ids)`
- [ ] `afterAttach($model, $field, $ids)`
- [ ] `beforeDetach($model, $field, $ids)`
- [ ] `afterDetach($model, $field, $ids)`
- [ ] `beforeSync($model, $field, $ids)`
- [ ] `afterSync($model, $field, $changes)`

### 6.2 Action Events (opcional)
- [ ] Registrar eventos de attach/detach/sync en action_events

**Archivos:**
- `src/Resource.php` (modificar)
- `src/Http/Services/ActionEventService.php` (modificar, opcional)

---

## Fase 7: Documentación

### 7.1 Actualizar Documentación
- [ ] Documentar endpoints en `docs/fields/README.md`
- [ ] Documentar configuración de attachments por tipo de relación
- [ ] Ejemplos de uso con pivot data

---

## Estructura de Request/Response

### Request: Attach
```json
POST /{resource}/resource/{id}/attach/{field}
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
POST /{resource}/resource/{id}/detach/{field}
{
  "items": [1, 2]
}
```

### Request: Sync
```json
POST /{resource}/resource/{id}/sync/{field}
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

### Response: Success
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

---

## Progreso

- [x] Análisis del sistema actual
- [x] Creación del roadmap
- [x] Fase 1: Infraestructura Base
  - [x] AttachmentServiceInterface
  - [x] AbstractAttachmentService
- [x] Fase 2: BelongsToMany
  - [x] BelongsToManyAttachmentService
  - [x] ManagesAttachments trait agregado
- [x] Fase 3: MorphToMany
  - [x] MorphToManyAttachmentService
  - [x] ManagesAttachments trait agregado
  - [x] MorphedByMany soportado
- [x] Fase 4: Controller y Rutas
  - [x] AttachmentController actualizado
  - [x] Ruta sync agregada
- [ ] Fase 5: Validación y Pivot (opcional)
- [ ] Fase 6: Hooks y Eventos (opcional)
- [x] Fase 7: Documentación
  - [x] ATTACHMENTS_API.md creado

---

## Notas de Implementación

### Diferencias entre HasMany y BelongsToMany

| Aspecto | HasMany | BelongsToMany |
|---------|---------|---------------|
| Attach | Setea FK en child | Inserta en pivot table |
| Detach | Setea FK a null | Elimina de pivot table |
| Sync | N/A | Sincroniza pivot table |
| Pivot Data | N/A | Soporta datos adicionales |

### Consideraciones de Seguridad

1. Verificar permisos `attach` y `detach` en Resource
2. Validar que items pertenezcan al modelo correcto
3. Sanitizar pivot data
4. Respetar `attachableLimit` si está configurado
