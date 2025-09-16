# Nadota Fields Documentation

This document provides comprehensive documentation for all field types available in the Nadota package.

## Table of Contents
- [Base Field Class](#base-field-class)
- [Basic Fields](#basic-fields)
  - [Input](#input)
  - [Textarea](#textarea)
  - [Number](#number)
  - [Email](#email)
  - [URL](#url)
  - [Hidden](#hidden)
- [Selection Fields](#selection-fields)
  - [Select](#select)
  - [Radio](#radio)
  - [Checkbox](#checkbox)
  - [CheckboxList](#checkboxlist)
  - [Toggle](#toggle)
- [Date & Time Fields](#date--time-fields)
  - [DateTime](#datetime)
- [File Fields](#file-fields)
  - [File](#file)
  - [Image](#image)
- [Relationship Fields](#relationship-fields)
  - [BelongsTo](#belongsto)
  - [HasOne](#hasone)
  - [HasMany](#hasmany)
  - [BelongsToMany](#belongstomany)

## Base Field Class

All field types extend from the base `Field` class which provides core functionality:

```php
use SchoolAid\Nadota\Http\Fields\Field;
```

### Common Methods Available on All Fields

- `name(string $name)` - Set the display name
- `attribute(string $attribute)` - Set the model attribute
- `rules(array|string $rules)` - Set validation rules
- `default($value)` - Set default value
- `help(string $text)` - Add help text
- `placeholder(string $text)` - Set placeholder text
- `required(bool $required = true)` - Mark as required
- `readonly(bool $readonly = true)` - Make field read-only
- `disabled(bool $disabled = true)` - Disable field
- `hideOnIndex()` - Hide on index view
- `hideOnDetail()` - Hide on detail view
- `hideOnCreate()` - Hide on create form
- `hideOnUpdate()` - Hide on update form
- `sortable(bool $sortable = true)` - Make field sortable
- `searchable(bool $searchable = true)` - Make field searchable
- `filterable(bool $filterable = true)` - Make field filterable

## Basic Fields

### Input

Standard text input field.

```php
use SchoolAid\Nadota\Http\Fields\Input;

Input::make('Title', 'title')
    ->required()
    ->placeholder('Enter title')
    ->rules('required|string|max:255')
```

**Component:** `field-text`
**Type:** `text`

### Textarea

Multi-line text input field.

```php
use SchoolAid\Nadota\Http\Fields\Textarea;

Textarea::make('Description', 'description')
    ->rows(5)
    ->cols(50)
    ->placeholder('Enter description')
```

**Methods:**
- `rows(int $rows)` - Set number of visible rows
- `cols(int $cols)` - Set number of visible columns

**Component:** `field-textarea`

### Number

Numeric input field.

```php
use SchoolAid\Nadota\Http\Fields\Number;

Number::make('Price', 'price')
    ->min(0)
    ->max(1000)
    ->step(0.01)
```

**Methods:**
- `min(float $min)` - Set minimum value
- `max(float $max)` - Set maximum value
- `step(float $step)` - Set increment step

**Component:** `field-number`

### Email

Email input field with built-in validation.

```php
use SchoolAid\Nadota\Http\Fields\Email;

Email::make('Email Address', 'email')
    ->required()
    ->rules('required|email|unique:users,email')
```

**Component:** `field-email`

### URL

URL input field.

```php
use SchoolAid\Nadota\Http\Fields\URL;

URL::make('Website', 'website_url')
    ->placeholder('https://example.com')
```

**Component:** `field-url`

### Hidden

Hidden field for storing values not visible to users.

```php
use SchoolAid\Nadota\Http\Fields\Hidden;

Hidden::make('User ID', 'user_id')
    ->default(auth()->id())
```

**Component:** `field-hidden`

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
    ->default('draft')
    ->clearable()
    ->placeholder('Select status')

// Multiple selection
Select::make('Tags', 'tags')
    ->options($tags)
    ->multiple()
```

**Methods:**
- `options(array $options)` - Set available options
- `multiple(bool $multiple = true)` - Allow multiple selections
- `clearable(bool $clearable = true)` - Add clear button
- `placeholder(string $placeholder)` - Set placeholder text

**Component:** `field-select`

### Radio

Radio button group field.

```php
use SchoolAid\Nadota\Http\Fields\Radio;

Radio::make('Gender', 'gender')
    ->options([
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other'
    ])
```

**Methods:**
- `options(array $options)` - Set available options

**Component:** `field-radio`

### Checkbox

Single checkbox field.

```php
use SchoolAid\Nadota\Http\Fields\Checkbox;

Checkbox::make('Accept Terms', 'terms_accepted')
    ->trueValue(1)
    ->falseValue(0)
```

**Methods:**
- `trueValue(mixed $value)` - Value when checked (default: 1)
- `falseValue(mixed $value)` - Value when unchecked (default: 0)

**Component:** `field-checkbox`

### CheckboxList

Multiple checkbox selection field.

```php
use SchoolAid\Nadota\Http\Fields\CheckboxList;

CheckboxList::make('Permissions', 'permissions')
    ->options([
        'read' => 'Read',
        'write' => 'Write',
        'delete' => 'Delete'
    ])
```

**Methods:**
- `options(array $options)` - Set available options

**Component:** `field-checkbox-list`

### Toggle

Toggle switch field.

```php
use SchoolAid\Nadota\Http\Fields\Toggle;

Toggle::make('Active', 'is_active')
    ->trueLabel('Active')
    ->falseLabel('Inactive')
    ->trueValue(1)
    ->falseValue(0)
```

**Methods:**
- `trueLabel(string $label)` - Label when on (default: 'On')
- `falseLabel(string $label)` - Label when off (default: 'Off')
- `trueValue(mixed $value)` - Value when on (default: 1)
- `falseValue(mixed $value)` - Value when off (default: 0)

**Component:** `field-toggle`

## Date & Time Fields

### DateTime

Date and time picker field.

```php
use SchoolAid\Nadota\Http\Fields\DateTime;

// Full datetime
DateTime::make('Published At', 'published_at')
    ->format('Y-m-d H:i:s')
    ->min(now())
    ->max(now()->addYear())

// Date only
DateTime::make('Birth Date', 'birth_date')
    ->dateOnly()

// Time only
DateTime::make('Start Time', 'start_time')
    ->timeOnly()
```

**Methods:**
- `format(string $format)` - Set date format (default: 'Y-m-d H:i:s')
- `min(DateTimeInterface $date)` - Set minimum date
- `max(DateTimeInterface $date)` - Set maximum date
- `dateOnly()` - Show only date picker
- `timeOnly()` - Show only time picker

**Component:** `field-datetime`

## File Fields

### File

File upload field.

```php
use SchoolAid\Nadota\Http\Fields\File;

File::make('Document', 'document_path')
    ->accept('.pdf,.doc,.docx')
    ->maxSize(5 * 1024) // 5MB in KB
    ->multiple()
```

**Methods:**
- `accept(string $types)` - Set accepted file types
- `maxSize(int $sizeInKB)` - Set maximum file size
- `multiple(bool $multiple = true)` - Allow multiple file uploads

**Component:** `field-file`

### Image

Image upload field with preview.

```php
use SchoolAid\Nadota\Http\Fields\Image;

Image::make('Avatar', 'avatar_path')
    ->accept('image/*')
    ->maxSize(2 * 1024) // 2MB
    ->preview()
```

**Methods:**
- `accept(string $types)` - Set accepted image types
- `maxSize(int $sizeInKB)` - Set maximum file size
- `preview(bool $show = true)` - Show image preview

**Component:** `field-image`

## Relationship Fields

All relationship fields extend from the base `RelationField` class.

### BelongsTo

For many-to-one relationships.

```php
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;

BelongsTo::make('Author', 'author')
    ->relationAttribute('name') // Display field from related model
    ->searchable()
    ->sortable()
```

**Methods:**
- `relationAttribute(string $attribute)` - Set display attribute from related model

**Component:** Configured via `nadota.fields.belongsTo.component`

### HasOne

For one-to-one relationships.

```php
use SchoolAid\Nadota\Http\Fields\Relations\HasOne;

HasOne::make('Profile', 'profile')
    ->relationAttribute('bio')
```

**Methods:**
- `relationAttribute(string $attribute)` - Set display attribute from related model

**Component:** `field-has-one`

### HasMany

For one-to-many relationships.

```php
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;

HasMany::make('Comments', 'comments')
    ->limit(10) // Limit displayed items
    ->showOnIndex(false)
```

**Methods:**
- `limit(int $limit)` - Limit number of displayed items

**Component:** `field-has-many`

### BelongsToMany

For many-to-many relationships.

```php
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;

BelongsToMany::make('Tags', 'tags')
    ->pivot(['created_at', 'updated_at']) // Include pivot fields
    ->displayUsing(function($tag) {
        return $tag->name;
    })
```

**Methods:**
- `pivot(array $fields)` - Include pivot table fields
- `displayUsing(callable $callback)` - Custom display formatting

**Component:** `field-belongs-to-many`

## Field Traits

The package includes several traits that provide additional functionality:

### DefaultValueTrait
Provides default value functionality.

### FilterableTrait
Makes fields available in filters.

### SearchableTrait
Enables field searching capabilities.

### SortableTrait
Allows sorting by field values.

### ValidationTrait
Handles field validation rules.

### VisibilityTrait
Controls field visibility in different contexts.

## Custom Field Components

Fields use Vue components for rendering. You can override the default components in the configuration:

```php
// config/nadota.php
return [
    'fields' => [
        'text' => [
            'component' => 'custom-text-field'
        ],
        'select' => [
            'component' => 'custom-select-field'
        ],
        // ... other field component overrides
    ]
];
```

## Creating Custom Fields

To create a custom field, extend the base `Field` class:

```php
namespace App\Fields;

use SchoolAid\Nadota\Http\Fields\Field;

class ColorPicker extends Field
{
    protected string $component = 'field-color-picker';
    
    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute);
        $this->type = 'color';
    }
    
    protected function getProps($request, $model, $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'format' => 'hex',
            // Additional props for your custom component
        ]);
    }
}
```

## Field Usage in Resources

Fields are defined in your resource classes:

```php
namespace App\Nadota;

use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;

class PostResource extends Resource
{
    public function fields(): array
    {
        return [
            Input::make('Title', 'title')
                ->required()
                ->searchable()
                ->sortable(),
                
            BelongsTo::make('Author', 'author')
                ->relationAttribute('name'),
                
            Select::make('Status', 'status')
                ->options([
                    'draft' => 'Draft',
                    'published' => 'Published'
                ])
                ->filterable()
        ];
    }
}
```