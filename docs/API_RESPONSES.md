# Nadota API Response Documentation

## File Fields API Responses

This document describes the JSON structure returned by the API for file-related fields.

---

## File Field

The `File` field returns comprehensive information about uploaded files.

### Response Structure

```json
{
  "name": "Document",
  "label": "Document",
  "id": "Document",
  "attribute": "document_path",
  "placeholder": "Document",
  "type": "file",
  "component": "FieldFile",
  "key": "document",
  "readonly": false,
  "disabled": false,
  "required": true,
  "helpText": "Upload your document (PDF, DOC, DOCX)",
  "sortable": false,
  "searchable": false,
  "filterable": false,
  "showOnIndex": true,
  "showOnDetail": true,
  "showOnCreation": true,
  "showOnUpdate": true,
  "props": {
    "acceptedTypes": ["pdf", "doc", "docx"],
    "maxSize": 10485760,
    "maxSizeMB": 10,
    "disk": "public",
    "path": "documents",
    "downloadable": true,
    "width": null,
    "tabSize": 4,
    "maxHeight": null,
    "minHeight": null
  },
  "rules": ["required", "file", "max:10240", "mimes:pdf,doc,vnd.openxmlformats-officedocument.wordprocessingml.document"],
  "optionsUrl": null,
  "value": {
    "path": "documents/2024/01/document-abc123.pdf"
  }
}
```

### Value Structure (When File Exists)

When a file has been uploaded, the `value` property contains:

```json
{
  "value": {
    "path": "documents/2024/01/document-abc123.pdf"
  }
}
```

**Note**: Additional properties like `name`, `url`, `downloadable`, `downloadUrl`, `size`, and `mimeType` are currently commented out in the implementation but may be enabled in future versions.

### Value Structure (No File)

When no file has been uploaded:

```json
{
  "value": null
}
```

### Props Breakdown

| Property | Type | Description | Example |
|----------|------|-------------|---------|
| `acceptedTypes` | array | List of accepted file extensions or MIME types | `["pdf", "doc", "docx"]` |
| `maxSize` | integer\|null | Maximum file size in bytes | `10485760` |
| `maxSizeMB` | float\|null | Maximum file size in megabytes (calculated) | `10` |
| `disk` | string\|null | Laravel storage disk to use | `"public"` |
| `path` | string\|null | Storage path within the disk | `"documents"` |
| `downloadable` | boolean | Whether file can be downloaded | `true` |
| `width` | string\|null | Field width in layout | `"1/2"` |
| `tabSize` | integer | Tab size (inherited from base) | `4` |
| `maxHeight` | integer\|null | Maximum field height in pixels | `null` |
| `minHeight` | integer\|null | Minimum field height in pixels | `null` |

### Validation Rules

The `rules` array contains Laravel validation rules:

```json
{
  "rules": [
    "required",              // Field is required
    "file",                  // Must be a file upload
    "max:10240",            // Max size in KB (10MB = 10240KB)
    "mimes:pdf,doc,..."     // Accepted MIME types
  ]
}
```

---

## Image Field

The `Image` field extends `File` with image-specific features and returns additional image metadata.

### Response Structure

```json
{
  "name": "Avatar",
  "label": "Avatar",
  "id": "Avatar",
  "attribute": "avatar",
  "placeholder": "Avatar",
  "type": "image",
  "component": "FieldImage",
  "key": "avatar",
  "readonly": false,
  "disabled": false,
  "required": false,
  "helpText": "Upload a profile picture",
  "sortable": false,
  "searchable": false,
  "filterable": false,
  "showOnIndex": true,
  "showOnDetail": true,
  "showOnCreation": true,
  "showOnUpdate": true,
  "props": {
    "acceptedTypes": ["jpg", "jpeg", "png", "gif", "webp"],
    "maxSize": 5242880,
    "maxSizeMB": 5,
    "disk": "public",
    "path": "avatars",
    "downloadable": true,
    "width": null,
    "tabSize": 4,
    "maxHeight": 300,
    "minHeight": null,
    "maxImageWidth": 1920,
    "maxImageHeight": 1080,
    "showPreview": true,
    "thumbnailSizes": {
      "small": {"width": 150, "height": 150},
      "medium": {"width": 500, "height": 500}
    },
    "alt": "User Avatar",
    "isImage": true,
    "rounded": false,
    "square": false,
    "previewSize": "medium",
    "lazy": true,
    "placeholder": "/images/avatar-placeholder.png",
    "convertFormat": null,
    "compressionQuality": 85
  },
  "rules": ["image", "max:5120", "mimes:jpeg,png,gif,webp", "dimensions:max_width=1920,max_height=1080"],
  "optionsUrl": null,
  "value": {
    "path": "avatars/2024/01/user-avatar-xyz789.jpg",
    "isImage": true,
    "showPreview": true,
    "alt": "User Avatar",
    "thumbnails": {
      "small": {
        "path": "avatars/thumbs/user-avatar-xyz789_small.jpg",
        "url": "https://example.com/storage/avatars/thumbs/user-avatar-xyz789_small.jpg",
        "width": 150,
        "height": 150
      },
      "medium": {
        "path": "avatars/thumbs/user-avatar-xyz789_medium.jpg",
        "url": "https://example.com/storage/avatars/thumbs/user-avatar-xyz789_medium.jpg",
        "width": 500,
        "height": 500
      }
    },
    "dimensions": {
      "width": 1200,
      "height": 800,
      "type": 2,
      "ratio": 1.5
    }
  }
}
```

### Value Structure (When Image Exists)

```json
{
  "value": {
    "path": "avatars/2024/01/user-avatar-xyz789.jpg",
    "isImage": true,
    "showPreview": true,
    "alt": "User Avatar",
    "thumbnails": {
      "small": {
        "path": "avatars/thumbs/user-avatar-xyz789_small.jpg",
        "url": "https://example.com/storage/avatars/thumbs/user-avatar-xyz789_small.jpg",
        "width": 150,
        "height": 150
      }
    },
    "dimensions": {
      "width": 1200,
      "height": 800,
      "type": 2,
      "ratio": 1.5
    }
  }
}
```

### Value Structure (No Image)

```json
{
  "value": null
}
```

### Image-Specific Props

| Property | Type | Description | Example |
|----------|------|-------------|---------|
| `maxImageWidth` | integer\|null | Maximum image width in pixels | `1920` |
| `maxImageHeight` | integer\|null | Maximum image height in pixels | `1080` |
| `showPreview` | boolean | Show image preview | `true` |
| `thumbnailSizes` | object | Thumbnail configurations | `{"small": {"width": 150}}` |
| `alt` | string\|null | Alt text for accessibility | `"User Avatar"` |
| `isImage` | boolean | Identifies as image field | `true` |
| `rounded` | boolean | Display with rounded corners | `false` |
| `square` | boolean | Force square aspect ratio | `false` |
| `previewSize` | string\|null | Preview size (small/medium/large) | `"medium"` |
| `lazy` | boolean | Enable lazy loading | `true` |
| `placeholder` | string\|null | Placeholder image URL | `"/images/placeholder.png"` |
| `convertFormat` | string\|null | Convert to format on upload | `"webp"` |
| `compressionQuality` | integer\|null | Image quality (1-100) | `85` |

### Image Value Properties

| Property | Type | Description | Example |
|----------|------|-------------|---------|
| `path` | string | Storage path of the image | `"avatars/2024/01/image.jpg"` |
| `isImage` | boolean | Confirms this is an image | `true` |
| `showPreview` | boolean | Whether to show preview | `true` |
| `alt` | string | Alt text for the image | `"User Avatar"` |
| `thumbnails` | object | Generated thumbnail information | See structure above |
| `dimensions` | object\|null | Image dimensions and metadata | See below |

### Dimensions Object

```json
{
  "dimensions": {
    "width": 1200,        // Width in pixels
    "height": 800,        // Height in pixels
    "type": 2,            // PHP image type constant (2 = JPEG)
    "ratio": 1.5          // Aspect ratio (width/height)
  }
}
```

### Thumbnail Structure

Each thumbnail in the `thumbnails` object contains:

```json
{
  "small": {
    "path": "avatars/thumbs/image_small.jpg",
    "url": "https://example.com/storage/avatars/thumbs/image_small.jpg",
    "width": 150,
    "height": 150
  }
}
```

### Image Validation Rules

```json
{
  "rules": [
    "image",                                    // Must be an image
    "max:5120",                                // Max 5MB (5120KB)
    "mimes:jpeg,png,gif,webp",                // Accepted formats
    "dimensions:max_width=1920,max_height=1080" // Max dimensions
  ]
}
```

---

## Upload Process

### Request Format (Multipart)

When uploading files, the frontend should send a multipart/form-data request:

```http
POST /api/resources/users
Content-Type: multipart/form-data

------WebKitFormBoundary
Content-Disposition: form-data; name="name"

John Doe
------WebKitFormBoundary
Content-Disposition: form-data; name="avatar"; filename="profile.jpg"
Content-Type: image/jpeg

[binary image data]
------WebKitFormBoundary--
```

### Upload Response

On successful upload:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "avatar": "avatars/2024/01/profile-abc123.jpg"
  }
}
```

### Error Response

On validation failure:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "avatar": [
      "The avatar must be an image.",
      "The avatar may not be greater than 5120 kilobytes."
    ]
  }
}
```

---

## Frontend Implementation Notes

### Displaying Files

```javascript
// Check if file exists
if (field.value && field.value.path) {
  // File exists, show download link or preview
  const filePath = field.value.path;
}
```

### Displaying Images

```javascript
// For image fields with preview
if (field.type === 'image' && field.value) {
  if (field.props.showPreview && field.value.isImage) {
    // Show image preview
    const imageSrc = field.value.url || `/storage/${field.value.path}`;

    // Use thumbnail if available
    if (field.value.thumbnails && field.value.thumbnails.medium) {
      const thumbnailSrc = field.value.thumbnails.medium.url;
    }
  }
}
```

### Handling Lazy Loading

```javascript
if (field.props.lazy) {
  // Implement lazy loading with placeholder
  const placeholder = field.props.placeholder || '/images/default-placeholder.png';
  // Use Intersection Observer or lazy loading library
}
```

### File Size Validation (Client-side)

```javascript
const maxSize = field.props.maxSize; // in bytes
const file = input.files[0];

if (file.size > maxSize) {
  const maxSizeMB = field.props.maxSizeMB;
  alert(`File size must be less than ${maxSizeMB}MB`);
}
```

---

## Best Practices

1. **Always validate file size on client-side** before uploading to improve UX
2. **Use thumbnails for image previews** in lists to improve performance
3. **Implement lazy loading** for images in long lists
4. **Show upload progress** for better user experience
5. **Handle upload errors gracefully** with clear error messages
6. **Use placeholder images** while images are loading
7. **Respect the `downloadable` flag** when showing download options
8. **Use the provided `alt` text** for accessibility

---

## Security Considerations

1. **File Type Validation**: Always respect the `acceptedTypes` configuration
2. **Size Limits**: Enforce both client and server-side size validation
3. **Path Traversal**: Never construct file paths from user input
4. **Direct Access**: Use the storage URLs provided by the API
5. **Sanitization**: File names are sanitized server-side before storage