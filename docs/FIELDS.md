# Nadota Fields Documentation

## Overview
Fields are the building blocks of resources in Nadota. They define how data is displayed, edited, validated, and stored.

## Table of Contents
- [Base Field Properties](#base-field-properties)
- [Text Fields](#text-fields)
- [Numeric Fields](#numeric-fields)
- [Date and Time](#date-and-time)
- [Boolean Fields](#boolean-fields)
- [Selection Fields](#selection-fields)
- [File and Media](#file-and-media)
- [Code and JSON](#code-and-json)
- [Relationship Fields](#relationship-fields)
- [Custom Components](#custom-components)
- [Computed Fields](#computed-fields)

---

## Base Field Properties

All fields inherit these common properties and methods:

### Layout & Sizing
```php
Text::make('Name', 'name')
    ->width('1/2')           // Field width: full, 1/2, 1/3, 1/4, 2/3, 3/4
    ->fullWidth()            // Convenience method for width('full')
    ->halfWidth()            // Convenience method for width('1/2')
    ->maxHeight(300)         // Maximum height in pixels
    ->minHeight(100)         // Minimum height in pixels
    ->tabSize(4)             // Tab size for code/text fields
```

### Visibility Control
```php
Text::make('Name', 'name')
    ->showOnIndex()          // Show on index listing
    ->showOnDetail()         // Show on detail view
    ->showOnCreation()       // Show on create form
    ->showOnUpdate()         // Show on update form
    ->hideFromIndex()        // Hide from index
    ->hideFromDetail()       // Hide from detail
    ->hideFromCreation()     // Hide from create form
    ->hideFromUpdate()       // Hide from update form
    ->onlyOnIndex()          // Show only on index
    ->onlyOnDetail()         // Show only on detail
    ->onlyOnForms()          // Show only on create/update
    ->exceptOnForms()        // Hide from create/update
```

### Validation
```php
Text::make('Email', 'email')
    ->required()                    // Field is required
    ->rules('email', 'max:255')     // Add validation rules
    ->creationRules('unique:users') // Rules only for creation
    ->updateRules('unique:users,email,{{resourceId}}') // Rules only for update
```

### Field State
```php
Text::make('Name', 'name')
    ->readonly()             // Field is read-only
    ->disabled()             // Field is disabled
    ->help('Enter full name') // Add help text
    ->placeholder('John Doe') // Set placeholder text
    ->default('N/A')         // Set default value
```

### Search & Filter
```php
Text::make('Name', 'name')
    ->searchable()           // Field is searchable
    ->sortable()             // Field is sortable
    ->filterable()           // Field can be filtered
```

---

## Text Fields

### Input
Basic text input field.

```php
use SchoolAid\Nadota\Http\Fields\Input;

Input::make('Name', 'name')
    ->placeholder('Enter name')
    ->rules('required', 'max:255')
    ->help('Full legal name')
```

### Email
Email input with validation.

```php
use SchoolAid\Nadota\Http\Fields\Email;

Email::make('Email Address', 'email')
    ->rules('required', 'email', 'unique:users')
    ->placeholder('user@example.com')
```

### URL
URL input field.

```php
use SchoolAid\Nadota\Http\Fields\URL;

URL::make('Website', 'website')
    ->placeholder('https://example.com')
    ->rules('url')
```

### Password
Password input field.

```php
use SchoolAid\Nadota\Http\Fields\Password;

Password::make('Password', 'password')
    ->rules('required', 'min:8')
    ->help('Minimum 8 characters')
    ->creationRules('required', 'confirmed')
    ->updateRules('nullable', 'confirmed')
```

### Textarea
Multi-line text input.

```php
use SchoolAid\Nadota\Http\Fields\Textarea;

Textarea::make('Description', 'description')
    ->rows(5)                // Number of rows
    ->cols(50)               // Number of columns
    ->maxHeight(300)         // Max height in pixels
    ->rules('max:1000')
```

### Hidden
Hidden input field.

```php
use SchoolAid\Nadota\Http\Fields\Hidden;

Hidden::make('Token', 'token')
    ->default(Str::random(32))
```

---

## Numeric Fields

### Number
Numeric input field.

```php
use SchoolAid\Nadota\Http\Fields\Number;

Number::make('Price', 'price')
    ->min(0)                 // Minimum value
    ->max(1000)              // Maximum value
    ->step(0.01)             // Step increment
    ->rules('required', 'numeric', 'min:0')
```

---

## Date and Time

### DateTime
Date and time picker.

```php
use SchoolAid\Nadota\Http\Fields\DateTime;

DateTime::make('Published At', 'published_at')
    ->format('Y-m-d H:i:s')  // Display format
    ->min('2024-01-01')      // Minimum date
    ->max('2024-12-31')      // Maximum date
    ->withTime()             // Include time picker
    ->rules('required', 'date')
```

---

## Boolean Fields

### Checkbox
Single checkbox field.

```php
use SchoolAid\Nadota\Http\Fields\Checkbox;

Checkbox::make('Active', 'is_active')
    ->default(true)
    ->help('Check to activate')
```

### Toggle
Toggle switch field.

```php
use SchoolAid\Nadota\Http\Fields\Toggle;

Toggle::make('Featured', 'is_featured')
    ->trueValue('yes')       // Value when true
    ->falseValue('no')       // Value when false
    ->default(false)
```

---

## Selection Fields

### Select
Dropdown selection field.

```php
use SchoolAid\Nadota\Http\Fields\Select;

Select::make('Status', 'status')
    ->options([
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived'
    ])
    ->multiple()             // Allow multiple selection
    ->clearable()            // Allow clearing selection
    ->placeholder('Choose status')
    ->default('draft')
```

### Radio
Radio button group.

```php
use SchoolAid\Nadota\Http\Fields\Radio;

Radio::make('Gender', 'gender')
    ->options([
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other'
    ])
    ->default('other')
```

### CheckboxList
Multiple checkbox selection.

```php
use SchoolAid\Nadota\Http\Fields\CheckboxList;

CheckboxList::make('Permissions', 'permissions')
    ->options([
        'read' => 'Read',
        'write' => 'Write',
        'delete' => 'Delete'
    ])
    ->rules('required', 'array')
```

---

## File and Media

### File
File upload field.

```php
use SchoolAid\Nadota\Http\Fields\File;

File::make('Document', 'document_path')
    ->accept(['pdf', 'doc', 'docx'])  // Accepted file types
    ->maxSize(10 * 1024 * 1024)       // Max size in bytes
    ->maxSizeMB(10)                   // Alternative: max size in MB
    ->disk('public')                  // Storage disk
    ->path('documents')               // Storage path
    ->downloadable()                  // Make downloadable
    ->rules('required', 'file', 'max:10240')
```

### Image
Image upload with preview.

```php
use SchoolAid\Nadota\Http\Fields\Image;

Image::make('Avatar', 'avatar')
    ->acceptImages()                  // Accept common image formats
    ->webSafe()                       // Only web-safe formats (jpg, png, webp)
    ->maxImageDimensions(1920, 1080)  // Max dimensions
    ->imageWidth(800)                 // Max width only
    ->imageHeight(600)                // Max height only
    ->preview()                       // Show preview
    ->previewSize('medium')           // Preview size: small, medium, large
    ->circle()                        // Display as circle
    ->rounded()                       // Rounded corners
    ->squared()                       // Force square aspect ratio
    ->lazy()                          // Lazy load images
    ->placeholder('/images/placeholder.png') // Placeholder image
    ->disablePreviewOnIndex()         // No preview on index for performance
    ->thumbnails([                    // Generate thumbnails
        'small' => ['width' => 150, 'height' => 150],
        'medium' => ['width' => 500, 'height' => 500]
    ])
    ->quality(85)                     // Compression quality (1-100)
    ->convertTo('webp')               // Convert to format
    ->alt('User Avatar')              // Alt text
```

---

## Code and JSON

### Code
Code editor field with syntax highlighting.

```php
use SchoolAid\Nadota\Http\Fields\Code;

Code::make('Script', 'script')
    ->language('javascript')         // Set language
    ->php()                          // Shortcut for PHP
    ->javascript()                   // Shortcut for JavaScript
    ->python()                       // Shortcut for Python
    ->html()                         // Shortcut for HTML
    ->css()                          // Shortcut for CSS
    ->sql()                          // Shortcut for SQL
    ->json()                         // Shortcut for JSON
    ->yaml()                         // Shortcut for YAML
    ->xml()                          // Shortcut for XML
    ->markdown()                     // Shortcut for Markdown
    ->shell()                        // Shortcut for Shell
    ->theme('dark')                  // Editor theme
    ->showLineNumbers()              // Show line numbers
    ->syntaxHighlighting()           // Enable syntax highlighting
    ->wordWrap()                     // Enable word wrap
    ->editable()                     // Make editable
    ->tabSize(2)                     // Tab size
    ->maxHeight(500)                 // Max editor height
    ->minHeight(200)                 // Min editor height
    ->hideFromIndex()                // Auto-hidden from index
```

### Json
JSON editor field.

```php
use SchoolAid\Nadota\Http\Fields\Json;

Json::make('Settings', 'settings')
    ->prettyPrint()                  // Format JSON
    ->showLineNumbers()              // Show line numbers
    ->editable()                     // Make editable
    ->indentSize(2)                  // Indentation size
    ->maxHeight(400)                 // Max editor height
    ->hideFromIndex()                // Auto-hidden from index
```

---

## Relationship Fields

### BelongsTo
Many-to-one relationship.

```php
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;

BelongsTo::make('Author', 'user_id', 'author')
    ->resource(UserResource::class)  // Related resource
    ->searchable()                   // Enable search
    ->displayUsing('name')           // Display field
    ->rules('required', 'exists:users,id')
```

### MorphTo
Polymorphic relationship.

```php
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;

MorphTo::make('Commentable', 'commentable')
    ->types([                        // Morphable types
        Post::class => PostResource::class,
        Video::class => VideoResource::class
    ])
    ->searchable()
    ->rules('required')
```

---

## Custom Components

### CustomComponent
Render custom frontend components.

```php
use SchoolAid\Nadota\Http\Fields\CustomComponent;

CustomComponent::make('User Stats', 'components/UserStatsCard.vue')
    ->withProps([                   // Pass static props
        'showChart' => true,
        'chartHeight' => 300
    ])
    ->withProp('theme', 'dark')      // Add single prop
    ->withData(function($model) {    // Pass dynamic data
        return [
            'total_orders' => $model->orders()->count(),
            'total_spent' => $model->orders()->sum('total'),
            'last_login' => $model->last_login_at
        ];
    })
    ->onlyOnDetail()                 // Default: only on detail
    ->showOnIndex()                  // Also show on index
    ->showEverywhere()               // Show on all views
```

---

## Computed Fields

Fields can display computed values without database columns:

### Using displayUsing()
```php
Text::make('Full Name', 'full_name')
    ->displayUsing(function($model, $resource) {
        return $model->first_name . ' ' . $model->last_name;
    })
    // Automatically becomes read-only and hidden from forms
```

### Using computed()
```php
Number::make('Total Orders', 'total_orders')
    ->computed()                     // Mark as computed
    ->displayUsing(fn($model) => $model->orders()->count())
```

### Computed Field Examples
```php
// Calculate age from date of birth
Number::make('Age', 'age')
    ->displayUsing(fn($model) => $model->date_of_birth->age)

// Format currency
Text::make('Price Display', 'price_display')
    ->displayUsing(fn($model) => '$' . number_format($model->price, 2))

// Show status with color
Text::make('Status', 'status_display')
    ->displayUsing(function($model) {
        return match($model->status) {
            'active' => '🟢 Active',
            'pending' => '🟡 Pending',
            'inactive' => '🔴 Inactive',
            default => $model->status
        };
    })

// Show related data count
Number::make('Comments', 'comment_count')
    ->displayUsing(fn($model) => $model->comments()->count())
    ->sortable()  // Can still be sortable if handled in query
```

---

## Advanced Field Features

### Conditional Display
```php
Text::make('Company', 'company')
    ->showWhen(fn($request, $model) => $model->is_business)
    ->hideWhen(fn($request, $model) => !$model->is_verified)
```

### Dynamic Rules
```php
Email::make('Email', 'email')
    ->rules(function($request, $model) {
        return $model->id
            ? ['required', 'email', Rule::unique('users')->ignore($model->id)]
            : ['required', 'email', 'unique:users'];
    })
```

### Field Dependencies
```php
Select::make('Country', 'country_id')
    ->options(Country::pluck('name', 'id'))

Select::make('State', 'state_id')
    ->dependsOn(['country_id'])
    ->options(function($values) {
        return State::where('country_id', $values['country_id'])
            ->pluck('name', 'id');
    })
```

### Custom Fill Logic
```php
class CustomField extends Field
{
    public function fill(Request $request, Model $model): void
    {
        // Custom logic for storing field value
        $value = $request->get($this->getAttribute());
        $model->{$this->getAttribute()} = strtoupper($value);
    }
}
```

### withData Callback
```php
// Any field can have additional data passed to frontend
Text::make('Name', 'name')
    ->withData(function($model, $resource) {
        return [
            'previous_names' => $model->name_history,
            'verified' => $model->is_verified
        ];
    })
```

---

## Best Practices

1. **Performance**: Use `hideFromIndex()` for heavy fields (JSON, Code, large text)
2. **Validation**: Always validate file uploads and user input
3. **Security**: Use proper authorization and sanitization
4. **UX**: Provide helpful placeholders and help text
5. **Accessibility**: Always set alt text for images
6. **Computed Fields**: Use for derived data, not for storing values
7. **Lazy Loading**: Enable for images in long lists
8. **Thumbnails**: Generate for better performance

---

## Creating Custom Fields

To create a custom field, extend the base `Field` class:

```php
namespace App\Nadota\Fields;

use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class ColorPicker extends Field
{
    protected array $swatches = [];

    public function __construct(string $name, string $attribute)
    {
        parent::__construct(
            $name,
            $attribute,
            'color', // Custom type
            'ColorPickerComponent' // Frontend component
        );
    }

    public function swatches(array $colors): static
    {
        $this->swatches = $colors;
        return $this;
    }

    protected function getProps($request, $model, $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'swatches' => $this->swatches
        ]);
    }
}
```

Usage:
```php
ColorPicker::make('Theme Color', 'theme_color')
    ->swatches(['#ff0000', '#00ff00', '#0000ff'])
    ->default('#ffffff')
```