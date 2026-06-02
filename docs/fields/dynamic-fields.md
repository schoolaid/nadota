# DynamicField

`SchoolAid\Nadota\Http\Fields\DynamicField` (`type: dynamic`, component `FieldDynamic`) renders a different field type depending on the value of another attribute. It is useful when one column's meaning changes based on a "type" column — for example a `value` column whose editor depends on a `field_type` column.

It inherits the full shared API from [README.md](README.md#shared-field-api).

## Quick start

```php
use SchoolAid\Nadota\Http\Fields\DynamicField;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Date;
use SchoolAid\Nadota\Http\Fields\Toggle;

DynamicField::make('Value', 'value')
    ->basedOn('field_type')
    ->types([
        'text'    => Input::make('Value', 'value'),
        'number'  => Number::make('Value', 'value'),
        'date'    => Date::make('Value', 'value'),
        'boolean' => Toggle::make('Value', 'value'),
    ])
    ->defaultType(Input::make('Value', 'value'));
```

When the model's `field_type` is `number`, the `Number` field is used to resolve, fill, and validate the `value` attribute; when it is `date`, the `Date` field is used, and so on.

## How it resolves

`getTypeValue()` reads the type from the model first, falling back to the request. The value is normalized:

- `BackedEnum` → its backing value
- `UnitEnum` → its case name
- Eloquent `Model` → its primary key
- anything else → used as-is

The normalized value is looked up in the type map. If there is no match (or the type is `null`), the default field is used. If there is no default field either, the raw model attribute is returned.

`basedOn()` supports dot notation for relations (e.g. `'formItem.type'`); in that case the relation is eager-loaded automatically.

## Closures as types

A map entry may be a `Closure` instead of a `Field`. It receives `($model, $request)` and must return a `Field`. This lets you build the field lazily per record:

```php
DynamicField::make('Value', 'value')
    ->basedOn('field_type')
    ->types([
        'select' => fn ($model, $request) =>
            Select::make('Value', 'value')->options($model->getOptionsArray()),
    ]);
```

## API

| Method | Description |
| ------ | ----------- |
| `basedOn(string $field)` | Attribute (or `relation.attribute`) that selects the type. Also registers a dependency. |
| `types(array $types)` | Map of `typeValue => Field\|Closure`. |
| `when(mixed $value, Field\|Closure $field)` | Add a single type mapping. |
| `defaultType(Field $field)` | Field used when no type matches. |
| `withAllTypes()` | Include every type's serialized config in the response (for frontends that switch fields client-side). |
| `onlyMatchedType()` | Include only the matched type's config (default behavior; kept for backwards compatibility). |

### Inspection helpers

| Method | Description |
| ------ | ----------- |
| `getTypeFieldRelation()` | The relation name if `basedOn()` used dot notation, else `null`. |
| `getRequiredRelations()` | Relations that must be eager-loaded. |
| `getNestedRelationFields()` | Non-closure mapped fields that are relationships. |
| `hasNestedRelations()` | Whether any mapped field is a relationship. |

## Behavior notes

- **Lifecycle delegation.** `resolve()`, `fill()`, `beforeSave()`, `afterSave()`, and `supportsAfterSave()` all delegate to the resolved field. `getRules()` merges the base rules with the resolved field's rules.
- **SELECT columns.** `getColumnsForSelect()` includes the type column (when not a dot-path relation) plus the columns required by every mapped field and the default field.
- **Response payload.** `toArray()` adds to `props`: `typeField`, `isDynamic`, `matchedType`, `matchedField`, and `defaultField`. When `withAllTypes()` is set, `props.types` contains every mapped field serialized.

## Validation caveat

During request validation the model may not be available yet, so type-specific rules cannot always be resolved server-side. The frontend is expected to apply type-specific validation, and `getRules()` only adds the resolved field's rules when a field has already been resolved.
