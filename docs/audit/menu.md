# Audit — Menu

Functional audit of the navigation menu (Nadota 1.3.0, 2026-11-02): how `GET /nadota-api/menu`
is built, every extension point, what the tests cover, and the issues found. Issues are
described in full in [`docs/testing/findings/audit.md`](../testing/findings/audit.md).

## Purpose

The menu endpoint returns the navigation tree for the SPA: one item per registered resource the
user may `viewAny`, optionally grouped into sections, plus links the application adds. It is built
in memory on every request; there is no table, no cache and no Blade layer.

## Pieces

| Piece | File | Role |
|-------|------|------|
| Route | `routes/api.php:14` | `GET /menu` → `MenuController::menu`, name `nadota.api.menu`, protected middleware group |
| Controller | `src/Http/Controllers/MenuController.php` | `readonly`; delegates to `MenuServiceInterface::build()` |
| Service | `src/Http/Services/MenuService.php` | The whole algorithm (bound as a singleton in `ServiceBindingServiceProvider`) |
| Registry | `src/NadotaService.php` | Static hooks: `prepareMenuUsing`, `addMenuItems`, `configureMenuSections`, section cache, excluded resources |
| Value objects | `src/Menu/MenuItem.php`, `MenuSection.php`, `MenuSectionDefinition.php` | Items, runtime sections, host-facing section definitions |
| Serializers | `src/Http/Resources/Menu/MenuResource.php`, `MenuItemResource.php`, `MenuSectionResource.php` | JSON shape |
| Resource hooks | `src/Http/Traits/ResourceMenuOptions.php`, `Resource::displayIcon()` / `title()` / `apiUrl()` / `frontendUrl()` | Per-resource menu options |

## Flow

```mermaid
flowchart TD
    A[GET /nadota-api/menu] --> B[MenuController::menu]
    B --> C{NadotaService::$prepareMenuUsing set?}
    C -- yes --> Z1[return callback result verbatim<br/>no authorization, no building]
    C -- no --> D[ResourceManager::getResources]
    D --> E[filter: ResourceAuthorizationInterface<br/>setModel(model)->authorizedTo(request, 'viewAny')]
    E --> F[foreach resource: new $class]
    F --> G{displayInMenu(request)?}
    G -- false --> F
    G -- true --> H[MenuItem(title, key, displayIcon, apiUrl, frontendUrl,<br/>null, [], orderInMenu, isResource=true)]
    H --> I{displayInSubMenu() === null?}
    I -- yes --> J["structure[title()] = item   (AUD-001)"]
    I -- no --> K[addMenuItemToSection(key)]
    K --> K1{trim(key) === ''?}
    K1 -- yes --> KX[resource dropped (AUD-003)]
    K1 -- no --> K2{MenuSectionDefinition for key?}
    K2 -- yes --> K3{definition->isVisible(request)?}
    K3 -- no --> KY[item dropped with its section]
    K3 -- yes --> K4["structure[key] ??= definition->toMenuSection()"]
    K2 -- no --> K5["structure[key] ??= MenuSection(key, 'Boxes')<br/>or convert an existing MenuItem into a section"]
    K4 --> K6[append item to structure[key] children]
    K5 --> K6
    J --> L{NadotaService::$addMenuItems set?}
    K6 --> L
    L -- yes --> M[foreach custom item: skip if !isVisible(request)<br/>parent? addMenuItemToSection : structure[getKey()] (AUD-002)]
    L -- no --> N
    M --> N[buildFinalMenu: array_values of structure]
    N --> O[sortMenuRecursively: usort by getOrder(), stable on ties]
    O --> P[MenuResource → JSON wrapped in data]
```

Order of operations that matters:

1. **Authorization first**, through the bound `ResourceAuthorizationInterface` (the real service asks the
   model's policy; **no policy ⇒ allowed**, see AUD-012).
2. **`displayInMenu($request)`** second; it receives the `NadotaRequest`, so it can depend on the user
   or the query string.
3. Placement keys: top-level resources by **title**, sections by **section key**, top-level custom items
   by **`getKey()`** — all in one array, which is the root of AUD-001/002/003.
4. Custom items are added after every resource, so a custom item can turn into / collide with a resource entry.
5. Sorting is by `order` only (PHP 8 `usort` is stable, so ties keep registration order), recursively.

### Default values that decide the order

| Element | Default order | Source |
|---------|---------------|--------|
| Resource item | `1` | `ResourceMenuOptions::orderInMenu()` |
| `MenuItem` created by hand | `2` | `MenuItem::__construct` |
| Fallback section (key without definition) | `2` | `new MenuSection($key, 'Boxes')` |
| Section from a definition | `0` | `MenuSectionDefinition::$order` |
| `MenuSection` created by hand | `2` | `MenuSection::__construct` |

So, by default: definition-based sections first, then resources, then custom items and fallback sections.

## JSON contract (as served)

```json
{
  "data": [
    { "label": "Student Resources", "key": "student", "icon": "LayoutPanelTop",
      "apiUrl": "nadota-api/student/resource", "frontendUrl": "resources/student",
      "children": [], "order": 1, "isResource": true },
    { "isSection": true, "title": "Content", "icon": "Folder", "enableSearch": false,
      "children": [ { "label": "…", "key": "…", "…": "…" } ], "order": 0 }
  ]
}
```

- The tree is wrapped in `data` (the guide says it is a bare array — AUD-010).
- Item keys are **singular** route keys (`student`, `student-profile`); labels default to
  "Student Resources" (CORE-006).
- `apiUrl`/`frontendUrl` have **no leading slash** and use `nadota.api.prefix` / `nadota.frontend.prefix`
  (the routes themselves are mounted on `nadota.prefix`, CORE-001).
- Items always serialize `children: []` (AUD-004); sections never carry `key`, `collapsible` or
  `defaultCollapsed` (AUD-006).

## Extension points

### 1. Per resource (`ResourceMenuOptions` + `Resource`)

```php
class PostResource extends Resource
{
    protected ?string $title = 'Posts';          // label (default "Post Resources")
    protected ?string $displayIcon = 'FileText';  // icon (default LayoutPanelTop)

    public function displayInMenu(NadotaRequest $request): bool
    {
        return $request->user()?->can('publish-posts') ?? false;   // hide per request
    }

    public function displayInSubMenu(): ?string
    {
        return 'content';   // section key; null = top level; '' drops the item (AUD-003)
    }

    public function orderInMenu(): int
    {
        return 3;           // lower first; default 1
    }
}
```

`Resource::canSee()` exists (trait `VisibleWhen`) but the menu ignores it (AUD-011) — use `displayInMenu()`.

### 2. Section definitions (`NadotaService::configureMenuSections`)

```php
NadotaService::configureMenuSections(fn () => [
    MenuSectionDefinition::make('content', 'Content')
        ->icon('Folder')
        ->order(0)
        ->visibleWhen(fn ($request) => $request->user()?->isEditor()),
]);
```

- Evaluated lazily on the first lookup, then cached in a static for the process lifetime (AUD-009).
- A hidden definition (`visibleWhen` false) drops every resource and custom item placed in it.
- `collapsible()` / `defaultCollapsed()` are accepted but not emitted (AUD-006).
- A key that has no definition creates a fallback section labelled with the key, icon `Boxes`, order 2.
- Do not reuse a resource **title** as a section key (AUD-003).

### 3. Custom items (`NadotaService::addMenuItems`)

```php
NadotaService::addMenuItems(fn (NadotaRequest $request) => [
    // label, key, icon, apiUrl, frontendUrl, parent (section key), children, order
    MenuItem::make('Reports', 'reports', 'BarChart', null, '/reports')->order(5),
    MenuItem::make('Drafts', 'drafts', null, null, '/drafts', 'content'),        // into section "content"
    MenuItem::make('Billing', 'billing')->canSee(fn ($r) => $r->user()?->isAdmin()),
]);
```

- The callback receives the request; each top-level item's `canSee()` is honoured.
- A `MenuItem` with children serializes `children: []` (AUD-004); use a `MenuSection` for nesting —
  but nested children are **not** visibility-checked (AUD-005), and only one top-level custom
  `MenuSection` survives (AUD-002).
- `MenuItem::make()` needs at least label and key (AUD-008). `MenuItem::fromResource()` exists but
  builds an item with order 2 and `isResource = false` (AUD-007).

### 4. Replace the whole builder (`NadotaService::prepareMenuUsing`)

```php
NadotaService::prepareMenuUsing(fn (NadotaRequest $request) => response()->json(['data' => $myTree]));
```

The return value is sent as is: **no authorization filtering, no sorting, no wrapping**.

### 5. Swap the service

Bind your own `MenuServiceInterface` (single method `build(NadotaRequest $request)`) after the package
provider registers; the controller resolves it from the container.

### 6. Excluding resources

`NadotaService::excludeResources([...])` removes resources from the registry (so from the menu and the
API) — but only if called before the package boots, i.e. from an application provider's `register()`
(AUD-036).

## Authorization and visibility summary

| Gate | Where | Honoured |
|------|-------|----------|
| Policy `viewAny` on the model | `MenuService::build()` | yes (no policy ⇒ visible, AUD-012) |
| `displayInMenu($request)` | per resource | yes |
| `Resource::canSee()` | per resource | **no** (AUD-011) |
| `MenuSectionDefinition::visibleWhen()` | per section | yes |
| `MenuItem/MenuSection::canSee()` top-level custom | per item | yes |
| `canSee()` on children of a custom section | nested | **no** (AUD-005) |
| Route middleware | `nadota.middlewares` | default `['api']` has no auth (AUD-012); dropped entirely when the config is not published (AUD-030) |

Built-in `ActionEventResource` is hidden (`displayInMenu()` returns false) but routable (AUD-028).

## Test coverage

| Behaviour | Test |
|-----------|------|
| `MenuItem` defaults, `make()` arguments, `order()`, children API, `canSee()`, `fromResource()` | `tests/Unit/Menu/MenuItemTest.php` |
| `MenuSection` defaults, container semantics, `canSee()` | `tests/Unit/Menu/MenuSectionTest.php` |
| `MenuSectionDefinition` builder, `visibleWhen` receives the request, `toMenuSection()` | `tests/Unit/Menu/MenuSectionDefinitionTest.php` |
| JSON shapes of item/section, type dispatch, recursive nesting | `tests/Unit/Menu/MenuResourceTest.php` |
| Build: item fields, prefixes, `viewAny` filtering, `displayInMenu`, ordering + stable ties, fallback and defined sections, hidden sections, trimming, custom items (top level, in sections, `canSee`), `prepareMenuUsing` bypass | `tests/Unit/Menu/MenuServiceTest.php` |
| Static registry: callbacks, lazy single evaluation, exclusions | `tests/Unit/Menu/NadotaServiceTest.php` |
| Trait defaults, `$displayIcon` | `tests/Unit/Menu/ResourceMenuOptionsTest.php` |
| HTTP: fixture domain, wrapper, singular keys, real policy denial, ActionEventResource hidden, request reaches `displayInMenu`, sections + custom items, `prepareMenuUsing` response, guest access | `tests/Feature/Http/Menu/MenuEndpointTest.php` |

Fixtures: `tests/Fixtures/Menu/` — `AbstractMenuFixture` (menu options from a static map, reset with
`MenuState::reset()`), `MenuAlpha|Beta|Gamma|DeltaResource`, `DenyViewAnyPolicy`, `MenuState`
(resets every `NadotaService` static and, with `resetRegistry()`, the `ResourceManager`).

Not covered: a custom `MenuServiceInterface` binding (trivial container swap); Octane-style reuse
across requests beyond AUD-009.

## Issues

| ID | Summary | Severity |
|----|---------|----------|
| [AUD-001](../testing/findings/audit.md#aud-001--resources-sharing-a-title-collapse-into-one-menu-item) | Resources sharing a title collapse into one item | medium |
| [AUD-002](../testing/findings/audit.md#aud-002--top-level-custom-menusections-overwrite-each-other) | Top-level custom sections overwrite each other | medium |
| [AUD-003](../testing/findings/audit.md#aud-003--section-placement-loses-resources-in-three-edge-cases) | Section placement loses resources (order-dependent, key = title, blank key) | medium |
| [AUD-004](../testing/findings/audit.md#aud-004--menuitemresource-never-serializes-a-menuitems-children) | `MenuItem` children never serialized | low |
| [AUD-005](../testing/findings/audit.md#aud-005--children-of-a-custom-section-are-not-visibility-filtered) | Nested custom children ignore `canSee` | medium |
| [AUD-006](../testing/findings/audit.md#aud-006--collapsible--defaultcollapsed-never-reach-the-menu-json) | `collapsible`/`defaultCollapsed` not emitted | low |
| [AUD-007](../testing/findings/audit.md#aud-007--menuitemfromresource-ignores-order-and-isresource) | `fromResource()` ignores order / `isResource` | low |
| [AUD-008](../testing/findings/audit.md#aud-008--menuitem-constructor-implicit-nullable-labelkey-on-non-nullable-properties) | `MenuItem::make()` without args → TypeError | low |
| [AUD-009](../testing/findings/audit.md#aud-009--menu-section-definitions-are-cached-forever-after-the-first-read) | Section definitions cached forever | low |
| [AUD-010](../testing/findings/audit.md#aud-010--menu-docs-contradict-the-endpoint) | Menu docs contradict the endpoint | low |
| [AUD-011](../testing/findings/audit.md#aud-011--resourcecansee-has-no-effect-on-the-menu) | `Resource::canSee()` ignored | low |
| [AUD-012](../testing/findings/audit.md#aud-012--default-install-serves-the-menu-and-crud-to-guests) | Guests get the menu and CRUD by default | medium |
| Related | CORE-001 (prefix keys), CORE-006 (titles keep "Resource"), AUD-028, AUD-030, AUD-036 | |
