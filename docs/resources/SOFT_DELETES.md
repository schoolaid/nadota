# Soft Deletes API (Nadota)

Documentación de los endpoints relacionados con borrado lógico: listar registros eliminados, **restaurar** y **eliminación total (force delete)**.

Todas las rutas viven bajo el prefijo de la API (`nadota.prefix`, por defecto `nadota-api`) y el grupo de recurso `/{resourceKey}/resource`.

| Acción | Método | Ruta | Nombre |
|---|---|---|---|
| Listar (incluye/solo eliminados) | `GET` | `/nadota-api/{resourceKey}/resource?withTrashed=...` | `nadota.api.resource.index` |
| Restaurar | `POST` | `/nadota-api/{resourceKey}/resource/{id}/restore` | `nadota.api.resource.restore` |
| Eliminación total | `DELETE` | `/nadota-api/{resourceKey}/resource/{id}/force` | `nadota.api.resource.forceDelete` |

> `{id}` está restringido a enteros (`[0-9]+`).

---

## Requisito previo

El recurso debe declarar que usa soft deletes; de lo contrario `restore` y `forceDelete` devuelven **400**.

```php
class PostResource extends Resource
{
    public string $model = Post::class;

    // Habilita los endpoints de soft delete / restore / force delete
    protected bool $usesSoftDeletes = true;
}
```

El modelo Eloquent debe usar el trait `Illuminate\Database\Eloquent\SoftDeletes` (columna `deleted_at`, o la que devuelva `getDeletedAtColumn()`).

---

## 1. Listar con eliminados (`withTrashed`)

Controla qué registros devuelve el index mediante el query param `withTrashed`. Solo tiene efecto si el recurso usa soft deletes; en caso contrario se ignora y se devuelven únicamente los activos.

```
GET /nadota-api/{resourceKey}/resource?withTrashed=only
```

### Valores aceptados

| Comportamiento | Valores admitidos | Scope aplicado |
|---|---|---|
| Solo activos (default) | `without`, `active`, `0`, `''`, ausente | — (default Eloquent) |
| Solo eliminados | `only`, `deleted`, `1` | `onlyTrashed()` |
| Todos (activos + eliminados) | `with`, `all`, `2`, `true` | `withTrashed()` |

Se combina con los demás parámetros del index (búsqueda, filtros, ordenamiento, paginación).

### Respuesta

Cada registro incluye `deletedAt` (la fecha de borrado o `null`) y el bloque `permissions`:

```json
{
  "data": [
    {
      "id": 12,
      "attributes": [ /* fields del index */ ],
      "deletedAt": "2026-05-28T14:03:00.000000Z",
      "permissions": {
        "view": true,
        "update": true,
        "delete": true,
        "forceDelete": true,
        "restore": true,
        "attach": true,
        "detach": true,
        "fields": { /* ... */ }
      }
    }
  ]
}
```

- `deletedAt` es `null` para registros activos y trae la fecha para los eliminados.
- `permissions.restore` es `true` solo si: el recurso usa soft deletes **y** el registro está eliminado (`deletedAt != null`) **y** el usuario está autorizado (`restore`).
- `permissions.forceDelete` es `true` si el recurso usa soft deletes y el usuario está autorizado (`forceDelete`).

> Estas banderas permiten al frontend decidir cuándo mostrar los botones "Restaurar" / "Eliminar definitivamente".

---

## 2. Restaurar un registro

Restaura un registro previamente soft-deleted.

```
POST /nadota-api/{resourceKey}/resource/{id}/restore
```

- Busca el registro con `onlyTrashed()` (debe estar eliminado).
- Requiere autorización de la policy: ability `restore`.
- Ejecuta dentro de una transacción los hooks `beforeRestore` → `performRestore` → `afterRestore` y registra un `ActionEvent` con `name = restore`.

### Respuestas

**200 — restaurado**
```json
{
  "message": "Resource restored successfully",
  "data": { /* modelo restaurado */ }
}
```

**400 — el recurso no soporta restore** (`usesSoftDeletes = false`)
```json
{ "message": "This resource does not support restore" }
```

**404** — no existe un registro eliminado con ese `id`.

**403** — no autorizado por la policy.

**500 — fallo durante la operación** (se hace rollback)
```json
{ "message": "Failed to restore resource", "error": "..." }
```

### Ejemplo

```bash
curl -X POST \
  https://tu-app.test/nadota-api/posts/resource/12/restore \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json"
```

---

## 3. Eliminación total (force delete)

Elimina el registro de forma **permanente** (no recuperable).

```
DELETE /nadota-api/{resourceKey}/resource/{id}/force
```

- Busca el registro con `withTrashed()`, por lo que funciona tanto si está activo como si ya estaba soft-deleted.
- Requiere autorización de la policy: ability `forceDelete`.
- Ejecuta dentro de una transacción los hooks `beforeForceDelete` → `performForceDelete` → `afterForceDelete` y registra un `ActionEvent` con `name = forceDelete` (incluye `changes: { "permanently_deleted": true }`).

### Respuestas

**200 — eliminado permanentemente**
```json
{ "message": "Resource permanently deleted" }
```

**400 — el recurso no soporta force delete** (`usesSoftDeletes = false`)
```json
{ "message": "This resource does not support force delete" }
```

**404** — no existe ningún registro (activo o eliminado) con ese `id`.

**403** — no autorizado por la policy.

**500 — fallo durante la operación** (se hace rollback)
```json
{ "message": "Failed to permanently delete resource", "error": "..." }
```

### Ejemplo

```bash
curl -X DELETE \
  https://tu-app.test/nadota-api/posts/resource/12/force \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json"
```

---

## Personalización en el recurso

Puedes sobreescribir los hooks y el cálculo de permisos por registro:

```php
class PostResource extends Resource
{
    public string $model = Post::class;
    protected bool $usesSoftDeletes = true;

    // --- Restore ---
    public function beforeRestore(Model $model, NadotaRequest $request): void { /* ... */ }
    public function performRestore(Model $model, NadotaRequest $request): bool { return $model->restore(); }
    public function afterRestore(Model $model, NadotaRequest $request): void { /* ... */ }

    // --- Force delete ---
    public function beforeForceDelete(Model $model, NadotaRequest $request): void { /* ... */ }
    public function performForceDelete(Model $model, NadotaRequest $request): bool { return $model->forceDelete(); }
    public function afterForceDelete(Model $model, NadotaRequest $request): void { /* ... */ }

    // --- Permisos por registro (además de la policy) ---
    public function canRestore(Model $model, NadotaRequest $request): bool { return true; }
    public function canForceDelete(Model $model, NadotaRequest $request): bool { return true; }
}
```

La autorización final combina la policy (`authorizedTo`) **y** estos métodos `can*`.

---

## Relación con otros documentos

- Eventos generados por estas acciones: ver [`ACTION_EVENTS.md`](../roadmaps/ACTION_EVENTS.md).
