# Exists

`SchoolAid\Nadota\Http\Fields\Exists` (`type: boolean`, component `FieldExists`) is a read-only field that shows whether a relation has at least one related record. It uses Laravel's `withExists()` so the check is performed in a single query without loading the related models.

It inherits the shared API from [README.md](README.md#shared-field-api).

## Quick start

```php
use SchoolAid\Nadota\Http\Fields\Exists;

Exists::make('Has Orders', 'orders');
```

Construct with `Exists::make(string $name, string $relation)`. The attribute is derived as `{relation}_exists` (snake_case) — the name Laravel uses for the `withExists()` virtual column. `resolve()` returns a boolean.

## Defaults

The constructor configures the field as:

- **Computed and read-only** — never filled or persisted.
- **Visible on index and detail**, hidden from create/update forms.
- **Applied in index and show queries** so the `withExists` flag is loaded.
- **No SELECT columns** (`getColumnsForSelect()` returns `[]`) because the exists flag is a virtual column.

## Constraining the relation

```php
public function constraint(\Closure $callback): static
```

```php
Exists::make('Has Paid Orders', 'orders')
    ->constraint(fn ($query) => $query->where('status', 'paid'));
```

## Filtering

When `filterable()` is enabled, `Exists` provides a custom `SchoolAid\Nadota\Http\Filters\ExistsFilter` instead of the auto-generated boolean filter. This is required because the virtual `*_exists` column cannot be used in a `WHERE` clause; the filter instead applies `whereHas` / `whereDoesntHave` (respecting the configured constraint).

```php
Exists::make('Has Orders', 'orders')->filterable();
```

See [../filters/README.md](../filters/README.md) for the filter request/response format.

## Inspection helpers

| Method | Description |
| ------ | ----------- |
| `getExistsRelation()` | The relation name being checked. |
| `getExistsConstraint()` | The constraint closure, if any. |
| `requiresWithExists()` | Whether the query needs `withExists` (always `true`). |

> Note: the public `applyFilter()` method on this class is deprecated and not used by the filter system — filtering goes through the `ExistsFilter` returned by `filters()`.

## Related

For counting related records instead of checking existence, use the [`Count`](basic-fields.md#count) field.
