# Menu API Documentation

## Base URL
```
https://your-domain.com/api
```

## Authentication

All API endpoints require authentication using Bearer tokens.

```http
Authorization: Bearer {token}
```

## Endpoints

### 1. List All Menus

Retrieve a paginated list of all menus.

```http
GET /menus
```

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| page | integer | 1 | Page number |
| per_page | integer | 15 | Items per page (max: 100) |
| active | boolean | - | Filter by active status |
| search | string | - | Search by name or description |
| sort | string | order | Sort field (name, slug, created_at, order) |
| direction | string | asc | Sort direction (asc, desc) |

#### Response

```json
{
    "data": [
        {
            "id": 1,
            "name": "Main Navigation",
            "slug": "main-navigation",
            "description": "Primary site navigation",
            "items": [
                {
                    "id": "home",
                    "label": "Home",
                    "url": "/",
                    "icon": "home",
                    "target": "_self",
                    "attributes": {},
                    "children": []
                }
            ],
            "is_active": true,
            "order": 0,
            "created_at": "2024-01-15T10:30:00Z",
            "updated_at": "2024-01-15T10:30:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "per_page": 15,
        "to": 15,
        "total": 72
    },
    "links": {
        "first": "/api/menus?page=1",
        "last": "/api/menus?page=5",
        "prev": null,
        "next": "/api/menus?page=2"
    }
}
```

#### Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 401 | Unauthorized |
| 422 | Invalid query parameters |

---

### 2. Get Menu by ID

Retrieve a specific menu by its ID.

```http
GET /menus/{id}
```

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Menu ID |

#### Response

```json
{
    "data": {
        "id": 1,
        "name": "Main Navigation",
        "slug": "main-navigation",
        "description": "Primary site navigation",
        "items": [
            {
                "id": "home",
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
                "icon": "box",
                "children": [
                    {
                        "id": "category-1",
                        "label": "Electronics",
                        "url": "/products/electronics"
                    },
                    {
                        "id": "category-2",
                        "label": "Clothing",
                        "url": "/products/clothing"
                    }
                ]
            }
        ],
        "is_active": true,
        "order": 0,
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:30:00Z"
    }
}
```

#### Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 401 | Unauthorized |
| 404 | Menu not found |

---

### 3. Get Menu by Slug

Retrieve a specific menu by its slug.

```http
GET /menus/slug/{slug}
```

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| slug | string | Yes | Menu slug |

#### Response

Same as "Get Menu by ID"

#### Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 401 | Unauthorized |
| 404 | Menu not found |

---

### 4. Create Menu

Create a new menu.

```http
POST /menus
```

#### Request Body

```json
{
    "name": "Footer Menu",
    "slug": "footer-menu",
    "description": "Footer navigation links",
    "items": [
        {
            "id": "about",
            "label": "About Us",
            "url": "/about",
            "icon": "info",
            "target": "_self",
            "attributes": {},
            "children": []
        },
        {
            "id": "contact",
            "label": "Contact",
            "url": "/contact",
            "icon": "mail",
            "target": "_self",
            "attributes": {},
            "children": []
        }
    ],
    "is_active": true,
    "order": 1
}
```

#### Request Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | Menu name (max: 255) |
| slug | string | Yes | URL-friendly identifier (unique) |
| description | string | No | Menu description |
| items | array | No | Menu items structure |
| is_active | boolean | No | Active status (default: true) |
| order | integer | No | Display order (default: 0) |

#### Menu Item Structure

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| id | string | Yes | Unique identifier |
| label | string | Yes | Display text |
| url | string | Yes | Link URL |
| icon | string | No | Icon identifier |
| target | string | No | Link target (_self, _blank, etc.) |
| attributes | object | No | HTML attributes |
| children | array | No | Nested menu items |

#### Response

```json
{
    "data": {
        "id": 2,
        "name": "Footer Menu",
        "slug": "footer-menu",
        "description": "Footer navigation links",
        "items": [...],
        "is_active": true,
        "order": 1,
        "created_at": "2024-01-20T14:25:00Z",
        "updated_at": "2024-01-20T14:25:00Z"
    },
    "message": "Menu created successfully"
}
```

#### Status Codes

| Code | Description |
|------|-------------|
| 201 | Created successfully |
| 400 | Bad request |
| 401 | Unauthorized |
| 409 | Duplicate slug |
| 422 | Validation error |

#### Validation Rules

- `name`: required, string, max:255
- `slug`: required, string, max:255, unique, regex:/^[a-z0-9-]+$/
- `description`: nullable, string, max:1000
- `items`: nullable, array, valid JSON structure
- `is_active`: boolean
- `order`: integer, min:0

---

### 5. Update Menu

Update an existing menu.

```http
PUT /menus/{id}
```

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Menu ID |

#### Request Body

```json
{
    "name": "Updated Footer Menu",
    "description": "Updated footer navigation",
    "items": [
        {
            "id": "privacy",
            "label": "Privacy Policy",
            "url": "/privacy",
            "icon": "shield",
            "target": "_self",
            "attributes": {},
            "children": []
        }
    ],
    "is_active": true,
    "order": 2
}
```

#### Request Fields

All fields are optional. Only provided fields will be updated.

| Field | Type | Description |
|-------|------|-------------|
| name | string | Menu name (max: 255) |
| slug | string | URL-friendly identifier (unique) |
| description | string | Menu description |
| items | array | Menu items structure |
| is_active | boolean | Active status |
| order | integer | Display order |

#### Response

```json
{
    "data": {
        "id": 2,
        "name": "Updated Footer Menu",
        "slug": "footer-menu",
        "description": "Updated footer navigation",
        "items": [...],
        "is_active": true,
        "order": 2,
        "created_at": "2024-01-20T14:25:00Z",
        "updated_at": "2024-01-20T15:30:00Z"
    },
    "message": "Menu updated successfully"
}
```

#### Status Codes

| Code | Description |
|------|-------------|
| 200 | Updated successfully |
| 400 | Bad request |
| 401 | Unauthorized |
| 404 | Menu not found |
| 409 | Duplicate slug |
| 422 | Validation error |

---

### 6. Partial Update Menu

Partially update specific fields of a menu.

```http
PATCH /menus/{id}
```

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Menu ID |

#### Request Body

```json
{
    "is_active": false
}
```

#### Response

Same as "Update Menu"

#### Status Codes

Same as "Update Menu"

---

### 7. Delete Menu

Delete a menu permanently.

```http
DELETE /menus/{id}
```

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Menu ID |

#### Response

```json
{
    "message": "Menu deleted successfully"
}
```

#### Status Codes

| Code | Description |
|------|-------------|
| 200 | Deleted successfully |
| 401 | Unauthorized |
| 404 | Menu not found |
| 409 | Cannot delete (menu in use) |

---

### 8. Bulk Operations

#### Bulk Update

Update multiple menus at once.

```http
POST /menus/bulk-update
```

#### Request Body

```json
{
    "menus": [
        {
            "id": 1,
            "is_active": true,
            "order": 0
        },
        {
            "id": 2,
            "is_active": false,
            "order": 1
        }
    ]
}
```

#### Response

```json
{
    "data": {
        "updated": 2,
        "failed": 0
    },
    "message": "Bulk update completed"
}
```

#### Bulk Delete

Delete multiple menus at once.

```http
POST /menus/bulk-delete
```

#### Request Body

```json
{
    "ids": [3, 4, 5]
}
```

#### Response

```json
{
    "data": {
        "deleted": 3,
        "failed": 0
    },
    "message": "Bulk delete completed"
}
```

---

### 9. Reorder Menus

Update the display order of multiple menus.

```http
POST /menus/reorder
```

#### Request Body

```json
{
    "orders": [
        {"id": 1, "order": 0},
        {"id": 2, "order": 1},
        {"id": 3, "order": 2}
    ]
}
```

#### Response

```json
{
    "message": "Menu order updated successfully"
}
```

---

## Error Responses

All error responses follow this format:

```json
{
    "message": "Error description",
    "errors": {
        "field_name": [
            "Validation error message"
        ]
    }
}
```

### Common Error Codes

| Code | Description |
|------|-------------|
| 400 | Bad Request - Invalid request format |
| 401 | Unauthorized - Missing or invalid token |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource doesn't exist |
| 409 | Conflict - Duplicate or constraint violation |
| 422 | Unprocessable Entity - Validation failed |
| 429 | Too Many Requests - Rate limit exceeded |
| 500 | Internal Server Error |

---

## Rate Limiting

API requests are limited to:
- 60 requests per minute for authenticated users
- 30 requests per minute for unauthenticated users

Rate limit headers:
```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 58
X-RateLimit-Reset: 1642435200
```

---

## Webhooks

Configure webhooks to receive notifications for menu events.

### Available Events

| Event | Description |
|-------|-------------|
| menu.created | New menu created |
| menu.updated | Menu updated |
| menu.deleted | Menu deleted |
| menu.activated | Menu activated |
| menu.deactivated | Menu deactivated |

### Webhook Payload

```json
{
    "event": "menu.updated",
    "timestamp": "2024-01-20T15:30:00Z",
    "data": {
        "id": 1,
        "name": "Main Navigation",
        "slug": "main-navigation",
        "changes": {
            "items": {
                "old": [...],
                "new": [...]
            }
        }
    }
}
```

---

## Code Examples

### PHP (Laravel)

```php
use Illuminate\Support\Facades\Http;

// Get all menus
$response = Http::withToken($token)
    ->get('https://api.example.com/menus');

$menus = $response->json('data');

// Create a menu
$response = Http::withToken($token)
    ->post('https://api.example.com/menus', [
        'name' => 'New Menu',
        'slug' => 'new-menu',
        'items' => [
            [
                'id' => 'item-1',
                'label' => 'Home',
                'url' => '/'
            ]
        ]
    ]);
```

### JavaScript (Axios)

```javascript
import axios from 'axios';

// Configure axios
const api = axios.create({
    baseURL: 'https://api.example.com',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});

// Get menu by slug
async function getMenu(slug) {
    try {
        const response = await api.get(`/menus/slug/${slug}`);
        return response.data.data;
    } catch (error) {
        console.error('Error fetching menu:', error);
    }
}

// Update menu
async function updateMenu(id, data) {
    try {
        const response = await api.put(`/menus/${id}`, data);
        return response.data.data;
    } catch (error) {
        console.error('Error updating menu:', error);
    }
}
```

### cURL

```bash
# Get all active menus
curl -X GET "https://api.example.com/menus?active=true" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Create a new menu
curl -X POST "https://api.example.com/menus" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Menu",
    "slug": "test-menu",
    "items": []
  }'

# Delete a menu
curl -X DELETE "https://api.example.com/menus/1" \
  -H "Authorization: Bearer {token}"
```

---

## SDK Support

Official SDKs are available for:
- PHP (Composer package)
- JavaScript/TypeScript (npm package)
- Python (pip package)
- Ruby (gem)

Install via package managers:

```bash
# PHP
composer require your-org/menu-sdk

# JavaScript/TypeScript
npm install @your-org/menu-sdk

# Python
pip install your-org-menu-sdk

# Ruby
gem install your-org-menu-sdk
```