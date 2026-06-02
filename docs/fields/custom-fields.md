# Custom Fields

There are two ways to extend Nadota with custom presentation: mounting an arbitrary frontend component with `CustomComponent`, or subclassing the base `Field` class to create a fully custom, reusable field type.

## CustomComponent

`SchoolAid\Nadota\Http\Fields\CustomComponent` (`type: customComponent`) mounts a frontend component that is not backed by a database column. It is shown only on the detail view by default and never persists data.

Construct with `CustomComponent::make(string $name, string $componentPath)`. The component path is also used as the field component and a dummy attribute (`custom_component_{name}`) is generated.

| Method | Description |
| ------ | ----------- |
| `component(string $path)` | Override the component path. |
| `withProps(array $props)` | Merge additional props passed to the component. |
| `withProp(string $key, mixed $value)` | Add a single prop. |
| `withData(callable $callback)` | Inherited; `($model, $resource)` result is emitted as `componentData` and returned by `resolve()`. |
| `inside()` | Render inside the detail card (default). |
| `below()` | Render below the detail card. |
| `position(string $position)` | Set position explicitly (`inside` or `below`). |
| `onlyOnForms()` | Show on forms instead of detail. |

```php
use SchoolAid\Nadota\Http\Fields\CustomComponent;

CustomComponent::make('Activity Feed', '@/components/fields/ActivityFeed.vue')
    ->below()
    ->withProps(['limit' => 10])
    ->withData(fn ($model) => $model->activities()->latest()->take(10)->get());
```

### Behavior notes

- `fill()` is a no-op and `shouldSkipFill()` returns `true` — no data is written.
- `getColumnsForSelect()` returns an empty array (no DB column).
- It is not a relationship and is excluded from index/show queries (`applyInIndexQuery`/`applyInShowQuery` are `false`).
- `getProps()` emits `componentPath`, `position`, `componentProps`, and (when a data callback is set and a model is present) `componentData`.

## Lightweight custom component on any field

For a one-off component without a dedicated class, any field can point at a custom frontend component via the base API:

```php
Input::make('Rating', 'rating')
    ->componentPath('@/components/fields/StarRating.vue');
```

`componentPath()` also marks the field as a custom field (`customField()`), and the path is emitted under `props.componentPath`. Use `withData()` to attach dynamic data and `skipFill()` / `afterSave()` if the field manages its own persistence.

## Building a custom field class

To create a reusable field type, extend `SchoolAid\Nadota\Http\Fields\Field` and set the type/component in the constructor. Override the lifecycle methods you need.

```php
<?php

namespace App\Nadota\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Field;

class Rating extends Field
{
    protected int $maxStars = 5;

    public function __construct(string $label, string $attribute)
    {
        parent::__construct($label, $attribute, 'rating', 'FieldRating');
    }

    public function maxStars(int $stars): static
    {
        $this->maxStars = $stars;
        return $this;
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'maxStars' => $this->maxStars,
        ]);
    }

    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        return (int) ($model->{$this->getAttribute()} ?? 0);
    }
}
```

```php
use App\Nadota\Fields\Rating;

Rating::make('Score', 'score')->maxStars(10)->sortable();
```

### What you can override

These are the main extension points on the base `Field` (see [README.md](README.md#shared-field-api) for full signatures):

- `getProps()` — add component props (always merge with `parent::getProps()`).
- `resolve()` — compute the display value.
- `resolveForStore()` / `resolveForUpdate()` — transform the value before persistence.
- `fill()` — assign the value to the model (or skip and use `afterSave()`).
- `getRules()` — append field-specific validation rules (merge with `parent::getRules()`).
- `beforeSave()` / `afterSave()` — pre/post-save logic; return `true` from `supportsAfterSave()` if you need the model ID.
- `getColumnsForSelect()` — declare which columns the field needs in SELECT (return `[]` for fields with no DB column).
- `resolveForExport()` — customize the exported value.

### Custom components shorthand

If a custom field maps to a frontend component, prefer setting the component name in the constructor (as above) or use `componentPath()` for path-based dynamic imports. To skip persistence entirely, call `skipFill()` in the constructor and handle data in `afterSave()`.

Existing built-in fields are good references — see `Currency`, `Status`, and `Signature` for examples of custom `getProps()`, `getRules()`, `resolve()`, and `fill()` implementations.
