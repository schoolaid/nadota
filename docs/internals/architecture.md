# Architecture (Internals)

How Nadota boots, binds its services, discovers resources, and routes a request from the network through to a CRUD service. This document targets contributors who need to understand the package wiring. Version 1.1.4, root namespace `SchoolAid\Nadota`.

## Overview

Nadota is a service-oriented Laravel package. A request enters through a route group, is resolved into a thin controller, and is delegated to a single-responsibility service that is bound against a contract interface. Resources (subclasses of `Resource`) are discovered from the host application at boot and registered into a static in-memory map keyed by URI slug.

```
Host App boots
   └─ NadotaServiceProvider
        ├─ register():  ServiceBindingServiceProvider  (binds contracts → services)
        └─ boot():      RouteServiceProvider            (loads route groups)
                        ResourceServiceProvider         (config + resource discovery)

HTTP request
   └─ Route group (prefix + middleware)
        └─ Controller (NadotaRequest injected)
             └─ Service (resolved from contract interface)
                  └─ Resource (model + fields + filters + auth)
```

## Package bootstrap

`src/NadotaServiceProvider.php` is the only provider registered by the host application (directly or via package auto-discovery). It does nothing itself except register the three sub-providers, splitting concerns by lifecycle phase:

```php
public function register(): void
{
    $this->app->register(ServiceBindingServiceProvider::class);
}

public function boot(): void
{
    $this->app->register(RouteServiceProvider::class);
    $this->app->register(ResourceServiceProvider::class);
}
```

- Container bindings happen in `register()` (no other services resolved yet).
- Routes and resource discovery happen in `boot()` (config and the container are available).

## Service binding

`src/Providers/ServiceBindingServiceProvider.php` binds every contract interface to its concrete service as a **singleton**. This is the seam that lets controllers depend on interfaces rather than implementations:

```php
$this->app->singleton(ResourceIndexInterface::class, ResourceIndexService::class);
$this->app->singleton(ResourceCreateInterface::class, ResourceCreateService::class);
$this->app->singleton(ResourceStoreInterface::class, ResourceStoreService::class);
// ... Show, Edit, Update, Destroy, ForceDelete, Restore, Export
$this->app->singleton(ResourceAuthorizationInterface::class, ResourceAuthorizationService::class);
$this->app->singleton(MenuServiceInterface::class, MenuService::class);
```

Two services are bound as singletons by concrete class (no interface):

- `ActionEventService` — registered as a singleton specifically so a single batch ID is shared across a request when logging action events.
- `RelationIndexService` — drives the paginated relation endpoint.

Because every binding is a singleton, swapping behavior means re-binding the interface after Nadota's provider runs (e.g. in the host app's own provider).

## Routing

`src/Providers/RouteServiceProvider.php` registers two route groups, loading from `routes/public.php` and `routes/api.php`:

```php
Route::group($this->publicRouteConfiguration(), fn () =>
    $this->loadRoutesFrom(__DIR__.'/../../routes/public.php'));   // middleware: ['api']

Route::group($this->routeConfiguration(), fn () =>
    $this->loadRoutesFrom(__DIR__.'/../../routes/api.php'));       // middleware: config('nadota.middlewares')
```

Both groups share `prefix => config('nadota.prefix', 'nadota-api')` and `as => 'nadota.api.'`. Both also **exclude** `SubstituteBindings::class` — Nadota resolves the resource itself from the `{resourceKey}` segment rather than relying on Laravel route-model binding. The protected group applies the middleware stack from `nadota.middlewares` (default `['api']`); the public group is fixed to `['api']` and currently serves only the global options endpoint. See [API routes](../api/routes.md) for the full route table.

## Resource discovery and caching

`src/Providers/ResourceServiceProvider.php` `boot()`:

1. Registers publishable assets when running in console (`nadota-provider` stub, `nadota-config`).
2. Merges `config/nadota.php` under the `nadota` key, unless config is cached.
3. Calls `registerResources()`.

`registerResources()` discovers resources via `ResourceManager`, registers built-in package resources, then applies exclusions:

```php
$path = Config::get('nadota.path_resources');               // default: 'app/Nadota'

if (config('app.env') == 'production') {
    if (!Cache::has(config('nadota.key_resources_cache'))) {
        ResourceManager::registerResource($path);
    }
}

ResourceManager::registerResource($path);                   // always runs

$this->registerBuiltInResources();                          // ActionEventResource if enabled

$excluded = NadotaService::getExcludedResources();
if (!empty($excluded)) {
    ResourceManager::removeResourcesByClass($excluded);
}
```

> Note: the production cache branch checks for the cache key but `registerResource($path)` runs unconditionally afterward regardless. The discovery key (`nadota.key_resources_cache`) is read but the map itself lives in a static property, not in the cache store — discovery currently re-runs each boot.

### ResourceManager

`src/ResourceManager.php` is a static registry. `static ?Collection $resources` holds the map for the lifetime of the process.

`registerResource($path)` uses Symfony `Finder` to scan `base_path($path)` for files matching `*Resource.php`. For each match it:

- Converts the file path into a fully-qualified class name (path separators → `\`, strip `base_path()` prefix and `.php`, `ucfirst`).
- Skips classes that are not subclasses of `Resource`.
- Computes the URI key via `Helpers::toUri($resourceClass)`; throws if two resources collide on the same key.
- Instantiates the resource to read its public `model` property; throws if missing.
- Stores `['class' => ..., 'model' => ...]` keyed by the URI slug.

Key lookup methods used elsewhere:

- `getResourceByKey($key)` — returns the class string, `abort(404)` if unknown. Wrapped in `once()` for per-request memoization.
- `exists($key)` — boolean check (used by request validation).
- `getResources()` — the full collection (used by the menu builder).
- `registerResourceClass($class)` — register a single class directly (used for built-in resources such as `ActionEventResource`).
- `removeResourcesByClass($classes)` — rejects classes from the map (used for exclusions).

`src/NadotaService.php` is the static configuration façade. It holds menu customization callbacks (`prepareMenuUsing`, `addMenuItems`, `configureMenuSections`), the menu-section definition registry, and the excluded-resources list (`excludeResources` / `getExcludedResources`). It is the host-app extension point invoked during boot and menu building.

## The contract layer

`src/Contracts/` defines the interface each service implements, so controllers and other callers depend on abstractions. The CRUD-facing contracts (`ResourceIndexInterface`, `ResourceCreateInterface`, `ResourceStoreInterface`, `ResourceShowInterface`, `ResourceEditInterface`, `ResourceUpdateInterface`, `ResourceDestroyInterface`, `ResourceForceDeleteInterface`, `ResourceRestoreInterface`) share a uniform shape — a single `handle(NadotaRequest $request, ...)` method, e.g.:

```php
interface ResourceIndexInterface
{
    public function handle(NadotaRequest $request);
}
```

Other contracts in the directory:

- `ResourceInterface` — the surface every `Resource` exposes.
- `ResourceAuthorizationInterface` — authorization service (`setModel()`, `authorizedTo()`).
- `ResourceExportInterface`, `ExporterInterface` — export pipeline.
- `MenuServiceInterface`, `MenuItemInterface` — menu building (see [menu internals](./menu.md)).
- `FilterInterface` — `apply()` + `resources()` (see [filter internals](./filters.md)).
- `ActionInterface` — resource actions.

## Request lifecycle: route → controller → service

`src/Http/Requests/NadotaRequest.php` is the form request injected into every controller. It composes two traits:

- `PrepareResource` — `getResource()` resolves the `{resourceKey}` route segment through `ResourceManager::getResourceByKey()` and instantiates it via the container (memoized in `$this->resource`); `setResource()` allows overriding (used in relation contexts).
- `AuthorizesResources` — `validateResource()` aborts 404 if the key is unknown; `authorized($action, $model)` prepares the resource and aborts 403 unless `authorizedTo()` passes.

The standard CRUD flow (`src/Http/Controllers/ResourceController.php`) injects all nine CRUD contracts via constructor and delegates one-to-one:

```php
public function index(NadotaRequest $request)
{
    return $this->indexService->handle($request);
}
```

Each service starts by authorizing (`$request->authorized('viewAny' | 'view' | 'create' | ...)`) and resolving the resource (`$request->getResource()`), then performs its operation. For example `ResourceIndexService` runs a pipeline (see [pipeline internals](./pipeline.md)); the persist services (`ResourceStoreService`, `ResourceUpdateService`) extend `AbstractResourcePersistService` and use the `Handlers/` for defaults and relations.

Metadata controllers are separate from `ResourceController`:

- `ResourceIndexController` — `config`, `info`, `fields`, `filters`, `lens`, `compact` (data) endpoints. Its constructor calls `$request->validateResource()` when not running in console.
- `MenuController` — delegates to `MenuServiceInterface::build()`.
- Specialized controllers: `ActionController`, `ActionEventController`, `AttachmentController`, `ExportController`, `FieldOptionsController`, `GlobalOptionsController`, `RelationController`, `ResourceOptionsController`.

## Directory structure overview

```
src/
├── NadotaServiceProvider.php          Entry provider; registers the three below
├── NadotaService.php                  Static config façade (menu callbacks, exclusions)
├── ResourceManager.php                Static resource registry / discovery
├── Resource.php                       Base resource class (see guides/resources.md)
├── Providers/
│   ├── ServiceBindingServiceProvider  Binds contracts → services (singletons)
│   ├── RouteServiceProvider           Loads public + protected route groups
│   └── ResourceServiceProvider        Config merge + resource discovery
├── Contracts/                         Interface layer for services & extension points
├── Events/                            ActionLogged
├── Jobs/                              LogActionEvent (async action logging)
├── Menu/                              MenuItem, MenuSection, MenuSectionDefinition
├── Models/                            ActionEvent
├── Resources/                         Built-in ActionEventResource
└── Http/
    ├── Controllers/                   Thin controllers delegating to services
    ├── Requests/                      NadotaRequest (+ traits)
    ├── Services/                      One service per operation
    │   ├── Pipes/                     Index query pipeline (see pipeline.md)
    │   ├── Handlers/                  DefaultValueHandler, RelationHandler
    │   ├── Attachments/               BelongsToMany / HasMany / Morph attach services
    │   ├── Exporters/                 Csv / Excel exporters
    │   ├── FieldOptions/              Strategy-based option resolution
    │   └── Traits/                    ProcessesFields
    ├── Fields/                        Field types, traits, enums, DTOs (see fields/README.md)
    ├── Filters/                       Filter types (see filters/README.md, internals/filters.md)
    ├── Criteria/                      FilterCriteria
    ├── Resources/                     JSON API resources (Index, Menu, Filters, ...)
    ├── DataTransferObjects/           IndexRequestDTO, ExportRequestDTO
    ├── Actions/                       Action, DestructiveAction, ActionResponse
    ├── Criteria/Helpers/Traits/       Cross-cutting helpers
    └── Helpers/                       Helpers (toUri, slug, ...)
```

## Related documents

- [Resources guide](../guides/resources.md)
- [Index query pipeline](./pipeline.md)
- [Menu internals](./menu.md)
- [Filter internals](./filters.md)
- [API routes](../api/routes.md)
