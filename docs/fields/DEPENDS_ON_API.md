# Field Dependencies - Frontend API Reference

Technical documentation for frontend developers implementing the field dependency system.

## Table of Contents

- [Response Structure](#response-structure)
- [Dependency Object](#dependency-object)
- [Operators Reference](#operators-reference)
- [Condition Evaluation](#condition-evaluation)
- [Implementation Guide](#implementation-guide)
- [TypeScript Definitions](#typescript-definitions)
- [Vue.js Implementation](#vuejs-implementation)
- [Edge Cases](#edge-cases)

---

## Response Structure

Fields include a `dependencies` key in their JSON representation. When empty, no dependencies exist.

### Field with Dependencies

```json
{
  "key": "city_id",
  "label": "City",
  "attribute": "city_id",
  "type": "belongsTo",
  "component": "field-belongs-to",
  "value": null,
  "required": false,
  "disabled": false,
  "readonly": false,
  "showOnIndex": true,
  "showOnDetail": true,
  "showOnCreation": true,
  "showOnUpdate": true,
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

### Field without Dependencies

```json
{
  "key": "name",
  "label": "Name",
  "type": "text",
  "dependencies": {}
}
```

---

## Dependency Object

### Complete Structure

```typescript
interface FieldDependencies {
  // Fields this field observes for changes
  fields?: string[];

  // Conditions for showing/hiding the field
  visibility?: Condition[];

  // Conditions for disabling the field
  disabled?: Condition[];

  // Conditions for making field required
  required?: Condition[];

  // Dynamic options configuration
  options?: OptionsConfig;

  // Formula for computed value (frontend evaluation)
  compute?: string;

  // Clear value when any dependency changes
  clearOnChange?: boolean;

  // Debounce time in milliseconds
  debounce?: number;
}
```

### Condition Structure

```typescript
interface Condition {
  // The field key to evaluate
  field: string;

  // The comparison operator
  operator: OperatorType;

  // The value to compare against (optional for some operators)
  value?: any;
}
```

### Options Configuration

```typescript
interface OptionsConfig {
  // API endpoint to fetch options
  endpoint?: string;

  // Field to use as parameter value
  paramField?: string;

  // Query parameter name
  paramName?: string;

  // Use another field's optionsUrl with its value
  cascadeFrom?: string;
}
```

---

## Operators Reference

### Comparison Operators

| Operator | Value Required | Value Type | Description |
|----------|----------------|------------|-------------|
| `equals` | Yes | `any` | Strict equality (`===`) |
| `notEquals` | Yes | `any` | Strict inequality (`!==`) |
| `greaterThan` | Yes | `number` | Greater than (`>`) |
| `lessThan` | Yes | `number` | Less than (`<`) |
| `greaterThanOrEquals` | Yes | `number` | Greater than or equal (`>=`) |
| `lessThanOrEquals` | Yes | `number` | Less than or equal (`<=`) |

### Presence Operators

| Operator | Value Required | Description |
|----------|----------------|-------------|
| `hasValue` | No | Field is not null, undefined, or empty string |
| `isEmpty` | No | Field is null, undefined, or empty string |
| `isTruthy` | No | Field is truthy (`true`, `1`, `"1"`, `"yes"`, `"true"`) |
| `isFalsy` | No | Field is falsy (`false`, `0`, `"0"`, `"no"`, `"false"`, `null`, `undefined`, `""`) |

### Collection Operators

| Operator | Value Required | Value Type | Description |
|----------|----------------|------------|-------------|
| `in` | Yes | `array` | Field value exists in array |
| `notIn` | Yes | `array` | Field value does not exist in array |
| `contains` | Yes | `string` | Field value contains substring |
| `notContains` | Yes | `string` | Field value does not contain substring |

### String Operators

| Operator | Value Required | Value Type | Description |
|----------|----------------|------------|-------------|
| `startsWith` | Yes | `string` | Field value starts with string |
| `endsWith` | Yes | `string` | Field value ends with string |
| `matches` | Yes | `string` | Field value matches regex pattern |

---

## Condition Evaluation

### Multiple Conditions = AND Logic

When multiple conditions exist in the same array, ALL must be true (AND logic).

```json
{
  "visibility": [
    { "field": "type", "operator": "equals", "value": "business" },
    { "field": "country", "operator": "equals", "value": "US" }
  ]
}
```

**Result:** Field is visible only when `type === "business" AND country === "US"`

### Evaluation Order

1. Check `visibility` conditions → Set `field.visible`
2. Check `disabled` conditions → Set `field.disabled`
3. Check `required` conditions → Set `field.required`
4. Evaluate `compute` formula → Set `field.value`

### Truthy/Falsy Values

The `isTruthy` and `isFalsy` operators should handle these values:

```javascript
// Truthy values
const TRUTHY = [true, 1, "1", "yes", "true", "on"];

// Falsy values
const FALSY = [false, 0, "0", "no", "false", "off", null, undefined, ""];

function isTruthy(value) {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return value === 1;
  if (typeof value === 'string') {
    return ['1', 'yes', 'true', 'on'].includes(value.toLowerCase());
  }
  return false;
}

function isFalsy(value) {
  return !isTruthy(value) || value === null || value === undefined || value === '';
}
```

---

## Implementation Guide

### Step 1: Extract Dependent Fields

On form initialization, build a map of which fields depend on which:

```javascript
function buildDependencyMap(fields) {
  const map = new Map(); // parentField -> [dependentFields]

  for (const field of fields) {
    const deps = field.dependencies?.fields || [];
    for (const depField of deps) {
      if (!map.has(depField)) {
        map.set(depField, []);
      }
      map.get(depField).push(field.key);
    }
  }

  return map;
}
```

### Step 2: Watch for Changes

When a field value changes, update all dependent fields:

```javascript
function onFieldChange(changedFieldKey, newValue, formValues) {
  const dependents = dependencyMap.get(changedFieldKey) || [];

  for (const dependentKey of dependents) {
    const field = getFieldByKey(dependentKey);
    updateFieldState(field, formValues);
  }
}
```

### Step 3: Evaluate Conditions

```javascript
function evaluateCondition(condition, formValues) {
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
      return isTruthy(value);

    case 'isFalsy':
      return isFalsy(value);

    case 'in':
      return Array.isArray(condition.value) && condition.value.includes(value);

    case 'notIn':
      return Array.isArray(condition.value) && !condition.value.includes(value);

    case 'greaterThan':
      return Number(value) > Number(condition.value);

    case 'lessThan':
      return Number(value) < Number(condition.value);

    case 'greaterThanOrEquals':
      return Number(value) >= Number(condition.value);

    case 'lessThanOrEquals':
      return Number(value) <= Number(condition.value);

    case 'contains':
      return String(value || '').includes(String(condition.value));

    case 'notContains':
      return !String(value || '').includes(String(condition.value));

    case 'startsWith':
      return String(value || '').startsWith(String(condition.value));

    case 'endsWith':
      return String(value || '').endsWith(String(condition.value));

    case 'matches':
      try {
        return new RegExp(condition.value).test(String(value || ''));
      } catch {
        return false;
      }

    default:
      console.warn(`Unknown operator: ${condition.operator}`);
      return true;
  }
}

function evaluateConditions(conditions, formValues) {
  if (!conditions || conditions.length === 0) return true;
  return conditions.every(c => evaluateCondition(c, formValues));
}
```

### Step 4: Update Field State

```javascript
function updateFieldState(field, formValues) {
  const deps = field.dependencies;
  if (!deps || Object.keys(deps).length === 0) return;

  // Visibility
  if (deps.visibility && deps.visibility.length > 0) {
    field.visible = evaluateConditions(deps.visibility, formValues);
  }

  // Disabled state
  if (deps.disabled && deps.disabled.length > 0) {
    field.isDisabled = evaluateConditions(deps.disabled, formValues);
  }

  // Required state
  if (deps.required && deps.required.length > 0) {
    field.isRequired = evaluateConditions(deps.required, formValues);
  }

  // Computed value
  if (deps.compute) {
    field.value = evaluateFormula(deps.compute, formValues);
  }
}
```

### Step 5: Handle Clear on Change

```javascript
function onFieldChange(changedFieldKey, newValue, formValues) {
  const dependents = dependencyMap.get(changedFieldKey) || [];

  for (const dependentKey of dependents) {
    const field = getFieldByKey(dependentKey);

    // Clear value if configured
    if (field.dependencies?.clearOnChange) {
      formValues[dependentKey] = null;
      field.value = null;
    }

    updateFieldState(field, formValues);
  }
}
```

### Step 6: Handle Dynamic Options

```javascript
async function loadDependentOptions(field, formValues) {
  const opts = field.dependencies?.options;
  if (!opts) return;

  let url = null;
  let params = {};

  if (opts.cascadeFrom) {
    // Use the parent field's optionsUrl
    const parentField = getFieldByKey(opts.cascadeFrom);
    const parentValue = formValues[opts.cascadeFrom];

    if (!parentValue) {
      field.options = [];
      return;
    }

    // Append parent value to field's optionsUrl
    url = field.optionsUrl;
    params[opts.cascadeFrom] = parentValue;

  } else if (opts.endpoint) {
    url = opts.endpoint;

    if (opts.paramField) {
      const paramValue = formValues[opts.paramField];
      if (!paramValue) {
        field.options = [];
        return;
      }
      params[opts.paramName || opts.paramField] = paramValue;
    }
  }

  if (url) {
    const queryString = new URLSearchParams(params).toString();
    const fullUrl = queryString ? `${url}?${queryString}` : url;

    try {
      const response = await fetch(fullUrl);
      const data = await response.json();
      field.options = data.options || data.data || data;
    } catch (error) {
      console.error('Failed to load options:', error);
      field.options = [];
    }
  }
}
```

### Step 7: Evaluate Computed Formula

```javascript
function evaluateFormula(formula, formValues) {
  try {
    // Replace field references with values
    let expression = formula;

    // Match field names (word characters, not starting with number)
    const fieldPattern = /\b([a-zA-Z_][a-zA-Z0-9_]*)\b/g;

    expression = expression.replace(fieldPattern, (match) => {
      // Skip if it's a Math function or keyword
      const reserved = ['Math', 'abs', 'ceil', 'floor', 'round', 'min', 'max', 'pow', 'sqrt'];
      if (reserved.includes(match)) return match;

      const value = formValues[match];
      return value != null ? Number(value) || 0 : 0;
    });

    // Safely evaluate the expression
    // WARNING: In production, use a proper expression parser
    // This is simplified for demonstration
    return Function(`"use strict"; return (${expression})`)();

  } catch (error) {
    console.error('Formula evaluation error:', error);
    return null;
  }
}
```

### Step 8: Handle Debounce

```javascript
function createDebouncedHandler(field) {
  const debounceTime = field.dependencies?.debounce || 0;

  if (debounceTime === 0) {
    return (formValues) => updateFieldState(field, formValues);
  }

  let timeoutId = null;

  return (formValues) => {
    if (timeoutId) clearTimeout(timeoutId);

    timeoutId = setTimeout(() => {
      updateFieldState(field, formValues);
    }, debounceTime);
  };
}
```

---

## TypeScript Definitions

```typescript
// Operator types
type OperatorType =
  | 'equals'
  | 'notEquals'
  | 'greaterThan'
  | 'lessThan'
  | 'greaterThanOrEquals'
  | 'lessThanOrEquals'
  | 'hasValue'
  | 'isEmpty'
  | 'isTruthy'
  | 'isFalsy'
  | 'in'
  | 'notIn'
  | 'contains'
  | 'notContains'
  | 'startsWith'
  | 'endsWith'
  | 'matches';

// Condition
interface DependencyCondition {
  field: string;
  operator: OperatorType;
  value?: string | number | boolean | string[] | number[];
}

// Options config
interface DependencyOptionsConfig {
  endpoint?: string;
  paramField?: string;
  paramName?: string;
  cascadeFrom?: string;
}

// Full dependencies object
interface FieldDependencies {
  fields?: string[];
  visibility?: DependencyCondition[];
  disabled?: DependencyCondition[];
  required?: DependencyCondition[];
  options?: DependencyOptionsConfig;
  compute?: string;
  clearOnChange?: boolean;
  debounce?: number;
}

// Extended field interface
interface NadotaField {
  key: string;
  label: string;
  attribute: string;
  type: string;
  component: string;
  value: any;
  required: boolean;
  disabled: boolean;
  readonly: boolean;
  showOnIndex: boolean;
  showOnDetail: boolean;
  showOnCreation: boolean;
  showOnUpdate: boolean;
  props: Record<string, any>;
  dependencies: FieldDependencies;
  optionsUrl?: string;
}

// Form values
type FormValues = Record<string, any>;

// Dependency service interface
interface DependencyService {
  buildDependencyMap(fields: NadotaField[]): Map<string, string[]>;
  evaluateCondition(condition: DependencyCondition, formValues: FormValues): boolean;
  evaluateConditions(conditions: DependencyCondition[], formValues: FormValues): boolean;
  updateFieldState(field: NadotaField, formValues: FormValues): void;
  evaluateFormula(formula: string, formValues: FormValues): number | null;
  loadDependentOptions(field: NadotaField, formValues: FormValues): Promise<void>;
}
```

---

## Vue.js Implementation

### Composable: useDependencies

```typescript
// composables/useDependencies.ts
import { ref, watch, computed } from 'vue';
import type { NadotaField, FormValues, DependencyCondition } from '@/types';

export function useDependencies(fields: Ref<NadotaField[]>, formValues: Ref<FormValues>) {

  // Build dependency map
  const dependencyMap = computed(() => {
    const map = new Map<string, string[]>();

    for (const field of fields.value) {
      const deps = field.dependencies?.fields || [];
      for (const depField of deps) {
        if (!map.has(depField)) {
          map.set(depField, []);
        }
        map.get(depField)!.push(field.key);
      }
    }

    return map;
  });

  // Field visibility state
  const fieldVisibility = ref<Record<string, boolean>>({});
  const fieldDisabled = ref<Record<string, boolean>>({});
  const fieldRequired = ref<Record<string, boolean>>({});

  // Initialize visibility
  function initializeFields() {
    for (const field of fields.value) {
      fieldVisibility.value[field.key] = true;
      fieldDisabled.value[field.key] = field.disabled;
      fieldRequired.value[field.key] = field.required;

      // Initial evaluation
      updateFieldState(field);
    }
  }

  // Evaluate single condition
  function evaluateCondition(condition: DependencyCondition): boolean {
    const value = formValues.value[condition.field];

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
        return isTruthy(value);
      case 'isFalsy':
        return !isTruthy(value);
      case 'in':
        return Array.isArray(condition.value) && condition.value.includes(value);
      case 'notIn':
        return Array.isArray(condition.value) && !condition.value.includes(value);
      case 'greaterThan':
        return Number(value) > Number(condition.value);
      case 'lessThan':
        return Number(value) < Number(condition.value);
      case 'contains':
        return String(value || '').includes(String(condition.value));
      default:
        return true;
    }
  }

  function isTruthy(value: any): boolean {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value === 1;
    if (typeof value === 'string') {
      return ['1', 'yes', 'true', 'on'].includes(value.toLowerCase());
    }
    return false;
  }

  // Evaluate all conditions (AND logic)
  function evaluateConditions(conditions: DependencyCondition[]): boolean {
    if (!conditions || conditions.length === 0) return true;
    return conditions.every(c => evaluateCondition(c));
  }

  // Update single field state
  function updateFieldState(field: NadotaField) {
    const deps = field.dependencies;
    if (!deps || Object.keys(deps).length === 0) return;

    // Visibility
    if (deps.visibility?.length) {
      fieldVisibility.value[field.key] = evaluateConditions(deps.visibility);
    }

    // Disabled
    if (deps.disabled?.length) {
      fieldDisabled.value[field.key] = evaluateConditions(deps.disabled);
    }

    // Required
    if (deps.required?.length) {
      fieldRequired.value[field.key] = evaluateConditions(deps.required);
    }

    // Computed value
    if (deps.compute) {
      formValues.value[field.key] = evaluateFormula(deps.compute);
    }
  }

  // Evaluate formula
  function evaluateFormula(formula: string): number | null {
    try {
      let expression = formula;
      const reserved = ['Math', 'abs', 'ceil', 'floor', 'round', 'min', 'max'];

      expression = expression.replace(/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/g, (match) => {
        if (reserved.includes(match)) return match;
        const value = formValues.value[match];
        return String(value != null ? Number(value) || 0 : 0);
      });

      return Function(`"use strict"; return (${expression})`)();
    } catch {
      return null;
    }
  }

  // Handle field change
  function onFieldChange(fieldKey: string) {
    const dependents = dependencyMap.value.get(fieldKey) || [];

    for (const depKey of dependents) {
      const field = fields.value.find(f => f.key === depKey);
      if (!field) continue;

      // Clear if configured
      if (field.dependencies?.clearOnChange) {
        formValues.value[depKey] = null;
      }

      updateFieldState(field);
    }
  }

  // Watch form values
  watch(
    formValues,
    (newValues, oldValues) => {
      for (const key of Object.keys(newValues)) {
        if (oldValues && newValues[key] !== oldValues[key]) {
          onFieldChange(key);
        }
      }
    },
    { deep: true }
  );

  // Helpers
  const isVisible = (key: string) => fieldVisibility.value[key] !== false;
  const isDisabled = (key: string) => fieldDisabled.value[key] === true;
  const isRequired = (key: string) => fieldRequired.value[key] === true;

  return {
    initializeFields,
    onFieldChange,
    isVisible,
    isDisabled,
    isRequired,
    fieldVisibility,
    fieldDisabled,
    fieldRequired,
  };
}
```

### Usage in Component

```vue
<template>
  <form @submit.prevent="handleSubmit">
    <template v-for="field in fields" :key="field.key">
      <div v-if="isVisible(field.key)" class="field-wrapper">
        <component
          :is="getFieldComponent(field)"
          :field="field"
          :value="formValues[field.key]"
          :disabled="isDisabled(field.key)"
          :required="isRequired(field.key)"
          @update:value="updateValue(field.key, $event)"
        />
      </div>
    </template>
  </form>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useDependencies } from '@/composables/useDependencies';

const props = defineProps<{
  fields: NadotaField[];
  initialValues?: FormValues;
}>();

const formValues = ref<FormValues>(props.initialValues || {});

const {
  initializeFields,
  isVisible,
  isDisabled,
  isRequired,
} = useDependencies(
  computed(() => props.fields),
  formValues
);

onMounted(() => {
  initializeFields();
});

function updateValue(key: string, value: any) {
  formValues.value[key] = value;
}
</script>
```

---

## Edge Cases

### 1. Circular Dependencies

The system does not detect circular dependencies. Avoid configurations like:

```php
// DON'T DO THIS
Input::make('A', 'a')->dependsOn('b');
Input::make('B', 'b')->dependsOn('a');
```

### 2. Missing Dependency Fields

If a dependency field doesn't exist in the form, treat it as `null`:

```javascript
const value = formValues[condition.field] ?? null;
```

### 3. Initial Load

Always evaluate dependencies on initial form load, not just on change:

```javascript
onMounted(() => {
  for (const field of fields) {
    updateFieldState(field, formValues);
  }
});
```

### 4. Hidden Field Values

When a field becomes hidden, decide whether to:
- **Keep value**: User can toggle visibility without losing data
- **Clear value**: Prevents submitting hidden field values

The `clearOnChange` flag helps control this behavior.

### 5. Validation of Hidden Fields

Hidden fields should typically be excluded from validation:

```javascript
function getValidationRules(field) {
  if (!isVisible(field.key)) {
    return []; // Skip validation for hidden fields
  }
  return field.rules;
}
```

### 6. Async Options Loading

When loading options from an endpoint:
1. Show loading state
2. Disable the field while loading
3. Handle errors gracefully
4. Clear options if parent becomes empty

```javascript
async function loadOptions(field) {
  field.loading = true;
  field.disabled = true;

  try {
    field.options = await fetchOptions(field);
  } catch (error) {
    field.options = [];
    field.error = 'Failed to load options';
  } finally {
    field.loading = false;
    field.disabled = false;
  }
}
```

### 7. Computed Field Errors

Handle formula evaluation errors gracefully:

```javascript
function evaluateFormula(formula, formValues) {
  try {
    return evaluate(formula, formValues);
  } catch (error) {
    console.warn(`Formula error: ${formula}`, error);
    return null; // or 0, or previous value
  }
}
```

---

## API Endpoints

### Options Endpoint

When using `cascadeFrom` or `optionsFromEndpoint`, the frontend makes GET requests:

```
GET /nadota-api/{resource}/resource/field/{fieldKey}/options?{parentField}={value}
```

**Response:**

```json
{
  "options": [
    { "value": 1, "label": "Option 1" },
    { "value": 2, "label": "Option 2" }
  ]
}
```

### Search Endpoint (for searchable fields)

```
GET /nadota-api/{resource}/resource/field/{fieldKey}/options?search={query}&{parentField}={value}
```

---

## Summary

| Feature | Frontend Responsibility |
|---------|------------------------|
| Visibility | Show/hide field based on conditions |
| Disabled | Enable/disable input based on conditions |
| Required | Add/remove required indicator and validation |
| Options | Fetch from API when dependencies change |
| Compute | Evaluate formula and set value |
| Clear | Reset value when dependency changes |
| Debounce | Delay evaluation for performance |
