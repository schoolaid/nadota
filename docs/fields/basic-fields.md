# Basic Fields

Reference for every non-relation, general-purpose field. All of these extend `SchoolAid\Nadota\Http\Fields\Field` and inherit the shared chainable API documented in [README.md](README.md#shared-field-api). This page only documents each field's own configuration methods and behavior.

For relations see [relation-fields.md](relation-fields.md); for the special fields see [dynamic-fields.md](dynamic-fields.md), [custom-fields.md](custom-fields.md), [signature.md](signature.md), and [exists.md](exists.md).

---

## Input

`SchoolAid\Nadota\Http\Fields\Input` — the plain text field (`type: text`). It adds nothing on top of the base `Field`; use it whenever you want a simple text input.

```php
Input::make('Name', 'name')->sortable()->searchable()->required();
```

---

## Email

`Email` — extends `Input`'s behavior and automatically appends the `email` validation rule (`type: email`).

```php
Email::make('Email', 'email')->required();
```

---

## URL

`URL` — automatically appends the `url` validation rule (`type: url`).

```php
URL::make('Website', 'website')->nullable();
```

---

## Password

`Password` (`type: password`, implements `FillableFieldInterface`). It is form-only (`onlyOnForms()`), required on creation and nullable on update by default, hashes the value on save, and never returns the stored value from `resolve()`. An empty submitted value leaves the password unchanged.

| Method | Description |
| ------ | ----------- |
| `confirmable(bool $confirmable = true)` | Adds the `confirmed` validation rule. |
| `minLength(int $length)` | Adds a `min:N` rule. |
| `showStrengthIndicator(bool $show = true)` | Frontend strength meter hint. |
| `hashUsing(callable $callback)` | Custom hasher; receives the raw string and returns the hash. Defaults to `Hash::make`. |

```php
Password::make('Password', 'password')
    ->minLength(8)
    ->confirmable()
    ->showStrengthIndicator();
```

---

## Textarea

`Textarea` (`type: textarea`).

| Method | Description |
| ------ | ----------- |
| `rows(int $rows)` | Number of visible text rows. |
| `cols(int $cols)` | Number of visible columns. |

```php
Textarea::make('Notes', 'notes')->rows(6);
```

---

## RichText

`RichText` (`type: richText`) — WYSIWYG HTML editor. Sanitization is enabled by default.

| Method | Description |
| ------ | ----------- |
| `sanitize(bool $sanitize = true)` | Toggle HTML sanitization. |
| `withoutSanitization()` | Disable sanitization (use with caution). |
| `allowedTags(array $tags)` | Whitelist of HTML tags. |
| `toolbar(array $toolbar)` | Toolbar configuration (format depends on the frontend editor). |
| `editorPlaceholder(string $placeholder)` | Placeholder text for the editor. |

```php
RichText::make('Body', 'body')
    ->allowedTags(['p', 'strong', 'em', 'a'])
    ->editorPlaceholder('Write something...');
```

---

## VariableText

`VariableText` (`type: variableText`) — templated text containing variables such as `$name` or `{{ name }}`. Required variables that are missing from the submitted text fail validation.

| Method | Description |
| ------ | ----------- |
| `requiredVariables(array $variables)` | Variables that must appear in the text. |
| `optionalVariables(array $variables)` | Variables that may appear. |
| `variable(string $name, array $config)` | Configure one variable: keys `label`, `description`, `type`, `default`, `required`. |
| `variables(array $variables)` | Configure multiple variables at once (`name => config`). |
| `variableLabels(array $labels)` | Set labels per variable. |
| `variableDescriptions(array $descriptions)` | Set descriptions per variable. |
| `variableTypes(array $types)` | Set type hints per variable (`text`, `date`, `number`, …). |
| `variableDefaults(array $defaults)` | Set default values per variable. |
| `variablePrefix(string $prefix)` | Variable prefix (default `$`). |
| `variableSuffix(string $suffix)` | Variable suffix (default empty). |
| `mustacheStyle()` | Use `{{ ... }}` delimiters. |
| `colonStyle()` | Use `:variable` delimiters. |
| `rows(int $rows)` | Textarea rows. |
| `maxLength(int $maxLength)` | Maximum text length. |

```php
VariableText::make('Template', 'template')
    ->mustacheStyle()
    ->variable('name', ['label' => 'Student name', 'required' => true])
    ->variable('date', ['type' => 'date']);
```

---

## Number

`Number` (`type: number`). Appends the `numeric` rule, plus `min:`/`max:` rules when configured.

| Method | Description |
| ------ | ----------- |
| `min(float $min)` | Minimum value. |
| `max(float $max)` | Maximum value. |
| `step(float $step)` | Increment step. |

```php
Number::make('Quantity', 'quantity')->min(1)->max(100)->step(1);
```

---

## Currency

`Currency` (`type: currency`). Appends the `numeric` rule, plus `min:`/`max:` when set. On export the value is formatted with the symbol.

| Method | Description |
| ------ | ----------- |
| `symbol(string $symbol)` | Currency symbol (default `$`). |
| `prefix()` | Place the symbol before the amount (default). |
| `suffix()` | Place the symbol after the amount. |
| `decimals(int $decimals)` | Decimal places (default `2`). |
| `thousandsSeparator(string $separator)` | Default `,`. |
| `decimalSeparator(string $separator)` | Default `.`. |
| `min(float $min)` / `max(float $max)` | Value bounds. |

```php
Currency::make('Price', 'price')->symbol('€')->suffix()->decimals(2);
```

---

## Count

`Count` (`type: number`, component `FieldCount`). Read-only, computed display of a relation count. Constructed as `Count::make(string $name, string $relation)`; the attribute is derived as `{relation}_count` (snake_case). Shown on index/detail, hidden from forms; loaded via `withCount()`.

| Method | Description |
| ------ | ----------- |
| `constraint(\Closure $callback)` | Constrain the count query. |
| `getCountRelation()` | The relation being counted. |
| `requiresWithCount()` | Whether the query needs `withCount`. |

```php
Count::make('Posts', 'posts')
    ->constraint(fn ($query) => $query->where('published', true));
```

---

## Date

`Date` (`type: date`). Display format defaults to `Y-m-d`; empty submissions are stored as `null`.

| Method | Description |
| ------ | ----------- |
| `format(string $format)` | Display format. |
| `min(\DateTimeInterface $date)` | Earliest selectable date. |
| `max(\DateTimeInterface $date)` | Latest selectable date. |

```php
Date::make('Birthday', 'birthday')->format('d/m/Y');
```

---

## DateTime

`DateTime` (`type: datetime`). Display format defaults to `Y-m-d H:i:s`. Values are parsed (using the display format first, then ISO 8601) and re-formatted to the store format on save.

| Method | Description |
| ------ | ----------- |
| `format(string $format)` | Display format. |
| `storeFormat(string $format)` | Format used when persisting (defaults to the display/mode format). |
| `dateOnly()` | Date-only mode (`Y-m-d`). |
| `timeOnly()` | Time-only mode (`H:i:s`). |
| `min(\DateTimeInterface $date)` / `max(\DateTimeInterface $date)` | Bounds. |

```php
DateTime::make('Published at', 'published_at')
    ->format('d/m/Y H:i')
    ->storeFormat('Y-m-d H:i:s');
```

---

## Time

`Time` (`type: time`). Display format defaults to `H:i:s`; `DateTimeInterface` values are formatted on resolve.

| Method | Description |
| ------ | ----------- |
| `format(string $format)` | Display format. |
| `min(string $time)` / `max(string $time)` | Time bounds. |
| `step(int $seconds)` | Step interval in seconds (default `60`). |
| `minuteStep()` | 60-second step. |
| `quarterHourStep()` | 900-second step. |
| `halfHourStep()` | 1800-second step. |
| `hourStep()` | 3600-second step. |

```php
Time::make('Start', 'start_time')->quarterHourStep();
```

---

## Checkbox

`Checkbox` (`type: checkbox`). A single boolean checkbox. `resolve()` returns `true`/`false`; on save the value is converted back to the configured true/false values.

| Method | Description |
| ------ | ----------- |
| `trueValue(mixed $value)` | Value stored when checked (default `1`). |
| `falseValue(mixed $value)` | Value stored when unchecked (default `0`). |

```php
Checkbox::make('Active', 'is_active')->trueValue('yes')->falseValue('no');
```

---

## Toggle

`Toggle` (`type: boolean`, component `FieldToggle`). Boolean switch; resolves to `true`/`false`.

| Method | Description |
| ------ | ----------- |
| `trueLabel(string $label)` | Label for the on state (default `On`). |
| `falseLabel(string $label)` | Label for the off state (default `Off`). |
| `trueValue(mixed $value)` | Stored on value (default `1`). |
| `falseValue(mixed $value)` | Stored off value (default `0`). |

```php
Toggle::make('Notifications', 'notify')->trueLabel('Enabled')->falseLabel('Disabled');
```

---

## Radio

`Radio` (`type: radio`).

| Method | Description |
| ------ | ----------- |
| `options(array $options)` | Associative `value => label` array, or pre-formatted `[{value,label}]`. |
| `inline(bool $inline = true)` | Lay options out inline. |

```php
Radio::make('Plan', 'plan')->options(['free' => 'Free', 'pro' => 'Pro'])->inline();
```

---

## Select

`Select` (`type: select`, component `FieldSelect`). Single or multiple selection. Accepts associative `value => label` arrays or pre-formatted option arrays. `BackedEnum` values are unwrapped on resolve. On export the value is converted to its label.

| Method | Description |
| ------ | ----------- |
| `options(array $options)` | The option list. |
| `multiple(bool $multiple = true)` | Allow multiple selection (JSON-decoded on resolve). |
| `clearable(bool $clearable = true)` | Allow clearing the selection. |
| `placeholder(string $placeholder)` | Placeholder text. |
| `translateLabels(bool $translate = true)` | Frontend label translation (default `true`). |
| `withoutTranslation()` | Disable label translation. |
| `valueKey(string $key)` | Key in option arrays used as the value. |
| `labelKey(string $key)` | Key in option arrays used as the label. |
| `optionKeys(string $valueKey, string $labelKey)` | Set both keys at once. |
| `uuidValueFormat()` | Shorthand for `optionKeys('uuid', 'value')`. |
| `withLabel(bool $withLabel = true)` | Make `resolve()` return `{value, label}` objects. |

```php
Select::make('Status', 'status')
    ->options(['draft' => 'Draft', 'published' => 'Published'])
    ->clearable()
    ->withLabel();
```

---

## CheckboxList

`CheckboxList` (`type: checkbox_list`, component `FieldCheckboxList`). Multi-select list of checkboxes; resolves to an array. Stored as a JSON string unless the model casts the attribute to `array`/`json`/`object`/`collection`.

| Method | Description |
| ------ | ----------- |
| `options(array $options)` | Associative `value => label` or pre-formatted options. |
| `min(int $min)` | Minimum selections (adds `array` + `min:` rules). |
| `max(int $max)` | Maximum selections (adds `array` + `max:` rules). |
| `limit(int $max)` | Alias of `max()`. |
| `inline(bool $inline = true)` | Inline layout. |

```php
CheckboxList::make('Tags', 'tags')
    ->options(['php' => 'PHP', 'js' => 'JavaScript'])
    ->min(1)->max(3);
```

---

## Status

`Status` (`type: status`, component `FieldStatus`). A select-style field that maps stored values to colored status badges. Keys can be strings, ints, or `BackedEnum` cases. By default `resolve()` returns the raw scalar value (safe for form pre-population); on export it returns the label.

| Method | Description |
| ------ | ----------- |
| `map(array $statuses)` | Status map. Values may be a label string or `['label' => ..., 'color' => ..., 'icon' => ...]`. |
| `addStatus(string\|int\|BackedEnum $value, string $label, string $color = 'gray', ?string $icon = null)` | Add a single entry. |
| `clearable(bool $clearable = true)` | Allow clearing. |
| `placeholder(string $placeholder)` | Placeholder text. |
| `translateLabels(bool $translate = true)` / `withoutTranslation()` | Frontend label translation. |
| `withStatus(bool $withStatus = true)` | Make `resolve()` return the full `{value, label, color}` object. |

```php
Status::make('State', 'state')->map([
    'paid'    => ['label' => 'Paid',    'color' => 'green'],
    'pending' => ['label' => 'Pending', 'color' => 'yellow'],
    'failed'  => ['label' => 'Failed',  'color' => 'red'],
])->withStatus();
```

---

## Color

`Color` (`type: color`). Color picker with no extra configuration methods.

```php
Color::make('Brand color', 'color');
```

---

## Code

`Code` (`type: code`, component `FieldCode`). Source-code editor; hidden from the index by default.

| Method | Description |
| ------ | ----------- |
| `language(string $language)` | Syntax language (default `javascript`). |
| `theme(string $theme)` | Editor theme (default `light`). |
| `showLineNumbers(bool $show = true)` | Toggle line numbers (default on). |
| `editable(bool $editable = true)` | Toggle editing (default on). |
| `syntaxHighlighting(bool $on = true)` | Toggle highlighting (default on). |
| `wordWrap(bool $on = true)` | Toggle word wrap (default off). |

Language shortcuts: `php()`, `javascript()`, `python()`, `html()`, `css()`, `sql()`, `json()`, `yaml()`, `xml()`, `markdown()`, `shell()`.

```php
Code::make('Snippet', 'snippet')->php()->wordWrap();
```

---

## Json

`Json` (`type: json`, component `FieldJson`). JSON editor; hidden from the index by default. `resolve()` decodes JSON strings to arrays; `fill()` stores arrays when the model casts the attribute, otherwise a JSON string.

| Method | Description |
| ------ | ----------- |
| `prettyPrint(bool $pretty = true)` | Pretty-print (default on). |
| `editable(bool $editable = true)` | Toggle editing (default on). |
| `indentSize(int $size)` | Indentation width (default `2`). |
| `showLineNumbers(bool $show = true)` | Toggle line numbers (default off). |

```php
Json::make('Settings', 'settings')->indentSize(4);
```

---

## KeyValue

`KeyValue` (`type: keyValue`, component `FieldKeyValue`). Schema-driven key/value editor; hidden from the index by default. Readonly and hidden keys are preserved across saves; values are stored as an array (model cast) or JSON string.

| Method | Description |
| ------ | ----------- |
| `schema(array $schema)` | Per-key config. A value may be a label string or `['label', 'type', 'options', 'rules', 'default', 'readonly', 'hidden']`. |
| `allowNewKeys(bool $allow = true)` | Allow users to add new keys. |
| `keyLabels(array $labels)` / `keyLabel(string $key, string $label)` | Set labels. |
| `readonlyKeys(string\|array $keys)` | Mark keys read-only. |
| `hideKeys(string\|array $keys)` | Hide keys. |
| `keyRules(string $key, array\|string $rules)` | Per-key validation rules. |
| `inputType(string $key, string $type, ?array $options = null)` | Per-key input type. |
| `group(string $groupName, array $keys)` | Group keys together. |
| `asTable(bool $asTable = true)` | Render as a table. |
| `useKeys()` | Show raw keys instead of labels. |
| `defaults(array $defaults)` | Default values for keys. |

```php
KeyValue::make('Metadata', 'meta')
    ->schema([
        'phone' => ['label' => 'Phone', 'type' => 'text'],
        'role'  => ['label' => 'Role', 'type' => 'select', 'options' => ['admin', 'user']],
    ])
    ->allowNewKeys();
```

---

## ArrayField

`ArrayField` (`type: array`, component `FieldArray`). A list of scalar values; hidden from the index by default. Resolves to a re-indexed array; `fill()` casts, de-duplicates, and removes empty values.

| Method | Description |
| ------ | ----------- |
| `valueType(string $type)` | `string`, `number`, `integer`, `boolean`, `email`, `url`. |
| `allowDuplicates(bool $allow = true)` / `unique()` | Toggle duplicates (`unique()` adds `distinct`). |
| `min(int $min)` / `max(int $max)` / `length(int $length)` | Item-count bounds. |
| `sortable(bool $sortable = true)` | Allow reordering (default on). |
| `itemPlaceholder(string $placeholder)` | Placeholder for new items. |
| `defaultValues(array $values)` | Default array. |
| `itemRules(array\|string $rules)` | Validation rules for each item. |
| `options(array $options)` | Options for select-style items. |
| `displayAsChips(bool $on = true)` | Render as chips/tags. |
| `addButtonText(string $text)` | Add-button label. |

Type shortcuts: `strings()`, `numbers()`, `integers()`, `emails()`, `urls()`.

```php
ArrayField::make('Aliases', 'aliases')->strings()->unique()->max(5);
```

---

## Html

`Html` (`type: html`). Renders HTML for display. Sanitization is on by default.

| Method | Description |
| ------ | ----------- |
| `sanitize(bool $sanitize = true)` | Toggle sanitization. |
| `withoutSanitization()` | Disable sanitization (use with caution). |
| `allowedTags(array $tags)` | Whitelist of HTML tags. |

```php
Html::make('Banner', 'banner_html')->allowedTags(['div', 'span', 'a']);
```

---

## Hidden

`Hidden` (`type: hidden`). A hidden input; constructor removes it from index and detail views (`hideFromIndex()->hideFromDetail()`).

```php
Hidden::make('Token', 'token')->default(fn () => Str::random(40));
```

---

## File

`File` (`type: file`, component `FieldFile`). Handles uploads, stores the path on the model, deletes the previous file on replace, and builds public/temporary/cached URLs on resolve. Default max size is 10 MB (configurable).

| Method | Description |
| ------ | ----------- |
| `accept(array $types)` | Accepted MIME types or extensions. |
| `maxSize(int $bytes)` / `maxSizeMB(int $megabytes)` | Size limit. |
| `disk(string $disk)` | Storage disk. |
| `path(string $path)` | Storage path (default `uploads`). |
| `visibility(string $visibility)` / `public()` / `private()` | File visibility. |
| `downloadable(bool $downloadable = true)` | Toggle download link (default on). |
| `downloadRoute(string $route)` | Named route for downloads. |
| `temporaryUrl(int $minutes = 30)` | Use temporary signed URLs. |
| `withSigning()` / `withoutSigning()` | Toggle URL signing (default signed). |
| `storedAsUrl(bool $stored = true)` | Treat the stored value as a full URL. |
| `cache(int $minutes = 30, ?string $prefix = null)` | Cache generated URLs. |
| `cachedTemporaryUrl(int $cacheMinutes = 30, int $urlMinutes = 30)` | Combine caching + temporary URLs. |
| `store(\Closure $callback)` | Custom store logic: `($request, $model, $attribute, $requestAttribute, $disk, $path)`. |
| `deleteUsing(\Closure $callback)` | Custom old-file delete: `($model, $disk, $oldPath)`. |
| `storeAs(\Closure $callback)` | Custom filename: `($request, $requestAttribute)`. |

```php
File::make('Document', 'document_path')
    ->disk('s3')->path('docs')->private()
    ->cachedTemporaryUrl();
```

---

## Image

`Image` (`type: image`, component `FieldImage`) — extends `File`. Defaults to accepting `jpg, jpeg, png, gif, webp`, a 5 MB max size, and adds image preview, thumbnails, and dimension data on resolve. All `File` methods apply.

| Method | Description |
| ------ | ----------- |
| `maxImageDimensions(int $width, int $height)` | Max dimensions. |
| `maxImageWidth(int $width)` / `maxImageHeight(int $height)` | Single-axis max. |
| `imageWidth(int $width)` / `imageHeight(int $height)` | Aliases for the above. |
| `preview(bool $show = true)` | Toggle preview. |
| `thumbnails(array $sizes)` | Thumbnail size definitions. |
| `alt(string $alt)` | Alt text. |
| `rounded(bool $rounded = true)` | Rounded corners. |
| `squared(bool $square = true)` | 1:1 aspect ratio. |
| `circle()` | Rounded + squared. |
| `previewSize(string $size)` | Preview size (`small`, `medium`, `large`, or px). |
| `lazy(bool $lazy = true)` | Lazy-load (default on). |
| `placeholder(string $placeholder)` | Placeholder image URL/base64. |
| `disablePreviewOnIndex()` | No preview on index. |
| `acceptImages()` / `webSafe()` | Adjust accepted formats. |
| `convertTo(string $format)` | Convert uploads to a format. |
| `quality(int $quality)` | Compression quality (1–100). |

```php
Image::make('Avatar', 'avatar')
    ->disk('public')->path('avatars')
    ->circle()->previewSize('small');
```

---

## Section

`SchoolAid\Nadota\Http\Fields\Section` is not a `Field` — it is a layout container placed in the resource's `fields()` array to group fields. It uses the `Makeable` and `VisibilityTrait` traits. Loose (non-section) fields are grouped into a default section automatically.

Construct with `Section::make(string $title, array $fields = [])`.

| Method | Description |
| ------ | ----------- |
| `icon(string $icon)` | Section icon. |
| `description(string $description)` | Section description. |
| `collapsible(bool $collapsible = true)` | Allow collapse. |
| `collapsed(bool $collapsed = true)` | Start collapsed (implies collapsible). |

It also supports the visibility helpers (`hideFromCreation()`, `onlyOnDetail()`, etc.).

```php
Section::make('Profile', [
    Input::make('Name', 'name'),
    Email::make('Email', 'email'),
])->icon('user')->collapsible();
```
