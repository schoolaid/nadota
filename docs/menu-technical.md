# Menu System - Technical Documentation

## Architecture Overview

The menu system is built following Laravel's service-oriented architecture pattern with clear separation of concerns between controllers, services, and data transfer objects.

### Core Components

```
src/
├── Http/
│   ├── Controllers/
│   │   └── MenuController.php
│   ├── Services/
│   │   └── MenuService.php
│   └── Requests/
│       ├── MenuCreateRequest.php
│       └── MenuUpdateRequest.php
├── Contracts/
│   └── MenuServiceInterface.php
├── Data/
│   └── MenuData.php
├── Models/
│   └── Menu.php
└── View/
    └── Components/
        └── Menu/
            ├── MenuBar.php
            ├── MenuItem.php
            └── MenuButton.php
```

## Component Architecture

### 1. Controller Layer (`MenuController.php`)

The controller acts as the HTTP request handler, delegating business logic to the service layer.

**Key responsibilities:**
- Handle HTTP requests and responses
- Validate input data via FormRequests
- Delegate operations to MenuService
- Return appropriate HTTP responses

**Implementation details:**
```php
class MenuController extends Controller
{
    public function __construct(
        protected MenuServiceInterface $menuService
    ) {}
    
    // CRUD operations delegated to service
}
```

### 2. Service Layer (`MenuService.php`)

Implements the business logic for menu operations.

**Key features:**
- Implements `MenuServiceInterface` contract
- Handles data transformation via DTOs
- Manages database transactions
- Provides error handling and logging

**Core methods:**
- `getAllMenus()`: Retrieve all menus with pagination
- `getMenuById($id)`: Get specific menu details
- `createMenu(MenuData $data)`: Create new menu
- `updateMenu($id, MenuData $data)`: Update existing menu
- `deleteMenu($id)`: Remove menu from system
- `getMenuBySlug($slug)`: Retrieve menu by URL slug

### 3. Data Transfer Objects (`MenuData.php`)

Provides type-safe data handling between layers.

```php
class MenuData extends Data
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
        public array $items = [],
        public bool $is_active = true,
        public ?int $order = 0
    ) {}
}
```

### 4. Database Model (`Menu.php`)

Eloquent model representing the menus table.

**Schema:**
```sql
CREATE TABLE menus (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    items JSON NOT NULL DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    order INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active_order (is_active, order)
);
```

**Model features:**
- JSON casting for items array
- Automatic slug generation
- Scope queries for active menus
- Relationship definitions

## View Components

### MenuBar Component

Main navigation container component.

```php
class MenuBar extends Component
{
    public function __construct(
        public string $menuSlug,
        public string $class = '',
        public array $attributes = []
    ) {}
    
    public function render()
    {
        $menu = app(MenuServiceInterface::class)
            ->getMenuBySlug($this->menuSlug);
            
        return view('components.menu.menu-bar', [
            'menu' => $menu
        ]);
    }
}
```

**Blade usage:**
```blade
<x-menu-bar menu-slug="main-navigation" class="navbar" />
```

### MenuItem Component

Individual menu item renderer with support for nested items.

**Features:**
- Recursive rendering for submenus
- Active state detection
- Icon support
- Custom attributes

```blade
<x-menu-item 
    :item="$menuItem" 
    :level="0"
    class="nav-item"
/>
```

### MenuButton Component

Dropdown/hamburger menu trigger button.

```blade
<x-menu-button 
    target="mobile-menu"
    class="lg:hidden"
/>
```

## Service Provider Configuration

```php
// AppServiceProvider.php
public function register()
{
    $this->app->bind(
        MenuServiceInterface::class,
        MenuService::class
    );
}
```

## Caching Strategy

The menu system implements caching for improved performance:

```php
class MenuService implements MenuServiceInterface
{
    private function getCacheKey(string $identifier): string
    {
        return "menu.{$identifier}";
    }
    
    public function getMenuBySlug(string $slug): ?Menu
    {
        return Cache::remember(
            $this->getCacheKey($slug),
            3600, // 1 hour TTL
            fn() => Menu::where('slug', $slug)
                ->where('is_active', true)
                ->first()
        );
    }
    
    private function clearMenuCache(Menu $menu): void
    {
        Cache::forget($this->getCacheKey($menu->slug));
        Cache::forget($this->getCacheKey($menu->id));
    }
}
```

## Menu Items Structure

Menu items are stored as JSON with the following structure:

```json
{
    "items": [
        {
            "id": "unique-id",
            "label": "Home",
            "url": "/",
            "icon": "home",
            "target": "_self",
            "attributes": {
                "class": "nav-link"
            },
            "children": []
        },
        {
            "id": "products",
            "label": "Products",
            "url": "/products",
            "children": [
                {
                    "id": "category-1",
                    "label": "Category 1",
                    "url": "/products/category-1"
                }
            ]
        }
    ]
}
```

## Error Handling

The service implements comprehensive error handling:

```php
public function createMenu(MenuData $data): Menu
{
    try {
        DB::beginTransaction();
        
        $menu = Menu::create($data->toArray());
        
        DB::commit();
        return $menu;
        
    } catch (QueryException $e) {
        DB::rollBack();
        
        if ($e->errorInfo[1] === 1062) { // Duplicate entry
            throw new DuplicateMenuException(
                "Menu with slug '{$data->slug}' already exists"
            );
        }
        
        throw new MenuCreationException(
            "Failed to create menu: " . $e->getMessage()
        );
    }
}
```

## Testing Strategy

### Unit Tests

```php
class MenuServiceTest extends TestCase
{
    public function test_can_create_menu_with_valid_data()
    {
        $service = app(MenuServiceInterface::class);
        $data = new MenuData(
            name: 'Test Menu',
            slug: 'test-menu',
            description: 'Test description'
        );
        
        $menu = $service->createMenu($data);
        
        $this->assertInstanceOf(Menu::class, $menu);
        $this->assertEquals('test-menu', $menu->slug);
    }
}
```

### Feature Tests

```php
class MenuControllerTest extends TestCase
{
    public function test_can_retrieve_menu_by_slug()
    {
        $menu = Menu::factory()->create([
            'slug' => 'main-menu'
        ]);
        
        $response = $this->get('/api/menus/main-menu');
        
        $response->assertOk()
            ->assertJsonPath('data.slug', 'main-menu');
    }
}
```

## Performance Considerations

1. **Query Optimization**
   - Indexed slug and is_active columns
   - Eager loading for nested relationships
   - Query result caching

2. **Caching Strategy**
   - 1-hour TTL for menu data
   - Cache invalidation on updates
   - Tagged cache for bulk operations

3. **JSON Column Performance**
   - Indexed virtual columns for frequent queries
   - Limit nesting depth to 3 levels
   - Consider separate table for large item sets (>100 items)

## Security Considerations

1. **Input Validation**
   - Sanitize all user inputs
   - Validate URL formats in menu items
   - Prevent XSS in item labels

2. **Authorization**
   - Role-based access control for menu management
   - Separate permissions for create/update/delete

3. **Data Protection**
   - Escape output in Blade components
   - Validate JSON structure before storage
   - Audit logging for menu changes

## Future Enhancements

1. **Menu Builder UI**
   - Drag-and-drop interface
   - Visual preview
   - Bulk operations

2. **Advanced Features**
   - Menu versioning
   - A/B testing support
   - Conditional visibility rules

3. **Performance Improvements**
   - Redis caching implementation
   - CDN integration for static menus
   - Database sharding for multi-tenant systems