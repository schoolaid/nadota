# Fields

Fields describe how a resource's model attributes are displayed, validated, and persisted. Every field extends the abstract `SchoolAid\Nadota\Http\Fields\Field` class and is added to a resource's `fields()` method.

```php
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Email;
use SchoolAid\Nadota\Http\Fields\Number;

public function fields(NadotaRequest $request): array
{
    return [
        Input::make('Name', 'name')->sortable()->searchable()->required(),
        Email::make('Email', 'email')->required(),
        Number::make('Age', 'age')->min(0)->max(120),
    ];
}
```

## Field Catalog

### Text

| Field | Description | Reference |
| ----- | ----------- | --------- |
| `Input` | Basic single-line text input (`type: text`). | [basic-fields.md](basic-fields.md#input) |
| `Email` | Text input pre-configured with the `email` validation rule. | [basic-fields.md](basic-fields.md#email) |
| `URL` | Text input pre-configured with the `url` validation rule. | [basic-fields.md](basic-fields.md#url) |
| `Password` | Password input that hashes on save and never echoes the value. | [basic-fields.md](basic-fields.md#password) |
| `Textarea` | Multi-line text input with `rows`/`cols`. | [basic-fields.md](basic-fields.md#textarea) |
| `RichText` | WYSIWYG HTML editor with toolbar and sanitization. | [basic-fields.md](basic-fields.md#richtext) |
| `VariableText` | Templated text supporting required/optional variables. | [basic-fields.md](basic-fields.md#variabletext) |

### Numeric

| Field | Description | Reference |
| ----- | ----------- | --------- |
| `Number` | Numeric input with `min`/`max`/`step`. | [basic-fields.md](basic-fields.md#number) |
| `Currency` | Money input with symbol, decimals, and separators. | [basic-fields.md](basic-fields.md#currency) |
| `Count` | Read-only count of a relation (uses `withCount`). | [basic-fields.md](basic-fields.md#count) |

### Date / Time

| Field | Description | Reference |
| ----- | ----------- | --------- |
| `Date` | Date picker with format and `min`/`max` bounds. | [basic-fields.md](basic-fields.md#date) |
| `DateTime` | Date + time picker with display/store formats. | [basic-fields.md](basic-fields.md#datetime) |
| `Time` | Time picker with step intervals. | [basic-fields.md](basic-fields.md#time) |

### Boolean

| Field | Description | Reference |
| ----- | ----------- | --------- |
| `Checkbox` | Single boolean checkbox with custom true/false values. | [basic-fields.md](basic-fields.md#checkbox) |
| `Toggle` | Boolean toggle switch with labels and custom values. | [basic-fields.md](basic-fields.md#toggle) |
| `Exists` | Read-only boolean showing if a relation exists (`withExists`). | [exists.md](exists.md) |

### Selection

| Field | Description | Reference |
| ----- | ----------- | --------- |
| `Select` | Dropdown select (single/multiple) with options. | [basic-fields.md](basic-fields.md#select) |
| `Radio` | Radio button group. | [basic-fields.md](basic-fields.md#radio) |
| `CheckboxList` | Multi-select checkbox list with min/max. | [basic-fields.md](basic-fields.md#checkboxlist) |
| `Status` | Select-like field that maps values to colored status badges. | [basic-fields.md](basic-fields.md#status) |

### File / Media

| Field | Description | Reference |
| ----- | ----------- | --------- |
| `File` | File upload with disk/path/visibility and URL handling. | [basic-fields.md](basic-fields.md#file) |
| `Image` | File subclass with image preview, thumbnails, dimensions. | [basic-fields.md](basic-fields.md#image) |
| `Signature` | Signature pad stored as base64 or as a file on disk. | [signature.md](signature.md) |

### Code / Data

| Field | Description | Reference |
| ----- | ----------- | --------- |
| `Code` | Source-code editor with language and theme. | [basic-fields.md](basic-fields.md#code) |
| `Json` | JSON editor that decodes/encodes the stored value. | [basic-fields.md](basic-fields.md#json) |
| `KeyValue` | Schema-driven key/value editor. | [basic-fields.md](basic-fields.md#keyvalue) |
| `ArrayField` | List of scalar values (strings, numbers, etc.). | [basic-fields.md](basic-fields.md#arrayfield) |
| `Color` | Color picker (`type: color`). | [basic-fields.md](basic-fields.md#color) |

### Layout / Display

| Field | Description | Reference |
| ----- | ----------- | --------- |
| `Section` | Groups fields under a titled, optionally collapsible section. | [basic-fields.md](basic-fields.md#section) |
| `Html` | Renders raw/sanitized HTML (display only). | [basic-fields.md](basic-fields.md#html) |
| `Hidden` | Hidden input, removed from index/detail. | [basic-fields.md](basic-fields.md#hidden) |

### Special

| Field | Description | Reference |
| ----- | ----------- | --------- |
| `DynamicField` | Renders a different field type based on another attribute. | [dynamic-fields.md](dynamic-fields.md) |
| `CustomComponent` | Mounts a custom frontend component (no DB column). | [custom-fields.md](custom-fields.md) |
| `Lookup` | Form-only value that narrows another field's options (no DB column). | [lookup.md](lookup.md) |

### Relations

| Field | Description | Reference |
| ----- | ----------- | --------- |
| `BelongsTo` | Inverse one-to-one / many-to-one relation. | [relation-fields.md](relation-fields.md) |
| `HasOne` | One-to-one relation. | [relation-fields.md](relation-fields.md) |
| `HasMany` | One-to-many relation. | [relation-fields.md](relation-fields.md) |
| `BelongsToMany` | Many-to-many relation (pivot). | [relation-fields.md](relation-fields.md) |
| `HasOneThrough` / `HasManyThrough` | "Through" relations. | [relation-fields.md](relation-fields.md) |
| `MorphTo` / `MorphOne` / `MorphMany` | Polymorphic relations. | [relation-fields.md](relation-fields.md) |
| `MorphToMany` / `MorphedByMany` | Polymorphic many-to-many relations. | [relation-fields.md](relation-fields.md) |

The full list of registered type strings lives in `SchoolAid\Nadota\Http\Fields\Enums\FieldType`.

---

## Shared Field API

Every field gets the methods below from the base `Field` class and its traits. They return `static`, so they are chainable. Field-specific options are documented in each field's page.

### Construction

```php
public static function make(...$arguments): static
public function __construct(string $label, string $attribute, string $type = 'text', ?string $component = null)
```

`make()` (from the `Makeable` trait) is the canonical entry point. Most fields take `make(string $label, string $attribute)`; relation and special fields differ (e.g. `Count::make($name, $relation)`, `CustomComponent::make($name, $componentPath)`).

```php
Input::make('Full Name', 'name');
```

### Labels, attribute & keys

```php
public function label(string $label): static
public function placeholder(string $placeholder): static
public function component(string $component): static
public function withKey(string $key): static       // override the field key (defaults to the attribute)
public function withAttribute(string $attribute): static
```

Accessors: `getLabel()`, `getName()` (alias of `getLabel()`), `getAttribute()`, `getType()`, `getPlaceholder()`, `getComponent()`, `getKey()` / `key()`.

### State

```php
public function readonly(bool $readonly = true): static
public function disabled(bool $disabled = true): static
public function help(string $text): static
public function alert(string $text, string $type = 'info'): static   // type: info|warning|error|success
```

Readonly and disabled fields are skipped by the default `fill()`.

### Validation

From `ValidationTrait`:

```php
public function rules(array|callable $rules): static
public function required(): static                 // adds the 'required' rule and marks isRequired()
public function nullable(): static
public function creationRules(array $rules): static // applied only on store
public function updateRules(array $rules): static   // applied only on update
public function sometimes(callable $callback): static
public function requiredIf(string $field, mixed $value): static
public function requiredUnless(string $field, mixed $value): static
```

`getRulesFor(bool $isUpdate)` merges base rules with the matching context rules; a context `nullable` cancels a base `required` (and vice versa). Some fields append their own rules (e.g. `Email` adds `email`, `Number` adds `numeric`).

### Default value

From `DefaultValueTrait`:

```php
public function default(mixed $value): static
public function defaultUsing(callable $callback): static          // ($request, $model, $resource)
public function defaultFromAttribute(string $attribute): static   // supports dot notation
public function defaultWhen(callable $condition, mixed $value): static
```

### Sorting & searching

```php
public function sortable(): static                 // SortableTrait
public function searchable(): static               // SearchableTrait
public function searchableGlobally(): static       // also enables searchable
public function searchWeight(int $weight): static
public function notSearchable(): static
```

### Filtering

From `FilterableTrait` (see [../filters/README.md](../filters/README.md)):

```php
public function filterable(): static
public function filterableRange(): static          // exposes {attr}_from / {attr}_to keys
public function getFilterKeys(): array
public function filters(): array                   // the generated Filter objects
```

### Visibility

From `VisibilityTrait`. Each accepts a `bool` or a `callable($request, $resource)`:

```php
public function showOnIndex(callable|bool $value = true): static
public function showOnDetail(callable|bool $value = true): static
public function showOnCreation(callable|bool $value = true): static
public function showOnUpdate(callable|bool $value = true): static
```

Convenience helpers:

```php
public function hideFromIndex(): static
public function hideFromDetail(): static
public function hideFromCreation(): static
public function hideFromUpdate(): static
public function onlyOnIndex(): static
public function onlyOnDetail(): static
public function onlyOnForms(): static     // create + update only
public function exceptOnForms(): static   // index + detail only
public function hideWhen(callable $callback): static
public function showWhen(callable $callback): static
public function onlyWhen(callable $callback): static   // alias of showWhen
```

### Conditional dependencies

The base field exposes the full `dependsOn`/`showWhenEquals`/`computeUsing` API from `DependsOnTrait`. See [depends-on.md](depends-on.md).

### Layout & sizing

```php
public function width(string $width): static   // 'full', '1/2', '1/3', '1/4', '2/3', '3/4', or CSS value
public function fullWidth(): static
public function halfWidth(): static
public function oneThirdWidth(): static
public function twoThirdsWidth(): static
public function oneQuarterWidth(): static
public function threeQuartersWidth(): static
public function tabSize(int $tabSize): static
public function maxHeight(?int $maxHeight): static
public function minHeight(?int $minHeight): static
```

### Computed, virtual & custom fields

```php
public function displayUsing(callable $callback): static  // ($model, $resource); marks non-relation fields computed
public function computed(bool $computed = true): static    // read-only, hidden from forms
public function virtual(bool $virtual = true): static      // validated but never persisted; implies skipFill
public function skipFill(bool $skip = true): static        // not assigned during store/update (use afterSave)
public function customField(bool $isCustom = true): static
public function componentPath(string $path): static        // path to a custom component; implies customField
public function withData(callable $callback): static       // ($model, $resource) -> props['data']
public function withoutId(): static                        // omit 'id' from relation responses
```

See [custom-fields.md](custom-fields.md) for building your own field types.

### Relation / select-column helpers

```php
public function except(array $columns): static       // exclude columns from the related model
public function exceptFields(array $fieldKeys): static
public function getColumnsForSelect(string $modelClass): array
public function getRelatedColumns(Request $request): ?array
```

### Export

Used by the export feature (see [../guides/exports.md](../guides/exports.md)):

```php
public function exportable(bool $exportable = true): static
public function exportUsing(string $attribute): static   // relation attribute; implies exportable
public function exportSeparator(string $separator): static
public function exportLimit(?int $limit): static
public function resolveForExport(Request $request, Model $model, ?ResourceInterface $resource): mixed
```

### Lifecycle hooks (override points)

Field subclasses may override these (see [../guides/lifecycle-hooks.md](../guides/lifecycle-hooks.md)):

```php
public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
public function resolveForStore(Request $request, Model $model, ?ResourceInterface $resource, $value): mixed
public function resolveForUpdate(Request $request, Model $model, ?ResourceInterface $resource, $value): mixed
public function fill(Request $request, Model $model): void
public function beforeSave(Request $request, Model $model, string $operation): void  // 'store' | 'update'
public function afterSave(Request $request, Model $model): void
public function supportsAfterSave(): bool
```

### Serialization

`toArray(NadotaRequest $request, ?Model $model = null, ?ResourceInterface $resource = null)` produces the field payload sent to the frontend. It includes `label`, `attribute`, `key`, `type`, `component`, `required`, `rules`, `default`, `sortable`, `searchable`, `filterable`, `filterKeys`, the `showOn*` flags, `props`, `optionsUrl`, `dependencies`, and (when a model is supplied) the resolved `value`. See [../api/responses.md](../api/responses.md).
