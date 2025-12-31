# Field Dependencies (dependsOn)

Field dependencies allow fields to react dynamically to changes in other fields on the frontend, without requiring server requests.

## Table of Contents

- [Overview](#overview)
- [Basic Usage](#basic-usage)
- [Visibility Conditions](#visibility-conditions)
- [Disabled State](#disabled-state)
- [Required State](#required-state)
- [Dynamic Options](#dynamic-options)
- [Computed Values](#computed-values)
- [Behavior Options](#behavior-options)
- [JSON Output](#json-output)
- [Frontend Integration](#frontend-integration)
- [Examples](#examples)

---

## Overview

The `dependsOn` system enables:

| Feature | Description |
|---------|-------------|
| **Conditional Visibility** | Show/hide fields based on other field values |
| **Dynamic Disabled State** | Enable/disable fields conditionally |
| **Conditional Required** | Make fields required based on conditions |
| **Dynamic Options** | Load options from API when dependencies change |
| **Computed Values** | Calculate field values from formulas |
| **Cascade Selects** | Chain dependent dropdowns (Country → State → City) |

All conditions are evaluated on the **frontend** for instant reactivity.

---

## Basic Usage

### Simple Dependency

```php
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Select;

// Field observes another field
Input::make('City', 'city')
    ->dependsOn('country_id');

// Observe multiple fields
Input::make('Total', 'total')
    ->dependsOn(['quantity', 'price']);
```

### Chaining Dependencies

```php
Select::make('District', 'district_id')
    ->dependsOn('country_id')
    ->dependsOn('state_id');
```

---

## Visibility Conditions

Control when a field is visible based on other field values.

### showWhenEquals

Show field when another field equals a specific value.

```php
Input::make('Company Name', 'company_name')
    ->showWhenEquals('customer_type', 'business');

Input::make('Tax ID', 'tax_id')
    ->showWhenEquals('customer_type', 'business')
    ->requiredWhenEquals('customer_type', 'business');
```

### showWhenNotEquals

Show field when another field does NOT equal a value.

```php
Input::make('Reason', 'cancellation_reason')
    ->showWhenNotEquals('status', 'completed');
```

### showWhenHasValue

Show field when another field has any non-empty value.

```php
Select::make('City', 'city_id')
    ->showWhenHasValue('country_id')
    ->cascadeFrom('country_id');
```

### showWhenEmpty

Show field when another field is empty.

```php
Input::make('Manual Entry', 'manual_value')
    ->showWhenEmpty('auto_value');
```

### showWhenIn / showWhenNotIn

Show field when value is in (or not in) a list.

```php
Input::make('Enterprise Features', 'enterprise_config')
    ->showWhenIn('plan', ['enterprise', 'premium']);

Input::make('Basic Info', 'basic_info')
    ->showWhenNotIn('plan', ['trial', 'free']);
```

### showWhenTruthy / showWhenFalsy

Show field based on boolean-like values.

```php
Input::make('Billing Address', 'billing_address')
    ->showWhenTruthy('requires_invoice');

Input::make('Default Address', 'default_address')
    ->showWhenFalsy('use_custom_address');
```

### showWhenGreaterThan / showWhenLessThan

Show field based on numeric comparisons.

```php
Input::make('Bulk Discount Code', 'bulk_discount')
    ->showWhenGreaterThan('quantity', 100);

Input::make('Low Stock Warning', 'reorder_note')
    ->showWhenLessThan('stock', 10);
```

### showWhenContains

Show field when another field contains a value.

```php
Input::make('Corporate Email Note', 'corp_note')
    ->showWhenContains('email', '@company.com');
```

### Combining Conditions

Multiple visibility conditions work as AND logic.

```php
Input::make('Premium Business Feature', 'premium_feature')
    ->showWhenEquals('customer_type', 'business')
    ->showWhenIn('plan', ['premium', 'enterprise']);
// Shows only when: customer_type = 'business' AND plan in ['premium', 'enterprise']
```

---

## Disabled State

Control when a field is disabled.

### disableWhenEquals

```php
Input::make('Price', 'price')
    ->disableWhenEquals('status', 'locked');
```

### disableWhenEmpty

```php
Select::make('Sub-Category', 'sub_category_id')
    ->disableWhenEmpty('category_id')
    ->cascadeFrom('category_id');
```

### disableWhenHasValue

```php
Input::make('Manual SKU', 'manual_sku')
    ->disableWhenHasValue('auto_sku');
```

### disableWhenTruthy / disableWhenFalsy

```php
Input::make('Editable Field', 'content')
    ->disableWhenTruthy('is_locked');

Select::make('Premium Feature', 'premium_option')
    ->disableWhenFalsy('has_premium');
```

---

## Required State

Make fields conditionally required based on other values.

### requiredWhenEquals

```php
Input::make('Business License', 'license_number')
    ->requiredWhenEquals('entity_type', 'business');
```

### requiredWhenHasValue

```php
Input::make('Password Confirmation', 'password_confirmation')
    ->requiredWhenHasValue('password');
```

### requiredWhenTruthy

```php
Textarea::make('Additional Details', 'details')
    ->requiredWhenTruthy('needs_details');
```

### requiredWhenIn

```php
Input::make('Tax ID', 'tax_id')
    ->requiredWhenIn('entity_type', ['business', 'nonprofit']);
```

---

## Dynamic Options

Load field options dynamically based on other field values.

### optionsFromEndpoint

Fetch options from an API endpoint.

```php
Select::make('City', 'city_id')
    ->optionsFromEndpoint('/api/cities', 'country_id')
    ->showWhenHasValue('country_id')
    ->clearOnDependencyChange();

// With custom parameter name
Select::make('State', 'state_id')
    ->optionsFromEndpoint('/api/states', 'country_id', 'countryCode');
// Calls: /api/states?countryCode={country_id_value}
```

### cascadeFrom

Cascade options from another BelongsTo/Select field using the field's optionsUrl.

```php
// Country → State → City cascade
BelongsTo::make('Country', 'country', CountryResource::class)
    ->searchable();

BelongsTo::make('State', 'state', StateResource::class)
    ->cascadeFrom('country_id')
    ->clearOnDependencyChange();

BelongsTo::make('City', 'city', CityResource::class)
    ->cascadeFrom('state_id')
    ->clearOnDependencyChange();
```

---

## Computed Values

Calculate field values from formulas evaluated on the frontend.

### computeUsing

Define a formula to compute the field value.

```php
// Simple multiplication
Number::make('Total', 'total')
    ->computeUsing('quantity * price')
    ->readonly();

// Multiple operations
Number::make('Grand Total', 'grand_total')
    ->computeUsing('subtotal + tax - discount')
    ->readonly();

// With explicit field list
Number::make('Result', 'result')
    ->computeUsing('a + b', ['field_a', 'field_b']);
```

**Supported operations:**
- Arithmetic: `+`, `-`, `*`, `/`
- Parentheses: `(subtotal + tax) * rate`
- Field references by key name

**Auto-detected fields:**
The formula is parsed to automatically detect field references. Common math functions (`Math`, `abs`, `ceil`, `floor`, `round`, `min`, `max`, `pow`, `sqrt`) are excluded from auto-detection.

---

## Behavior Options

### clearOnDependencyChange

Clear the field value when any dependency changes.

```php
Select::make('City', 'city_id')
    ->cascadeFrom('country_id')
    ->clearOnDependencyChange();
// When country changes, city is cleared
```

### debounce

Set debounce time for dependency updates (useful for computed fields with text inputs).

```php
Input::make('Search Results', 'results')
    ->dependsOn('search_query')
    ->debounce(300); // Wait 300ms after last change
```

---

## JSON Output

The `toArray()` method includes a `dependencies` key with the configuration:

```php
Select::make('City', 'city_id')
    ->showWhenHasValue('country_id')
    ->cascadeFrom('country_id')
    ->clearOnDependencyChange();
```

Produces:

```json
{
  "key": "city_id",
  "label": "City",
  "type": "select",
  "dependencies": {
    "fields": ["country_id"],
    "visibility": [
      {
        "field": "country_id",
        "operator": "hasValue"
      }
    ],
    "options": {
      "cascadeFrom": "country_id"
    },
    "clearOnChange": true
  }
}
```

### Dependency Configuration Structure

| Key | Type | Description |
|-----|------|-------------|
| `fields` | `string[]` | List of field keys this field depends on |
| `visibility` | `array[]` | Conditions for showing/hiding the field |
| `disabled` | `array[]` | Conditions for disabling the field |
| `required` | `array[]` | Conditions for making field required |
| `options` | `object` | Dynamic options configuration |
| `compute` | `string` | Formula for computed value |
| `clearOnChange` | `boolean` | Whether to clear value on dependency change |
| `debounce` | `number` | Debounce time in milliseconds |

### Condition Structure

```json
{
  "field": "country_id",
  "operator": "equals",
  "value": "US"
}
```

### Available Operators

| Operator | Requires Value | Description |
|----------|----------------|-------------|
| `equals` | Yes | Field equals value |
| `notEquals` | Yes | Field does not equal value |
| `greaterThan` | Yes | Field is greater than value |
| `lessThan` | Yes | Field is less than value |
| `greaterThanOrEquals` | Yes | Field is >= value |
| `lessThanOrEquals` | Yes | Field is <= value |
| `hasValue` | No | Field has any non-empty value |
| `isEmpty` | No | Field is empty/null/undefined |
| `isTruthy` | No | Field is truthy (true, 1, "1", "yes") |
| `isFalsy` | No | Field is falsy (false, 0, "0", "no", null) |
| `in` | Yes (array) | Field value is in array |
| `notIn` | Yes (array) | Field value is not in array |
| `contains` | Yes | Field contains substring |
| `notContains` | Yes | Field does not contain substring |
| `startsWith` | Yes | Field starts with value |
| `endsWith` | Yes | Field ends with value |
| `matches` | Yes | Field matches regex pattern |

---

## Frontend Integration

The frontend should implement a dependency resolver that:

1. **Watches** all fields listed in `dependencies.fields`
2. **Evaluates** conditions when watched fields change
3. **Updates** field state (visibility, disabled, required, value)
4. **Fetches** options when needed

### Pseudocode Implementation

```javascript
function evaluateConditions(conditions, formValues) {
  return conditions.every(condition => {
    const value = formValues[condition.field];

    switch (condition.operator) {
      case 'equals':
        return value === condition.value;
      case 'notEquals':
        return value !== condition.value;
      case 'hasValue':
        return value != null && value !== '';
      case 'isEmpty':
        return value == null || value === '';
      case 'isTruthy':
        return Boolean(value);
      case 'isFalsy':
        return !value;
      case 'in':
        return condition.value.includes(value);
      case 'notIn':
        return !condition.value.includes(value);
      case 'greaterThan':
        return Number(value) > Number(condition.value);
      case 'lessThan':
        return Number(value) < Number(condition.value);
      case 'contains':
        return String(value).includes(condition.value);
      // ... other operators
    }
  });
}

function updateFieldState(field, formValues) {
  const deps = field.dependencies;
  if (!deps) return;

  // Visibility
  if (deps.visibility?.length) {
    field.visible = evaluateConditions(deps.visibility, formValues);
  }

  // Disabled
  if (deps.disabled?.length) {
    field.disabled = evaluateConditions(deps.disabled, formValues);
  }

  // Required
  if (deps.required?.length) {
    field.required = evaluateConditions(deps.required, formValues);
  }

  // Computed value
  if (deps.compute) {
    field.value = evaluateFormula(deps.compute, formValues);
  }
}
```

---

## Examples

### Complete Registration Form

```php
public function fields(Request $request): array
{
    return [
        // Basic info
        Input::make('Name', 'name')
            ->required(),

        Email::make('Email', 'email')
            ->required(),

        // Customer type selection
        Select::make('Customer Type', 'customer_type')
            ->options([
                'individual' => 'Individual',
                'business' => 'Business',
            ])
            ->required(),

        // Business fields - only for business customers
        Input::make('Company Name', 'company_name')
            ->showWhenEquals('customer_type', 'business')
            ->requiredWhenEquals('customer_type', 'business'),

        Input::make('Tax ID', 'tax_id')
            ->showWhenEquals('customer_type', 'business')
            ->requiredWhenEquals('customer_type', 'business'),

        // Location cascade
        BelongsTo::make('Country', 'country', CountryResource::class)
            ->required(),

        BelongsTo::make('State', 'state', StateResource::class)
            ->showWhenHasValue('country_id')
            ->cascadeFrom('country_id')
            ->clearOnDependencyChange(),

        BelongsTo::make('City', 'city', CityResource::class)
            ->showWhenHasValue('state_id')
            ->cascadeFrom('state_id')
            ->clearOnDependencyChange(),
    ];
}
```

### Order Form with Calculations

```php
public function fields(Request $request): array
{
    return [
        BelongsTo::make('Product', 'product', ProductResource::class)
            ->required(),

        Number::make('Quantity', 'quantity')
            ->required()
            ->min(1)
            ->default(1),

        Number::make('Unit Price', 'unit_price')
            ->required()
            ->step(0.01),

        // Calculated fields
        Number::make('Subtotal', 'subtotal')
            ->computeUsing('quantity * unit_price')
            ->readonly(),

        Number::make('Tax (10%)', 'tax')
            ->computeUsing('subtotal * 0.10')
            ->readonly(),

        // Discount only for large orders
        Number::make('Discount', 'discount')
            ->showWhenGreaterThan('quantity', 10)
            ->default(0),

        Number::make('Total', 'total')
            ->computeUsing('subtotal + tax - discount')
            ->readonly(),
    ];
}
```

### Conditional Form Sections

```php
public function fields(Request $request): array
{
    return [
        Select::make('Payment Method', 'payment_method')
            ->options([
                'credit_card' => 'Credit Card',
                'bank_transfer' => 'Bank Transfer',
                'cash' => 'Cash',
            ])
            ->required(),

        // Credit card fields
        Input::make('Card Number', 'card_number')
            ->showWhenEquals('payment_method', 'credit_card')
            ->requiredWhenEquals('payment_method', 'credit_card'),

        Input::make('Expiry Date', 'card_expiry')
            ->showWhenEquals('payment_method', 'credit_card')
            ->requiredWhenEquals('payment_method', 'credit_card'),

        Input::make('CVV', 'card_cvv')
            ->showWhenEquals('payment_method', 'credit_card')
            ->requiredWhenEquals('payment_method', 'credit_card'),

        // Bank transfer fields
        Input::make('Bank Name', 'bank_name')
            ->showWhenEquals('payment_method', 'bank_transfer')
            ->requiredWhenEquals('payment_method', 'bank_transfer'),

        Input::make('Account Number', 'account_number')
            ->showWhenEquals('payment_method', 'bank_transfer')
            ->requiredWhenEquals('payment_method', 'bank_transfer'),

        // Cash - no additional fields needed
        Input::make('Receipt Number', 'receipt_number')
            ->showWhenEquals('payment_method', 'cash'),
    ];
}
```

### Multi-Condition Visibility

```php
Input::make('VIP Features', 'vip_config')
    ->showWhenEquals('customer_type', 'business')
    ->showWhenIn('plan', ['premium', 'enterprise'])
    ->showWhenGreaterThan('total_purchases', 10000);
// All conditions must be true (AND logic)
```

---

## Best Practices

1. **Always use `clearOnDependencyChange()`** for cascade selects to avoid stale values

2. **Combine visibility with required** when showing conditional fields:
   ```php
   ->showWhenEquals('type', 'business')
   ->requiredWhenEquals('type', 'business')
   ```

3. **Use `readonly()` for computed fields** to prevent user editing:
   ```php
   ->computeUsing('quantity * price')
   ->readonly()
   ```

4. **Add debounce for computed fields** when depending on text inputs:
   ```php
   ->computeUsing('search_query')
   ->debounce(300)
   ```

5. **Keep formulas simple** - complex calculations should be done server-side

6. **Test cascade chains** thoroughly - ensure each level clears properly

7. **Consider mobile UX** - hidden fields should not disrupt form layout
