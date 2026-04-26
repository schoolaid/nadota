# Acciones (Action classes) y sus hooks

Este documento describe las **clases de Action** que se usan en los Resources de Nadota: el contrato, la clase base, los hooks/lifecycle, los métodos de configuración fluida, las respuestas y cómo se integran con el sistema de `ActionEvent`.

> Para el detalle de la API HTTP (request/response payloads, query params), ver [`ACTIONS_API.md`](ACTIONS_API.md). Este documento se centra en la capa PHP: clases, hooks y wiring interno.

---

## 1. Jerarquía de clases

```
ActionInterface  (src/Contracts/ActionInterface.php)
        │
        ▼
Action           (src/Http/Actions/Action.php, abstract)
        │
        ├── DestructiveAction  (src/Http/Actions/DestructiveAction.php, abstract)
        │
        └── (Acciones concretas definidas por la app o el paquete)
```

| Componente            | Archivo                                              |
|-----------------------|------------------------------------------------------|
| `ActionInterface`     | `src/Contracts/ActionInterface.php`                  |
| `Action` (abstract)   | `src/Http/Actions/Action.php`                        |
| `DestructiveAction`   | `src/Http/Actions/DestructiveAction.php`             |
| `ActionResponse`      | `src/Http/Actions/ActionResponse.php`                |
| `ActionController`    | `src/Http/Controllers/ActionController.php`          |
| `ActionExecutionService` | `src/Http/Services/ActionExecutionService.php`    |

---

## 2. Contrato: `ActionInterface`

**Archivo:** `src/Contracts/ActionInterface.php`

```php
interface ActionInterface
{
    public static function getKey(): string;
    public function name(): string;
    public function handle(Collection $models, NadotaRequest $request): mixed;
    public function fields(NadotaRequest $request): array;
    public function authorizedToRun(NadotaRequest $request, Model $model): bool;
    public function showOnIndex(): bool;
    public function showOnDetail(): bool;
    public function isDestructive(): bool;
    public function confirmText(): ?string;
    public function confirmButtonText(): string;
    public function cancelButtonText(): string;
    public function toArray(NadotaRequest $request): array;
}
```

Toda Action debe cumplir este contrato. La forma usual es extender la clase abstracta `Action`, que ya implementa todo excepto `handle()`.

---

## 3. Clase base: `Action`

**Archivo:** `src/Http/Actions/Action.php` (381 líneas)
**Implementa:** `ActionInterface`

### 3.1 Propiedades configurables (líneas 14-68)

| Propiedad             | Tipo            | Default        | Uso                                                      |
|-----------------------|-----------------|----------------|----------------------------------------------------------|
| `$name`               | `?string`       | `null`         | Nombre legible (si es null, se deriva del classname)     |
| `$showOnIndex`        | `bool`          | `true`         | Visible en la vista de listado                           |
| `$showOnDetail`       | `bool`          | `true`         | Visible en la vista de detalle                           |
| `$destructive`        | `bool`          | `false`        | Marca la acción como peligrosa (estilo de UI)            |
| `$standalone`         | `bool`          | `false`        | Permite ejecutar sin modelos seleccionados               |
| `$confirmText`        | `?string`       | `null`         | Texto del diálogo de confirmación                        |
| `$confirmButtonText`  | `string`        | `'Run Action'` | Texto del botón de confirmación                          |
| `$cancelButtonText`   | `string`        | `'Cancel'`     | Texto del botón de cancelar                              |
| `$component`          | `?string`       | `null`         | Componente frontend custom                               |
| `$icon`               | `?string`       | `null`         | Icono                                                    |
| `$authCallback`       | `?Closure`      | `null`         | Callback de autorización registrado vía `canRun()`       |

### 3.2 Único método abstracto

```php
abstract public function handle(Collection $models, NadotaRequest $request): mixed;
```

Es el **único hook obligatorio** en una Action concreta. Recibe la colección de modelos autorizados y el request; devuelve `void`, una `ActionResponse`, o cualquier valor que el runner convierte a respuesta.

### 3.3 `getKey()` (estático, línea 73-76)

Convierte el FQCN de la Action a un slug usado en las rutas HTTP, p. ej.
`SchoolAid\Nadota\Http\Actions\SendEmailAction` → `school-aid-nadota-http-actions-send-email-action`. Se usa para resolver la Action en `ActionExecutionService::findAction()`.

---

## 4. Hooks / lifecycle disponibles

A diferencia de los `Resource*Service`, **`Action` no expone hooks `before*` / `after*`**. El ciclo de vida es deliberadamente plano. Los puntos extensibles son:

| Hook / método                                 | Cuándo se invoca                                                              | Para qué se usa                                       |
|-----------------------------------------------|-------------------------------------------------------------------------------|-------------------------------------------------------|
| `fields(NadotaRequest $request): array`       | `GET /resource/actions/{key}/fields` — al cargar el formulario de la acción   | Definir los inputs del modal/formulario               |
| `authorizedToRun(NadotaRequest $request, Model $model): bool` | Antes de `handle()`, una vez **por cada modelo**                  | Filtrar qué modelos puede afectar el usuario          |
| `handle(Collection $models, NadotaRequest $request): mixed`   | **Único método de ejecución** — recibe sólo los modelos autorizados | Lógica de la acción                                   |
| `showOnIndex(): bool`                         | Cuando `ActionController::index()` filtra por contexto                        | Ocultar/mostrar la acción en listado                  |
| `showOnDetail(): bool`                        | Idem para vista detalle                                                       | Ocultar/mostrar la acción en detalle                  |
| `toArray(NadotaRequest $request): array`      | Al serializar la Action al frontend                                           | Personalizar el payload (raramente se sobreescribe)   |

**No existen** `boot()`, `setUp()`, `tearDown()`, `beforeHandle()`, `afterHandle()`, `validate()` ni `rules()` propios de Action. La validación se delega a los `Field` declarados en `fields()` o se hace dentro de `handle()`.

> Si necesitás reaccionar **después** de ejecutar la acción (por ejemplo, enviar una notificación), escuchá el evento `ActionLogged` (ver §10) en lugar de añadir un hook.

---

## 5. Setters fluidos (configuración encadenable)

`Action` ofrece *fluent setters* para configurar instancias sin crear subclases triviales:

| Método                                    | Equivalente a                          |
|-------------------------------------------|----------------------------------------|
| `onlyOnIndex(): static`                   | `showOnIndex=true`, `showOnDetail=false` |
| `onlyOnDetail(): static`                  | `showOnIndex=false`, `showOnDetail=true` |
| `showOnTableRow(): static`                | `showOnIndex=true`, `showOnDetail=true`  |
| `destructive(bool $v = true): static`     | `$destructive = $v`                    |
| `standalone(bool $v = true): static`      | `$standalone = $v`                     |
| `withConfirmation(string $text): static`  | `$confirmText = $text`                 |
| `setConfirmButtonText(string $t): static` | `$confirmButtonText = $t`              |
| `setCancelButtonText(string $t): static`  | `$cancelButtonText = $t`               |
| `component(string $c): static`            | `$component = $c`                      |
| `icon(string $i): static`                 | `$icon = $i`                           |
| `canRun(\Closure $cb): static`            | Registra callback de autorización      |
| `make(): static`                          | Factory estática (`new static()`)      |

### Ejemplo de declaración en un Resource

```php
// app/Nadota/PostResource.php
public function actions(NadotaRequest $request): array
{
    return [
        SendEmailAction::make()
            ->onlyOnDetail()
            ->withConfirmation('¿Enviar email a este post?')
            ->canRun(fn ($req, $model) => $req->user()->id === $model->author_id),

        PurgePostsAction::make()
            ->destructive()
            ->setConfirmButtonText('Eliminar para siempre'),
    ];
}
```

---

## 6. Action concreta: `DestructiveAction`

**Archivo:** `src/Http/Actions/DestructiveAction.php` (21 líneas)
**Extends:** `Action`

Subclase abstracta para operaciones peligrosas. Ajustes preconfigurados:

- `$destructive = true`
- `$confirmButtonText = 'Delete'`
- `$confirmText = 'Are you sure you want to run this action?'` (default si la subclase no lo cambia)

Cualquier acción que borre o invalide datos de forma irreversible debería extender de aquí en lugar de `Action`.

---

## 7. Cómo declararlas en un Resource

`Resource::actions()` devuelve un array de instancias `ActionInterface`:

```php
// src/Resource.php:355-358 (default)
public function actions(NadotaRequest $request): array
{
    return [];
}
```

> No existe trait `HasActions` o `InteractsWithActions`: el método está directamente en la clase base `Resource`. Cada subclase de Resource lo sobreescribe si necesita exponer acciones.

---

## 8. Flujo de ejecución

### 8.1 Endpoints

| Método | Ruta                                                        | Controller                    |
|--------|-------------------------------------------------------------|-------------------------------|
| GET    | `/nadota-api/{resourceKey}/resource/actions`                | `ActionController::index`     |
| GET    | `/nadota-api/{resourceKey}/resource/actions/{key}/fields`   | `ActionController::fields`    |
| POST   | `/nadota-api/{resourceKey}/resource/actions/{key}`          | `ActionController::execute`   |

(definidos en `routes/api.php:26-28`)

### 8.2 `ActionController::execute()` (`src/Http/Controllers/ActionController.php:78-110`)

1. Autoriza con `viewAny` sobre el Resource.
2. Resuelve la Action vía `ActionExecutionService::findAction($request, $actionKey)`.
3. Lee el array `resources` (IDs) del request.
4. Valida que se proporcionaron modelos (a menos que la acción sea `standalone`).
5. Llama a `ActionExecutionService::execute($request, $action, $modelIds)`.
6. Devuelve la `ActionResponse` como JSON.
7. Captura excepciones y devuelve `Action::danger($message)`.

### 8.3 `ActionExecutionService::execute()` (líneas 41-72)

Orden exacto de los hooks invocados:

```
1. getModels($modelClass, $modelIds)            // SELECT con softDelete-aware
2. filterAuthorizedModels($action, $models)     // → llama $action->authorizedToRun() por cada modelo
3. abort si no hay autorizados (y no es standalone)
4. $action->handle($authorizedModels, $request) // ←— HOOK PRINCIPAL
5. logActionExecution(...)                      // → ActionEventService::logAction() con prefijo 'action:'
6. Convertir resultado a ActionResponse y devolver
```

### 8.4 Variante por chunks: `executeBatched()` (líneas 132-163)

Para datasets grandes parte la colección en chunks (default 100), aplica el mismo flujo a cada chunk y registra cada batch en `ActionEvent`.

---

## 9. Autorización

Hay **dos niveles** independientes:

1. **Autorización del Resource** — en `ActionController` se llama `$request->authorized('viewAny')` antes de listar/ejecutar acciones. Aquí sí se integra con policies vía `ResourceAuthorizationService`.
2. **Autorización por modelo** — `Action::authorizedToRun(NadotaRequest $request, Model $model): bool`. Por defecto retorna `true`. Se puede:
   - sobreescribir el método en la subclase, o
   - registrar un callback con `canRun(Closure $cb)` (líneas 118-123 de `Action.php`).

`ActionExecutionService::filterAuthorizedModels()` itera la colección y ejecuta `authorizedToRun()` por cada modelo. Sólo los autorizados llegan a `handle()`.

> No hay integración directa con Laravel Policies en la clase base: si la querés, llamala explícitamente dentro de `authorizedToRun()` (`return $request->user()->can('runAction', $model);`).

---

## 10. Respuestas: `ActionResponse`

**Archivo:** `src/Http/Actions/ActionResponse.php` (185 líneas)
**Implementa:** `Arrayable`, `JsonSerializable`

### 10.1 Factories estáticas

| Helper en `ActionResponse`                       | Helper equivalente en `Action` (estático) | `type` resultante |
|--------------------------------------------------|-------------------------------------------|-------------------|
| `ActionResponse::message(string $msg)`           | `Action::message($msg)`                   | `message`         |
| `ActionResponse::danger(string $msg)`            | `Action::danger($msg)`                    | `danger`          |
| `ActionResponse::redirect(string $url)`          | `Action::redirect($url)`                  | `redirect`        |
| `ActionResponse::download(string $url, string $name)` | `Action::download($url, $name)`      | `download`        |
| `ActionResponse::openInNewTab(string $url)`      | `Action::openInNewTab($url)`              | `openInNewTab`    |

Cada uno permite encadenar `->withData(array $data)` para añadir payload custom.

### 10.2 Forma serializada

```json
{
  "type": "message",
  "message": "Email enviado",
  "url": null,
  "filename": null,
  "openInNewTab": false,
  "data": { ... }
}
```

Las claves con valor `null` se filtran al serializar (`toArray()`, líneas 166-176).

### 10.3 Ejemplo dentro de `handle()`

```php
public function handle(Collection $models, NadotaRequest $request): mixed
{
    foreach ($models as $model) {
        Mail::to($model->email)->send(new Welcome($model));
    }

    return Action::message("Se enviaron {$models->count()} correos");
}
```

Si `handle()` no retorna una `ActionResponse`, el runner la envuelve automáticamente (líneas 66-71 de `ActionExecutionService`).

---

## 11. Integración con `ActionEvent`

Cada Action ejecutada genera **un `ActionEvent` por cada modelo afectado**, automáticamente, después de `handle()`:

`ActionExecutionService::logActionExecution()` (líneas 108-127):

```php
$this->actionEventService->logAction(
    action:   'action:' . $action::getKey(),     // ← prefijo distintivo
    model:    $model,
    resource: $resource,
    request:  $request,
    fields:   $request->only(array_keys($request->all())),
    metadata: [
        'action_name' => $action->name(),
        'action_key'  => $action::getKey(),
    ]
);
```

Detalles:

- El `name` del `ActionEvent` queda como `action:school-aid-...-send-email-action`. Esto permite distinguir en queries entre acciones CRUD (`create`, `update`, etc.) y acciones custom.
- El `metadata` se guarda en las columnas `original` / `changes` del `ActionEvent` (vía `ActionEventService::log()`).
- Tras crear el `ActionEvent`, se dispara el evento Laravel `ActionLogged` (si `nadota.action_events.dispatch_events = true`). Es el punto recomendado para reaccionar a la finalización de una Action sin tocar la propia clase.
- Si `nadota.action_events.queue = true`, el registro se hace vía el job `LogActionEvent` en cola.

Para detalles completos de este sistema, ver [`EVENT_TRACKING.md`](EVENT_TRACKING.md).

---

## 12. Tests

A día de hoy **no hay tests dedicados a Actions** en la suite del paquete:

- `tests/Unit/` y `tests/ServiceIntegration/` cubren campos, modelos y servicios CRUD.
- No existen archivos `*ActionTest.php` ni casos para `ActionExecutionService` / `ActionController`.

Es un hueco a tener en cuenta si se modifica el flujo de ejecución o se añaden nuevos hooks.

---

## 13. Resumen de hooks por contrato

| Hook                              | Obligatorio | Default                                    | Cuándo se llama                          |
|-----------------------------------|-------------|--------------------------------------------|------------------------------------------|
| `handle($models, $request)`       | Sí          | (abstracto)                                | Una vez, con todos los modelos autorizados |
| `fields($request)`                | No          | `[]`                                       | Al pedir el form de la Action            |
| `authorizedToRun($request, $model)` | No        | `true` (o evalúa `$authCallback` si existe) | Por cada modelo, antes de `handle()`     |
| `name()`                          | No          | Deriva del classname                       | En `toArray()` y al loguear              |
| `getKey()` (static)               | No          | Slug del FQCN                              | En el routing y al loguear               |
| `showOnIndex()` / `showOnDetail()`| No          | `true` / `true`                            | Filtrado por contexto en `index()`       |
| `isDestructive()`                 | No          | `$destructive`                             | UI                                        |
| `confirmText()`                   | No          | `null`                                     | UI                                        |
| `confirmButtonText()` / `cancelButtonText()` | No | `'Run Action'` / `'Cancel'`              | UI                                        |
| `toArray($request)`               | No          | Serializa propiedades                      | Al exponer al frontend                   |

---

## 14. Validación: ¿qué clases de Action hay realmente en el paquete?

Búsqueda en `src/`:

| Clase                               | Tipo               | Notas                                  |
|-------------------------------------|--------------------|----------------------------------------|
| `Action` (`src/Http/Actions/Action.php`) | abstract base   | Único método abstracto: `handle()`     |
| `DestructiveAction` (`src/Http/Actions/DestructiveAction.php`) | abstract | Para operaciones peligrosas |

El paquete **no incluye Actions concretas listas para usar** (`SendEmailAction`, etc.). Las acciones concretas se definen en cada aplicación que consuma Nadota, extendiendo `Action` o `DestructiveAction` y registrándolas en el método `actions()` de cada Resource.
