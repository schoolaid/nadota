# Menu System (Internals)

How Nadota assembles the admin navigation menu at request time from discovered resources, host-app callbacks, and configured sections. Version 1.1.4, namespace `SchoolAid\Nadota`. For the user-facing configuration guide, see [menu guide](../guides/menu.md).

> The menu is built **in-memory per request** from the resource registry. There is no `menus` database table, Eloquent `Menu` model, or Blade component layer — any earlier documentation describing a DB-backed/Blade menu is obsolete (see [discrepancies](#discrepancies-with-old-docs)).

## Overview

```
GET /menu
   └─ MenuController::menu(NadotaRequest)
        └─ MenuServiceInterface::build()        (bound → MenuService)
             ├─ host callback override?         NadotaService::$prepareMenuUsing
             ├─ ResourceManager::getResources() filtered by viewAny authorization
             ├─ build MenuItem per resource, placed top-level or into a section
             ├─ host extra items                NadotaService::$addMenuItems
             ├─ flatten structure → array of MenuItemInterface
             └─ recursive sort by order → MenuResource (JSON)
```

## Entry point

`src/Http/Controllers/MenuController.php` is a `readonly` controller that injects the contract and delegates:

```php
public function __construct(protected MenuServiceInterface $menuService) {}

public function menu(NadotaRequest $request)
{
    return $this->menuService->build($request);
}
```

`MenuServiceInterface` (`src/Contracts/MenuServiceInterface.php`) declares the single method `build(NadotaRequest $request)`. It is bound to `MenuService` as a singleton in `ServiceBindingServiceProvider`. The route `GET /menu` is named `nadota.api.menu`.

## MenuService::build()

`src/Http/Services/MenuService.php` is the whole algorithm. Steps in order:

### 1. Full override hook

```php
if (NadotaService::$prepareMenuUsing) {
    return call_user_func(NadotaService::$prepareMenuUsing, $request);
}
```

If the host app registered `NadotaService::prepareMenuUsing(...)`, that callback wholly replaces the default builder and its return value is returned verbatim.

### 2. Authorization filtering

```php
$resources = ResourceManager::getResources();
$resourceAuthorization = app(ResourceAuthorizationInterface::class);

$resources = $resources->filter(fn ($resource) =>
    $resourceAuthorization->setModel($resource['model'])->authorizedTo($request, 'viewAny'));
```

Every registered resource is tested against the `viewAny` policy ability; unauthorized resources never enter the menu.

### 3. Build a MenuItem per resource

For each remaining resource the service instantiates it and skips it if `displayInMenu($request)` is false. Otherwise it builds a `MenuItem` from resource metadata:

```php
$menuItem = new MenuItem(
    $resourceInstance->title(),
    $resourceInstance->getKey(),
    $resourceInstance->displayIcon(),
    $resourceInstance->apiUrl(),
    $resourceInstance->frontendUrl(),
    null,                              // parent set via section placement, not constructor
    [],
    $resourceInstance->orderInMenu(),
    true                               // isResource = true
);
```

Placement depends on `displayInSubMenu()`:

- `null` → top-level: `$menuStructure[$resource->title()] = $menuItem`.
- a section key → `addToMenuPath()` → `addMenuItemToSection()`.

### 4. Section placement (`addMenuItemToSection`)

Given a non-empty section key:

- Looks up a configured definition via `NadotaService::getMenuSection($key)` (registered through `NadotaService::configureMenuSections(...)`, returning `MenuSectionDefinition` objects).
- If a definition exists: it is skipped when `isVisible($request)` is false; otherwise a `MenuSection` is created from it on first use (`toMenuSection()`).
- If no definition exists (backwards-compatibility fallback): a `MenuSection` is created with the key as its label and `'Boxes'` icon. If a plain `MenuItem` already occupied that key, it is converted into a `MenuSection` containing the existing item.
- The new item is appended to the section's children via `getChildren()` / `setChildren()`.

### 5. Host-supplied extra items

```php
if (NadotaService::$addMenuItems) {
    foreach (call_user_func(NadotaService::$addMenuItems, $request) as $item) {
        if (!$item->isVisible($request)) continue;
        $parent = $item->getParent();
        $parent !== null
            ? $this->addMenuItemToSection($menuStructure, $parent, $item, $request)
            : $menuStructure[$item->getKey()] = $item;        // top-level
    }
}
```

Extra items (registered via `NadotaService::addMenuItems(...)`) are visibility-filtered and placed top-level or into a section based on their `getParent()`.

### 6. Flatten and sort

- `buildFinalMenu()` reduces the keyed structure to a flat array of `MenuItemInterface`.
- `sortMenuRecursively()` `usort`s by `getOrder()` ascending and recurses into children.
- Returns `new MenuResource($finalMenu)`.

## Menu model objects (`src/Menu/`)

### MenuItem

`src/Menu/MenuItem.php` implements `MenuItemInterface`, uses `Makeable` and `VisibleWhen`. Properties: `label`, `key`, `icon` (default `'LayoutPanelTop'`), `apiUrl`, `frontendUrl`, `parent`, `children`, `order` (default `2`), `isResource` (default `false`). `fromResource($resource)` builds an item from a resource's metadata. Exposes the full `MenuItemInterface` getter surface plus `addChild()`, `order()`, and `setChildren()`.

### MenuSection

`src/Menu/MenuSection.php` also implements `MenuItemInterface` (uses `VisibleWhen`). It represents a grouping: `title`, `icon`, `children`, `enableSearch`, `order` (default `2`). `getKey()` returns `''`, `getApiUrl()`/`getFrontendUrl()`/`getParent()` return `null` — a section is a container, not a navigable target. `isSearchEnabled()` exposes `enableSearch`.

### MenuSectionDefinition

`src/Menu/MenuSectionDefinition.php` is the host-facing builder for sections (registered via `NadotaService::configureMenuSections()`), separate from the runtime `MenuSection`. Fluent setters: `make(key, label)`, `icon()`, `order()`, `collapsible()`, `defaultCollapsed()`, `visibleWhen(callable)`. `isVisible($request)` evaluates the `visibleWhen` callback (true if none set). `toMenuSection($children = [])` materializes a runtime `MenuSection` (label, icon, children, order, `enableSearch = false`).

> `collapsible` / `defaultCollapsed` are stored on the definition but are **not** carried onto the `MenuSection` produced by `toMenuSection()` and therefore are not emitted in the menu JSON in 1.1.4.

## JSON serialization (`src/Http/Resources/Menu/`)

`MenuResource` (a `ResourceCollection`) maps each `MenuItemInterface`, dispatching by concrete type:

```php
return $this->collection->map(fn (MenuItemInterface $item) =>
    $item instanceof MenuSection
        ? (new MenuSectionResource($item))->toArray($request)
        : (new MenuItemResource($item))->toArray($request))->all();
```

`MenuItemResource` output:

```json
{
  "label": "...",
  "key": "...",
  "icon": "...",
  "apiUrl": "...",
  "frontendUrl": "...",
  "children": [],
  "order": 2,
  "isResource": true
}
```

`MenuSectionResource` output (children are recursively wrapped in a `MenuResource`):

```json
{
  "isSection": true,
  "title": "...",
  "icon": "...",
  "enableSearch": false,
  "children": [ /* MenuResource */ ],
  "order": 2
}
```

Note `MenuItemResource` always emits `"children": []` (children are only attached to sections, which use `MenuSectionResource`).

## Extension points (NadotaService)

Configured from the host app (typically in a service provider). All live in `src/NadotaService.php`:

- `prepareMenuUsing(callable)` — replace the entire builder.
- `addMenuItems(callable)` — append extra `MenuItem`/`MenuSection` objects.
- `configureMenuSections(callable)` — register `MenuSectionDefinition[]`; consumed lazily by `getMenuSections()` / `getMenuSection($key)`.

## Discrepancies with old docs

`docs/menu-technical.md` (legacy, Spanish/English mix) does **not** match the current implementation and should not be relied on:

- Describes a `menus` DB table, an Eloquent `Menu` model, `MenuData` DTO, and CRUD methods (`createMenu`, `updateMenu`, `deleteMenu`, `getMenuBySlug`). None exist — `MenuServiceInterface` declares only `build()`.
- Describes Blade `View/Components/Menu/*` (`MenuBar`, `MenuItem`, `MenuButton`). Nadota is API-only; there are no Blade menu components.
- Describes `Cache::remember` slug-based caching. The real `MenuService` performs no caching; it rebuilds per request.
- Real namespaces are `SchoolAid\Nadota\Menu\*` and `SchoolAid\Nadota\Http\Resources\Menu\*`, not the `Data/`, `Models/`, `View/` paths the old doc lists.

## Related documents

- [Menu guide](../guides/menu.md)
- [Architecture](./architecture.md)
- [API routes](../api/routes.md)
