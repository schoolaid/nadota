# Menu

Build the admin panel's navigation from your registered resources, organize them into sections, and add custom links.

Nadota assembles a single navigation tree from the resources it discovers. Each resource that is visible and authorized becomes a menu item; resources can also be grouped under named sections. A single endpoint returns the finished tree for the frontend to render.

## How the menu is built

`MenuService::build()` produces the tree:

1. Load all registered resources.
2. Drop resources the current user is not authorized to `viewAny`.
3. Drop resources whose `displayInMenu($request)` returns `false`.
4. Place each remaining resource as a top-level item, or nest it under a section (when `displayInSubMenu()` returns a section key).
5. Append any custom items registered with `NadotaService::addMenuItems()`.
6. Sort everything by `order`, recursively.

The result is serialized by `MenuResource` into a flat array where each entry is either a menu item or a section (with nested children).

## Controlling a resource's menu entry

Resources use the `ResourceMenuOptions` trait, which provides overridable methods:

```php
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class PostResource extends Resource
{
    // Hide this resource from the menu entirely
    public function displayInMenu(NadotaRequest $request): bool
    {
        return true;
    }

    // Place under a section; return null for a top-level item
    public function displayInSubMenu(): ?string
    {
        return 'content';
    }

    // Lucide-style icon name; null falls back to the item default
    public function displayIcon(): ?string
    {
        return 'FileText';
    }

    // Lower numbers sort first
    public function orderInMenu(): int
    {
        return 1;
    }
}
```

| Method | Default | Purpose |
|--------|---------|---------|
| `displayInMenu(NadotaRequest)` | `true` | Whether the resource appears at all |
| `displayInSubMenu()` | `null` | Section key to nest under, or `null` for top level |
| `displayIcon()` | `null` | Icon name (item default is `LayoutPanelTop`) |
| `orderInMenu()` | `1` | Sort order |

The item's label is the resource `title()`, its key is the resource key, and its `apiUrl` / `frontendUrl` are derived from the configured prefixes (e.g. `nadota-api/posts/resource` and `resources/posts`).

## Defining sections

When a resource (or custom item) declares a section key, the service looks up a matching `MenuSectionDefinition`. Register definitions with `NadotaService::configureMenuSections()`, typically from a service provider's `boot()`:

```php
use SchoolAid\Nadota\NadotaService;
use SchoolAid\Nadota\Menu\MenuSectionDefinition;

NadotaService::configureMenuSections(fn () => [
    MenuSectionDefinition::make('content', 'Content')
        ->icon('Boxes')
        ->order(1)
        ->collapsible()
        ->visibleWhen(fn ($request) => $request->user()?->isEditor()),
]);
```

`MenuSectionDefinition` builder methods:

| Method | Default | Purpose |
|--------|---------|---------|
| `make(string $key, string $label)` | — | Create a definition |
| `icon(string)` | `null` | Section icon |
| `order(int)` | `0` | Sort order |
| `collapsible(bool)` | `true` | Whether the section can collapse |
| `defaultCollapsed(bool)` | `false` | Initial collapsed state |
| `visibleWhen(callable)` | always visible | Visibility predicate (receives the request) |

If a resource references a section key that has no definition, the service falls back to creating a section using the key as its label with a `Boxes` icon (backwards compatible).

## Adding custom menu items

Register extra links (external URLs, dashboards, etc.) with `NadotaService::addMenuItems()`:

```php
use SchoolAid\Nadota\NadotaService;
use SchoolAid\Nadota\Menu\MenuItem;

NadotaService::addMenuItems(fn ($request) => [
    MenuItem::make('Reports', 'reports', 'BarChart', '/reports', '/reports')
        ->order(5),
]);
```

Custom items respect their own visibility (`isVisible($request)`), can be nested by setting a parent section, and are sorted alongside resource items. A `MenuItem` can also be created directly from a resource with `MenuItem::fromResource(PostResource::class)`.

## Fully replacing the menu

To bypass the automatic builder entirely, provide your own callback. Whatever it returns is sent back as the menu response:

```php
NadotaService::prepareMenuUsing(fn ($request) => $myCustomMenu);
```

## Endpoint

```http
GET /nadota-api/menu
```

Returns the navigation tree. Items and sections have different shapes.

Menu item:

```json
{
  "label": "Posts",
  "key": "posts",
  "icon": "FileText",
  "apiUrl": "nadota-api/posts/resource",
  "frontendUrl": "resources/posts",
  "children": [],
  "order": 1,
  "isResource": true
}
```

Section (identified by `isSection: true`, with nested `children`):

```json
{
  "isSection": true,
  "title": "Content",
  "icon": "Boxes",
  "enableSearch": false,
  "children": [
    { "label": "Posts", "key": "posts", "icon": "FileText", "apiUrl": "...", "frontendUrl": "...", "children": [], "order": 1, "isResource": true }
  ],
  "order": 1
}
```

The top-level response is an array of these objects, already sorted by `order`. See [API routes](../api/routes.md) and [API responses](../api/responses.md).

## Internals

For the deeper mechanics — the `MenuItem` / `MenuSection` value objects, the `MenuItemInterface` contract, how `MenuService` converts the structure and sorts recursively, and the resource serializers — see [Menu internals](../internals/menu.md).

## Related

- [Resources](resources.md) — defining resources that populate the menu
- [Authorization](authorization.md) — the `viewAny` check that filters the menu
- [API routes](../api/routes.md), [API responses](../api/responses.md)
