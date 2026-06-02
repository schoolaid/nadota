# Conditional Field Dependencies (`dependsOn`)

Every field can react to the value of other fields in the same form: showing/hiding itself, becoming disabled or required, loading options dynamically, or computing its value. This is provided by `SchoolAid\Nadota\Http\Fields\Traits\DependsOnTrait`, included in the base `Field`.

The conditions are evaluated **in the frontend** — they are serialized into the field's `dependencies` payload (`DependencyDTO`) and the client applies them reactively. No server round-trip is needed for visibility/required/disabled state.

## Quick start

```php
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Input;

Select::make('Type', 'type')->options(['person' => 'Person', 'company' => 'Company']);

Input::make('Company name', 'company_name')
    ->showWhenEquals('type', 'company')
    ->requiredWhenEquals('type', 'company');
```

The `company_name` field is hidden until `type` equals `company`, at which point it becomes visible and required.

## Declaring dependencies

```php
public function dependsOn(string|array $fields): static
```

`dependsOn()` registers the fields to observe. The `showWhen*`, `disableWhen*`, `requiredWhen*`, `cascadeFrom`, `optionsFromEndpoint`, and `computeUsing` helpers call it for you, so you rarely call it directly.

## Visibility conditions

| Method | Shows the field when the observed field… |
| ------ | ---------------------------------------- |
| `showWhenEquals(string $field, mixed $value)` | equals `$value`. |
| `showWhenNotEquals(string $field, mixed $value)` | does not equal `$value`. |
| `showWhenHasValue(string $field)` | has any non-empty value. |
| `showWhenEmpty(string $field)` | is empty. |
| `showWhenIn(string $field, array $values)` | is in `$values`. |
| `showWhenNotIn(string $field, array $values)` | is not in `$values`. |
| `showWhenTruthy(string $field)` | is truthy. |
| `showWhenFalsy(string $field)` | is falsy. |
| `showWhenGreaterThan(string $field, mixed $value)` | is greater than `$value`. |
| `showWhenLessThan(string $field, mixed $value)` | is less than `$value`. |
| `showWhenContains(string $field, mixed $value)` | contains `$value`. |

## Disabled-state conditions

| Method | Disables the field when the observed field… |
| ------ | ------------------------------------------- |
| `disableWhenEquals(string $field, mixed $value)` | equals `$value`. |
| `disableWhenEmpty(string $field)` | is empty. |
| `disableWhenHasValue(string $field)` | has a value. |
| `disableWhenTruthy(string $field)` | is truthy. |
| `disableWhenFalsy(string $field)` | is falsy. |

## Required-state conditions

| Method | Makes the field required when the observed field… |
| ------ | ------------------------------------------------- |
| `requiredWhenEquals(string $field, mixed $value)` | equals `$value`. |
| `requiredWhenHasValue(string $field)` | has a value. |
| `requiredWhenTruthy(string $field)` | is truthy. |
| `requiredWhenIn(string $field, array $values)` | is in `$values`. |

## Dynamic options

```php
public function optionsFromEndpoint(string $endpoint, ?string $paramField = null, ?string $paramName = null): static
public function cascadeFrom(string $field): static
```

- `optionsFromEndpoint()` tells the frontend to fetch options from `$endpoint`. If `$paramField` is given, it is observed and sent as the query parameter `$paramName` (which defaults to `$paramField`).
- `cascadeFrom()` is the classic cascading dropdown (e.g. cities depending on country) — the field's own options URL is called with the parent field's value as the parameter.

```php
Select::make('City', 'city_id')->cascadeFrom('country_id');

Select::make('Manager', 'manager_id')
    ->optionsFromEndpoint('/api/managers', 'department_id', 'department');
```

## Computed values

```php
public function computeUsing(string $formula, array $fields = []): static
```

The formula is evaluated client-side and may reference other field keys. Field references are auto-detected from the formula when `$fields` is omitted (common math helpers like `Math`, `abs`, `ceil`, `floor`, `round`, `min`, `max`, `pow`, `sqrt` are ignored).

```php
Number::make('Total', 'total')->computeUsing('quantity * price');
```

## Behavior options

| Method | Description |
| ------ | ----------- |
| `clearOnDependencyChange(bool $clear = true)` | Clear this field's value whenever a dependency changes. |
| `debounce(int $milliseconds)` | Debounce dependency-driven updates. |

## Inspection

| Method | Description |
| ------ | ----------- |
| `hasDependencies()` | Whether any dependency is configured. |
| `getDependsOnFields()` | The observed field keys. |
| `getDependencyConfig()` | The serialized dependency array (also emitted as `dependencies` in `toArray()`). |

## Operators

The conditions map to `SchoolAid\Nadota\Http\Fields\Enums\DependencyOperator`:

`equals`, `notEquals`, `greaterThan`, `lessThan`, `greaterThanOrEquals`, `lessThanOrEquals`, `hasValue`, `isEmpty`, `isTruthy`, `isFalsy`, `in`, `notIn`, `contains`, `notContains`, `startsWith`, `endsWith`, `matches`.

`requiresValue()` returns `false` for `hasValue`, `isEmpty`, `isTruthy`, `isFalsy` (these omit the `value` key in the payload). `expectsArray()` returns `true` for `in` and `notIn`.

## Serialized payload

`DependencyDTO::toArray()` produces (omitting empty sections):

```json
{
  "fields": ["type"],
  "visibility": [{ "field": "type", "operator": "equals", "value": "company" }],
  "disabled":   [{ "field": "locked", "operator": "isTruthy" }],
  "required":   [{ "field": "type", "operator": "equals", "value": "company" }],
  "options":    { "endpoint": "/api/cities", "paramField": "country_id", "paramName": "country" },
  "compute":    "quantity * price",
  "clearOnChange": true,
  "debounce": 300
}
```

This block appears under the field's `dependencies` key in the API response — see [../api/responses.md](../api/responses.md).
