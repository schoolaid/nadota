# Nadota Filters and API Documentation

## Table of Contents
- [API Endpoints](#api-endpoints)
- [Filters System](#filters-system)
- [Field Options API](#field-options-api)
- [Request/Response Examples](#requestresponse-examples)

## API Endpoints

### Base Configuration
The API prefix is configurable in `config/nadota.php`:
```php
'api' => [
    'prefix' => 'nadota-api'
],
```

### Menu Endpoints

#### Get Menu Structure
```
GET /nadota-api/menu
```
Returns the complete menu structure with sections and items based on user permissions.

**Response:**
```json
[
  {
    "label": "Users",
    "icon": "Users",
    "children": [...],
    "order": 1
  }
]
```

### Resource Endpoints

All resource endpoints follow the pattern: `/nadota-api/{resourceKey}/resource/...`

#### Resource Information
```
GET /nadota-api/{resourceKey}/resource/info
```
Returns metadata about the resource.

#### Resource Fields
```
GET /nadota-api/{resourceKey}/resource/fields
```
Returns all fields configuration for the resource.

#### Resource Filters
```
GET /nadota-api/{resourceKey}/resource/filters
```
Returns available filters for the resource.

#### Resource Actions
```
GET /nadota-api/{resourceKey}/resource/actions
```
Returns available bulk actions for the resource.

#### Resource Lens
```
GET /nadota-api/{resourceKey}/resource/lens
```
Returns available lenses (filtered views) for the resource.

#### Resource Data (Compact)
```
GET /nadota-api/{resourceKey}/resource/data
```
Returns compact data representation for dropdowns/selects.

### CRUD Operations

#### List Resources (Index)
```
GET /nadota-api/{resourceKey}/resource
```
**Query Parameters:**
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15)
- `sort` - Sort field
- `direction` - Sort direction (asc/desc)
- `filters` - JSON encoded filters
- `search` - Search query

#### Create Form
```
GET /nadota-api/{resourceKey}/resource/create
```
Returns field configuration for creation form.

#### Store Resource
```
POST /nadota-api/{resourceKey}/resource
```
Creates a new resource.

#### Show Resource
```
GET /nadota-api/{resourceKey}/resource/{id}
```
Returns single resource details.

#### Edit Form
```
GET /nadota-api/{resourceKey}/resource/{id}/edit
```
Returns field configuration with current values for editing.

#### Update Resource
```
PUT/PATCH /nadota-api/{resourceKey}/resource/{id}
```
Updates an existing resource.

#### Delete Resource
```
DELETE /nadota-api/{resourceKey}/resource/{id}
```
Deletes a resource.

### Field Options API

#### Get Field Options
```
GET /nadota-api/{resourceKey}/resource/field/{fieldName}/options
```
Returns options for relationship fields (BelongsTo, etc.)

**Query Parameters:**
- `search` - Search term to filter options
- `limit` - Maximum number of results (default: 100)

**Response:**
```json
{
  "success": true,
  "options": [
    {"value": 1, "label": "Option 1"},
    {"value": 2, "label": "Option 2"}
  ],
  "meta": {
    "total": 2,
    "search": "",
    "field_type": "belongsTo"
  }
}
```

#### Get Paginated Field Options
```
GET /nadota-api/{resourceKey}/resource/field/{fieldName}/options/paginated
```
Returns paginated options for large datasets.

**Query Parameters:**
- `search` - Search term
- `per_page` - Items per page (default: 15)
- `page` - Current page

## Filters System

### Creating a Filter

Filters extend the base `Filter` class:

```php
use SchoolAid\Nadota\Http\Filters\Filter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class StatusFilter extends Filter
{
    public string $name = 'Status';
    public string $component = 'FilterSelect';
    
    public function apply(NadotaRequest $request, $query, $value)
    {
        return $query->where('status', $value);
    }
    
    public function resources(NadotaRequest $request): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'pending' => 'Pending'
        ];
    }
}
```

### Filter Types

#### DefaultFilter
Basic filter with simple equality check:
```php
use SchoolAid\Nadota\Http\Filters\DefaultFilter;

DefaultFilter::make('Status', 'status')
    ->options([
        'active' => 'Active',
        'inactive' => 'Inactive'
    ])
```

#### RangeFilter
For filtering between two values:
```php
use SchoolAid\Nadota\Http\Filters\RangeFilter;

RangeFilter::make('Price Range', 'price')
    ->min(0)
    ->max(1000)
```

### Using Filters in Resources

Add filters to your resource class:

```php
public function filters(NadotaRequest $request): array
{
    return [
        StatusFilter::make(),
        DefaultFilter::make('Category', 'category_id')
            ->options($this->getCategories()),
        RangeFilter::make('Created Date', 'created_at')
            ->dateRange()
    ];
}
```

### Filter Components

Available filter components:
- `FilterText` - Text input filter
- `FilterSelect` - Dropdown select filter
- `FilterCheckbox` - Checkbox filter
- `FilterDate` - Date picker filter
- `FilterRange` - Range slider filter
- `FilterBoolean` - Yes/No toggle filter

### Filter Response Format

Filters endpoint returns:
```json
[
  {
    "id": "status-filter",
    "key": "status",
    "name": "Status",
    "component": "FilterSelect",
    "options": [
      {"label": "Active", "value": "active"},
      {"label": "Inactive", "value": "inactive"}
    ],
    "value": "",
    "props": {}
  }
]
```

## Request/Response Examples

### Example: Fetching Filtered Resources

**Request:**
```http
GET /nadota-api/users/resource?page=1&per_page=20&filters={"status":"active"}&sort=created_at&direction=desc
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "status": "active",
      "created_at": "2024-01-15 10:30:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100,
    "from": 1,
    "to": 20
  }
}
```

### Example: Creating a Resource

**Request:**
```http
POST /nadota-api/posts/resource
Content-Type: application/json

{
  "title": "New Post",
  "content": "Post content here",
  "category_id": 5,
  "status": "draft"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "title": "New Post",
    "content": "Post content here",
    "category_id": 5,
    "status": "draft",
    "created_at": "2024-01-20 14:30:00"
  },
  "message": "Resource created successfully"
}
```

### Example: Applying Multiple Filters

**Request:**
```http
GET /nadota-api/products/resource?filters={"category":"electronics","price_range":{"min":100,"max":500},"in_stock":true}
```

The filters are processed through the pipeline:
1. BuildQuery
2. ApplyFilters (applies each filter's `apply()` method)
3. ApplyFields
4. ApplySorting
5. PaginateAndTransform

## Field Filtering

Fields can also be made filterable directly:

```php
Text::make('Name', 'name')
    ->sortable()
    ->searchable()
    ->filterable() // Makes this field available as a filter
```

When a field is filterable, it automatically appears in the filters list with appropriate component based on field type.

## Authorization

All API endpoints respect Laravel policies:
- `viewAny` - List resources
- `view` - Show single resource
- `create` - Create new resource
- `update` - Update existing resource
- `delete` - Delete resource

Authorization is handled by `ResourceAuthorizationService` and checked before any operation.

## Error Responses

Standard error response format:
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

HTTP Status Codes:
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error